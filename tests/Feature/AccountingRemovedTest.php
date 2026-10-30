<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountingRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_accounting_tables_are_not_created(): void
    {
        foreach (['accounts', 'journal_entries', 'journal_lines', 'accounting_events'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} should not exist");
        }
    }

    public function test_the_accounting_page_and_routes_are_gone(): void
    {
        $user = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);

        $this->actingAs($user)->get('/accounting')->assertNotFound();
        $this->actingAs($user)->post('/accounting/accounts', [])->assertStatus(404);
        $this->actingAs($user)->post('/superuser/revenue/post-fee', [])->assertStatus(404);
    }

    public function test_creating_a_property_no_longer_seeds_a_chart_of_accounts(): void
    {
        $user = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);

        $this->actingAs($user)->post('/superuser/properties', [
            'name' => 'No Ledger Tower', 'address' => 'Samora Ave', 'city' => 'Dar es Salaam', 'country' => 'Tanzania',
            'bank_name' => 'DTB', 'bank_account' => '123', 'bank_account_name' => 'Mwamba', 'swift_code' => 'DTKETZTZ',
            'status' => 'active', 'upper_floors' => 3,
        ])->assertRedirect();

        $this->assertNotNull(Property::where('name', 'No Ledger Tower')->first());
    }
}
