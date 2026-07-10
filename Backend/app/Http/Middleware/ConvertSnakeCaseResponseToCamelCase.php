<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirror of ConvertCamelCaseRequestToSnakeCase: converts outgoing JSON
 * response keys snake_case -> camelCase (Eloquent's default), so the
 * frontend can keep using the original app's camelCase field names
 * (startTime, isPrivate, qrCode, checkInTime, ...) without every
 * controller or model needing an explicit API Resource transform.
 */
class ConvertSnakeCaseResponseToCamelCase
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $response->setData($this->convertKeys($data, fn (string $key) => Str::camel($key)));
        }

        return $response;
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
