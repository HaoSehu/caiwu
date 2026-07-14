<?php

namespace Tests\Unit;

use App\Models\FirstProductGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductGroupProjectionModelTest extends TestCase
{
    #[Test]
    public function first_product_group_writes_to_the_product_groups_source_table(): void
    {
        $this->assertSame('product_groups', (new FirstProductGroup)->getTable());
    }

    #[Test]
    public function admin_product_group_queries_do_not_reference_the_first_group_projection_view(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Services/ProductCatalog/ProductGroupV2QueryService.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString("->select('first_product_groups.*')", $source);
        $this->assertStringNotContainsString("productTreeCountSubquery('first_product_groups.id', 1)", $source);
        $this->assertStringNotContainsString("directProductCountSubquery('first_product_groups.id')", $source);
        $this->assertStringContainsString("->select('product_groups.*')", $source);
    }

    #[Test]
    public function coupon_first_group_query_uses_the_product_groups_source_table(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Services/ProductCatalog/CouponProductGroupQueryService.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString("productTreeCountSubquery('first_product_groups.id', 1)", $source);
        $this->assertStringNotContainsString("directProductCountSubquery('first_product_groups.id')", $source);
        $this->assertStringContainsString("productTreeCountSubquery('product_groups.id', 1)", $source);
    }

    #[Test]
    public function product_tree_count_subquery_uses_a_distinct_inner_group_alias(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Services/ProductCatalog/ProductGroupV2QueryService.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("->from('product_groups as group_tree')", $source);
        $this->assertStringContainsString("->whereColumn('group_tree.id', \$outerColumn)", $source);
    }
}
