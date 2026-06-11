<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\HttpStatusCodeEnum;
use App\Http\Controllers\Api\V1\Auth\AuthV1ApiController;
use App\Http\Requests\Api\V1\Auth\SignInAuthV1ApiRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class SignInAuthV1ApiController extends AuthV1ApiController
{
    private const int CODE_SUCCESS_OK = HttpStatusCodeEnum::SUCCESS_OK->value;
    private const int CODE_UNAUTHORIZED = HttpStatusCodeEnum::CLIENT_ERROR_UNAUTHORIZED->value;

    public function __invoke(SignInAuthV1ApiRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        $isUnauthorizedUser = self::isUnauthorizedUser($user, $request);
        if ($isUnauthorizedUser) {
            return response()->json(['message' => 'Unauthorized'], self::CODE_UNAUTHORIZED);
        }

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
        ];

        return response()->json($responseBody, self::CODE_SUCCESS_OK);
    }

    private function isUnauthorizedUser(User|null $user, SignInAuthV1ApiRequest $request): bool
    {
        $invalidUser = !$user;
        if ($invalidUser) return true;

        $isInvalidPassword = !Hash::check($request->password, $user->password);
        $isInactiveUser = !$user->is_active;

        $isUnauthorizedUser = $isInvalidPassword || $isInactiveUser;

        return $isUnauthorizedUser;
    }
}
