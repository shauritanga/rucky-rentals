<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaseEndDateTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $property = Property::create(['name' => 'End Tower', 'code' => 'END', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_number' => 'E1', 'floor' => '1', 'type' => 'Office', 'size_sqft' => 100, 'size_sqm' => 9.29,
            'rate_per_sqm' => 100, 'service_charge_per_sqm' => 0, 'currency' => 'TZS', 'rent' => 1000, 'status' => 'vacant', 'deposit' => 0,
            'service_charge' => 0, 'electricity_type' => 'direct', 'approval_status' => 'approved',
        ]);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'End Tenant', 'email' => 'e@example.com', 'phone' => '+255700000000', 'initials' => 'ET', 'tenant_type' => 'individual']);

        return [$property, $superuser, $manager, $unit, $tenant];
    }

    private function create(User $manager, Unit $unit, Tenant $tenant, string $start, string $end)
    {
        return $this->actingAs($manager)->post('/leases', [
            'tenant_mode' => 'existing', 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'start_date' => $start, 'end_date' => $end,
            'duration_months' => 12, 'payment_cycle' => 3, 'monthly_rent' => 1000, 'deposit' => 0,
        ]);
    }

    public function test_a_year_from_6_april_ends_on_5_april_and_the_schedule_has_four_instalments(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $this->create($manager, $unit, $tenant, '2026-04-06', '2027-04-05')->assertSessionHasNoErrors();
        $lease = Lease::firstOrFail();

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->patch("/leases/{$lease->id}", ['action' => 'approve_superuser'])->assertSessionHasNoErrors();

        $rows = LeaseInstallment::where('lease_id', $lease->id)->orderBy('sequence')->get();
        $this->assertCount(4, $rows);
        $this->assertSame('2026-04-06', substr((string) $rows->first()->period_start, 0, 10));
        $this->assertSame('2027-04-05', substr((string) $rows->last()->period_end, 0, 10));
    }

    public function test_a_renewal_starting_the_day_after_is_free_but_the_last_day_is_taken(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $this->create($manager, $unit, $tenant, '2026-04-06', '2027-04-05')->assertSessionHasNoErrors();

        $this->create($manager, $unit, $tenant, '2027-04-05', '2028-04-04')->assertSessionHasErrors('unit_id');
        $this->create($manager, $unit, $tenant, '2027-04-06', '2028-04-05')->assertSessionHasNoErrors();
    }
}
