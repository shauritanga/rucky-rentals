<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginOtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_email_otp_when_global_setting_is_enabled(): void
    {
        Mail::fake();

        SystemSetting::set('require_2fa', '1');

        $user = User::factory()->create([
            'role' => 'viewer',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
        $this->assertDatabaseCount('login_otps', 1);
        $this->assertSame($user->id, session('auth.pending_login.user_id'));

        Mail::assertSent(LoginOtpMail::class);
    }

    public function test_correct_otp_completes_login_after_password_step(): void
    {
        Mail::fake();

        SystemSetting::set('require_2fa', '1');

        $user = User::factory()->create([
            'role' => 'viewer',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login.otp.show'));

        $otpCode = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$otpCode) {
            $otpCode = $mail->otpCode;
            return true;
        });

        $challenge = LoginOtp::query()->first();
        $this->assertNotNull($challenge);
        $this->assertNotNull($otpCode);

        $response = $this->post(route('login.otp.verify'), [
            'otp' => $otpCode,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_otps', [
            'id' => $challenge->id,
        ]);
        $this->assertNotNull(LoginOtp::query()->find($challenge->id)?->consumed_at);
    }
}
