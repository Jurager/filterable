<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Concerns\HasFilterable;

/**
 * Maps to the same table as Post, but — unlike Post — does not override
 * newFilterable(). Exercises HasFilterable's default FilterableFactory-based
 * wiring of $filterable/$sortable/$cache/$sanitizers straight off the model,
 * exactly as documented in docs/sanitization.md.
 */
class Article extends Model
{
    use HasFilterable;

    protected $table = 'posts';

    public $timestamps = true;

    protected $guarded = [];

    protected array $filterable = [
        'title'  => ['eq', 'like'],
        'status' => ['eq'],
    ];

    protected array $sanitizers = [
        'title'  => 'trim',
        'status' => UppercaseSanitizer::class,
    ];
}
