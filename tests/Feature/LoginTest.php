<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_signs_in_with_email_and_password_only(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'status' => 'active', 'must_change_password' => false, 'email' => 'jane@example.com', 'password' => Hash::make('Secret-pass-1')]);

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'Secret-pass-1'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_email_is_matched_in_any_case(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'status' => 'active', 'must_change_password' => false, 'email' => 'jane@example.com', 'password' => Hash::make('Secret-pass-1')]);

        $this->post('/login', ['email' => 'Jane@Example.COM', 'password' => 'Secret-pass-1']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        User::factory()->create(['status' => 'active', 'email' => 'jane@example.com', 'password' => Hash::make('Secret-pass-1')]);

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_verification_code_pages_no_longer_exist(): void
    {
        $this->get('/login/verify-otp')->assertNotFound();
        $this->post('/login/resend-otp')->assertNotFound();
    }
}
