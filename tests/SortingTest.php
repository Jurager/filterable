<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\SortResolver;
use Jurager\Filterable\Filterable;
use Jurager\Filterable\FilterableFactory;
use Jurager\Filterable\Tests\Fixtures\Article;
use Jurager\Filterable\Tests\Fixtures\Post;
use Jurager\Filterable\Tests\Fixtures\RecordingSortResolver;
use Jurager\Filterable\Tests\Fixtures\TitleLengthSortResolver;

class SortingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingSortResolver::$seen = null;

        Post::create(['title' => 'Beta Book', 'price' => 20.00]);
        Post::create(['title' => 'Alpha Phone', 'price' => 150.00]);
        Post::create(['title' => 'Gamma Gadget', 'price' => 99.99]);
    }

    public function test_sort_ascending(): void
    {
        $titles = Post::query()->sort('price')->pluck('title');

        $this->assertSame(['Beta Book', 'Gamma Gadget', 'Alpha Phone'], $titles->all());
    }

    public function test_sort_descending(): void
    {
        $titles = Post::query()->sort('-price')->pluck('title');

        $this->assertSame(['Alpha Phone', 'Gamma Gadget', 'Beta Book'], $titles->all());
    }

    public function test_null_sort_is_a_no_op(): void
    {
        $count = Post::query()->sort(null)->count();

        $this->assertSame(3, $count);
    }

    public function test_unsortable_field_is_silently_ignored(): void
    {
        $count = Post::query()->sort('not_a_sortable_field')->count();

        $this->assertSame(3, $count);
    }

    public function test_custom_sort_resolver(): void
    {
        $resolver = new class () implements SortResolver {
            public function resolve(object $query, string $field, string $direction, Model $model, array $context = []): bool
            {
                if ($field !== 'title_length') {
                    return false;
                }

                $query->orderByRaw('length(title) ' . $direction);

                return true;
            }
        };

        $filterable = new Filterable();
        $filterable->addSortResolver($resolver);

        $query = Post::query();

        $filterable->sort($query, '-title_length');

        $this->assertSame('Gamma Gadget', $query->pluck('title')->first());
    }

    public function test_sort_resolver_receives_context(): void
    {
        $resolver = new class () implements SortResolver {
            public function resolve(object $query, string $field, string $direction, Model $model, array $context = []): bool
            {
                if ($field !== 'above_threshold' || ! isset($context['threshold'])) {
                    return false;
                }

                $query->orderByRaw('(price > ?) ' . $direction, [$context['threshold']]);

                return true;
            }
        };

        $filterable = new Filterable();
        $filterable->addSortResolver($resolver);

        $query = Post::query();

        $filterable->sort($query, '-above_threshold', ['threshold' => 100.00]);

        $this->assertSame('Alpha Phone', $query->pluck('title')->first());
    }

    public function test_sort_resolver_without_context_is_silently_ignored(): void
    {
        $resolver = new class () implements SortResolver {
            public function resolve(object $query, string $field, string $direction, Model $model, array $context = []): bool
            {
                if ($field !== 'above_threshold' || ! isset($context['threshold'])) {
                    return false;
                }

                $query->orderByRaw('(price > ?) ' . $direction, [$context['threshold']]);

                return true;
            }
        };

        $filterable = new Filterable();
        $filterable->addSortResolver($resolver);

        $query = Post::query();

        $filterable->sort($query, '-above_threshold');

        $this->assertSame(3, $query->count());
    }

    public function test_context_reaches_resolver_through_the_scope(): void
    {
        $count = Post::query()->sort('-above_threshold', ['threshold' => 100.00])->count();

        $this->assertSame(3, $count);
    }

    public function test_included_filters_reach_sort_resolvers_as_context(): void
    {
        Post::query()
            ->filter(['included.prices.currency' => ['in' => 'USD']])
            ->sort('-recording_field')
            ->get();

        $this->assertSame(['prices.currency' => ['in' => 'USD']], RecordingSortResolver::$seen);
    }

    public function test_context_requires_filter_to_be_chained_before_sort(): void
    {
        Post::query()
            ->sort('-recording_field')
            ->filter(['included.prices.currency' => ['in' => 'USD']])
            ->get();

        $this->assertSame([], RecordingSortResolver::$seen, 'sort() picks up context at call time, so filter() has to come first');
    }

    public function test_context_is_not_reused_by_a_later_sort_without_filter(): void
    {
        $model = new Post();

        $model->newQuery()->filter(['included.prices.currency' => ['in' => 'USD']])->sort('-recording_field')->get();
        $model->newQuery()->sort('-recording_field')->get();

        $this->assertSame([], RecordingSortResolver::$seen);
    }

    public function test_context_is_not_shared_between_queries_of_one_model_instance(): void
    {
        $model = new Post();

        $first  = $model->newQuery()->filter(['included.prices.currency' => ['in' => 'USD']])->sort('-recording_field');
        $second = $model->newQuery()->filter(['included.prices.currency' => ['in' => 'EUR']])->sort('-recording_field');

        $second->get();
        $this->assertSame(['prices.currency' => ['in' => 'EUR']], RecordingSortResolver::$seen);

        $first->get();
        $this->assertSame(['prices.currency' => ['in' => 'USD']], RecordingSortResolver::$seen);
    }

    public function test_explicit_context_wins_over_recorded_one(): void
    {
        Post::query()
            ->filter(['included.prices.currency' => ['in' => 'USD']])
            ->sort('-recording_field', ['prices.currency' => ['in' => 'EUR']])
            ->get();

        $this->assertSame(['prices.currency' => ['in' => 'EUR']], RecordingSortResolver::$seen);
    }

    public function test_declared_resolver_is_resolved_from_the_container(): void
    {
        $filterable = (new FilterableFactory())->make([], [], [], [TitleLengthSortResolver::class]);

        $query = Post::query();

        $filterable->sort($query, '-title_length');

        $this->assertSame('Gamma Gadget', $query->pluck('title')->first());
    }

    public function test_declared_resolver_accepts_an_instance(): void
    {
        $filterable = (new FilterableFactory())->make([], [], [], [new TitleLengthSortResolver()]);

        $query = Post::query();

        $filterable->sort($query, 'title_length');

        $this->assertSame('Beta Book', $query->pluck('title')->first());
    }

    public function test_declared_resolver_serves_filtering_too(): void
    {
        $count = Article::query()->filter(['cheap' => 100])->count();

        $this->assertSame(2, $count, 'FieldResolver declared in $resolvers should handle the virtual key');
    }

    public function test_one_declared_resolver_can_serve_both_contracts(): void
    {
        $titles = Article::query()->sort('-cheapness')->pluck('title');

        $this->assertSame(['Alpha Phone', 'Gamma Gadget', 'Beta Book'], $titles->all());
    }
}
