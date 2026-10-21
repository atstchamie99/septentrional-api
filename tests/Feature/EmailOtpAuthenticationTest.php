<?php

namespace Tests\Feature;

use App\Mail\EmailOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailOtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_an_alternative_and_does_not_modify_authenticator_setup(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'google2fa_enabled' => false,
        ]);
        $login = $this->postJson('/api/v2/login', [
            'email' => $user->email, 'password' => 'password',
        ])->assertOk()->assertJson(['otp_setup_required' => true])->json();

        $email = $this->postJson('/api/v2/login/send-email-otp', [
            'challenge_token' => $login['challenge_token'],
        ])->assertOk()->assertJson(['otp_method' => 'email', 'expires_in' => 600])->json();
        $this->assertNotNull(Cache::get("2fa:setup:{$login['challenge_token']}"));
        $this->assertSame(0, $user->tokens()->count());

        $code = Mail::sent(EmailOtpMail::class)->sole()->code;
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson('/api/v2/login/verify-otp', [
                'challenge_token' => $email['challenge_token'], 'code' => $wrong,
            ])->assertUnprocessable()->assertJson(['message' => 'Code invalide.']);
        }
        $this->postJson('/api/v2/login/verify-otp', [
            'challenge_token' => $email['challenge_token'], 'code' => $code,
        ])->assertOk()->assertCookie('auth_token')->assertCookie('otp_reauth');
        $this->assertFalse($user->fresh()->google2fa_enabled);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_a_login_challenge_is_required_to_send_email(): void
    {
        Mail::fake();
        $this->postJson('/api/v2/login/send-email-otp', [
            'challenge_token' => str_repeat('x', 40),
        ])->assertUnprocessable()->assertJson(['code' => 'OTP_CHALLENGE_EXPIRED']);
        Mail::assertNothingSent();
    }

    public function test_email_cannot_be_requested_for_an_invalid_reauthentication_ticket(): void
    {
        Mail::fake();
        $this->postJson('/api/v2/reauth/send-email-otp')->assertUnauthorized();
        Mail::assertNothingSent();
    }

    public function test_email_can_reactivate_a_session_with_its_browser_ticket(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'google2fa_enabled' => false,
        ]);
        $ticket = $user->id.'.'.str_repeat('a', 64);
        Cache::put('2fa:reauth:user:'.$user->id, hash('sha256', $ticket), 600);
        $this->withCredentials()->withUnencryptedCookie('otp_reauth', $ticket);
        $challenge = $this->postJson('/api/v2/reauth/send-email-otp')->assertOk()->json();
        $code = Mail::sent(EmailOtpMail::class)->sole()->code;

        $this->postJson('/api/v2/reauth/verify-otp', [
            'challenge_token' => $challenge['challenge_token'], 'code' => $code,
        ])->assertOk()->assertCookie('auth_token')->assertCookie('otp_reauth');
        $this->assertNotSame(hash('sha256', $ticket), Cache::get('2fa:reauth:user:'.$user->id));
    }
}
