<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Padosoft\ProductImageDiscovery\Actions\ScoreCandidateImageAction;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Jobs\AssessImageQualityJob;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Jobs\DownloadCandidateImageJob;
use Padosoft\ProductImageDiscovery\Jobs\ExtractCandidateSourcesJob;
use Padosoft\ProductImageDiscovery\Jobs\IngestProductImageDiscoveryJob;
use Padosoft\ProductImageDiscovery\Jobs\SearchProductImageJob;
use Padosoft\ProductImageDiscovery\Jobs\VerifyCandidateImageJob;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryEvent;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryRequest;
use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Search\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Services\Support\TextNormalizer;
use RuntimeException;
use Throwable;

final class ProductImageDiscoveryDebugFlowCommand extends Command
{
    protected $signature = 'product-image-discovery:debug-flow
        {request : Path to the product image discovery request JSON}
        {--max-candidates=10 : Maximum candidates to verify in this debug run}
        {--report= : Optional path where the full JSON report should be written}
        {--json : Print the full JSON report instead of formatted console output}
        {--fresh : Delete the existing request for client_id + erp_model_color_id before running}
        {--migrate : Run package migrations before the flow, useful in local demo/Testbench environments}
        {--no-download : Skip download and quality assessment}
        {--download-all : Download and quality-assess every verified candidate instead of only the best verified candidate}
        {--stop-on-first-good : Stop verifying more candidates once a good verified candidate is found}
        {--exhaustive : Verify every candidate up to --max-candidates, ignoring early-stop settings}
        {--good-score= : Final-score threshold used by --stop-on-first-good}
        {--no-env-brave : Do not auto-create a Brave debug provider from BRAVE_SEARCH_API_KEY}
        {--fail-on-no-match : Return a failure exit code when no candidate reaches verified_match}';

    protected $description = 'Run a full product image discovery debug flow from a JSON request and print search, scoring, AI and quality evidence.';

