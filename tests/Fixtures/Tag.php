<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = true;

    protected $guarded = [];
}
