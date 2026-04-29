<?php

declare(strict_types=1);

use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use Padosoft\ProductImageDiscovery\Services\Extraction\PatternSourceResolver;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/src/Services/Support/TextNormalizer.php';
require_once dirname(__DIR__, 4) . '/src/Services/Support/DomainNormalizer.php';
require_once dirname(__DIR__, 4) . '/src/DTO/ProductIdentityData.php';
require_once dirname(__DIR__, 4) . '/src/DTO/CandidateImageData.php';
require_once dirname(__DIR__, 4) . '/src/Services/Extraction/PatternSourceResolver.php';

final class PatternSourceResolverTest extends TestCase
{
    public function test_it_resolves_only_active_searchable_sources_and_skips_missing_placeholders(): void
    {
        $identity = ProductIdentityData::fromArray([
            'ean' => '8012345678901',
            'model_code' => 'AB123',
        ]);

        $candidates = (new PatternSourceResolver())->resolve($identity, [
            [
                'domain' => 'https://www.trusted.example',
                'allow_search' => true,
                'is_active' => true,
                'trust_score' => 90,
                'url_patterns' => [
                    ['type' => 'image', 'pattern' => '/images/{ean}.jpg'],
                    ['type' => 'page', 'pattern' => '/p/{missing_code}'],
                ],
            ],
            [
                'domain' => 'inactive.example',
                'allow_search' => true,
                'is_active' => false,
                'url_patterns' => ['/images/{ean}.jpg'],
            ],
        ]);

        self::assertCount(1, $candidates);
        self::assertSame('https://trusted.example/images/8012345678901.jpg', $candidates[0]->imageUrl);
        self::assertSame('pattern:image', $candidates[0]->resolverName);
        self::assertTrue($candidates[0]->sourceTrusted);
    }
}
