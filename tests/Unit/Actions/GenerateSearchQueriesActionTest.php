<?php

declare(strict_types=1);

use Padosoft\ProductImageDiscovery\Actions\GenerateSearchQueriesAction;
use Padosoft\ProductImageDiscovery\DTO\ProductIdentityData;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Services/Support/TextNormalizer.php';
require_once dirname(__DIR__, 3) . '/src/Services/Support/DomainNormalizer.php';
require_once dirname(__DIR__, 3) . '/src/DTO/ProductIdentityData.php';
require_once dirname(__DIR__, 3) . '/src/DTO/SearchQueryData.php';
require_once dirname(__DIR__, 3) . '/src/Actions/GenerateSearchQueriesAction.php';

final class GenerateSearchQueriesActionTest extends TestCase
{
    public function test_it_prioritizes_strong_identifier_and_trusted_source_queries(): void
    {
        $identity = ProductIdentityData::fromArray([
            'brand' => 'Acme',
            'ean' => '8012345678901',
            'supplier_sku' => 'SKU-1',
            'model_code' => 'AB123',
            'color_code' => '001',
            'color_name' => 'Black',
            'description' => 'Padded jacket',
        ]);

        $queries = (new GenerateSearchQueriesAction())->handle($identity, [
            ['domain' => 'https://www.trusted.example', 'allow_search' => true, 'is_active' => true],
        ], ['max_queries' => 4, 'supports_site_filter' => true]);

        self::assertCount(4, $queries);
        self::assertSame('"Acme" "8012345678901"', $queries[0]->query);
        self::assertSame('ean', $queries[0]->intent);
        self::assertTrue((bool) array_filter($queries, static fn ($query) => str_contains($query->query, '"8012345678901"')));
        self::assertTrue((bool) array_filter($queries, static fn ($query) => str_starts_with($query->query, 'site:trusted.example')));
    }

    public function test_it_does_not_generate_generic_queries_without_strong_identifier(): void
    {
        $identity = ProductIdentityData::fromArray([
            'brand' => 'Acme',
            'description' => 'black nylon jacket',
            'color_name' => 'black',
        ]);

        $queries = (new GenerateSearchQueriesAction())->handle($identity);

        self::assertSame([], $queries);
    }

    public function test_it_prioritizes_color_aware_model_queries_before_bare_supplier_sku(): void
    {
        $identity = ProductIdentityData::fromArray([
            'brand' => 'Herno',
            'supplier_sku' => 'PI002223D',
            'model_code' => 'PI002223D',
            'color_code' => 'CAMMELLO',
            'color_name' => 'Cammello',
            'description' => 'Cappa In Nylon Ultralight Cammello',
        ]);

        $queries = (new GenerateSearchQueriesAction())->handle($identity, [], ['max_queries' => 4]);

        self::assertNotSame([], $queries);
        self::assertSame('"Herno" "PI002223D" "CAMMELLO"', $queries[0]->query);
        self::assertSame('supplier_sku_color_code', $queries[0]->intent);
        $bareSupplierIndex = array_search('supplier_sku', array_map(static fn ($query): string => $query->intent, $queries), true);

        self::assertIsInt($bareSupplierIndex);
        self::assertGreaterThan(0, $bareSupplierIndex);
        self::assertSame('"Herno" "PI002223D"', $queries[$bareSupplierIndex]->query);
    }
}
