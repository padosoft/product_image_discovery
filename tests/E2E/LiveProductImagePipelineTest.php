<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\E2E;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;
use Padosoft\ProductImageDiscovery\Jobs\AssessImageQualityJob;
use Padosoft\ProductImageDiscovery\Jobs\Contracts\PipelineStoreInterface;
use Padosoft\ProductImageDiscovery\Jobs\DownloadCandidateImageJob;
use Padosoft\ProductImageDiscovery\Jobs\ExtractCandidateSourcesJob;
use Padosoft\ProductImageDiscovery\Jobs\IngestProductImageDiscoveryJob;
use Padosoft\ProductImageDiscovery\Jobs\SearchProductImageJob;
use Padosoft\ProductImageDiscovery\Jobs\VerifyCandidateImageJob;
use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\LaravelAiSearchProviders\SearchProviderManager;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class LiveProductImagePipelineTest extends TestCase
{
    public function testLiveBravePipelineFindsVerifiesDownloadsAndAssessesARealProductImage(): void
    {
        $apiKey = $this->envValue('BRAVE_SEARCH_API_KEY');

        if ($apiKey === null || $apiKey === '') {
            self::markTestSkipped('Set BRAVE_SEARCH_API_KEY in .env to run the live full pipeline test.');
        }

        Bus::fake();
        $this->loadMigrationsFrom(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
        $this->artisan('migrate')->run();

        ProductImageSearchProvider::query()->create([
            'code' => 'brave-live',
            'name' => 'Brave Live',
            'driver' => 'brave',
            'base_url' => 'https://api.search.brave.com',
            'api_key_encrypted' => $apiKey,
            'config' => [
                'supports_image_search' => true,
                'supports_site_filter' => true,
            ],
            'priority' => 1,
            'timeout_seconds' => 20,
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        /** @var PipelineStoreInterface $store */
        $store = $this->app->make(PipelineStoreInterface::class);
        /** @var ProductImageEventLogger $logger */
        $logger = $this->app->make(ProductImageEventLogger::class);
        /** @var SearchProviderManager $searchManager */
        $searchManager = $this->app->make(SearchProviderManager::class);

        $request = (new IngestProductImageDiscoveryJob([
            'client_id' => 1,
            'erp_model_id' => 'NIKE-AF1-07',
            'erp_model_color_id' => 'NIKE-AF1-07-CW2288-111',
            'brand' => 'Nike',
            'supplier' => 'Nike',
            'supplier_sku' => 'CW2288-111',
            'model_code' => 'Air Force 1 07',
            'color_code' => 'CW2288-111',
            'color_name' => 'White',
            'category' => 'Sneakers',
            'material' => 'Leather',
        ]))->handle($store, $logger);

        $request = (new SearchProductImageJob($request['id']))->handle($store, $searchManager, $logger);
        self::assertNotEmpty($request['context']['search']['execution']['results'] ?? [], 'Live Brave search returned no candidate results.');

        (new ExtractCandidateSourcesJob($request['id']))->handle($store, $logger);
        $candidates = $store->listCandidates($request['id']);
        self::assertNotEmpty($candidates, 'Extraction produced no candidates from live Brave results.');

        $verified = null;
        $downloaded = null;
        $lastDownloadError = null;

        foreach ($candidates as $candidate) {
            $checked = (new VerifyCandidateImageJob($request['id'], $candidate['id']))->handle($store, $logger);

            if (($checked['status'] ?? null) !== ProductImageDiscoveryCandidateStatus::VerifiedMatch->value) {
                continue;
            }

            $verified = $checked;

            try {
                $downloaded = (new DownloadCandidateImageJob($request['id'], $verified['id']))->handle($store, $logger);
                break;
            } catch (ConnectionException|RequestException $exception) {
                $lastDownloadError = $exception->getMessage();
            }
        }

        self::assertNotNull($verified, 'No live candidate passed deterministic verification.');
        self::assertNotEmpty($verified['image_url']);
        self::assertGreaterThanOrEqual(45, (int) ($verified['final_score'] ?? 0));

        if ($downloaded === null) {
            self::markTestSkipped('Live candidates passed verification, but third-party image downloads were blocked or unavailable: ' . ($lastDownloadError ?? 'unknown download error'));
        }

        self::assertSame(ProductImageDiscoveryCandidateStatus::Downloaded->value, $downloaded['status']);
        self::assertGreaterThan(0, (int) ($downloaded['file_size'] ?? 0));
        self::assertNotEmpty($downloaded['sha256'] ?? null);

        $quality = (new AssessImageQualityJob($request['id'], $verified['id']))->handle($store, $logger);
        self::assertContains($quality['status'], [
            ProductImageDiscoveryCandidateStatus::QualityPassed->value,
            ProductImageDiscoveryCandidateStatus::QualityFailed->value,
        ]);
        self::assertArrayHasKey('quality_score', $quality);

        $finalRequest = $store->getRequest($request['id']);
        self::assertNotNull($finalRequest);
        self::assertNotEmpty($finalRequest['best_candidate_id'] ?? null);
    }

    private function envValue(string $key): ?string
    {
        $value = getenv($key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

        if (! is_file($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $rawValue] = explode('=', $line, 2);

            if (trim($name) !== $key) {
                continue;
            }

            $rawValue = trim($rawValue);

            if (
                strlen($rawValue) >= 2
                && (($rawValue[0] === '"' && $rawValue[strlen($rawValue) - 1] === '"')
                    || ($rawValue[0] === "'" && $rawValue[strlen($rawValue) - 1] === "'"))
            ) {
                $rawValue = substr($rawValue, 1, -1);
            }

            return trim($rawValue);
        }

        return null;
    }
}
