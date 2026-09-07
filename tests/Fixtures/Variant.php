<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Jurager\Filterable\Concerns\HasFilterable;

/**
 * A variant of another Variant, whose own categories may be empty — models a product-offer /
 * parent relationship, used to exercise a `tree` filter through a multi-hop relation path
 * (`parent.categories.id`).
 */
class Variant extends Model
{
    use HasFilterable;

    public $timestamps = false;

    protected $guarded = [];

    protected array $filterable = [
        'parent.categories.id' => ['tree'],
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(TreeCategory::class, 'variant_category', 'variant_id', 'category_id');
    }
}