    public function handle(
        PipelineStoreInterface $store,
        SearchProviderManager $searchManager,
        ProductImageEventLogger $logger,
    ): int {
        $requestPath = $this->absolutePath((string) $this->argument('request'));

        try {
            $payload = $this->readPayload($requestPath);

            if ((bool) $this->option('migrate')) {
                $this->callSilent('migrate', ['--force' => true]);
            }

            $this->assertTablesExist();
            $this->configureAiSdk();

            if (! (bool) $this->option('no-env-brave')) {
                $this->upsertEnvBraveProvider();
            }

            if ((bool) $this->option('fresh')) {
                $this->deleteExistingRequest($payload);
            }

            Bus::fake();

            if ($this->shouldStream()) {
                $this->renderHeader();
            }

            $report = $this->runPipeline($payload, $requestPath, $store, $searchManager, $logger);
            $this->writeReport($report);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                $this->renderReport($report);
            }

            $hasMatch = (bool) ($report['summary']['has_verified_match'] ?? false);

            if ((bool) $this->option('fail-on-no-match') && ! $hasMatch) {
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line($exception->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function runPipeline(
        array $payload,
        string $requestPath,
        PipelineStoreInterface $store,
        SearchProviderManager $searchManager,
        ProductImageEventLogger $logger,
    ): array {
        $startedAt = gmdate('c');
        $maxCandidates = max(1, (int) $this->option('max-candidates'));
        $downloadEnabled = ! (bool) $this->option('no-download');
        $downloadAll = (bool) $this->option('download-all');
        $stopOnFirstGood = $this->shouldStopOnFirstGood();
        $goodScoreThreshold = $this->goodScoreThreshold();
        $stoppedEarly = false;
        $stopReason = null;

        $request = (new IngestProductImageDiscoveryJob($payload))->handle($store, $logger);
        $request = (new SearchProductImageJob($request['id']))->handle($store, $searchManager, $logger);
        $request = (new ExtractCandidateSourcesJob($request['id']))->handle($store, $logger);
        $identity = ProductIdentityData::fromArray(array_merge($request, [
            'raw_payload' => $request['raw_payload'] ?? $request,
        ]));

        $checked = [];
        $downloaded = [];
        $quality = [];

        $this->streamStage('1/6 Ingest request');
        $this->streamTable(['Field', 'Value'], [
            ['Request id', $request['id'] ?? '-'],
            ['Identity', trim((string) ($request['brand'] ?? '') . ' ' . (string) ($request['model_code'] ?? '') . ' ' . (string) ($request['color_name'] ?? ''))],
            ['ERP color id', $request['erp_model_color_id'] ?? '-'],
            ['Client id', $request['client_id'] ?? '-'],
            ['Request file', $requestPath],
        ]);

        $this->streamStage('2/6 Search providers');
        $searchSummary = $this->summarizeSearch($request['context']['search'] ?? []);
        $this->streamSearchSummary($searchSummary);

        $this->streamStage('3/6 Candidate extraction');
        $extractContext = $request['context']['extract'] ?? [];
        $this->streamTable(['Field', 'Value'], [
            ['Candidate ids', implode(', ', $extractContext['candidate_ids'] ?? []) ?: '-'],
            ['Source pages', implode(PHP_EOL, $extractContext['source_pages'] ?? []) ?: '-'],
        ]);

        $rankedCandidates = array_slice($this->candidatesForDebugVerification($store->listCandidates($request['id']), $request), 0, $maxCandidates);
        $this->streamCandidatePlan($rankedCandidates);
        $this->streamTable(['Field', 'Value'], [
            ['Stop on first good', $stopOnFirstGood ? 'yes' : 'no'],
            ['Good score threshold', $goodScoreThreshold],
        ]);

        $this->streamStage('4/6 Candidate verification and scoring');
        $candidatePosition = 0;
        $candidateTotal = count($rankedCandidates);

        foreach ($rankedCandidates as $candidate) {
            $candidatePosition++;
            $this->streamCandidateStart($candidate, $candidatePosition, $candidateTotal);

            $verified = (new VerifyCandidateImageJob($request['id'], $candidate['id']))->handle($store, $logger);
            $checked[] = $verified['id'] ?? $candidate['id'];

            $this->streamCandidateResult($this->summarizeCandidate($verified));

            if ($stopOnFirstGood) {
                $reason = $this->goodCandidateStopReason($verified, $identity, $goodScoreThreshold);

                if ($reason !== null) {
                    $stoppedEarly = true;
                    $stopReason = $reason;
                    $this->streamLine("Stopping verification early: candidate #{$verified['id']} is good enough ({$reason}). Use --exhaustive to inspect all max candidates.");
                    break;
                }
            }
        }

        if ($downloadEnabled) {
            $verifiedCandidates = array_values(array_filter(
                $this->candidatesForDebugVerification($store->listCandidates($request['id'])),
                static fn (array $candidate): bool => ($candidate['status'] ?? null) === ProductImageDiscoveryCandidateStatus::VerifiedMatch->value,
            ));

            $downloadTargets = $downloadAll ? $verifiedCandidates : array_slice($verifiedCandidates, 0, 1);
            $this->streamStage('5/6 Download and quality analysis');
            $this->streamTable(['Field', 'Value'], [
                ['Download enabled', 'yes'],
                ['Mode', $downloadAll ? 'all verified candidates' : 'best verified candidate'],
                ['Target candidate ids', implode(', ', array_column($downloadTargets, 'id')) ?: '-'],
            ]);

            foreach ($downloadTargets as $verified) {
                try {
                    $this->streamLine('Downloading candidate #' . ($verified['id'] ?? '-') . ' from ' . ($verified['image_url'] ?? '-'));
                    $downloadedCandidate = (new DownloadCandidateImageJob($request['id'], $verified['id']))->handle($store, $logger);
                    $downloaded[] = $downloadedCandidate['id'] ?? $verified['id'];
                    $this->streamDownloadedCandidate($downloadedCandidate);

                    $qualityCandidate = (new AssessImageQualityJob($request['id'], $verified['id']))->handle($store, $logger);
                    $quality[] = $qualityCandidate['id'] ?? $verified['id'];
                    $this->streamQualityCandidate($qualityCandidate);
                } catch (Throwable $exception) {
                    $this->streamError('Download/quality failed for candidate #' . ($verified['id'] ?? '-') . ': ' . $exception->getMessage());
                    $logger->record('pipeline.debug.download_failed', [
                        'error' => $exception->getMessage(),
                    ], 'warning', $request['id'], $verified['id'] ?? null);
                }
            }
        } else {
            $this->streamStage('5/6 Download and quality analysis');
            $this->streamLine('Download disabled by --no-download.');
        }

        $finalRequest = $store->getRequest($request['id']) ?? $request;
        $candidates = $store->listCandidates($request['id']);
        $verifiedMatches = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => ($candidate['status'] ?? null) === ProductImageDiscoveryCandidateStatus::VerifiedMatch->value
                || ($candidate['status'] ?? null) === ProductImageDiscoveryCandidateStatus::Downloaded->value
                || ($candidate['status'] ?? null) === ProductImageDiscoveryCandidateStatus::QualityPassed->value
                || ($candidate['status'] ?? null) === ProductImageDiscoveryCandidateStatus::QualityFailed->value,
        ));

        $this->streamStage('6/6 Final decision');
        $this->streamTable(['Field', 'Value'], [
            ['Request status', $finalRequest['status'] ?? '-'],
            ['Final score', $finalRequest['final_score'] ?? '-'],
            ['Best candidate id', $finalRequest['best_candidate_id'] ?? '-'],
            ['Selected candidate id', $finalRequest['selected_candidate_id'] ?? '-'],
            ['Verified matches', count($verifiedMatches)],
            ['Stopped early', $stoppedEarly ? 'yes' : 'no'],
            ['Stop reason', $stopReason ?? '-'],
            ['Full report', $this->option('report') ?: 'not written'],
        ]);

        return [
            'summary' => [
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'request_file' => $requestPath,
                'max_candidates' => $maxCandidates,
                'download_enabled' => $downloadEnabled,
                'download_all' => $downloadAll,
                'stop_on_first_good' => $stopOnFirstGood,
                'good_score_threshold' => $goodScoreThreshold,
                'stopped_early' => $stoppedEarly,
                'stop_reason' => $stopReason,
                'candidates_checked' => count(array_unique($checked)),
                'candidate_count' => count($candidates),
                'verified_match_count' => count($verifiedMatches),
                'has_verified_match' => $verifiedMatches !== [],
            ],
            'config' => [
                'ai_enabled' => (bool) config('product-image-discovery.ai.enabled', false),
                'ai_provider' => config('product-image-discovery.ai.provider'),
                'ai_vision_model' => config('product-image-discovery.ai.vision_model'),
                'ai_description_model' => config('product-image-discovery.ai.description_model'),
                'ai_attach_remote_image' => (bool) config('product-image-discovery.ai.attach_remote_image', false),
                'storage_disk' => config('product-image-discovery.storage.disk'),
            ],
            'request' => $this->summarizeRequest($finalRequest),
            'search' => $this->summarizeSearch($finalRequest['context']['search'] ?? []),
            'extract' => $finalRequest['context']['extract'] ?? [],
            'candidates' => array_map(fn (array $candidate): array => $this->summarizeCandidate($candidate), $candidates),
            'downloaded_candidate_ids' => array_values(array_unique($downloaded)),
            'quality_candidate_ids' => array_values(array_unique($quality)),
            'events' => $this->eventsForRequest((int) ($finalRequest['id'] ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Request JSON not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('Request JSON must decode to an object.');
        }

        foreach (['client_id', 'erp_model_color_id', 'brand'] as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                throw new RuntimeException("Request JSON is missing required key [{$key}].");
            }
        }

        return $payload;
    }

    private function assertTablesExist(): void
    {
        foreach ([
            'product_image_discovery_requests',
            'product_image_discovery_candidates',
            'product_image_search_providers',
            'product_image_discovery_events',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Database table [{$table}] does not exist. Run migrations first or pass --migrate in a demo/Testbench environment.");
            }
        }
    }

    private function configureAiSdk(): void
    {
        $provider = (string) config('product-image-discovery.ai.provider', 'anthropic');
        config()->set('ai.default', $provider);

        foreach (['openai', 'anthropic', 'openrouter'] as $name) {
            $apiKey = config("product-image-discovery.ai.providers.{$name}.api_key");
            $baseUrl = config("product-image-discovery.ai.providers.{$name}.base_url");

            if ($apiKey !== null) {
                config()->set("ai.providers.{$name}.key", $apiKey);
            }

            if ($baseUrl !== null) {
                config()->set("ai.providers.{$name}.url", $baseUrl);
            }
        }
    }

    private function upsertEnvBraveProvider(): void
    {
        $apiKey = env('BRAVE_SEARCH_API_KEY');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return;
        }

        ProductImageSearchProvider::query()->updateOrCreate(
            ['code' => 'brave-live-debug'],
            [
                'name' => 'Brave Live Debug',
                'driver' => 'brave',
                'base_url' => 'https://api.search.brave.com',
                'api_key_encrypted' => trim($apiKey),
                'config' => [
                    'supports_image_search' => true,
                    'supports_site_filter' => true,
                    'supports_web_search' => true,
                    'max_results_per_request' => 20,
                ],
                'priority' => 1,
                'timeout_seconds' => 20,
                'rate_limit_per_minute' => 60,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function deleteExistingRequest(array $payload): void
    {
        ProductImageDiscoveryRequest::query()
            ->where('client_id', $payload['client_id'])
            ->where('erp_model_color_id', $payload['erp_model_color_id'])
            ->delete();
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    private function candidatesForDebugVerification(array $candidates, ?array $request = null): array
    {
        $identity = $request === null ? null : ProductIdentityData::fromArray(array_merge($request, [
            'raw_payload' => $request['raw_payload'] ?? $request,
        ]));
        $scorer = $identity === null ? null : new ScoreCandidateImageAction();

        $candidates = array_map(static function (array $candidate) use ($identity, $scorer): array {
            $candidateStatus = $candidate['status'] ?? null;
            $hasPersistedScore = ($candidate['final_score'] ?? null) !== null
                && $candidateStatus !== ProductImageDiscoveryCandidateStatus::Candidate->value;

            if ($hasPersistedScore || $identity === null || $scorer === null) {
                $candidate['_debug_rank_score'] = $candidate['final_score'] ?? null;

                return $candidate;
            }

            try {
                $candidate['_debug_rank_score'] = $scorer->handle($identity, $candidate)->finalScore;
            } catch (Throwable) {
                $candidate['_debug_rank_score'] = null;
            }

            return $candidate;
        }, $candidates);

        usort($candidates, static function (array $a, array $b): int {
            $aScore = $a['_debug_rank_score'] ?? null;
            $bScore = $b['_debug_rank_score'] ?? null;

            if ($aScore !== null || $bScore !== null) {
                return ((int) ($bScore ?? -1)) <=> ((int) ($aScore ?? -1))
                    ?: ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $candidates;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function writeReport(array $report): void
    {
        $path = $this->option('report');

        if (! is_string($path) || trim($path) === '') {
            return;
        }

        $path = $this->absolutePath($path);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
        $this->info("Report written to {$path}");
    }

    /**
     * @param array<string, mixed> $report
     */
    private function renderReport(array $report): void
    {
        $this->line('');
        $this->line(str_repeat('=', 72));
        $this->line('Final formatted report');
        $this->line(str_repeat('=', 72));
        $this->line('');

        $this->renderSummary($report);
        $this->renderSearch($report['search'] ?? []);
        $this->renderCandidates($report['candidates'] ?? []);
        $this->renderEvents($report['events'] ?? []);
    }

    /**
     * @param array<string, mixed> $report
     */
    private function renderSummary(array $report): void
    {
        $request = $report['request'] ?? [];
        $summary = $report['summary'] ?? [];
        $config = $report['config'] ?? [];

        $this->info('Summary');
        $this->table(['Field', 'Value'], [
            ['Request file', $summary['request_file'] ?? null],
            ['Request id', $request['id'] ?? null],
            ['Identity', trim(($request['brand'] ?? '') . ' ' . ($request['model_code'] ?? '') . ' ' . ($request['color_name'] ?? ''))],
            ['ERP color id', $request['erp_model_color_id'] ?? null],
            ['Status', $request['status'] ?? null],
            ['Final score', $request['final_score'] ?? '-'],
            ['Best candidate id', $request['best_candidate_id'] ?? '-'],
            ['Candidates checked', ($summary['candidates_checked'] ?? 0) . ' / ' . ($summary['candidate_count'] ?? 0)],
            ['Verified matches', $summary['verified_match_count'] ?? 0],
            ['Download mode', ($summary['download_enabled'] ?? false) ? (($summary['download_all'] ?? false) ? 'all verified candidates' : 'best verified candidate') : 'disabled'],
            ['Early stop', ($summary['stop_on_first_good'] ?? false) ? (($summary['stopped_early'] ?? false) ? 'yes: ' . ($summary['stop_reason'] ?? '-') : 'enabled, not triggered') : 'disabled'],
            ['AI', (($config['ai_enabled'] ?? false) ? 'enabled' : 'disabled') . ' / ' . ($config['ai_provider'] ?? '-')],
            ['AI model', $config['ai_vision_model'] ?? $config['ai_description_model'] ?? '-'],
            ['Remote image attachment', ($config['ai_attach_remote_image'] ?? false) ? 'true' : 'false'],
        ]);
        $this->newLine();
    }

    /**
     * @param array<string, mixed> $search
     */
    private function renderSearch(array $search): void
    {
        $this->info('Search');
        $this->table(['Field', 'Value'], [
            ['Provider', $search['provider'] ?? '-'],
            ['Result count', $search['result_count'] ?? 0],
            ['Completed at', $search['completed_at'] ?? '-'],
        ]);

        $queries = array_map(static fn (array $query): array => [
            $query['intent'] ?? '-',
            $query['weight'] ?? '-',
            Str::limit((string) ($query['query'] ?? ''), 110),
        ], array_filter($search['queries'] ?? [], 'is_array'));

        if ($queries !== []) {
            $this->line('Queries');
            $this->table(['Intent', 'Weight', 'Query'], $queries);
        }

        $results = array_map(fn (array $result, int $index): array => [
            $index + 1,
            Str::limit((string) ($result['title'] ?? ''), 55),
            $result['source_domain'] ?? '-',
            trim((string) ($result['width'] ?? '-') . 'x' . (string) ($result['height'] ?? '-')),
            Str::limit((string) ($result['page_url'] ?? ''), 70),
            Str::limit((string) ($result['image_url'] ?? ''), 70),
        ], array_filter($search['results'] ?? [], 'is_array'), array_keys(array_filter($search['results'] ?? [], 'is_array')));

        if ($results !== []) {
            $this->line('Top Search Results');
            $this->table(['#', 'Title', 'Domain', 'Size', 'Page', 'Image'], $results);
        }

        $this->newLine();
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     */
    private function renderCandidates(array $candidates): void
    {
        $this->info('Candidates');

        if ($candidates === []) {
            $this->warn('No candidates found.');
            $this->newLine();

            return;
        }

        $this->table(['ID', 'Status', 'Final', 'Text', 'Visual', 'Quality', 'Reject', 'Domain'], array_map(static fn (array $candidate): array => [
            $candidate['id'] ?? '-',
            $candidate['status'] ?? '-',
            Arr::get($candidate, 'scores.final_score', '-'),
            Arr::get($candidate, 'scores.textual_match_score', '-'),
            Arr::get($candidate, 'scores.visual_match_score', '-'),
            Arr::get($candidate, 'scores.quality_score', '-'),
            $candidate['rejection_reason'] ?? '-',
            $candidate['source_domain'] ?? '-',
        ], $candidates));

        foreach ($candidates as $candidate) {
            $this->line('');
            $this->line('Candidate #' . ($candidate['id'] ?? '-'));
            $this->table(['Field', 'Value'], [
                ['Title', Str::limit((string) ($candidate['title'] ?? '-'), 100)],
                ['Source page', $candidate['source_page_url'] ?? '-'],
                ['Image URL', $candidate['image_url'] ?? '-'],
                ['Local path', $candidate['local_original_path'] ?? '-'],
                ['SHA256', $candidate['sha256'] ?? '-'],
                ['Matches', implode(', ', $candidate['evidence']['matches'] ?? []) ?: '-'],
                ['Mismatches', implode(', ', $candidate['evidence']['mismatches'] ?? []) ?: '-'],
                ['Strong matches', implode(', ', $candidate['evidence']['strong_matches'] ?? []) ?: '-'],
            ]);

            $ai = $candidate['ai_verification'] ?? null;

            if (is_array($ai)) {
                $this->line('AI verification');
                $this->table(['Field', 'Value'], [
                    ['Status', $ai['status'] ?? '-'],
                    ['Provider', $ai['provider'] ?? '-'],
                    ['Model', $ai['model'] ?? '-'],
                    ['Match', $this->yesNo($ai['match'] ?? null)],
                    ['Variant safe', $this->yesNo($ai['variant_safe'] ?? null)],
                    ['Confidence', $ai['confidence'] ?? '-'],
                    ['Brand / model / color', $this->yesNo($ai['brand_match'] ?? null) . ' / ' . $this->yesNo($ai['model_match'] ?? null) . ' / ' . $this->yesNo($ai['color_match'] ?? null)],
                    ['Type / quality', $this->yesNo($ai['product_type_match'] ?? null) . ' / ' . $this->yesNo($ai['image_quality_ok'] ?? null)],
                    ['AI rejection', $ai['rejection_reason'] ?? '-'],
                    ['AI notes', $ai['notes'] ?? '-'],
                    ['AI error', $ai['error'] ?? '-'],
                ]);
            }

            if (is_array($candidate['quality_analysis'] ?? null)) {
                $quality = $candidate['quality_analysis'];
                $this->line('Quality analysis');
                $this->table(['Field', 'Value'], [
                    ['Passed', $this->yesNo($quality['passed'] ?? null)],
                    ['Score', $quality['quality_score'] ?? '-'],
                    ['Width x height', (string) ($quality['width'] ?? '-') . 'x' . (string) ($quality['height'] ?? '-')],
                    ['Issues', implode(', ', $quality['issues'] ?? []) ?: '-'],
                    ['Download', $quality['download_status'] ?? '-'],
                    ['Bytes', $quality['download_bytes'] ?? $candidate['file_size'] ?? '-'],
                ]);
            }
        }

        $this->newLine();
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function renderEvents(array $events): void
    {
        $this->info('Audit Events');

        if ($events === []) {
            $this->warn('No audit events recorded.');

            return;
        }

        $this->table(['ID', 'Level', 'Type', 'Candidate', 'Context'], array_map(static fn (array $event): array => [
            $event['id'] ?? '-',
            $event['level'] ?? '-',
            $event['event_type'] ?? '-',
            $event['candidate_id'] ?? '-',
            Str::limit(json_encode($event['context'] ?? [], JSON_UNESCAPED_SLASHES), 500),
        ], $events));
    }

    private function renderHeader(): void
    {
        $this->line('');
        $this->line(' ____            _            _     ___                         ');
        $this->line('|  _ \ _ __ ___ | |_ ___  ___| |_  |_ _|_ __ ___   __ _  __ _  ___');
        $this->line('| |_) |  _ ` _ \| __/ _ \/ __| __|  | ||  _ ` _ \ / _` |/ _` |/ _ \\');
        $this->line('|  __/| | | | | | ||  __/ (__| |_   | || | | | | | (_| | (_| |  __/');
        $this->line('|_|   |_| |_| |_|\__\___|\___|\__| |___|_| |_| |_|\__,_|\__, |\___|');
        $this->line('                                                        |___/      ');
        $this->line('Product Image Discovery Debug Flow');
        $this->line('');
    }

    private function shouldStream(): bool
    {
        return ! (bool) $this->option('json');
    }

    private function shouldStopOnFirstGood(): bool
    {
        if ((bool) $this->option('exhaustive')) {
            return false;
        }

        if ((bool) $this->option('stop-on-first-good')) {
            return true;
        }

        return (bool) config('product-image-discovery.debug.stop_on_first_good', true);
    }

    private function goodScoreThreshold(): int
    {
        $option = $this->option('good-score');

        if (is_numeric($option)) {
            return max(0, min(100, (int) $option));
        }

        return max(0, min(100, (int) config('product-image-discovery.debug.good_score_threshold', 65)));
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function goodCandidateStopReason(array $candidate, ProductIdentityData $identity, int $threshold): ?string
    {
        if (($candidate['status'] ?? null) !== ProductImageDiscoveryCandidateStatus::VerifiedMatch->value) {
            return null;
        }

        $source = is_array($candidate['evidence']['source'] ?? null) ? $candidate['evidence']['source'] : [];

        if (($source['allow_auto_publish'] ?? false) === true) {
            return 'source allows auto-publish';
        }

        if (($source['trusted'] ?? false) === true) {
            return 'trusted source';
        }

        $brand = TextNormalizer::normalizeText($identity->brand);
        $domain = TextNormalizer::normalizeText((string) ($candidate['source_domain'] ?? parse_url((string) ($candidate['source_page_url'] ?? ''), PHP_URL_HOST)));

        if ($brand !== null && $domain !== null && TextNormalizer::containsWord($domain, $brand)) {
            return 'source domain contains brand';
        }

        $score = (int) ($candidate['final_score'] ?? 0);

        if ($score >= $threshold) {
            return "final score {$score} >= {$threshold}";
        }

        return null;
    }

    private function streamStage(string $title): void
    {
        if (! $this->shouldStream()) {
            return;
        }

        $this->newLine();
        $this->line(str_repeat('-', 72));
        $this->info($title);
    }

    /**
     * @param list<string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    private function streamTable(array $headers, array $rows): void
    {
        if (! $this->shouldStream()) {
            return;
        }

        $this->table($headers, $rows);
    }

    private function streamLine(string $message): void
    {
        if ($this->shouldStream()) {
            $this->line($message);
        }
    }

    private function streamError(string $message): void
    {
        if ($this->shouldStream()) {
            $this->error($message);
        }
    }

    /**
     * @param array<string, mixed> $search
     */
    private function streamSearchSummary(array $search): void
    {
        if (! $this->shouldStream()) {
            return;
        }

        $this->streamTable(['Field', 'Value'], [
            ['Provider', $search['provider'] ?? '-'],
            ['Result count', $search['result_count'] ?? 0],
            ['Completed at', $search['completed_at'] ?? '-'],
        ]);

        if (($search['queries'] ?? []) !== []) {
            $this->line('Queries sent');
            $this->table(['Intent', 'Weight', 'Query'], array_map(static fn (array $query): array => [
                $query['intent'] ?? '-',
                $query['weight'] ?? '-',
                $query['query'] ?? '-',
            ], array_filter($search['queries'], 'is_array')));
        }

        if (($search['results'] ?? []) !== []) {
            $this->line('Sites and images found');
            $this->table(['#', 'Domain', 'Size', 'Title', 'Page', 'Image'], array_map(static fn (array $result, int $index): array => [
                $index + 1,
                $result['source_domain'] ?? '-',
                (string) ($result['width'] ?? '-') . 'x' . (string) ($result['height'] ?? '-'),
                Str::limit((string) ($result['title'] ?? '-'), 70),
                Str::limit((string) ($result['page_url'] ?? '-'), 90),
                Str::limit((string) ($result['image_url'] ?? '-'), 90),
            ], array_filter($search['results'], 'is_array'), array_keys(array_filter($search['results'], 'is_array'))));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     */
    private function streamCandidatePlan(array $candidates): void
    {
        if (! $this->shouldStream()) {
            return;
        }

        $this->line('Candidate verification order');
        $this->table(['#', 'ID', 'Debug rank', 'Domain', 'Title', 'Page'], array_map(fn (array $candidate, int $index): array => [
            $index + 1,
            $candidate['id'] ?? '-',
            $candidate['_debug_rank_score'] ?? '-',
            $candidate['source_domain'] ?? '-',
            Str::limit((string) ($this->candidateTitle($candidate) ?? '-'), 70),
            Str::limit((string) ($candidate['source_page_url'] ?? '-'), 90),
        ], $candidates, array_keys($candidates)));
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function streamCandidateStart(array $candidate, int $position, int $total): void
    {
        if (! $this->shouldStream()) {
            return;
        }

        $this->line('');
        $this->line("Examining candidate {$position}/{$total} - #" . ($candidate['id'] ?? '-'));
        $this->table(['Field', 'Value'], [
            ['Debug rank score', $candidate['_debug_rank_score'] ?? '-'],
            ['Title', $this->candidateTitle($candidate) ?? '-'],
            ['Domain', $candidate['source_domain'] ?? '-'],
            ['Source page', $candidate['source_page_url'] ?? '-'],
            ['Image URL', $candidate['image_url'] ?? '-'],
        ]);
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function streamCandidateResult(array $candidate): void
    {
        if (! $this->shouldStream()) {
            return;
        }

        $scores = $candidate['scores'] ?? [];
        $evidence = $candidate['evidence'] ?? [];

        $this->table(['Result', 'Value'], [
            ['Status', $candidate['status'] ?? '-'],
            ['Final score', $scores['final_score'] ?? '-'],
            ['Source trust', $scores['source_trust_score'] ?? '-'],
            ['Textual', $scores['textual_match_score'] ?? '-'],
            ['Structured', $scores['structured_match_score'] ?? '-'],
            ['Visual', $scores['visual_match_score'] ?? '-'],
            ['Quality', $scores['quality_score'] ?? '-'],
            ['Risk penalty', $scores['risk_penalty'] ?? '-'],
            ['Rejection', $candidate['rejection_reason'] ?? '-'],
            ['Matches', implode(', ', $evidence['matches'] ?? []) ?: '-'],
            ['Mismatches', implode(', ', $evidence['mismatches'] ?? []) ?: '-'],
            ['Strong matches', implode(', ', $evidence['strong_matches'] ?? []) ?: '-'],
            ['Source policy', json_encode($evidence['source'] ?? [], JSON_UNESCAPED_SLASHES) ?: '-'],
        ]);

        $ai = $candidate['ai_verification'] ?? null;

        if (is_array($ai)) {
            $this->line('AI verification raw decision');
            $this->table(['Field', 'Value'], [
                ['Status', $ai['status'] ?? '-'],
                ['Provider', $ai['provider'] ?? '-'],
                ['Model', $ai['model'] ?? '-'],
                ['Available', $this->yesNo($ai['available'] ?? null)],
                ['Match', $this->yesNo($ai['match'] ?? null)],
                ['Variant safe', $this->yesNo($ai['variant_safe'] ?? null)],
                ['Confidence', $ai['confidence'] ?? '-'],
                ['Brand match', $this->yesNo($ai['brand_match'] ?? null)],
                ['Model match', $this->yesNo($ai['model_match'] ?? null)],
                ['Color match', $this->yesNo($ai['color_match'] ?? null)],
                ['Product type match', $this->yesNo($ai['product_type_match'] ?? null)],
                ['Image quality ok', $this->yesNo($ai['image_quality_ok'] ?? null)],
                ['AI rejection', $ai['rejection_reason'] ?? '-'],
                ['AI notes', $ai['notes'] ?? '-'],
                ['AI error', $ai['error'] ?? '-'],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function streamDownloadedCandidate(array $candidate): void
    {
        if (! $this->shouldStream()) {
            return;
        }

        $this->line('Download stored');
        $this->table(['Field', 'Value'], [
            ['Candidate id', $candidate['id'] ?? '-'],
            ['Status', $candidate['status'] ?? '-'],
            ['Local original path', $candidate['local_original_path'] ?? '-'],
            ['Local processed path', $candidate['local_processed_path'] ?? '-'],
            ['MIME type', $candidate['mime_type'] ?? '-'],
            ['Bytes', $candidate['file_size'] ?? '-'],
            ['SHA256', $candidate['sha256'] ?? '-'],
        ]);
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function streamQualityCandidate(array $candidate): void
    {
        if (! $this->shouldStream()) {
            return;
        }

        $quality = is_array($candidate['quality_analysis'] ?? null) ? $candidate['quality_analysis'] : [];

        $this->line('Quality analysis result');
        $this->table(['Field', 'Value'], [
            ['Candidate id', $candidate['id'] ?? '-'],
            ['Status', $candidate['status'] ?? '-'],
            ['Passed', $this->yesNo($quality['passed'] ?? null)],
            ['Quality score', $quality['quality_score'] ?? $candidate['quality_score'] ?? '-'],
            ['Width x height', (string) ($quality['width'] ?? $candidate['width'] ?? '-') . 'x' . (string) ($quality['height'] ?? $candidate['height'] ?? '-')],
            ['MIME type', $quality['mime_type'] ?? $candidate['mime_type'] ?? '-'],
            ['Issues', implode(', ', $quality['issues'] ?? []) ?: '-'],
        ]);
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function summarizeRequest(array $request): array
    {
        return [
            'id' => $request['id'] ?? null,
            'client_id' => $request['client_id'] ?? null,
            'erp_model_color_id' => $request['erp_model_color_id'] ?? null,
            'brand' => $request['brand'] ?? null,
            'model_code' => $request['model_code'] ?? null,
            'color_name' => $request['color_name'] ?? null,
            'status' => $request['status'] ?? null,
            'final_score' => $request['final_score'] ?? null,
            'best_candidate_id' => $request['best_candidate_id'] ?? null,
            'selected_candidate_id' => $request['selected_candidate_id'] ?? null,
            'rejection_reason' => $request['rejection_reason'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $search
     * @return array<string, mixed>
     */
    private function summarizeSearch(array $search): array
    {
        $execution = is_array($search['execution'] ?? null) ? $search['execution'] : [];
        $results = array_values(array_filter($execution['results'] ?? [], 'is_array'));

        return [
            'completed_at' => $search['completed_at'] ?? null,
            'queries' => $search['queries'] ?? [],
            'provider' => $execution['provider']['code'] ?? null,
            'attempts' => $execution['attempts'] ?? [],
            'result_count' => count($results),
            'results' => array_map(static fn (array $result): array => [
                'title' => $result['title'] ?? null,
                'page_url' => $result['page_url'] ?? null,
                'image_url' => $result['image_url'] ?? null,
                'source_domain' => $result['source_domain'] ?? null,
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'score' => $result['score'] ?? null,
            ], array_slice($results, 0, 10)),
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function summarizeCandidate(array $candidate): array
    {
        $ai = is_array($candidate['ai_analysis']['verification'] ?? null)
            ? $candidate['ai_analysis']['verification']
            : null;
        $searchResult = is_array($candidate['evidence']['search_result'] ?? null)
            ? $candidate['evidence']['search_result']
            : [];

        return [
            'id' => $candidate['id'] ?? null,
            'status' => $candidate['status'] ?? null,
            'title' => $candidate['title'] ?? $searchResult['title'] ?? null,
            'source_domain' => $candidate['source_domain'] ?? null,
            'source_page_url' => $candidate['source_page_url'] ?? null,
            'image_url' => $candidate['image_url'] ?? null,
            'width' => $candidate['width'] ?? null,
            'height' => $candidate['height'] ?? null,
            'mime_type' => $candidate['mime_type'] ?? null,
            'file_size' => $candidate['file_size'] ?? null,
            'local_original_path' => $candidate['local_original_path'] ?? null,
            'sha256' => $candidate['sha256'] ?? null,
            'scores' => [
                'source_trust_score' => $candidate['source_trust_score'] ?? null,
                'textual_match_score' => $candidate['textual_match_score'] ?? null,
                'structured_match_score' => $candidate['structured_match_score'] ?? null,
                'visual_match_score' => $candidate['visual_match_score'] ?? null,
                'quality_score' => $candidate['quality_score'] ?? null,
                'risk_penalty' => $candidate['risk_penalty'] ?? null,
                'final_score' => $candidate['final_score'] ?? null,
            ],
            'rejection_reason' => $candidate['rejection_reason'] ?? null,
            'evidence' => [
                'matches' => $candidate['evidence']['matches'] ?? [],
                'mismatches' => $candidate['evidence']['mismatches'] ?? [],
                'strong_matches' => $candidate['evidence']['strong_matches'] ?? [],
                'source' => $candidate['evidence']['source'] ?? [],
            ],
            'quality_analysis' => $candidate['quality_analysis'] ?? null,
            'ai_verification' => $ai === null ? null : [
                'available' => $ai['available'] ?? null,
                'status' => $ai['status'] ?? null,
                'provider' => $ai['provider'] ?? null,
                'model' => $ai['model'] ?? null,
                'match' => $ai['match'] ?? null,
                'variant_safe' => $ai['variant_safe'] ?? null,
                'confidence' => $ai['confidence'] ?? null,
                'brand_match' => $ai['brand_match'] ?? null,
                'model_match' => $ai['model_match'] ?? null,
                'color_match' => $ai['color_match'] ?? null,
                'product_type_match' => $ai['product_type_match'] ?? null,
                'image_quality_ok' => $ai['image_quality_ok'] ?? null,
                'rejection_reason' => $ai['rejection_reason'] ?? null,
                'notes' => $ai['notes'] ?? null,
                'error' => $ai['error'] ?? null,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventsForRequest(int $requestId): array
    {
        if ($requestId <= 0) {
            return [];
        }

        return ProductImageDiscoveryEvent::query()
            ->where('request_id', $requestId)
            ->chronological()
            ->get()
            ->map(static fn (ProductImageDiscoveryEvent $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'level' => $event->level,
                'request_id' => $event->request_id,
                'candidate_id' => $event->candidate_id,
                'message' => $event->message,
                'context' => $event->context,
                'created_at' => $event->created_at?->toISOString(),
            ])
            ->all();
    }

    private function yesNo(mixed $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => '-',
            default => (string) $value,
        };
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function candidateTitle(array $candidate): ?string
    {
        $searchResult = is_array($candidate['evidence']['search_result'] ?? null)
            ? $candidate['evidence']['search_result']
            : [];

        return is_string($candidate['title'] ?? null)
            ? $candidate['title']
            : (is_string($searchResult['title'] ?? null) ? $searchResult['title'] : null);
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }
}
