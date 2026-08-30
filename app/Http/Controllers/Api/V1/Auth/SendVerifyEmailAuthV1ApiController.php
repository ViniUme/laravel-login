<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\HttpStatusCodeEnum;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SendVerifyEmailAuthV1ApiController extends AuthV1ApiController
{
    private const int CODE_SUCCESS_OK = HttpStatusCodeEnum::SUCCESS_OK->value;

    private const int CODE_BAD_REQUEST = HttpStatusCodeEnum::CLIENT_ERROR_BAD_REQUEST->value;

    public function __construct(
        private readonly EmailVerificationService $verificationService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user && $request->filled('email')) {
            $user = User::where('email', $request->input('email'))->first();
        }

        if (! $user) {
            // Mitigate user enumeration attacks
            return response()->json([
                'message' => 'If your email is registered and unverified, a verification link has been sent.',
            ], self::CODE_SUCCESS_OK);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'This e-mail is already verified.',
            ], self::CODE_BAD_REQUEST);
        }

        $this->verificationService->generateAndSend($user);

        return response()->json([
            'message' => 'Verification link sent successfully.',
        ], self::CODE_SUCCESS_OK);
    }
}
