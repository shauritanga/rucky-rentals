<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\LeaseChangeRequest;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeaseChangeApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function world(float $deposit = 0, string $status = 'active'): array
    {
        $property = Property::create(['name' => 'Chg Tower', 'code' => 'CHG', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_number' => 'C1', 'floor' => '1', 'type' => 'Office', 'size_sqft' => 100, 'size_sqm' => 9.29,
            'rate_per_sqm' => 100, 'service_charge_per_sqm' => 0, 'currency' => 'TZS', 'rent' => 1000, 'status' => 'occupied', 'deposit' => 0,
            'service_charge' => 0, 'electricity_type' => 'direct', 'approval_status' => 'approved',
        ]);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Chg Tenant', 'email' => 'c@example.com', 'phone' => '+255700000000', 'initials' => 'CT', 'tenant_type' => 'individual']);
        $lease = Lease::create([
            'property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'duration_months' => 12, 'payment_cycle' => 3, 'monthly_rent' => 1000, 'deposit' => $deposit, 'status' => $status, 'currency' => 'TZS', 'vat_rate' => 18,
        ]);

        return [$property, $superuser, $manager, $unit, $tenant, $lease];
    }

    private function editPayload(Lease $lease, array $over = []): array
    {
        return array_merge([
            'action' => 'edit', 'tenant_id' => $lease->tenant_id, 'unit_id' => $lease->unit_id, 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'duration_months' => 12, 'payment_cycle' => 3, 'monthly_rent' => 1000, 'deposit' => 0, 'terms' => null,
        ], $over);
    }

    public function test_a_managers_edit_of_a_live_lease_waits_for_approval_and_is_then_applied(): void
    {
        Notification::fake();
        [$property, $superuser, $manager, $unit, $tenant, $lease] = $this->world();

        $this->actingAs($manager)->patch("/leases/{$lease->id}", $this->editPayload($lease, ['monthly_rent' => 1500]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertEquals(1000, $lease->fresh()->monthly_rent);                 // unchanged
        $request = LeaseChangeRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame('edit', $request->type);
        Notification::assertSentTo($superuser, \App\Notifications\LeaseChangeRequestNotification::class);

        // A second request while one is open is refused.
        $this->actingAs($manager)->patch("/leases/{$lease->id}", $this->editPayload($lease, ['monthly_rent' => 1700]))->assertSessionHasErrors('lease');

        $this->actingAs($superuser)->post("/superuser/lease-changes/{$request->id}/approve")->assertSessionHasNoErrors();

        $this->assertEquals(1500, $lease->fresh()->monthly_rent);
        $this->assertSame('approved', $request->fresh()->status);
        Notification::assertSentTo($manager, \App\Notifications\LeaseChangeDecisionNotification::class);
    }

    public function test_a_rejected_edit_leaves_the_lease_alone_and_needs_a_reason(): void
    {
        Notification::fake();
        [$property, $superuser, $manager, $unit, $tenant, $lease] = $this->world();
        $this->actingAs($manager)->patch("/leases/{$lease->id}", $this->editPayload($lease, ['monthly_rent' => 1500]));
        $request = LeaseChangeRequest::firstOrFail();

        $this->actingAs($superuser)->post("/superuser/lease-changes/{$request->id}/reject", [])->assertSessionHasErrors('message');
        $this->actingAs($superuser)->post("/superuser/lease-changes/{$request->id}/reject", ['message' => 'Rent was agreed at 1000'])->assertSessionHasNoErrors();

        $this->assertEquals(1000, $lease->fresh()->monthly_rent);
        $this->assertSame('rejected', $request->fresh()->status);
        Notification::assertSentTo($manager, \App\Notifications\LeaseChangeDecisionNotification::class);
    }

    public function test_a_managers_delete_of_a_live_lease_waits_for_approval_then_frees_the_unit(): void
    {
        Notification::fake();
        [$property, $superuser, $manager, $unit, $tenant, $lease] = $this->world();

        $this->actingAs($manager)->delete("/leases/{$lease->id}")->assertSessionHasNoErrors();
        $this->assertNotNull(Lease::find($lease->id));
        $request = LeaseChangeRequest::firstOrFail();
        $this->assertSame('delete', $request->type);

        $this->actingAs($superuser)->post("/superuser/lease-changes/{$request->id}/approve")->assertSessionHasNoErrors();

        $this->assertNull(Lease::find($lease->id));
        $this->assertSame('vacant', $unit->fresh()->status);
    }

    public function test_a_superusers_own_edit_and_delete_apply_immediately(): void
    {
        [$property, $superuser, $manager, $unit, $tenant, $lease] = $this->world();

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->patch("/leases/{$lease->id}", $this->editPayload($lease, ['monthly_rent' => 2000]))->assertSessionHasNoErrors();
        $this->assertEquals(2000, $lease->fresh()->monthly_rent);
        $this->assertSame(0, LeaseChangeRequest::count());

        $this->actingAs($superuser)->delete("/leases/{$lease->id}")->assertSessionHasNoErrors();
        $this->assertNull(Lease::find($lease->id));
        $this->assertSame(0, LeaseChangeRequest::count());
    }

    public function test_a_managers_edit_or_withdrawal_of_a_lease_not_yet_approved_is_direct(): void
    {
        [$property, $superuser, $manager, $unit, $tenant, $lease] = $this->world(0, 'pending_accountant');

        $this->actingAs($manager)->patch("/leases/{$lease->id}", $this->editPayload($lease, ['monthly_rent' => 1200]))->assertSessionHasNoErrors();
        $this->assertEquals(1200, $lease->fresh()->monthly_rent);

        $this->actingAs($manager)->delete("/leases/{$lease->id}")->assertSessionHasNoErrors();
        $this->assertNull(Lease::find($lease->id));
        $this->assertSame(0, LeaseChangeRequest::count());
    }

    public function test_a_lease_with_a_deposit_cannot_be_deleted_by_anyone(): void
    {
        [$property, $superuser, $manager, $unit, $tenant, $lease] = $this->world(500);

        $this->actingAs($manager)->delete("/leases/{$lease->id}")->assertStatus(422);
        $this->actingAs($superuser)->delete("/leases/{$lease->id}")->assertStatus(422);
        $this->assertNotNull(Lease::find($lease->id));
        $this->assertSame(0, LeaseChangeRequest::count());
    }

    public function test_the_leases_page_tells_the_drawer_about_a_waiting_change(): void
    {
        [$property, $superuser, $manager, $unit, $tenant, $lease] = $this->world();
        $this->actingAs($manager)->delete("/leases/{$lease->id}");

        $this->actingAs($manager)->get('/leases')->assertInertia(fn ($p) => $p
            ->has('leases', 1, fn ($l) => $l->where('pending_change.type', 'delete')->etc()));
    }
}
