<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Concerns\HasFilterable;

/** Category with no nested-set support anywhere — the `tree` operator must be a no-op. */
class FlatCategory extends Model
{
    use HasFilterable;

    public $timestamps = false;

    protected $table = 'flat_categories';

    protected $guarded = [];

    protected array $filterable = [
        'id' => ['in', 'tree'],
    ];
}
