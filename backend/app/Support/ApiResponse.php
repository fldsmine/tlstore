<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * The single JSON envelope every API response uses.
 *
 * Success: {"success": true, "message": "...", "data": ...}
 * Failure: {"success": false, "message": "...", "errors": {...}?}
 */
class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    public static function created(string $message, mixed $data = null): JsonResponse
    {
        return static::success($message, $data, 201);
    }

    public static function error(string $message, int $status, ?array $errors = null, mixed $data = null): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = (object) $errors;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Paginated collection envelope: items + mobile-friendly pagination metadata.
     *
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>  $resource
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resource, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'items' => $resource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ]);
    }
}
