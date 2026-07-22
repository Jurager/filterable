<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\SortResolver;
use Jurager\Filterable\Filterable;
use Jurager\Filterable\Tests\Fixtures\Post;

class SortingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
            public function resolve(Builder $query, string $field, string $direction, Model $model): bool
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
}
