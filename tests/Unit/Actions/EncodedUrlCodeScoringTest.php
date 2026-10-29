<?php

declare(strict_types=1);

use Padosoft\ProductImageDiscovery\Actions\ScoreCandidateImageAction;
use Padosoft\ProductImageDiscovery\DTO\CandidateScoreData;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Services/Support/TextNormalizer.php';
require_once dirname(__DIR__, 3) . '/src/Services/Support/DomainNormalizer.php';
require_once dirname(__DIR__, 3) . '/src/DTO/ProductIdentityData.php';
require_once dirname(__DIR__, 3) . '/src/DTO/CandidateImageData.php';
require_once dirname(__DIR__, 3) . '/src/DTO/CandidateScoreData.php';
require_once dirname(__DIR__, 3) . '/src/Actions/ScoreCandidateImageAction.php';

/**
 * Gescat request 21 (Jil Sander J15WS0047 P7821 904, "Etage velvet sneakers"): Tavily returned four
 * vitkac.com images whose URLs spell the code with encoded spaces. Three came with a description
 * repeating the code and scored 65; the one without a description was read as "20J15WS0047" and
 * rejected at 11, although the AI confirmed it.
 */
final class EncodedUrlCodeScoringTest extends TestCase
{
    private const IMAGE = 'https://img.vitkac.com/uploads/product_thumb/BUTY%20J15WS0047%20P7821-904/up/1.png';

    private const AI_CONFIRMS = [
        'available' => true,
        'status' => 'completed',
        'confidence' => 95,
        'match' => true,
        'variant_safe' => true,
        'brand_match' => true,
        'model_match' => true,
        'product_type_match' => true,
        'color_match' => true,
    ];

    public function test_the_code_in_an_encoded_url_counts_as_a_model_match(): void
    {
        $score = $this->score([
            'source_page_url' => self::IMAGE,
            'image_url' => self::IMAGE,
            'title' => 'Tavily image result',
        ]);

        self::assertSame(['model_phrase', 'color_code'], $score->evidence['matches']);
        self::assertSame(['model_phrase'], $score->evidence['strong_matches']);
        self::assertSame(37, $score->textualMatchScore);
    }

    public function test_an_ai_confirmation_of_the_image_without_a_description_now_reaches_the_threshold(): void
    {
        $score = $this->score([
            'source_page_url' => self::IMAGE,
            'image_url' => self::IMAGE,
            'title' => 'Tavily image result',
            'ai_analysis' => ['verification' => self::AI_CONFIRMS],
        ], ['ai_confirms_identifier_match' => true]);

        self::assertSame(33, $score->visualMatchScore);
        self::assertSame(55, $score->finalScore);
        self::assertSame('candidate', $score->status);
    }

    public function test_the_images_with_a_description_keep_their_score(): void
    {
        $description = 'The image shows a pair of Jil Sander J15WS0047 P7821 904 904 shoes, featuring a rich burgundy exterior.';
        $image = str_replace('/1.png', '/6.png', self::IMAGE);

        $score = $this->score([
            'source_page_url' => $image,
            'image_url' => $image,
            'title' => $description,
            'description' => $description,
            'ai_analysis' => ['verification' => self::AI_CONFIRMS],
        ], ['ai_confirms_identifier_match' => true]);

        self::assertSame(47, $score->textualMatchScore);
        self::assertSame(65, $score->finalScore);
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $settings
     */
    private function score(array $candidate, array $settings = []): CandidateScoreData
    {
        $identity = ProductIdentityData::fromArray([
            'brand' => 'Jil Sander',
            'supplier_sku' => 'J15WS0047 P7821 904',
            'model_code' => 'J15WS0047 P7821',
            'color_code' => '904',
            'raw_payload' => ['name' => 'Etage velvet sneakers'],
        ]);

        return (new ScoreCandidateImageAction())->handle($identity, $candidate, [], $settings);
    }
}
