<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionTimeout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionKeepAliveTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
    }

    public function test_keep_alive_restarts_the_idle_clock(): void
    {
        $key = EnforceSessionTimeout::lastActivitySessionKey();
        $tenMinutesAgo = now()->subMinutes(10)->timestamp;

        $this->actingAs($this->user())->withSession([$key => $tenMinutesAgo])
            ->postJson('/session/keep-alive')->assertNoContent();

        $this->assertGreaterThan($tenMinutesAgo + 500, (int) session($key));
    }

    public function test_the_passive_notification_check_does_not_restart_the_clock(): void
    {
        $key = EnforceSessionTimeout::lastActivitySessionKey();
        $tenMinutesAgo = now()->subMinutes(10)->timestamp;

        $this->actingAs($this->user())->withSession([$key => $tenMinutesAgo])
            ->getJson('/notifications', ['X-Session-Activity' => 'passive'])->assertOk();

        $this->assertSame($tenMinutesAgo, (int) session($key));
    }

    public function test_a_session_idle_for_longer_than_the_limit_is_still_ended(): void
    {
        $key = EnforceSessionTimeout::lastActivitySessionKey();

        $this->actingAs($this->user())->withSession([$key => now()->subMinutes(30)->timestamp])
            ->postJson('/session/keep-alive')->assertStatus(401);
    }
}
