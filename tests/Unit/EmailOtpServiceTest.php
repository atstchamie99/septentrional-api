<?php

namespace Tests\Unit;

use App\Mail\EmailOtpMail;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class EmailOtpServiceTest extends TestCase
{
    private EmailOtpService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'app.key' => str_repeat('x', 32)]);
        Mail::fake();
        $this->service = new EmailOtpService;
        $this->user = new User(['email' => 'staff@example.com', 'role' => 'admin']);
        $this->user->id = 18;
    }

    private function code(): string
    {
        return Mail::sent(EmailOtpMail::class)->last()->code;
    }

    private function reject(callable $callback, int $status, ?string $code = null): void
    {
        try {
            $callback();
            $this->fail('La validation aurait dû échouer.');
        } catch (HttpResponseException $e) {
            $this->assertSame($status, $e->getResponse()->getStatusCode());
            if ($code) {
                $this->assertSame($code, $e->getResponse()->getData(true)['code']);
            }
        }
    }

    public function test_code_is_sent_from_requested_address_and_stored_as_a_digest(): void
    {
        $challenge = $this->service->issue($this->user);
        Mail::assertSent(EmailOtpMail::class, function ($mail) {
            return $mail->hasTo($this->user->email)
                && $mail->envelope()->from->address === 'no-reply@septentrional.tg'
                && preg_match('/\A[0-9]{6}\z/', $mail->code);
        });
        $this->assertSame(600, $challenge['expires_in']);
        $record = $this->service->find($challenge['challenge_token']);
        $this->assertArrayNotHasKey('code', $record);
        $this->assertNotSame($this->code(), $record['code_hash']);
    }

    public function test_two_wrong_codes_do_not_prevent_success_and_success_cannot_be_replayed(): void
    {
        $token = $this->service->issue($this->user)['challenge_token'];
        $code = $this->code();
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->reject(fn () => $this->service->verify($token, $wrong, 'login'), 422);
        }
        $this->assertSame(18, $this->service->verify($token, $code, 'login')['user_id']);
        $this->reject(fn () => $this->service->verify($token, $code, 'login'), 422, 'OTP_CHALLENGE_EXPIRED');
    }

    public function test_wrong_attempts_do_not_extend_the_ten_minute_expiration(): void
    {
        $token = $this->service->issue($this->user)['challenge_token'];
        $code = $this->code();
        $this->travel(9)->minutes();
        $this->reject(fn () => $this->service->verify($token, 'invalid', 'login'), 422);
        $this->travel(1)->minutes();
        $this->reject(fn () => $this->service->verify($token, $code, 'login'), 422, 'OTP_CHALLENGE_EXPIRED');
    }

    public function test_code_works_just_before_expiration(): void
    {
        $token = $this->service->issue($this->user)['challenge_token'];
        $code = $this->code();
        $this->travel(599)->seconds();
        $this->assertSame(18, $this->service->verify($token, $code, 'login')['user_id']);
    }

    public function test_fifth_failure_invalidates_the_code(): void
    {
        $token = $this->service->issue($this->user)['challenge_token'];
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->reject(fn () => $this->service->verify($token, 'invalid', 'login'), 422);
        }
        $this->reject(fn () => $this->service->verify($token, 'invalid', 'login'), 422, 'OTP_ATTEMPTS_EXHAUSTED');
        $this->assertNull($this->service->find($token));
    }

    public function test_sending_is_limited_per_account(): void
    {
        $this->service->issue($this->user);
        $this->reject(fn () => $this->service->issue($this->user), 429);
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->service->issue($this->user);
        Mail::assertSentCount(2);
    }

    public function test_reauthentication_code_is_bound_to_its_browser_ticket_and_purpose(): void
    {
        $token = $this->service->issue($this->user, 'reauth', 'ticket-hash')['challenge_token'];
        $code = $this->code();
        $this->reject(fn () => $this->service->verify($token, $code, 'login'), 422);
        $this->reject(fn () => $this->service->verify($token, $code, 'reauth', 'other-ticket'), 422);
        $this->assertSame(18, $this->service->verify($token, $code, 'reauth', 'ticket-hash')['user_id']);
    }

    public function test_mail_failure_removes_the_challenge_and_allows_a_retry(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->reject(fn () => $this->service->issue($this->user), 503);
        $this->assertFalse(Cache::has('2fa:email:cooldown:18'));
    }
}
