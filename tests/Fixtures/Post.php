<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jurager\Filterable\Concerns\HasFilterable;
use Jurager\Filterable\Filterable;

class Post extends Model
{
    use HasFilterable;

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
    ];

    protected array $sanitizers = [
        'title' => 'trim',
    ];

    protected array $cache = [
        'tags' => ['posts_cache_test'],
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag')->withPivot('weight');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    protected function newFilterable(): Filterable
    {
        return new PostFilterable();
    }
}
