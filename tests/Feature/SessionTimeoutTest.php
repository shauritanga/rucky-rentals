<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionTimeout;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_authenticated_request_refreshes_session_activity(): void
    {
        SystemSetting::set('session_timeout', '15');

        $user = User::factory()->create([
            'role' => 'viewer',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $oldTimestamp = now()->subMinutes(10)->timestamp;

        $this->actingAs($user)
            ->withSession([EnforceSessionTimeout::lastActivitySessionKey() => $oldTimestamp])
            ->get('/notifications')
            ->assertOk();

        $this->assertGreaterThan(
            $oldTimestamp,
            (int) session(EnforceSessionTimeout::lastActivitySessionKey())
        );
    }

    public function test_inactive_session_is_logged_out_on_next_authenticated_request(): void
    {
        SystemSetting::set('session_timeout', '15');

        $user = User::factory()->create([
            'role' => 'viewer',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->withSession([EnforceSessionTimeout::lastActivitySessionKey() => now()->subMinutes(16)->timestamp])
            ->get('/')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_passive_notification_polling_does_not_extend_session_activity(): void
    {
        SystemSetting::set('session_timeout', '15');

        $user = User::factory()->create([
            'role' => 'viewer',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $oldTimestamp = now()->subMinutes(10)->timestamp;

        $this->actingAs($user)
            ->withSession([EnforceSessionTimeout::lastActivitySessionKey() => $oldTimestamp])
            ->get('/notifications', ['X-Session-Activity' => 'passive'])
            ->assertOk();

        $this->assertSame(
            $oldTimestamp,
            (int) session(EnforceSessionTimeout::lastActivitySessionKey())
        );
    }

    public function test_timed_out_json_request_returns_unauthorized_message(): void
    {
        SystemSetting::set('session_timeout', '15');

        $user = User::factory()->create([
            'role' => 'viewer',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->withSession([EnforceSessionTimeout::lastActivitySessionKey() => now()->subMinutes(16)->timestamp])
            ->getJson('/notifications')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Your session expired after inactivity. Please sign in again.',
            ]);

        $this->assertGuest();
    }
}
