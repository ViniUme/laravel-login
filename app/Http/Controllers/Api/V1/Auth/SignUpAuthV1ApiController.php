<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\HttpStatusCodeEnum;
use App\Http\Requests\Api\V1\Auth\SignUpAuthV1ApiRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class SignUpAuthV1ApiController extends AuthV1ApiController
{
    private const int CODE_SUCCESS_CREATED = HttpStatusCodeEnum::SUCCESS_CREATED->value;

    public function __invoke(SignUpAuthV1ApiRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $responseBody = [
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];

        return self::apiResponse(self::CODE_SUCCESS_CREATED, 'Created', $responseBody);
    }
}
