<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The frontend sends camelCase JSON/form bodies (matching the original
 * app's field names, e.g. startTime/isPrivate/paymentScreenshot) but
 * Eloquent models and migrations use snake_case columns. This converts
 * incoming request keys camelCase -> snake_case before validation runs,
 * so controllers can keep idiomatic Laravel snake_case validation rules
 * unchanged. Pairs with ConvertSnakeCaseResponseToCamelCase for the
 * outgoing direction.
 *
 * Body params, query params, and uploaded files are converted separately
 * (rather than via $request->all(), which merges files into the input
 * array) - replacing $request's file bag with input-bag data would break
 * $request->file()/hasFile() lookups on the next middleware/controller.
 */
class ConvertCamelCaseRequestToSnakeCase
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query->count() > 0) {
            $request->query->replace($this->convertKeys($request->query->all(), fn (string $key) => Str::snake($key)));
        }

        if ($request->request->count() > 0) {
            $request->request->replace($this->convertKeys($request->request->all(), fn (string $key) => Str::snake($key)));
        }

        if ($request->isJson() && $request->json()->count() > 0) {
            $request->json()->replace($this->convertKeys($request->json()->all(), fn (string $key) => Str::snake($key)));
        }

        if ($request->files->count() > 0) {
            $convertedFiles = [];
            foreach ($request->files->all() as $key => $file) {
                $convertedFiles[Str::snake((string) $key)] = $file;
            }
            $request->files->replace($convertedFiles);
        }

        return $next($request);
    }

    private function convertKeys(mixed $value, callable $convert): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $result = [];

        foreach ($value as $key => $item) {
            $newKey = $isList ? $key : $convert((string) $key);
            $result[$newKey] = $this->convertKeys($item, $convert);
        }

        return $result;
    }
}
