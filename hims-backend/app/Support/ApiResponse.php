<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>|null  $errors
     */
    public static function success(
        Request $request,
        mixed $data = null,
        string $message = 'Operation completed successfully',
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
            'errors' => null,
            'request_id' => $request->attributes->get('request_id'),
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function error(
        Request $request,
        string $message,
        array $errors = [],
        int $status = 400,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => (object) $meta,
            'errors' => (object) $errors,
            'request_id' => $request->attributes->get('request_id'),
        ], $status);
    }
}
