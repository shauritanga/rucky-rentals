<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QueuedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lease_submission_queues_the_emails_and_the_worker_delivers_them(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);

        $property = Property::create(['name' => 'Queue Tower', 'code' => 'QUE', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_number' => 'F1.01', 'floor' => '1', 'type' => 'Office Suite', 'size_sqft' => 100, 'size_sqm' => 9.29,
            'rate_per_sqm' => 107.64, 'service_charge_per_sqm' => 0, 'currency' => 'TZS', 'rent' => 1000, 'status' => 'vacant', 'deposit' => 0,
            'service_charge' => 0, 'electricity_type' => 'direct', 'approval_status' => 'approved',
        ]);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Queue Tenant', 'email' => 'q@example.com', 'phone' => '+255700000000', 'initials' => 'QT', 'tenant_type' => 'individual']);

        $this->actingAs($manager)->post('/leases', [
            'tenant_mode' => 'existing', 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'start_date' => '2026-04-01', 'end_date' => '2027-04-01',
            'duration_months' => 12, 'payment_cycle' => 3, 'monthly_rent' => 1000, 'deposit' => 0,
        ])->assertSessionHasNoErrors();

        // The request finished with the work only queued: nothing has been sent or stored yet.
        $this->assertSame('pending_accountant', Lease::firstOrFail()->status);
        $this->assertGreaterThan(0, DB::table('jobs')->count()); // one job per channel (email, in-app, push)
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());

        // The worker (the scheduler runs it every minute) delivers it.
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $superuser->id)->count());
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertNotNull(Cache::get('queue.last_processed_at'));
    }

    public function test_the_health_command_reports_a_stalled_queue(): void
    {
        config(['queue.default' => 'database']);
        Cache::put('queue.last_processed_at', now()->subMinutes(30)->toIso8601String());
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time() - 1200]);

        $this->artisan('queue:health')->expectsOutputToContain('Jobs are waiting but nothing was processed')->assertExitCode(1);
    }
}
