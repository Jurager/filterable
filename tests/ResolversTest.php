<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\FieldResolver;
use Jurager\Filterable\Contracts\RelationResolver;
use Jurager\Filterable\Filterable;
use Jurager\Filterable\Tests\Fixtures\Category;
use Jurager\Filterable\Tests\Fixtures\Post;

class ResolversTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $electronics = Category::create(['name' => 'Electronics']);

        Post::create(['title' => 'Cheap', 'price' => 20.00]);
        Post::create(['title' => 'Mid', 'price' => 90.00]);
        Post::create(['title' => 'Expensive', 'price' => 200.00, 'category_id' => $electronics->id]);
    }

    public function test_field_resolver_handles_unknown_key(): void
    {
        $resolver = new class () implements FieldResolver {
            public function resolve(Builder $query, string $name, mixed $value, Model $model): bool
            {
                if ($name !== 'price_range') {
                    return false;
                }

                [$min, $max] = explode(',', (string) $value, 2);
                $query->whereBetween('price', [(float) $min, (float) $max]);

                return true;
            }
        };

        $filterable = new Filterable();
        $filterable->addFieldResolver($resolver);

        $query = Post::query();
        $filterable->apply($query, ['price_range' => '50,150']);

        $this->assertSame(['Mid'], $query->pluck('title')->all());
    }

    public function test_field_resolver_is_skipped_for_declared_fields(): void
    {
        $resolver = new class () implements FieldResolver {
            public bool $called = false;

            public function resolve(Builder $query, string $name, mixed $value, Model $model): bool
            {
                $this->called = true;

                return false;
            }
        };

        $filterable = new Filterable(['title' => ['eq']]);
        $filterable->addFieldResolver($resolver);

        $query = Post::query();
        $filterable->apply($query, ['title' => 'Mid']);

        $this->assertFalse($resolver->called);
        $this->assertSame(['Mid'], $query->pluck('title')->all());
    }

    public function test_relation_resolver_handles_unknown_dotted_key(): void
    {
        $resolver = new class () implements RelationResolver {
            public function resolveRelation(Builder $query, string $name, mixed $value, Model $model): bool
            {
                if ($name !== 'category.custom') {
                    return false;
                }

                $query->whereHas('category', fn (Builder $q) => $q->where('name', $value));

                return true;
            }
        };

        $filterable = new Filterable();
        $filterable->addRelationResolver($resolver);

        $query = Post::query();
        $filterable->apply($query, ['category.custom' => 'Electronics']);

        $this->assertSame(['Expensive'], $query->pluck('title')->all());
    }
}
