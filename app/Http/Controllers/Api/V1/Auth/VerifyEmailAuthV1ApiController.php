<?php

namespace App\Http\Controllers\Api\V1\Auth;

use Illuminate\Http\Request;
use App\Enums\HttpStatusCodeEnum as Status;
use Illuminate\Auth\Events\Verified;
use App\Models\User;

class VerifyEmailAuthV1ApiController extends AuthV1ApiController
{
    public function __invoke(Request $request, string $id, string $hash)
    {
        if (! $request->hasValidSignature()) {
            return response()->json([
                'message' => 'Invalid or expired verification link.'
            ], Status::CLIENT_ERROR_FORBIDDEN->value);
        }

        if ($request->user() && $request->user()->id !== $id) {
            return response()->json([
                'message' => 'This verification link does not belong to you.'
            ], Status::CLIENT_ERROR_FORBIDDEN->value);
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], Status::CLIENT_ERROR_UNAUTHORIZED->value);
        }

        // Garante que o hash da URL corresponde ao e-mail do usuário
        if (! hash_equals($hash, sha1($user->email))) {
            return response()->json([
                'message' => 'Invalid verification link.'
            ], Status::CLIENT_ERROR_FORBIDDEN->value);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'This e-mail is already verified.'
            ], Status::CLIENT_ERROR_BAD_REQUEST->value);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json([
            'message' => 'Verified e-mail with success.'
        ], Status::SUCCESS_OK->value);
    }
}
