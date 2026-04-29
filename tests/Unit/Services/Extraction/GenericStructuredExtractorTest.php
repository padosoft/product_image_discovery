<?php

declare(strict_types=1);

use Padosoft\ProductImageDiscovery\Services\Extraction\GenericStructuredExtractor;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/src/Services/Support/TextNormalizer.php';
require_once dirname(__DIR__, 4) . '/src/Services/Support/DomainNormalizer.php';
require_once dirname(__DIR__, 4) . '/src/DTO/CandidateImageData.php';
require_once dirname(__DIR__, 4) . '/src/Services/Extraction/GenericStructuredExtractor.php';

final class GenericStructuredExtractorTest extends TestCase
{
    public function test_it_extracts_json_ld_product_open_graph_and_gallery_images(): void
    {
        $html = <<<'HTML'
<!doctype html>
<html>
<head>
  <link rel="canonical" href="/product/ab123">
  <meta property="og:title" content="Acme AB123 black jacket">
  <meta property="og:image" content="/og/ab123.jpg">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Product",
        "name": "Acme AB123",
        "brand": {"name": "Acme"},
        "sku": "SKU-123",
        "mpn": "AB123",
        "color": "black",
        "image": ["/images/ab123-main.jpg"]
      }
    ]
  }
  </script>
</head>
<body>
  <img class="site-logo" src="/logo.png" width="80" height="40" alt="logo">
  <img class="product-gallery" srcset="/small.jpg 300w, /large.jpg 1200w" width="900" height="1200" alt="Acme AB123 black">
</body>
</html>
HTML;

        $candidates = (new GenericStructuredExtractor())->extract($html, 'https://shop.example/product/ab123');
        $imageUrls = array_map(static fn ($candidate) => $candidate->imageUrl, $candidates);

        self::assertContains('https://shop.example/images/ab123-main.jpg', $imageUrls);
        self::assertContains('https://shop.example/og/ab123.jpg', $imageUrls);
        self::assertContains('https://shop.example/large.jpg', $imageUrls);
        self::assertNotContains('https://shop.example/logo.png', $imageUrls);
        self::assertSame('Acme', $candidates[0]->structuredData['brand']);
    }

    public function test_malformed_json_ld_does_not_break_extraction(): void
    {
        $html = '<script type="application/ld+json">{bad json</script><meta property="og:image" content="/ok.jpg">';

        $candidates = (new GenericStructuredExtractor())->extract($html, 'https://shop.example/p');

        self::assertCount(1, $candidates);
        self::assertSame('https://shop.example/ok.jpg', $candidates[0]->imageUrl);
    }
}
