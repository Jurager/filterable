<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Concerns\HasFilterable;

/**
 * Maps to the same table as Post. Declares $cache['enabled'] = true, which per
 * docs/caching.md must enable caching for every filtered query regardless of
 * the global `filterable.cache.enabled` config value.
 */
class AutoCachedPost extends Model
{
    use HasFilterable;

    protected $table = 'posts';

    public $timestamps = true;

    protected $guarded = [];

    protected array $filterable = [
        'status' => ['eq'],
    ];

    protected array $cache = [
        'enabled' => true,
        'tags'    => ['auto_cached_posts_test'],
    ];
}
