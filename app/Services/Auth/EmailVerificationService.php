<?php

namespace App\Services\Auth;

use App\Models\EmailVerification;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

class EmailVerificationService
{
    public const int TOKEN_EXPIRATION_MINUTES = 30;

    /**
     * Generate a cryptographically secure token, store its SHA-256 hash, and notify the user.
     */
    public function generateAndSend(User $user): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        DB::transaction(function () use ($user, $tokenHash): void {
            // Invalidate/soft-delete previous active tokens for this user
            EmailVerification::where('user_id', $user->id)->delete();

            EmailVerification::create([
                'user_id' => $user->id,
                'token_hash' => $tokenHash,
                'expires_at' => now()->addMinutes(self::TOKEN_EXPIRATION_MINUTES),
            ]);
        });

        $user->notify(new VerifyEmailNotification($plainToken));

        return $plainToken;
    }

    /**
     * Verify the token, consume it (single-use), mark the email verified, and fire the Verified event.
     */
    public function verify(string $plainToken): ?User
    {
        $tokenHash = hash('sha256', $plainToken);

        $verification = EmailVerification::with('user')
            ->where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if (! $verification || ! $verification->user) {
            return null;
        }

        $user = $verification->user;

        if ($user->hasVerifiedEmail()) {
            $verification->delete();

            return null;
        }

        return DB::transaction(function () use ($user): User {
            // Delete all pending verification tokens for this user (Single-Use Token guarantee)
            EmailVerification::where('user_id', $user->id)->delete();

            $user->markEmailAsVerified();

            event(new Verified($user));

            return $user;
        });
    }
}
