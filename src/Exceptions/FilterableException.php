<?php

declare(strict_types=1);

namespace Jurager\Filterable\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Base exception for filter parsing and application errors. */
abstract class FilterableException extends RuntimeException implements ShouldntReport
{
    /** Render the exception into an HTTP 400 JSON response. */
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 400);
    }
}