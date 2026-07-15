<?php

namespace Jurager\Filterable\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Base class for exceptions raised while parsing or applying filters.
 * Rendered as an HTTP 400 response, matching the previous abort_if()-based behavior.
 */
abstract class FilterableException extends RuntimeException implements ShouldntReport
{
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 400);
    }
}
