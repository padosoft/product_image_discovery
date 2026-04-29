<?php

declare(strict_types=1);

use Padosoft\ProductImageDiscovery\Actions\NormalizeProductIdentityAction;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Services/Support/TextNormalizer.php';
require_once dirname(__DIR__, 3) . '/src/DTO/ProductIdentityData.php';
require_once dirname(__DIR__, 3) . '/src/Actions/NormalizeProductIdentityAction.php';

final class ProductIdentityDataTest extends TestCase
{
    public function test_it_normalizes_identity_without_using_size_for_search_intent(): void
    {
        $identity = (new NormalizeProductIdentityAction())->handle([
            'client_id' => 10,
            'erp_model_id' => 'M-1',
            'erp_model_color_id' => 'M-1-BLK',
            'erp_model_color_size_id' => 'M-1-BLK-XL',
            'brand' => '  Acme  ',
            'supplier_sku' => ' sku- 001 ',
            'model_code' => ' ab-123 ',
            'color_name' => ' Nero ',
            'ean' => ' 80 12345 678901 ',
        ]);

        self::assertSame('Acme', $identity->brand);
        self::assertSame('8012345678901', $identity->ean);
        self::assertSame('SKU001', $identity->normalizedSupplierSku());
        self::assertSame('AB123', $identity->normalizedModelCode());
        self::assertSame('black', $identity->normalizedColorName());
        self::assertTrue($identity->hasStrongIdentifier());
        self::assertArrayNotHasKey('erp_model_color_size_id', $identity->toSearchIntent());
    }
}
