<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Illuminate\Support\Facades\DB;
use Jurager\Filterable\Tests\Fixtures\Post;

class IncludedRelationsTest extends TestCase
{
    private Post $alpha;

    private Post $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Post::create(['title' => 'Alpha Phone', 'price' => 150]);
        $this->beta  = Post::create(['title' => 'Beta Book', 'price' => 20]);

        $this->alpha->prices()->create(['price_type_id' => 1, 'amount' => 150]);
        $this->alpha->prices()->create(['price_type_id' => 2, 'amount' => 140]);
        $this->beta->prices()->create(['price_type_id' => 1, 'amount' => 20]);
    }

    public function test_included_prefix_eager_loads_scoped_relation_without_constraining_main_query(): void
    {
        $posts = Post::query()
            ->filter(['included.prices.price_type_id' => ['in' => [1]]])
            ->get();

        // Both posts are returned — the main query is not constrained.
        $this->assertCount(2, $posts);

        foreach ($posts as $post) {
            $this->assertTrue($post->relationLoaded('prices'));
            $this->assertTrue($post->prices->every(fn ($price) => (int) $price->price_type_id === 1));
        }

        $alpha = $posts->firstWhere('id', $this->alpha->id);

        $this->assertCount(1, $alpha->prices);
    }

    public function test_without_included_prefix_relation_filter_constrains_main_query(): void
    {
        $posts = Post::query()
            ->filter(['prices.price_type_id' => ['in' => [2]]])
            ->get();

        $this->assertCount(1, $posts);
        $this->assertSame('Alpha Phone', $posts->first()->title);
    }

    // -----------------------------------------------------------------------
    // loadIncludedRelations() — the per-model path, for a single model whose
    // query never went through filter() (e.g. Model::find() on a show endpoint).
    // -----------------------------------------------------------------------

    public function test_load_included_relations_skips_a_relation_already_loaded_by_the_query(): void
    {
        $filter = ['included.prices.price_type_id' => ['in' => [1]]];

        // filter()->get() already eager-loads "prices" for the whole result set, scoped the
        // same way loadIncludedRelations() would — mirrors what WithEagerIncludes does: filter
        // a listing at the query level, then call loadIncludedRelations() per model.
        $posts = Post::query()->filter($filter)->get();

        DB::enableQueryLog();

        foreach ($posts as $post) {
            $post->loadIncludedRelations($filter);
        }

        $this->assertSame([], DB::getQueryLog(), 'loadIncludedRelations() re-queried a relation the query builder already loaded.');

        DB::disableQueryLog();
    }

    public function test_load_included_relations_still_loads_a_relation_the_query_never_touched(): void
    {
        // A model fetched without filter() — e.g. Model::find() on a show endpoint — never had
        // "prices" eager-loaded. loadIncludedRelations() is what applies the included scope there,
        // and must still run the query in that case.
        $alpha = Post::query()->find($this->alpha->id);

        $this->assertFalse($alpha->relationLoaded('prices'));

        $alpha->loadIncludedRelations(['included.prices.price_type_id' => ['in' => [1]]]);

        $this->assertTrue($alpha->relationLoaded('prices'));
        $this->assertCount(1, $alpha->prices);
    }

    // -----------------------------------------------------------------------
    // loadIncludedRelationsForMany() — the collection counterpart, for results
    // that never went through filter() at all (e.g. a search engine's hits).
    // -----------------------------------------------------------------------

    public function test_load_included_relations_for_many_batches_into_one_query(): void
    {
        // Fetched independently of filter() — mirrors search-engine hits hydrated via
        // whereIn(id, ...), so neither post carries "prices" yet.
        $posts = Post::query()->whereIn('id', [$this->alpha->id, $this->beta->id])->get();

        foreach ($posts as $post) {
            $this->assertFalse($post->relationLoaded('prices'));
        }

        DB::enableQueryLog();

        Post::loadIncludedRelationsForMany($posts, ['included.prices.price_type_id' => ['in' => [1]]]);

        $priceQueries = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], '"prices"'));

        $this->assertCount(1, $priceQueries, 'one relation, one batched query — not one per model.');

        foreach ($posts as $post) {
            $this->assertTrue($post->relationLoaded('prices'));
            $this->assertTrue($post->prices->every(fn ($price) => (int) $price->price_type_id === 1));
        }

        $alpha = $posts->firstWhere('id', $this->alpha->id);

        $this->assertCount(1, $alpha->prices);
    }

    public function test_load_included_relations_for_many_skips_a_relation_already_loaded(): void
    {
        $filter = ['included.prices.price_type_id' => ['in' => [1]]];

        $posts = Post::query()->filter($filter)->get();

        DB::enableQueryLog();

        Post::loadIncludedRelationsForMany($posts, $filter);

        $this->assertSame([], DB::getQueryLog(), 'loadIncludedRelationsForMany() re-queried a relation the query builder already loaded.');

        DB::disableQueryLog();
    }

    public function test_load_included_relations_for_many_does_nothing_for_an_empty_collection(): void
    {
        $empty = Post::query()->whereRaw('1 = 0')->get();

        DB::enableQueryLog();

        Post::loadIncludedRelationsForMany($empty, ['included.prices.price_type_id' => ['in' => [1]]]);

        $this->assertSame([], DB::getQueryLog());

        DB::disableQueryLog();
    }
}
