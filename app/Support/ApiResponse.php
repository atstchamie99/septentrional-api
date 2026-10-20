<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        $payload = ['message' => $message];

        if ($data instanceof JsonResource) {
            return $data->additional(['message' => $message])->response()->setStatusCode($status);
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    public static function created(mixed $data, string $message, string $resourceKey = 'data'): JsonResponse
    {
        if ($data instanceof JsonResource) {
            return $data->additional(['message' => $message])->response()->setStatusCode(201);
        }

        return response()->json([
            'message'     => $message,
            $resourceKey  => $data,
        ], 201);
    }
}
