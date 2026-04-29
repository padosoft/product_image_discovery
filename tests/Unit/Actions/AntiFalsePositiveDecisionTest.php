<?php

declare(strict_types=1);

use Padosoft\ProductImageDiscovery\Actions\ResolveDecisionAction;
use Padosoft\ProductImageDiscovery\Actions\ScoreCandidateImageAction;
use Padosoft\ProductImageDiscovery\DTO\CandidateImageData;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Services/Support/TextNormalizer.php';
require_once dirname(__DIR__, 3) . '/src/Services/Support/DomainNormalizer.php';
require_once dirname(__DIR__, 3) . '/src/DTO/ProductIdentityData.php';
require_once dirname(__DIR__, 3) . '/src/DTO/CandidateImageData.php';
require_once dirname(__DIR__, 3) . '/src/DTO/CandidateScoreData.php';
require_once dirname(__DIR__, 3) . '/src/Actions/ScoreCandidateImageAction.php';
require_once dirname(__DIR__, 3) . '/src/Actions/ResolveDecisionAction.php';

final class AntiFalsePositiveDecisionTest extends TestCase
{
    private ScoreCandidateImageAction $score;
    private ResolveDecisionAction $decision;
    private ProductIdentityData $identity;

    protected function setUp(): void
    {
        $this->score = new ScoreCandidateImageAction();
        $this->decision = new ResolveDecisionAction();
        $this->identity = ProductIdentityData::fromArray([
            'brand' => 'Acme',
            'model_code' => 'AB123',
            'color_name' => 'black',
            'supplier_sku' => 'SKU-123',
        ]);
    }

    public function test_wrong_color_is_not_auto_published_even_with_correct_brand_and_model(): void
    {
        $candidate = CandidateImageData::fromArray([
            'source_page_url' => 'https://trusted.example/products/ab123-blue',
            'image_url' => 'https://trusted.example/images/ab123-blue.jpg',
            'title' => 'Acme AB123 blue jacket',
            'structured_data' => ['brand' => 'Acme', 'mpn' => 'AB123', 'color' => 'blue'],
            'quality_score' => 95,
        ]);

        $score = $this->score->handle($this->identity, $candidate, $this->trustedSource());
        $decision = $this->decision->handle([$score]);

        self::assertTrue($score->colorMismatch);
        self::assertContains('WRONG_COLOR', $score->issues);
        self::assertNotSame('ready_to_publish', $decision['status']);
    }

    public function test_similar_model_code_is_not_treated_as_a_strong_match(): void
    {
        $candidate = CandidateImageData::fromArray([
            'source_page_url' => 'https://trusted.example/products/ab124-black',
            'image_url' => 'https://trusted.example/images/ab124-black.jpg',
            'title' => 'Acme AB124 black jacket',
            'quality_score' => 95,
            'ai_analysis' => ['match_score' => 99],
        ]);

        $score = $this->score->handle($this->identity, $candidate, $this->trustedSource());
        $decision = $this->decision->handle([$score]);

        self::assertTrue($score->modelMismatch);
        self::assertFalse($score->hasStrongMatch);
        self::assertNotSame('ready_to_publish', $decision['status']);
    }

    public function test_visual_ai_score_is_not_enough_without_strong_identifier_match(): void
    {
        $candidate = CandidateImageData::fromArray([
            'source_page_url' => 'https://trusted.example/products/generic-black-jacket',
            'image_url' => 'https://trusted.example/images/generic-black-jacket.jpg',
            'title' => 'Acme black padded jacket',
            'quality_score' => 96,
            'ai_analysis' => ['match_score' => 0.99],
        ]);

        $score = $this->score->handle($this->identity, $candidate, $this->trustedSource());
        $decision = $this->decision->handle([$score]);

        self::assertFalse($score->hasStrongMatch);
        self::assertSame('manual_review', $decision['status']);
        self::assertSame('missing_strong_identifier_match', $decision['reason']);
    }

    public function test_untrusted_source_is_not_auto_published_even_with_exact_model_match(): void
    {
        $candidate = CandidateImageData::fromArray([
            'source_page_url' => 'https://unknown.example/products/ab123-black',
            'image_url' => 'https://unknown.example/images/ab123-black.jpg',
            'title' => 'Acme AB123 black jacket',
            'structured_data' => ['brand' => 'Acme', 'mpn' => 'AB123', 'color' => 'black'],
            'quality_score' => 98,
        ]);

        $score = $this->score->handle($this->identity, $candidate);
        $decision = $this->decision->handle([$score]);

        self::assertTrue($score->hasStrongMatch);
        self::assertFalse($score->sourceTrusted);
        self::assertNotSame('ready_to_publish', $decision['status']);
        self::assertSame('source_not_auto_publishable', $decision['reason']);
    }

    public function test_multi_word_model_name_can_be_a_strong_match_for_real_catalog_titles(): void
    {
        $identity = ProductIdentityData::fromArray([
            'brand' => 'Nike',
            'model_code' => 'Air Force 1 07',
            'color_name' => 'white',
        ]);
        $candidate = CandidateImageData::fromArray([
            'source_page_url' => 'https://www.nike.com/t/air-force-1-07-mens-shoes-jBrhbr',
            'image_url' => 'https://static.nike.com/a/images/t_prod/w_1200/air-force-1-07.jpg',
            'title' => "Nike Air Force 1 '07 Men's Shoes White",
            'width' => 1200,
            'height' => 1200,
            'quality_score' => 95,
        ]);

        $score = $this->score->handle($identity, $candidate);

        self::assertTrue($score->hasStrongMatch);
        self::assertContains('model_phrase', $score->evidence['strong_matches']);
        self::assertTrue($score->modelMatched);
    }

    /**
     * @return array<string, mixed>
     */
    private function trustedSource(): array
    {
        return [
            'domain' => 'trusted.example',
            'trust_score' => 95,
            'allow_auto_publish' => true,
            'allow_download' => true,
            'is_active' => true,
        ];
    }
}
