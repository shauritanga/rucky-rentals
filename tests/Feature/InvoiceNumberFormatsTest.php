<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceNumberFormatsTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Athanas Test', 'email' => 'a@example.com', 'phone' => '1', 'initials' => 'AT',
            'color' => 'x', 'text_color' => 'y', 'tenant_type' => 'individual', 'invoice_code' => 'ATHANAS',
        ]);
    }

    private function save(string $number): void
    {
        Invoice::create([
            'invoice_number' => $number, 'type' => 'proforma', 'tenant_name' => 'Athanas Test', 'unit_ref' => 'A',
            'issued_date' => '2026-03-01', 'status' => 'proforma', 'currency' => 'TZS',
        ]);
    }

    private function numbers(): InvoiceNumberService
    {
        return app(InvoiceNumberService::class);
    }

    public function test_deposit_number_has_no_sequence_the_first_time(): void
    {
        $number = DB::transaction(fn () => $this->numbers()->generateForDeposit($this->tenant(), null, '2026-03-01'));
        $this->assertSame('ATHANAS/DEPOSIT/2026', $number);
    }

    public function test_a_second_deposit_in_the_same_year_gets_a_sequence(): void
    {
        $tenant = $this->tenant();
        $this->save('ATHANAS/DEPOSIT/2026');
        $this->assertSame('ATHANAS/DEPOSIT/02/2026', DB::transaction(fn () => $this->numbers()->generateForDeposit($tenant, null, '2026-05-01')));

        $this->save('ATHANAS/DEPOSIT/02/2026');
        $this->assertSame('ATHANAS/DEPOSIT/03/2026', DB::transaction(fn () => $this->numbers()->generateForDeposit($tenant, null, '2026-06-01')));
    }

    public function test_deposit_numbers_restart_each_year(): void
    {
        $tenant = $this->tenant();
        $this->save('ATHANAS/DEPOSIT/2026');
        $this->assertSame('ATHANAS/DEPOSIT/2027', DB::transaction(fn () => $this->numbers()->generateForDeposit($tenant, null, '2027-01-10')));
    }

    public function test_electricity_numbers_count_per_tenant_and_year(): void
    {
        $tenant = $this->tenant();
        $this->assertSame('ATHANAS/ELC/01/2026', DB::transaction(fn () => $this->numbers()->generateForElectricity($tenant, null, '2026-03-01')));

        $this->save('ATHANAS/ELC/01/2026');
        $this->assertSame('ATHANAS/ELC/02/2026', DB::transaction(fn () => $this->numbers()->generateForElectricity($tenant, null, '2026-04-01')));
        $this->assertSame('ATHANAS/ELC/01/2027', DB::transaction(fn () => $this->numbers()->generateForElectricity($tenant, null, '2027-01-01')));
    }

    public function test_rent_numbers_ignore_deposit_and_electricity_numbers(): void
    {
        $tenant = $this->tenant();
        $this->save('ATHANAS/DEPOSIT/2026');
        $this->save('ATHANAS/ELC/01/2026');
        $this->save('ATHANAS/ELC/02/2026');

        $this->assertSame('ATHANAS/01/2026', DB::transaction(fn () => $this->numbers()->generateForTenant($tenant, null, '2026-03-01')));

        $this->save('ATHANAS/01/2026');
        $this->assertSame('ATHANAS/02/2026', DB::transaction(fn () => $this->numbers()->generateForTenant($tenant, null, '2026-03-02')));
    }
}
