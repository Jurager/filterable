<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Jurager\Filterable\Tests\Fixtures\FlatCategory;
use Jurager\Filterable\Tests\Fixtures\TreeCategory;

class TreeConditionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TreeCategory::create(['id' => 1, 'name' => 'One']);
        TreeCategory::create(['id' => 2, 'name' => 'Two']);
        TreeCategory::create(['id' => 3, 'name' => 'Three']);

        FlatCategory::create(['id' => 1, 'name' => 'One']);
        FlatCategory::create(['id' => 2, 'name' => 'Two']);
        FlatCategory::create(['id' => 3, 'name' => 'Three']);
    }

    /** Tree support detected via the query builder (aimeos/laravel-nestedset style). */
    public function test_tree_operator_applies_when_query_builder_supports_it(): void
    {
        $results = TreeCategory::query()->filter(['id' => ['tree' => '1,3']])->get();

        $this->assertSame([1, 3], $results->pluck('id')->sort()->values()->all());
    }

    /** A single tree root still applies the constraint (not just the multi-id OR branch). */
    public function test_tree_operator_applies_with_a_single_root(): void
    {
        $results = TreeCategory::query()->filter(['id' => ['tree' => '2']])->get();

        $this->assertSame([2], $results->pluck('id')->all());
    }

    /** No model/query builder support anywhere: the tree filter is a silent no-op. */
    public function test_tree_operator_is_noop_without_any_support(): void
    {
        $results = FlatCategory::query()->filter(['id' => ['tree' => '1,3']])->get();

        $this->assertSame([1, 2, 3], $results->pluck('id')->sort()->values()->all());
    }
}
