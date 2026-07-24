<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Jurager\Filterable\Exceptions\InvalidBetweenOperandException;
use Jurager\Filterable\Exceptions\OperatorNotAllowedException;
use Jurager\Filterable\Exceptions\TooManyFiltersException;
use Jurager\Filterable\Exceptions\TooManyValuesException;
use Jurager\Filterable\Tests\Fixtures\Category;
use Jurager\Filterable\Tests\Fixtures\Post;
use Jurager\Filterable\Tests\Fixtures\Tag;

class FilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $electronics = Category::create(['name' => 'Electronics']);
        $books       = Category::create(['name' => 'Books']);

        $sale = Tag::create(['name' => 'sale']);
        $new  = Tag::create(['name' => 'new']);

        $alpha = Post::create([
            'title'         => 'Alpha Phone',
            'status'        => 'active',
            'price'         => 150.00,
            'is_active'     => true,
            'category_id'   => $electronics->id,
            'published_at'  => now(),
        ]);

        $beta = Post::create([
            'title'         => 'Beta Book',
            'status'        => 'draft',
            'price'         => 20.00,
            'is_active'     => false,
            'category_id'   => $books->id,
            'published_at'  => null,
        ]);

        $gamma = Post::create([
            'title'         => 'Gamma Gadget',
            'status'        => 'active',
            'price'         => 99.99,
            'is_active'     => true,
            'category_id'   => $electronics->id,
            'published_at'  => now(),
        ]);

        $alpha->tags()->attach($sale->id, ['weight' => 10]);
        $beta->tags()->attach($new->id, ['weight' => 3]);
        $gamma->tags()->attach([$sale->id => ['weight' => 1], $new->id => ['weight' => 8]]);
    }

    public function test_eq_shorthand(): void
    {
        $titles = Post::query()->filter(['status' => 'active'])->pluck('title')->sort()->values();

        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $titles->all());
    }

    public function test_like_operator(): void
    {
        $titles = Post::query()->filter(['title' => ['like' => 'Phone']])->pluck('title');

        $this->assertSame(['Alpha Phone'], $titles->all());
    }

    public function test_ne_operator(): void
    {
        $titles = Post::query()->filter(['title' => ['ne' => 'Alpha Phone']])->pluck('title')->sort()->values();

        $this->assertSame(['Beta Book', 'Gamma Gadget'], $titles->all());
    }

    public function test_in_and_nin_operators(): void
    {
        $in  = Post::query()->filter(['status' => ['in' => ['active']]])->pluck('title')->sort()->values();
        $nin = Post::query()->filter(['status' => ['nin' => ['draft']]])->pluck('title')->sort()->values();

        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $in->all());
        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $nin->all());
    }

    public function test_comparison_operators(): void
    {
        $gte = Post::query()->filter(['price' => ['gte' => 100]])->pluck('title');
        $gt  = Post::query()->filter(['price' => ['gt' => 50]])->pluck('title')->sort()->values();
        $lt  = Post::query()->filter(['price' => ['lt' => 50]])->pluck('title');
        $lte = Post::query()->filter(['price' => ['lte' => 99.99]])->pluck('title')->sort()->values();

        $this->assertSame(['Alpha Phone'], $gte->all());
        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $gt->all());
        $this->assertSame(['Beta Book'], $lt->all());
        $this->assertSame(['Beta Book', 'Gamma Gadget'], $lte->all());
    }

    public function test_between_and_not_between(): void
    {
        $between    = Post::query()->filter(['price' => ['between' => [90, 160]]])->pluck('title')->sort()->values();
        $notBetween = Post::query()->filter(['price' => ['not_between' => [90, 160]]])->pluck('title');

        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $between->all());
        $this->assertSame(['Beta Book'], $notBetween->all());
    }

    public function test_boolean_shorthand_field(): void
    {
        $titles = Post::query()->filter(['is_active' => '1'])->pluck('title')->sort()->values();

        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $titles->all());
    }

    public function test_null_and_not_null_operators(): void
    {
        $null    = Post::query()->filter(['published_at' => ['null' => 1]])->pluck('title');
        $notNull = Post::query()->filter(['published_at' => ['not_null' => 1]])->pluck('title')->sort()->values();

        $this->assertSame(['Beta Book'], $null->all());
        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $notNull->all());
    }

    public function test_relation_dot_filter(): void
    {
        $titles = Post::query()->filter(['category.name' => ['like' => 'Elect']])->pluck('title')->sort()->values();

        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $titles->all());
    }

    public function test_relation_in_filter(): void
    {
        $titles = Post::query()->filter(['tags.name' => ['in' => ['sale']]])->pluck('title')->sort()->values();

        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $titles->all());
    }

    public function test_pivot_column_filter(): void
    {
        $titles = Post::query()->filter(['tags.pivot.weight' => ['gte' => 5]])->pluck('title')->sort()->values();

        $this->assertSame(['Alpha Phone', 'Gamma Gadget'], $titles->all());
    }

    public function test_or_group(): void
    {
        $titles = Post::query()
            ->filter(['or' => ['status' => 'draft', 'price' => ['gte' => 100]]])
            ->pluck('title')->sort()->values();

        $this->assertSame(['Alpha Phone', 'Beta Book'], $titles->all());
    }

    public function test_and_group(): void
    {
        $titles = Post::query()
            ->filter(['and' => [['status' => 'active'], ['price' => ['gte' => 100]]]])
            ->pluck('title');

        $this->assertSame(['Alpha Phone'], $titles->all());
    }

    public function test_custom_filter_method_dispatch(): void
    {
        $titles = Post::query()->filter(['featured' => 1])->pluck('title');

        $this->assertSame(['Alpha Phone'], $titles->all());
    }

    public function test_empty_filter_is_a_no_op(): void
    {
        $count = Post::query()->filter([])->count();

        $this->assertSame(3, $count);
    }

    public function test_matches_filter_checks_an_already_resolved_model(): void
    {
        $active = Post::where('title', 'Alpha Phone')->first();
        $draft  = Post::where('title', 'Beta Book')->first();

        $this->assertTrue($active->matchesFilter(['status' => ['eq' => 'active']]));
        $this->assertFalse($draft->matchesFilter(['status' => ['eq' => 'active']]));
    }

    public function test_too_many_filters_throws(): void
    {
        $this->expectException(TooManyFiltersException::class);

        $filters = [];

        for ($i = 0; $i < 51; $i++) {
            $filters["field{$i}"] = 1;
        }

        Post::query()->filter($filters)->get();
    }

    public function test_too_many_in_values_throws(): void
    {
        $this->expectException(TooManyValuesException::class);

        Post::query()->filter(['status' => ['in' => range(1, 501)]])->get();
    }

    public function test_disallowed_operator_throws(): void
    {
        $this->expectException(OperatorNotAllowedException::class);

        Post::query()->filter(['title' => ['gt' => 'x']])->get();
    }

    public function test_invalid_between_operand_throws(): void
    {
        $this->expectException(InvalidBetweenOperandException::class);

        Post::query()->filter(['price' => ['between' => [10]]])->get();
    }
}
