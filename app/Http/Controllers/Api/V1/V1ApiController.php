<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class V1ApiController extends ApiController
{
   protected function apiResponse(int $status, string $message, array $body = []): JsonResponse
    {
        $response = [
            'status' => $status,
            'message' => $message,
            'body' => $body
        ];

        return response()->json($response, $response['status']);
    }
}
