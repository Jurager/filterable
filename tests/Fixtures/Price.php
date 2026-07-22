<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jurager\Filterable\Concerns\HasFilterable;

class Price extends Model
{
    use HasFilterable;

    public $timestamps = true;

    protected $guarded = [];

    protected array $filterable = [
        'price_type_id' => ['eq', 'in'],
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
