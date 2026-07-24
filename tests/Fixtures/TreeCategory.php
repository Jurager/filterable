<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Concerns\HasFilterable;

/** Category whose tree support lives on a custom query builder, not on the model. */
class TreeCategory extends Model
{
    use HasFilterable;

    public $timestamps = false;

    protected $table = 'tree_categories';

    protected $guarded = [];

    protected array $filterable = [
        'id' => ['in', 'tree'],
    ];

    public function newEloquentBuilder($query): TreeCategoryBuilder
    {
        return new TreeCategoryBuilder($query);
    }
}
