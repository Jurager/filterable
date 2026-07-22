<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

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
}
