<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\HttpStatusCodeEnum;
use App\Models\EmailVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifyEmailAuthV1ApiController extends AuthV1ApiController
{
    private const int CODE_SUCCESS_OK = HttpStatusCodeEnum::SUCCESS_OK->value;

    private const int CODE_BAD_REQUEST = HttpStatusCodeEnum::CLIENT_ERROR_BAD_REQUEST->value;

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || trim($token) === '') {
            return response()->json([
                'message' => 'Token is required.',
            ], self::CODE_BAD_REQUEST);
        }

        $tokenHash = hash('sha256', $token);

        $verification = EmailVerification::with('user')
            ->where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if (! $verification || ! $verification->user) {
            return response()->json([
                'message' => 'Invalid or expired verification token.',
            ], self::CODE_BAD_REQUEST);
        }

        $user = $verification->user;

        if ($user->hasVerifiedEmail()) {
            $verification->delete();

            return response()->json([
                'message' => 'This e-mail is already verified.',
            ], self::CODE_BAD_REQUEST);
        }

        DB::transaction(function () use ($user): void {
            // Delete all pending verification tokens for this user (single-use guarantee)
            EmailVerification::where('user_id', $user->id)->delete();

            $user->markEmailAsVerified();

            event(new Verified($user));
        });

        return response()->json([
            'message' => 'Email verified successfully.',
        ], self::CODE_SUCCESS_OK);
    }
}
