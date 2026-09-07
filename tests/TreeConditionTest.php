<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jurager\Filterable\Tests\Fixtures\FlatCategory;
use Jurager\Filterable\Tests\Fixtures\TreeCategory;
use Jurager\Filterable\Tests\Fixtures\Variant;

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

        Schema::create('variants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
        });

        Schema::create('variant_category', function (Blueprint $table): void {
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('category_id');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('variant_category');
        Schema::dropIfExists('variants');

        parent::tearDown();
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

    /** matchesFilter() lets an already-resolved model (e.g. from route binding) be checked against a scope. */
    public function test_matches_filter_checks_a_resolved_model_against_a_tree_scope(): void
    {
        $inTree = TreeCategory::find(1);
        $outsideTree = TreeCategory::find(2);

        $this->assertTrue($inTree->matchesFilter(['id' => ['tree' => '1,3']]));
        $this->assertFalse($outsideTree->matchesFilter(['id' => ['tree' => '1,3']]));
    }

    /**
     * A `tree` filter through a multi-hop relation path (e.g. `parent.categories.id`) constrains
     * the *last* relation in the chain, not the first — a variant with no categories of its own
     * still matches through its parent's.
     */
    public function test_tree_operator_applies_through_a_multi_hop_relation_path(): void
    {
        $parentInTree = Variant::create(['name' => 'Parent in tree']);
        $parentInTree->categories()->attach(1);

        $parentOutsideTree = Variant::create(['name' => 'Parent outside tree']);
        $parentOutsideTree->categories()->attach(2);

        $variantInTree = Variant::create(['name' => 'Variant', 'parent_id' => $parentInTree->id]);
        Variant::create(['name' => 'Variant', 'parent_id' => $parentOutsideTree->id]);

        $results = Variant::query()->filter(['parent.categories.id' => ['tree' => '1']])->get();

        $this->assertSame([$variantInTree->id], $results->pluck('id')->all());
    }
}
