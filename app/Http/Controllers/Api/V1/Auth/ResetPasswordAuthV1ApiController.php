<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\AuthV1ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResetPasswordAuthV1ApiController extends AuthV1ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        //
    }
}
