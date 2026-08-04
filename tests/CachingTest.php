<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Illuminate\Support\Facades\DB;
use Jurager\Filterable\Tests\Fixtures\Article;
use Jurager\Filterable\Tests\Fixtures\Post;

class CachingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Post::create(['title' => 'Alpha', 'price' => 10, 'status' => 'active']);
        Post::create(['title' => 'Beta', 'price' => 20, 'status' => 'draft']);
    }

    public function test_cache_serves_repeated_query_without_hitting_the_database(): void
    {
        Post::query()->filter(['status' => 'active'])->cached()->get();

        DB::enableQueryLog();

        $cached = Post::query()->filter(['status' => 'active'])->cached()->get();

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame(['Alpha'], $cached->pluck('title')->all());
    }

    public function test_saving_a_model_invalidates_its_cache_tags(): void
    {
        Post::query()->filter(['status' => 'active'])->cached()->get();

        Post::where('title', 'Beta')->first()->update(['status' => 'active']);

        DB::enableQueryLog();

        $refreshed = Post::query()->filter(['status' => 'active'])->cached()->get();

        $this->assertNotCount(0, DB::getQueryLog());
        $this->assertSame(['Alpha', 'Beta'], $refreshed->pluck('title')->sort()->values()->all());
    }

    public function test_count_is_cached(): void
    {
        Post::query()->filter(['status' => 'active'])->cached()->count();

        DB::enableQueryLog();

        $count = Post::query()->filter(['status' => 'active'])->cached()->count();

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame(1, $count);
    }

    public function test_exists_and_doesnt_exist_are_cached(): void
    {
        Post::query()->filter(['status' => 'active'])->cached()->exists();
        Post::query()->filter(['status' => 'archived'])->cached()->doesntExist();

        DB::enableQueryLog();

        $exists      = Post::query()->filter(['status' => 'active'])->cached()->exists();
        $doesntExist = Post::query()->filter(['status' => 'archived'])->cached()->doesntExist();

        $this->assertCount(0, DB::getQueryLog());
        $this->assertTrue($exists);
        $this->assertTrue($doesntExist);
    }

    public function test_get_and_count_do_not_collide_on_the_same_cache_key(): void
    {
        // Same filter, same underlying SQL — get() and count() must not share a cache
        // entry, or one would deserialize the other's cached value as the wrong type.
        $count = Post::query()->filter(['status' => 'active'])->cached()->count();
        $rows  = Post::query()->filter(['status' => 'active'])->cached()->get();

        $this->assertSame(1, $count);
        $this->assertSame(['Alpha'], $rows->pluck('title')->all());
    }

    public function test_get_falls_back_to_uncached_when_the_driver_does_not_support_tags(): void
    {
        // FileStore implements Store directly (unlike e.g. array/redis, which extend
        // TaggableStore) — no tags() method at all, so Cache::tags() throws
        // BadMethodCallException. This must not bubble up; it must just skip caching
        // for that query, same as FilterableCacheObserver's invalidation does.
        config(['cache.default' => 'file']);
        config(['cache.stores.file.path' => sys_get_temp_dir() . '/filterable-test-cache']);

        $result = Post::query()->filter(['status' => 'active'])->cached()->get();

        $this->assertSame(['Alpha'], $result->pluck('title')->all());
    }

    public function test_invalidation_works_for_models_without_a_cache_property(): void
    {
        // Article declares no $cache property at all — tags fall back to the table name.
        // 'posts' is shared with the Post fixture (setUp already inserted rows there), so
        // use a status value unique to this test. Article uppercases 'status' filter input
        // via its sanitizer, so rows are stored pre-uppercased to match what filtering for
        // 'archived' will actually look up.
        Article::create(['title' => 'Gamma', 'price' => 5, 'status' => 'ARCHIVED']);
        Article::query()->filter(['status' => 'archived'])->cached()->get();

        Article::create(['title' => 'Delta', 'price' => 7, 'status' => 'ARCHIVED']);

        DB::enableQueryLog();

        $refreshed = Article::query()->filter(['status' => 'archived'])->cached()->get();

        $this->assertNotCount(0, DB::getQueryLog());
        $this->assertSame(['Gamma', 'Delta'], $refreshed->pluck('title')->all());
    }
}
