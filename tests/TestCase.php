<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jurager\Filterable\FilterableServiceProvider;
use Jurager\Filterable\Tests\Fixtures\Article;
use Jurager\Filterable\Tests\Fixtures\AutoCachedPost;
use Jurager\Filterable\Tests\Fixtures\FlatCategory;
use Jurager\Filterable\Tests\Fixtures\Post;
use Jurager\Filterable\Tests\Fixtures\Price;
use Jurager\Filterable\Tests\Fixtures\TreeCategory;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ReflectionClass;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FilterableServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach (['post_tag', 'prices', 'posts', 'tags', 'categories', 'tree_categories', 'flat_categories'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('tree_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('flat_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->decimal('price', 8, 2)->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table): void {
            $table->foreignId('post_id');
            $table->foreignId('tag_id');
            $table->unsignedInteger('weight')->default(0);
        });

        Schema::create('prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id');
            $table->unsignedInteger('price_type_id');
            $table->decimal('amount', 8, 2);
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->defineDatabaseMigrations();
        $this->resetFilterableObserverGuard();
    }

    /**
     * HasFilterable caches "observer already attached" per model class in a static array
     * that intentionally survives across requests under Octane. Testbench boots a fresh
     * Application (and event dispatcher) for every test, so without resetting this here,
     * only the first test to touch a given model gets the cache-invalidation observer
     * attached to its dispatcher.
     */
    private function resetFilterableObserverGuard(): void
    {
        foreach ([Post::class, Price::class, Article::class, AutoCachedPost::class, TreeCategory::class, FlatCategory::class] as $class) {
            $property = (new ReflectionClass($class))->getProperty('filterableObserved');
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
    }
}
