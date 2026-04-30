<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Padosoft\ProductImageDiscovery\Models\ProductImageSearchProvider;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class DebugFlowCommandTest extends TestCase
{
    public function testDebugFlowCommandRunsFromARequestJsonAndWritesReport(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
        $this->artisan('migrate')->run();
        config()->set('product-image-discovery.ai.enabled', false);

        ProductImageSearchProvider::query()->create([
            'code' => 'fake-debug',
            'name' => 'Fake Debug',
            'driver' => 'fake',
            'base_url' => 'https://example.test',
            'config' => [
                'supports_image_search' => true,
                'supports_site_filter' => true,
                'image_results' => [
                    [
                        'title' => 'Herno PI002223D Cappa In Nylon Ultralight Nera',
                        'page_url' => 'https://example.test/herno-pi002223d-nera',
                        'image_url' => 'data:image/jpeg;base64,' . base64_encode(str_repeat('b', 120000)),
                        'source_domain' => 'example.test',
                        'width' => 1200,
                        'height' => 1200,
                        'provider_metadata' => [
                            'inline_image_base64' => base64_encode(str_repeat('b', 120000)),
                            'inline_extension' => 'jpg',
                        ],
                    ],
                    [
                        'title' => 'Herno PI002223D Cappa In Nylon Ultralight Cammello',
                        'page_url' => 'https://example.test/herno-pi002223d-cammello',
                        'image_url' => 'data:image/jpeg;base64,' . base64_encode(str_repeat('a', 120000)),
                        'source_domain' => 'example.test',
                        'width' => 1200,
                        'height' => 1200,
                        'provider_metadata' => [
                            'inline_image_base64' => base64_encode(str_repeat('a', 120000)),
                            'inline_extension' => 'jpg',
                        ],
                    ],
                ],
            ],
            'priority' => 1,
            'timeout_seconds' => 5,
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        $dir = base_path('storage/testing');
        File::ensureDirectoryExists($dir);

        $requestPath = $dir . DIRECTORY_SEPARATOR . 'debug-flow-request.json';
        $reportPath = $dir . DIRECTORY_SEPARATOR . 'debug-flow-report.json';

        File::put($requestPath, json_encode([
            'client_id' => 1,
            'erp_model_id' => 'HERNO-PI002223D',
            'erp_model_color_id' => 'HERNO-PI002223D-CAMMELLO',
            'brand' => 'Herno',
            'supplier' => 'Herno',
            'supplier_sku' => 'PI002223D',
            'model_code' => 'PI002223D',
            'color_code' => 'CAMMELLO',
            'color_name' => 'Cammello',
            'category' => 'Donna > Maglie e camicie > Felpe e maglie',
            'material' => '100% Nylon',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $this->artisan('product-image-discovery:debug-flow', [
            'request' => $requestPath,
            '--max-candidates' => 2,
            '--report' => $reportPath,
            '--no-download' => true,
            '--no-env-brave' => true,
        ])->assertExitCode(0);

        self::assertFileExists($reportPath);

        $report = json_decode((string) File::get($reportPath), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('HERNO-PI002223D-CAMMELLO', $report['request']['erp_model_color_id'] ?? null);
        self::assertSame(2, $report['summary']['candidate_count'] ?? 0);
        self::assertSame(1, $report['summary']['candidates_checked'] ?? 0);
        self::assertSame(1, $report['summary']['verified_match_count'] ?? 0);
        self::assertTrue($report['summary']['stop_on_first_good'] ?? false);
        self::assertTrue($report['summary']['stopped_early'] ?? false);
        self::assertSame(65, $report['summary']['good_score_threshold'] ?? null);
        self::assertSame('fake-debug', $report['search']['provider'] ?? null);
        self::assertSame('https://example.test/herno-pi002223d-cammello', $report['candidates'][0]['source_page_url'] ?? null);
    }
}
