<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use Padosoft\ProductImageDiscovery\Ai\Agents\ProductImageCandidateVerifierAgent;
use Padosoft\ProductImageDiscovery\Services\Ai\ProductImageAiVerifier;
use Padosoft\ProductImageDiscovery\Tests\TestCase;

final class ProductImageAiVerifierTest extends TestCase
{
    public function test_it_uses_laravel_ai_sdk_agent_and_returns_structured_verification(): void
    {
        ProductImageCandidateVerifierAgent::fake([[
            'match' => true,
            'variant_safe' => true,
            'confidence' => 91,
            'brand_match' => true,
            'model_match' => true,
            'color_match' => true,
            'product_type_match' => true,
            'image_quality_ok' => true,
            'rejection_reason' => '',
            'notes' => 'Candidate looks consistent with the product-color identity.',
        ]])->preventStrayPrompts();

        config()->set('product-image-discovery.ai.enabled', true);
        config()->set('product-image-discovery.ai.provider', 'regolo');
        config()->set('product-image-discovery.ai.providers.regolo.api_key', 'fake-key');
        config()->set('product-image-discovery.ai.fail_silently', false);

        $result = (new ProductImageAiVerifier())->verify([
            'brand' => 'Nike',
            'model_code' => 'Air Force 1 07',
            'color_name' => 'White',
        ], [
            'title' => "Nike Air Force 1 '07 Men's Shoes White",
            'source_page_url' => 'https://www.nike.com/t/air-force-1-07-mens-shoes-jBrhbr',
            'image_url' => 'https://static.nike.com/a/images/t_prod/w_1200/air-force-1-07.jpg',
            'width' => 1200,
            'height' => 1200,
        ]);

        self::assertTrue($result->available);
        self::assertTrue($result->match);
        self::assertTrue($result->variantSafe);
        self::assertSame(91, $result->confidence);
        self::assertSame('regolo', $result->provider);

        ProductImageCandidateVerifierAgent::assertPrompted(
            static fn (object $prompt): bool => str_contains($prompt->prompt, 'Air Force 1 07')
                && str_contains($prompt->prompt, 'candidate_image')
                && str_contains($prompt->prompt, 'wrong_product_is_worse_than_no_image')
                && str_contains($prompt->prompt, 'inspect_attached_image_before_metadata')
                && str_contains($prompt->prompt, 'accept_visible_color_synonyms'),
        );
    }

    public function test_it_skips_when_ai_is_disabled(): void
    {
        config()->set('product-image-discovery.ai.enabled', false);

        $result = (new ProductImageAiVerifier())->verify([], []);

        self::assertFalse($result->enabled);
        self::assertSame('skipped', $result->status);
    }
}
