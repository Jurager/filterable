<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public $timestamps = true;

    protected $guarded = [];
}
