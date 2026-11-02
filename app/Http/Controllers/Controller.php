<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * A filter from the query string, or null when it is missing or not one of $allowed
     * (a hand-edited or stale link then shows everything instead of an empty list).
     *
     * @param  list<string>  $allowed
     */
    protected function filter(Request $request, string $key, array $allowed): ?string
    {
        $value = $request->query($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    protected function searchTerm(Request $request): ?string
    {
        $term = $request->query('q');

        return is_string($term) && trim($term) !== '' ? trim($term) : null;
    }
}
