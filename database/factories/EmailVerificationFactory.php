<?php

namespace Database\Factories;

use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailVerification>
 */
class EmailVerificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainToken = bin2hex(random_bytes(32));

        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(30),
        ];
    }

    /**
     * Indicate that the verification token is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
