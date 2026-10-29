<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Padosoft\ProductImageDiscovery\Jobs\AssessImageQualityJob;
use Padosoft\ProductImageDiscovery\Jobs\DownloadCandidateImageJob;
use Padosoft\ProductImageDiscovery\Jobs\ExtractCandidateSourcesJob;
use Padosoft\ProductImageDiscovery\Jobs\IngestProductImageDiscoveryJob;
use Padosoft\ProductImageDiscovery\Jobs\SearchProductImageJob;
use Padosoft\ProductImageDiscovery\Jobs\VerifyCandidateImageJob;
use Padosoft\ProductImageDiscovery\Models\ProductImageDiscoveryRequest;
use Padosoft\ProductImageDiscovery\Services\Logging\ProductImageEventLogger;
use Padosoft\ProductImageDiscovery\Services\Storage\EloquentPipelineStore;
use Padosoft\ProductImageDiscovery\Tests\Support\ScriptedSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

/**
 * Gescat request 20: a retry found the image of the first run again (same fingerprint, so the same
 * candidate) and the request stayed "matched" for good. The download job saw the first run's file
 * and stopped without starting the quality check.
 */
final class RetrySameImageTest extends TestCase
{
    private EloquentPipelineStore $store;

    private ProductImageEventLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        $this->artisan('migrate')->run();
        Bus::fake();
        Storage::fake('local');
        Http::fake(['shop.example.test/*' => Http::response(str_repeat('a', 120000), 200, ['Content-Type' => 'image/jpeg'])]);
        $this->store = new EloquentPipelineStore();
        $this->logger = app(ProductImageEventLogger::class);
    }

    public function test_a_retry_that_finds_the_same_image_downloads_and_checks_it_again(): void
    {
        $provider = new ScriptedSearchProvider(['Brand' => [[
            'title' => 'Brand Model Red',
            'page_url' => 'https://shop.example.test/p/1',
            'image_url' => 'https://shop.example.test/p/1.jpg',
            'source_domain' => 'shop.example.test',
            'width' => 1200,
            'height' => 1200,
        ]]]);
        $requestId = (new IngestProductImageDiscoveryJob([
            'client_id' => 1,
            'erp_model_id' => 'MODEL',
            'erp_model_color_id' => 'MODEL-RED',
            'brand' => 'Brand',
            'model_code' => 'Model',
            'color_name' => 'Red',
        ]))->handle($this->store, $this->logger)['id'];

        $candidateId = $this->searchAndExtract($provider, $requestId);
        $this->verifyDownloadAndCheck($requestId, $candidateId);
        $firstRunStatus = $this->store->getRequest($requestId)['status'];

        self::assertSame('quality_passed', $this->store->getCandidate($candidateId)['status']);
        self::assertNotNull($this->store->getCandidate($candidateId)['local_original_path']);
        self::assertNotSame('matched', $firstRunStatus);

        // POST /requests/{id}/retry clears the pipeline context: run 2 finds the same image.
        $request = ProductImageDiscoveryRequest::query()->findOrFail($requestId);
        $request->forceFill(['status' => 'queued', 'raw_payload' => array_diff_key($request->raw_payload, ['context' => true])])->save();

        self::assertSame($candidateId, $this->searchAndExtract($provider, $requestId));

        $reused = $this->store->getCandidate($candidateId);
        self::assertSame(2, $reused['search_run']);
        self::assertNull($reused['local_original_path']);
        self::assertNull($reused['downloaded_at']);
        self::assertNull($reused['quality_checked_at']);

        $this->verifyDownloadAndCheck($requestId, $candidateId);
        $candidate = $this->store->getCandidate($candidateId);

        self::assertSame('quality_passed', $candidate['status']);
        self::assertNotNull($candidate['local_original_path']);
        self::assertNotNull($candidate['quality_checked_at']);
        self::assertSame($firstRunStatus, $this->store->getRequest($requestId)['status']);
        Http::assertSentCount(2);
    }

    private function searchAndExtract(ScriptedSearchProvider $provider, int|string $requestId): int|string
    {
        (new SearchProductImageJob($requestId))->handle($this->store, $provider->manager(), $this->logger);
        (new ExtractCandidateSourcesJob($requestId))->handle($this->store, $this->logger);

        $candidates = $this->store->listCandidates($requestId);
        self::assertCount(1, $candidates);

        return $candidates[0]['id'];
    }

    private function verifyDownloadAndCheck(int|string $requestId, int|string $candidateId): void
    {
        (new VerifyCandidateImageJob($requestId, $candidateId))->handle($this->store, $this->logger);
        (new DownloadCandidateImageJob($requestId, $candidateId))->handle($this->store, $this->logger);
        (new AssessImageQualityJob($requestId, $candidateId))->handle($this->store, $this->logger);
    }
}
