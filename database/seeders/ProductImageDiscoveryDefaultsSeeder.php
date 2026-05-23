<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductImageDiscoveryDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = Carbon::now();

        foreach ($this->defaultSettings() as $setting) {
            DB::table('product_image_discovery_settings')->updateOrInsert(
                [
                    'client_id' => null,
                    'setting_key' => $setting['setting_key'],
                ],
                [
                    'setting_value' => json_encode($setting['setting_value'], JSON_THROW_ON_ERROR),
                    'value_type' => $setting['value_type'],
                    'description' => $setting['description'],
                    'is_active' => $setting['is_active'],
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ],
            );
        }

        foreach ($this->defaultProviders() as $provider) {
            DB::table('product_image_search_providers')->updateOrInsert(
                ['code' => $provider['code']],
                [
                    'name' => $provider['name'],
                    'driver' => $provider['driver'],
                    'base_url' => $provider['base_url'],
                    'config' => json_encode($provider['config'], JSON_THROW_ON_ERROR),
                    'priority' => $provider['priority'],
                    'timeout_seconds' => $provider['timeout_seconds'],
                    'rate_limit_per_minute' => $provider['rate_limit_per_minute'],
                    'is_active' => $provider['is_active'],
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ],
            );
        }
    }

    /**
     * @return array<int, array{setting_key: string, setting_value: mixed, value_type: string, description: string, is_active: bool}>
     */
    protected function defaultSettings(): array
    {
        return [
            ['setting_key' => 'matching.weights.source_score', 'setting_value' => 25, 'value_type' => 'integer', 'description' => 'Weight applied to trusted source affinity.', 'is_active' => true],
            ['setting_key' => 'matching.weights.textual_score', 'setting_value' => 20, 'value_type' => 'integer', 'description' => 'Weight applied to textual candidate matching.', 'is_active' => true],
            ['setting_key' => 'matching.weights.structured_score', 'setting_value' => 20, 'value_type' => 'integer', 'description' => 'Weight applied to structured data matching.', 'is_active' => true],
            ['setting_key' => 'matching.weights.visual_score', 'setting_value' => 20, 'value_type' => 'integer', 'description' => 'Weight applied to visual verification.', 'is_active' => true],
            ['setting_key' => 'matching.thresholds.auto_publish', 'setting_value' => 90, 'value_type' => 'integer', 'description' => 'Minimum score for automatic publication.', 'is_active' => true],
            ['setting_key' => 'matching.thresholds.auto_accept', 'setting_value' => 80, 'value_type' => 'integer', 'description' => 'Minimum score for automatic acceptance.', 'is_active' => true],
            ['setting_key' => 'matching.thresholds.manual_review', 'setting_value' => 60, 'value_type' => 'integer', 'description' => 'Minimum score for manual review routing.', 'is_active' => true],
            ['setting_key' => 'matching.thresholds.reject_below', 'setting_value' => 60, 'value_type' => 'integer', 'description' => 'Scores below this threshold are rejected.', 'is_active' => true],
            ['setting_key' => 'search.max_queries_per_product', 'setting_value' => 5, 'value_type' => 'integer', 'description' => 'Maximum search queries generated for a product.', 'is_active' => true],
            ['setting_key' => 'search.max_results_per_query', 'setting_value' => 20, 'value_type' => 'integer', 'description' => 'Maximum provider results collected per query.', 'is_active' => true],
            ['setting_key' => 'search.max_candidate_pages', 'setting_value' => 10, 'value_type' => 'integer', 'description' => 'Maximum source pages fetched for candidate extraction.', 'is_active' => true],
            ['setting_key' => 'search.max_candidate_images', 'setting_value' => 30, 'value_type' => 'integer', 'description' => 'Maximum candidate images retained before scoring.', 'is_active' => true],
            ['setting_key' => 'scraping.respect_robots_txt', 'setting_value' => true, 'value_type' => 'boolean', 'description' => 'Global robots.txt compliance flag.', 'is_active' => true],
            ['setting_key' => 'ai.vision_verification_enabled', 'setting_value' => false, 'value_type' => 'boolean', 'description' => 'Enable AI-based vision verification.', 'is_active' => true],
            ['setting_key' => 'ai.image_enhancement_enabled', 'setting_value' => false, 'value_type' => 'boolean', 'description' => 'Enable AI-based image enhancement.', 'is_active' => true],
            ['setting_key' => 'ai.description_generation_enabled', 'setting_value' => false, 'value_type' => 'boolean', 'description' => 'Enable AI-generated descriptions.', 'is_active' => true],
            ['setting_key' => 'quality.min_width', 'setting_value' => 800, 'value_type' => 'integer', 'description' => 'Minimum accepted width for source images.', 'is_active' => true],
            ['setting_key' => 'quality.min_height', 'setting_value' => 800, 'value_type' => 'integer', 'description' => 'Minimum accepted height for source images.', 'is_active' => true],
            ['setting_key' => 'quality.reject_watermark', 'setting_value' => true, 'value_type' => 'boolean', 'description' => 'Reject images containing watermarks.', 'is_active' => true],
            ['setting_key' => 'quality.reject_text_overlay', 'setting_value' => true, 'value_type' => 'boolean', 'description' => 'Reject images containing text overlays.', 'is_active' => true],
        ];
    }

    /**
     * @return array<int, array{code: string, name: string, driver: string, base_url: string, config: array<string, mixed>, priority: int, timeout_seconds: int, rate_limit_per_minute: ?int, is_active: bool}>
     */
    protected function defaultProviders(): array
    {
        return [
            [
                'code' => 'brave',
                'name' => 'Brave Search',
                'driver' => 'brave',
                'base_url' => 'https://api.search.brave.com',
                'config' => [
                    'supports_image_search' => true,
                    'supports_site_filter' => true,
                    'supports_web_search' => true,
                    'max_results_per_request' => 20,
                ],
                'priority' => 10,
                'timeout_seconds' => 15,
                'rate_limit_per_minute' => 60,
                'is_active' => false,
            ],
            [
                'code' => 'tavily',
                'name' => 'Tavily Search',
                'driver' => 'tavily',
                'base_url' => 'https://api.tavily.com',
                'config' => [
                    'supports_image_search' => true,
                    'supports_site_filter' => true,
                    'supports_web_search' => true,
                    'max_results_per_request' => 20,
                    'search_depth' => 'basic',
                ],
                'priority' => 40,
                'timeout_seconds' => 20,
                'rate_limit_per_minute' => 60,
                'is_active' => false,
            ],
            [
                'code' => 'exa',
                'name' => 'Exa.ai Search',
                'driver' => 'exa',
                'base_url' => 'https://api.exa.ai',
                'config' => [
                    'supports_image_search' => true,
                    'supports_site_filter' => true,
                    'supports_web_search' => true,
                    'max_results_per_request' => 25,
                    'search_type' => 'auto',
                    'image_links_per_result' => 5,
                ],
                'priority' => 50,
                'timeout_seconds' => 20,
                'rate_limit_per_minute' => 60,
                'is_active' => false,
            ],
            [
                'code' => 'firecrawl',
                'name' => 'Firecrawl Search',
                'driver' => 'firecrawl',
                'base_url' => 'https://api.firecrawl.dev',
                'config' => [
                    'supports_image_search' => true,
                    'supports_site_filter' => true,
                    'supports_web_search' => true,
                    'max_results_per_request' => 20,
                ],
                'priority' => 60,
                'timeout_seconds' => 60,
                'rate_limit_per_minute' => 30,
                'is_active' => false,
            ],
            [
                'code' => 'websearchapi',
                'name' => 'WebSearchAPI.ai',
                'driver' => 'websearchapi',
                'base_url' => 'https://api.websearchapi.ai',
                'config' => [
                    'supports_image_search' => false,
                    'supports_site_filter' => true,
                    'supports_web_search' => true,
                    'max_results_per_request' => 20,
                    'include_content' => false,
                ],
                'priority' => 70,
                'timeout_seconds' => 30,
                'rate_limit_per_minute' => 60,
                'is_active' => false,
            ],
            [
                'code' => 'serpapi',
                'name' => 'SerpAPI',
                'driver' => 'serpapi',
                'base_url' => 'https://serpapi.com',
                'config' => [
                    'supports_image_search' => true,
                    'supports_site_filter' => true,
                    'supports_web_search' => true,
                    'max_results_per_request' => 20,
                ],
                'priority' => 20,
                'timeout_seconds' => 15,
                'rate_limit_per_minute' => 60,
                'is_active' => false,
            ],
            [
                'code' => 'google_custom_search',
                'name' => 'Google Custom Search',
                'driver' => 'google_custom_search',
                'base_url' => 'https://customsearch.googleapis.com',
                'config' => [
                    'supports_image_search' => true,
                    'supports_site_filter' => true,
                    'supports_web_search' => true,
                    'max_results_per_request' => 10,
                ],
                'priority' => 30,
                'timeout_seconds' => 15,
                'rate_limit_per_minute' => 30,
                'is_active' => false,
            ],
        ];
    }
}
