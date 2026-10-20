<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OtpRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_a_valid_code_after_two_invalid_codes(): void
    {
        $this->assertOtpRetries(true);
    }

    public function test_setup_accepts_a_valid_code_after_two_invalid_codes(): void
    {
        $this->assertOtpRetries(false);
    }

    private function assertOtpRetries(bool $enabled): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'google2fa_enabled' => $enabled,
            'google2fa_secret' => $enabled ? $secret : null,
        ]);

        $challenge = $this->postJson('/api/v2/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json();

        $secret = $challenge['otp_secret'] ?? $secret;
        $validCode = $google2fa->getCurrentOtp($secret);
        // Une valeur hors format OTP garantit un refus sans collision TOTP.
        foreach (['invalid-one', 'invalid-two'] as $code) {
            $this->postJson('/api/v2/login/verify-otp', [
                'challenge_token' => $challenge['challenge_token'],
                'code' => $code,
            ])->assertUnprocessable()->assertJson(['message' => 'Code invalide.']);
            $prefix = $enabled ? 'login' : 'setup';
            $this->assertNotNull(Cache::get("2fa:{$prefix}:{$challenge['challenge_token']}"));
            $this->assertSame(0, $user->tokens()->count());
        }

        $this->postJson('/api/v2/login/verify-otp', [
            'challenge_token' => $challenge['challenge_token'],
            'code' => $validCode,
        ])->assertOk()->assertCookie('auth_token');

        $this->assertTrue($user->fresh()->google2fa_enabled);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertNull(Cache::get("2fa:{$prefix}:{$challenge['challenge_token']}"));

        $this->postJson('/api/v2/login/verify-otp', [
            'challenge_token' => $challenge['challenge_token'],
            'code' => $validCode,
        ])->assertUnprocessable()->assertJson(['code' => 'OTP_CHALLENGE_EXPIRED']);
    }
}
