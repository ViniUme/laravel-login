<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\HttpStatusCodeEnum;
use App\Events\PasswordUpdated;
use App\Http\Requests\Api\V1\Auth\ResetPasswordAuthV1ApiRequest;
use App\Models\User;
use App\Services\Auth\SecureAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetPasswordAuthV1ApiController extends AuthV1ApiController
{
    private const int CODE_SUCCESS_OK = HttpStatusCodeEnum::SUCCESS_OK->value;

    private const int CODE_BAD_REQUEST = HttpStatusCodeEnum::CLIENT_ERROR_BAD_REQUEST->value;

    private const int TOKEN_EXPIRATION_MINUTES = 15;

    public function __invoke(ResetPasswordAuthV1ApiRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();
        $resetRecord = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        // Mitigation against user enumeration and timing attacks (LGPD & Security)
        if (! $user || ! $resetRecord) {
            Hash::check($request->token, SecureAuthService::getDummyHash());

            return response()->json([
                'message' => 'Invalid or expired password reset token.',
            ], self::CODE_BAD_REQUEST);
        }

        // Validate token expiration window
        $createdAt = Carbon::parse($resetRecord->created_at);
        if ($createdAt->addMinutes(self::TOKEN_EXPIRATION_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'message' => 'Invalid or expired password reset token.',
            ], self::CODE_BAD_REQUEST);
        }

        // Validate cryptographic hash comparison
        if (! Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'message' => 'Invalid or expired password reset token.',
            ], self::CODE_BAD_REQUEST);
        }

        // Distributed concurrency safety: atomic token consumption and session revocation
        $hasReset = DB::transaction(function () use ($user, $request): bool {
            $deleted = DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            if ($deleted === 0) {
                return false;
            }

            $user->password = Hash::make($request->password);
            $user->save();

            // Revoke all personal access tokens across all devices
            $user->tokens()->delete();

            // Purge all active web/mobile sessions in distributed storage
            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Dispatch domain event for downstream notification and audit log services
            event(new PasswordUpdated($user));

            return true;
        });

        if (! $hasReset) {
            return response()->json([
                'message' => 'Invalid or expired password reset token.',
            ], self::CODE_BAD_REQUEST);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ], self::CODE_SUCCESS_OK);
    }
}
