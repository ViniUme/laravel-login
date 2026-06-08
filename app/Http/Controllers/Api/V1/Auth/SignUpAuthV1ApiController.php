<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\HttpStatusCodeEnum;
use App\Http\Controllers\Api\V1\Auth\AuthV1ApiController;
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];

        return response()->json($responseBody, self::CODE_SUCCESS_CREATED);
    }
}
