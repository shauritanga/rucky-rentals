<?php

namespace App\Support;

use Carbon\Carbon;

class MockRentalData
{
    public static function shouldUse(): bool
    {
        if (request()->has('mock')) {
            return request()->boolean('mock');
        }

        return (bool) config('app.use_mock_data', true);
    }

    public static function dashboard(): array
    {
        $data = self::build();

        return [
            'stats' => $data['stats'],
            'recentPayments' => $data['recentPayments'],
            'maintenanceItems' => $data['maintenanceItems'],
            'units' => $data['dashboardUnits'],
            'occupancyByFloor' => $data['occupancyByFloor'],
        ];
    }

    public static function units(): array
    {
        return self::build()['units'];
    }

    public static function tenants(): array
    {
        return self::build()['tenants'];
    }

    public static function leases(): array
    {
        return self::build()['leases'];
    }

    public static function payments(): array
    {
        return self::build()['payments'];
    }

    public static function maintenanceTickets(): array
    {
        return self::build()['maintenance'];
    }

    public static function documents(): array
    {
        return self::build()['documents'];
    }

    public static function invoices(): array
    {
        return self::build()['invoices'];
    }

    private static function build(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $tenantSeed = [
            'SR' => ['name' => 'Sarah Rutto', 'email' => 'sarah.rutto@gmail.com', 'phone' => '+254 712 345 678', 'national_id' => 'KE-34521890', 'nok_name' => 'Grace Rutto', 'nok_phone' => '+254 700 111 222', 'nok_relation' => 'Sister', 'notes' => null, 'color' => 'rgba(59,130,246,.18)', 'text_color' => 'var(--accent)'],
            'BK' => ['name' => 'Brian Kimani', 'email' => 'b.kimani@outlook.com', 'phone' => '+254 722 100 200', 'national_id' => 'KE-12094567', 'nok_name' => 'Alice Kimani', 'nok_phone' => '+254 722 333 444', 'nok_relation' => 'Mother', 'notes' => 'Overdue since Mar 5. Two missed payments total.', 'color' => 'rgba(34,197,94,.18)', 'text_color' => 'var(--green)'],
            'AM' => ['name' => 'Amina Musa', 'email' => 'amina.musa@gmail.com', 'phone' => '+254 733 887 654', 'national_id' => 'KE-56781234', 'nok_name' => 'Omar Musa', 'nok_phone' => '+254 733 555 666', 'nok_relation' => 'Husband', 'notes' => null, 'color' => 'rgba(245,158,11,.18)', 'text_color' => 'var(--amber)'],
            'JO' => ['name' => 'James Omondi', 'email' => 'james.omondi@yahoo.com', 'phone' => '+254 700 234 567', 'national_id' => 'KE-78902345', 'nok_name' => 'Mary Omondi', 'nok_phone' => '+254 700 777 888', 'nok_relation' => 'Wife', 'notes' => '2 months overdue - $2,200 balance. Notice issued Feb 28.', 'color' => 'rgba(239,68,68,.18)', 'text_color' => 'var(--red)'],
            'FN' => ['name' => 'Fatima Ngugi', 'email' => 'f.ngugi@proton.me', 'phone' => '+254 711 998 001', 'national_id' => 'KE-23456789', 'nok_name' => 'Hassan Ngugi', 'nok_phone' => '+254 711 999 000', 'nok_relation' => 'Brother', 'notes' => null, 'color' => 'rgba(59,130,246,.18)', 'text_color' => 'var(--accent)'],
            'CK' => ['name' => 'Charles Kiprop', 'email' => 'c.kiprop@gmail.com', 'phone' => '+254 729 456 123', 'national_id' => 'KE-90123456', 'nok_name' => 'Esther Kiprop', 'nok_phone' => '+254 729 112 223', 'nok_relation' => 'Spouse', 'notes' => null, 'color' => 'rgba(34,197,94,.18)', 'text_color' => 'var(--green)'],
            'LW' => ['name' => 'Lydia Wambui', 'email' => 'lydia.w@gmail.com', 'phone' => '+254 745 321 654', 'national_id' => 'KE-34567012', 'nok_name' => 'John Wambui', 'nok_phone' => '+254 745 334 445', 'nok_relation' => 'Husband', 'notes' => null, 'color' => 'rgba(245,158,11,.18)', 'text_color' => 'var(--amber)'],
            'PO' => ['name' => 'Peter Otieno', 'email' => 'p.otieno@gmail.com', 'phone' => '+254 788 654 321', 'national_id' => 'KE-45678901', 'nok_name' => 'Eunice Otieno', 'nok_phone' => '+254 788 556 667', 'nok_relation' => 'Mother', 'notes' => null, 'color' => 'rgba(59,130,246,.18)', 'text_color' => 'var(--accent)'],
            'NM' => ['name' => 'Nancy Mwende', 'email' => 'n.mwende@gmail.com', 'phone' => '+254 700 112 233', 'national_id' => 'KE-56789012', 'nok_name' => 'Stephen Mwende', 'nok_phone' => '+254 700 778 889', 'nok_relation' => 'Father', 'notes' => null, 'color' => 'rgba(239,68,68,.18)', 'text_color' => 'var(--red)'],
            'DK' => ['name' => 'David Kamau', 'email' => 'david.kamau@gmail.com', 'phone' => '+254 733 445 566', 'national_id' => 'KE-67890123', 'nok_name' => 'Ruth Kamau', 'nok_phone' => '+254 733 990 001', 'nok_relation' => 'Wife', 'notes' => null, 'color' => 'rgba(34,197,94,.18)', 'text_color' => 'var(--green)'],
            'RN' => ['name' => 'Rose Njeri', 'email' => 'rose.njeri@gmail.com', 'phone' => '+254 711 778 899', 'national_id' => 'KE-78901234', 'nok_name' => 'Patrick Njeri', 'nok_phone' => '+254 711 223 334', 'nok_relation' => 'Father', 'notes' => null, 'color' => 'rgba(245,158,11,.18)', 'text_color' => 'var(--amber)'],
            'MO' => ['name' => 'Moses Odhiambo', 'email' => 'm.odhiambo@gmail.com', 'phone' => '+254 722 334 455', 'national_id' => 'KE-89012345', 'nok_name' => 'Clara Odhiambo', 'nok_phone' => '+254 722 445 556', 'nok_relation' => 'Sister', 'notes' => null, 'color' => 'rgba(59,130,246,.18)', 'text_color' => 'var(--accent)'],
        ];

        $unitSeed = [
            ['unit_number' => 'A-101', 'floor' => 1, 'type' => '1 Bed', 'size_sqft' => 620, 'rent' => 1200, 'status' => 'occupied', 'tenant' => 'SR', 'lease_start' => 'Jan 2024', 'lease_end' => 'Jan 2025'],
            ['unit_number' => 'A-102', 'floor' => 1, 'type' => '1 Bed', 'size_sqft' => 600, 'rent' => 950, 'status' => 'overdue', 'tenant' => 'BK', 'lease_start' => 'Mar 2024', 'lease_end' => 'Mar 2025'],
            ['unit_number' => 'A-103', 'floor' => 1, 'type' => 'Studio', 'size_sqft' => 420, 'rent' => 750, 'status' => 'occupied', 'tenant' => 'CK', 'lease_start' => 'Jun 2024', 'lease_end' => 'Jun 2025'],
            ['unit_number' => 'A-104', 'floor' => 1, 'type' => '2 Bed', 'size_sqft' => 880, 'rent' => 1450, 'status' => 'occupied', 'tenant' => 'LW', 'lease_start' => 'Feb 2024', 'lease_end' => 'Feb 2025'],
            ['unit_number' => 'A-105', 'floor' => 1, 'type' => '1 Bed', 'size_sqft' => 610, 'rent' => 920, 'status' => 'occupied', 'tenant' => 'PO', 'lease_start' => 'Apr 2024', 'lease_end' => 'Apr 2025'],
            ['unit_number' => 'A-106', 'floor' => 1, 'type' => 'Studio', 'size_sqft' => 400, 'rent' => 720, 'status' => 'occupied', 'tenant' => 'NM', 'lease_start' => 'Jul 2024', 'lease_end' => 'Jul 2025'],
            ['unit_number' => 'B-201', 'floor' => 2, 'type' => '2 Bed', 'size_sqft' => 920, 'rent' => 1400, 'status' => 'occupied', 'tenant' => 'AM', 'lease_start' => 'May 2024', 'lease_end' => 'May 2025'],
            ['unit_number' => 'B-202', 'floor' => 2, 'type' => '1 Bed', 'size_sqft' => 650, 'rent' => 1250, 'status' => 'vacant', 'tenant' => null, 'lease_start' => null, 'lease_end' => null],
            ['unit_number' => 'B-203', 'floor' => 2, 'type' => 'Studio', 'size_sqft' => 430, 'rent' => 780, 'status' => 'occupied', 'tenant' => 'DK', 'lease_start' => 'Aug 2024', 'lease_end' => 'Aug 2025'],
            ['unit_number' => 'B-204', 'floor' => 2, 'type' => '1 Bed', 'size_sqft' => 640, 'rent' => 1050, 'status' => 'occupied', 'tenant' => 'RN', 'lease_start' => 'Sep 2024', 'lease_end' => 'Sep 2025'],
            ['unit_number' => 'B-205', 'floor' => 2, 'type' => '2 Bed', 'size_sqft' => 890, 'rent' => 1380, 'status' => 'occupied', 'tenant' => 'MO', 'lease_start' => 'Oct 2024', 'lease_end' => 'Oct 2025'],
            ['unit_number' => 'C-301', 'floor' => 3, 'type' => '2 Bed', 'size_sqft' => 900, 'rent' => 1100, 'status' => 'overdue', 'tenant' => 'JO', 'lease_start' => 'Nov 2023', 'lease_end' => 'Nov 2024'],
            ['unit_number' => 'C-302', 'floor' => 3, 'type' => '2 Bed', 'size_sqft' => 940, 'rent' => 1300, 'status' => 'occupied', 'tenant' => 'FN', 'lease_start' => 'Dec 2023', 'lease_end' => 'Dec 2024'],
            ['unit_number' => 'C-303', 'floor' => 3, 'type' => 'Studio', 'size_sqft' => 415, 'rent' => 760, 'status' => 'occupied', 'tenant' => 'SR', 'lease_start' => 'Jan 2025', 'lease_end' => 'Jan 2026'],
            ['unit_number' => 'C-304', 'floor' => 3, 'type' => '1 Bed', 'size_sqft' => 630, 'rent' => 1000, 'status' => 'occupied', 'tenant' => 'BK', 'lease_start' => 'Feb 2025', 'lease_end' => 'Feb 2026'],
            ['unit_number' => 'C-305', 'floor' => 3, 'type' => '3 Bed', 'size_sqft' => 1100, 'rent' => 1800, 'status' => 'occupied', 'tenant' => 'AM', 'lease_start' => 'Mar 2025', 'lease_end' => 'Mar 2026'],
            ['unit_number' => 'D-401', 'floor' => 4, 'type' => '1 Bed', 'size_sqft' => 660, 'rent' => 1500, 'status' => 'maintenance', 'tenant' => null, 'lease_start' => null, 'lease_end' => null],
            ['unit_number' => 'D-402', 'floor' => 4, 'type' => '2 Bed', 'size_sqft' => 950, 'rent' => 1600, 'status' => 'occupied', 'tenant' => 'CK', 'lease_start' => 'Apr 2025', 'lease_end' => 'Apr 2026'],
            ['unit_number' => 'D-403', 'floor' => 4, 'type' => 'Studio', 'size_sqft' => 440, 'rent' => 820, 'status' => 'occupied', 'tenant' => 'LW', 'lease_start' => 'May 2025', 'lease_end' => 'May 2026'],
            ['unit_number' => 'D-404', 'floor' => 4, 'type' => '1 Bed', 'size_sqft' => 645, 'rent' => 1100, 'status' => 'vacant', 'tenant' => null, 'lease_start' => null, 'lease_end' => null],
            ['unit_number' => 'E-501', 'floor' => 5, 'type' => '2 Bed', 'size_sqft' => 960, 'rent' => 1650, 'status' => 'occupied', 'tenant' => 'PO', 'lease_start' => 'Jun 2025', 'lease_end' => 'Jun 2026'],
            ['unit_number' => 'E-502', 'floor' => 5, 'type' => '3 Bed', 'size_sqft' => 1150, 'rent' => 2000, 'status' => 'occupied', 'tenant' => 'NM', 'lease_start' => 'Jul 2025', 'lease_end' => 'Jul 2026'],
            ['unit_number' => 'E-503', 'floor' => 5, 'type' => '1 Bed', 'size_sqft' => 670, 'rent' => 1150, 'status' => 'occupied', 'tenant' => 'DK', 'lease_start' => 'Aug 2025', 'lease_end' => 'Aug 2026'],
            ['unit_number' => 'E-504', 'floor' => 5, 'type' => 'Studio', 'size_sqft' => 425, 'rent' => 800, 'status' => 'occupied', 'tenant' => 'RN', 'lease_start' => 'Sep 2025', 'lease_end' => 'Sep 2026'],
            ['unit_number' => 'E-505', 'floor' => 5, 'type' => '2 Bed', 'size_sqft' => 980, 'rent' => 1700, 'status' => 'occupied', 'tenant' => 'MO', 'lease_start' => 'Oct 2025', 'lease_end' => 'Oct 2026'],
            ['unit_number' => 'E-506', 'floor' => 5, 'type' => '1 Bed', 'size_sqft' => 635, 'rent' => 1100, 'status' => 'occupied', 'tenant' => 'JO', 'lease_start' => 'Nov 2025', 'lease_end' => 'Nov 2026'],
            ['unit_number' => 'E-507', 'floor' => 5, 'type' => 'Studio', 'size_sqft' => 410, 'rent' => 790, 'status' => 'occupied', 'tenant' => 'FN', 'lease_start' => 'Dec 2025', 'lease_end' => 'Dec 2026'],
            ['unit_number' => 'F-601', 'floor' => 6, 'type' => 'Penthouse', 'size_sqft' => 2100, 'rent' => 4500, 'status' => 'occupied', 'tenant' => 'CK', 'lease_start' => 'Jan 2025', 'lease_end' => 'Jan 2027'],
            ['unit_number' => 'F-602', 'floor' => 6, 'type' => '3 Bed', 'size_sqft' => 1250, 'rent' => 2400, 'status' => 'occupied', 'tenant' => 'LW', 'lease_start' => 'Feb 2025', 'lease_end' => 'Feb 2027'],
            ['unit_number' => 'F-603', 'floor' => 6, 'type' => '3 Bed', 'size_sqft' => 1200, 'rent' => 2200, 'status' => 'occupied', 'tenant' => 'PO', 'lease_start' => 'Mar 2025', 'lease_end' => 'Mar 2027'],
            ['unit_number' => 'F-604', 'floor' => 6, 'type' => '2 Bed', 'size_sqft' => 1020, 'rent' => 1900, 'status' => 'occupied', 'tenant' => 'NM', 'lease_start' => 'Apr 2025', 'lease_end' => 'Apr 2027'],
            ['unit_number' => 'F-605', 'floor' => 6, 'type' => 'Penthouse', 'size_sqft' => 2050, 'rent' => 4200, 'status' => 'occupied', 'tenant' => 'DK', 'lease_start' => 'May 2025', 'lease_end' => 'May 2027'],
        ];

        $cycleByTenant = [
            'SR' => 3,
            'BK' => 3,
            'AM' => 6,
            'JO' => 3,
            'FN' => 12,
            'CK' => 4,
            'LW' => 6,
            'PO' => 4,
            'NM' => 12,
            'DK' => 3,
            'RN' => 6,
            'MO' => 4,
        ];

        $tenants = [];
        $tenantIdByCode = [];
        $tenantsById = [];
        $tenantId = 1;

        foreach ($tenantSeed as $code => $seed) {
            $tenant = [
                'id' => $tenantId,
                'name' => $seed['name'],
                'email' => $seed['email'],
                'phone' => $seed['phone'],
                'national_id' => $seed['national_id'],
                'initials' => $code,
                'color' => $seed['color'],
                'text_color' => $seed['text_color'],
                'nok_name' => $seed['nok_name'],
                'nok_phone' => $seed['nok_phone'],
                'nok_relation' => $seed['nok_relation'],
                'notes' => $seed['notes'],
                'leases' => [],
            ];

            $tenants[] = $tenant;
            $tenantIdByCode[$code] = $tenantId;
            $tenantsById[$tenantId] = &$tenants[array_key_last($tenants)];
            $tenantId++;
        }

        $units = [];
        $unitIdByNumber = [];
        $unitsById = [];
        $unitId = 1;

        foreach ($unitSeed as $seed) {
            $sizeSqm = round($seed['size_sqft'] * 0.09290304, 2);
            $ratePerSqm = $sizeSqm > 0 ? round($seed['rent'] / $sizeSqm, 2) : 0;

            $unit = [
                'id' => $unitId,
                'unit_number' => $seed['unit_number'],
                'number' => $seed['unit_number'],
                'floor' => $seed['floor'],
                'type' => $seed['type'],
                'size_sqft' => $seed['size_sqft'],
                'size_sqm' => $sizeSqm,
                'rate_per_sqm' => $ratePerSqm,
                'currency' => 'USD',
                'rent' => $seed['rent'],
                'status' => $seed['status'],
                'deposit' => $seed['rent'] * 2,
                'notes' => null,
                'leases' => [],
            ];

            $units[] = $unit;
            $unitIdByNumber[$seed['unit_number']] = $unitId;
            $unitsById[$unitId] = &$units[array_key_last($units)];
            $unitId++;
        }

        $leases = [];
        $leaseId = 1;
        $leaseIdByTenantUnit = [];

        foreach ($unitSeed as $seed) {
            if (!$seed['tenant']) {
                continue;
            }

            $tenantId = $tenantIdByCode[$seed['tenant']];
            $unitId = $unitIdByNumber[$seed['unit_number']];
            $startDate = self::monthYearToDate($seed['lease_start']);
            $endDate = self::monthYearToDate($seed['lease_end']);
            $status = self::deriveLeaseStatus($seed['status'], $endDate);
            $monthlyRent = (float) $seed['rent'];
            $deposit = (float) ($seed['rent'] * 2);
            $paymentCycle = $cycleByTenant[$seed['tenant']] ?? 3;
            $approvalLog = json_encode([
                ['step' => 0, 'action' => 'submitted', 'by' => 'James Mwangi (Lease Manager)', 'date' => '2026-01-05', 'text' => 'Lease submitted for approval.'],
                ['step' => 1, 'action' => 'approved', 'by' => 'Diana Ochieng (Accountant)', 'date' => '2026-01-06', 'text' => 'Financials verified. Approved.'],
                ['step' => 2, 'action' => 'approved', 'by' => 'James Mwangi (Property Manager)', 'date' => '2026-01-07', 'text' => 'Final approval. Lease activated.'],
            ]);

            if ($status === 'overdue') {
                $approvalLog = json_encode([
                    ['step' => 0, 'action' => 'submitted', 'by' => 'James Mwangi (Lease Manager)', 'date' => '2025-11-01', 'text' => 'Lease submitted for approval.'],
                    ['step' => 1, 'action' => 'approved', 'by' => 'Diana Ochieng (Accountant)', 'date' => '2025-11-02', 'text' => 'Financials verified. Approved.'],
                    ['step' => 2, 'action' => 'approved', 'by' => 'James Mwangi (Property Manager)', 'date' => '2025-11-03', 'text' => 'Final approval. Lease activated.'],
                    ['step' => 3, 'action' => 'overdue', 'by' => 'System', 'date' => '2026-03-05', 'text' => 'Rent payment overdue.'],
                ]);
            }

            $tenantLite = self::tenantLite($tenantsById[$tenantId]);
            $unitLite = self::unitLite($unitsById[$unitId]);

            $lease = [
                'id' => $leaseId,
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'duration_months' => self::monthsBetween($startDate, $endDate),
                'payment_cycle' => $paymentCycle,
                'currency' => $unitsById[$unitId]['currency'] ?? 'USD',
                'monthly_rent' => $monthlyRent,
                'deposit' => $deposit,
                'status' => $status,
                'terms' => 'Late payment penalty: 5% after 7-day grace period. Tenant responsible for utility bills.',
                'approval_log' => $approvalLog,
                'tenant' => $tenantLite,
                'unit' => $unitLite,
            ];

            $leases[] = $lease;
            $leaseIdByTenantUnit[$tenantId . '-' . $unitId] = $leaseId;

            $unitsById[$unitId]['leases'][] = [
                'id' => $leaseId,
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'currency' => $unitsById[$unitId]['currency'] ?? 'USD',
                'monthly_rent' => $monthlyRent,
                'deposit' => $deposit,
                'tenant' => $tenantLite,
            ];

            $tenantsById[$tenantId]['leases'][] = [
                'id' => $leaseId,
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'currency' => $unitsById[$unitId]['currency'] ?? 'USD',
                'monthly_rent' => $monthlyRent,
                'deposit' => $deposit,
                'unit' => $unitLite,
            ];

            $leaseId++;
        }

        $paymentSeed = [
            ['tenant' => 'SR', 'unit' => 'A-101', 'month' => 'Mar 2026', 'amount' => 1200, 'method' => 'M-Pesa', 'status' => 'paid', 'date' => 'Mar 1, 2026'],
            ['tenant' => 'BK', 'unit' => 'A-102', 'month' => 'Mar 2026', 'amount' => 950, 'method' => null, 'status' => 'overdue', 'date' => null],
            ['tenant' => 'AM', 'unit' => 'B-201', 'month' => 'Mar 2026', 'amount' => 1400, 'method' => 'Bank Transfer', 'status' => 'paid', 'date' => 'Mar 1, 2026'],
            ['tenant' => 'JO', 'unit' => 'C-301', 'month' => 'Mar 2026', 'amount' => 1100, 'method' => null, 'status' => 'overdue', 'date' => null],
            ['tenant' => 'FN', 'unit' => 'C-302', 'month' => 'Mar 2026', 'amount' => 1300, 'method' => 'M-Pesa', 'status' => 'paid', 'date' => 'Mar 2, 2026'],
            ['tenant' => 'CK', 'unit' => 'A-103', 'month' => 'Mar 2026', 'amount' => 750, 'method' => 'Cash', 'status' => 'paid', 'date' => 'Mar 3, 2026'],
            ['tenant' => 'LW', 'unit' => 'A-104', 'month' => 'Mar 2026', 'amount' => 1450, 'method' => 'Bank Transfer', 'status' => 'paid', 'date' => 'Mar 1, 2026'],
            ['tenant' => 'PO', 'unit' => 'A-105', 'month' => 'Mar 2026', 'amount' => 920, 'method' => 'M-Pesa', 'status' => 'paid', 'date' => 'Mar 2, 2026'],
            ['tenant' => 'NM', 'unit' => 'A-106', 'month' => 'Mar 2026', 'amount' => 720, 'method' => 'M-Pesa', 'status' => 'paid', 'date' => 'Mar 1, 2026'],
            ['tenant' => 'DK', 'unit' => 'B-203', 'month' => 'Mar 2026', 'amount' => 780, 'method' => 'Cash', 'status' => 'pending', 'date' => null],
            ['tenant' => 'RN', 'unit' => 'B-204', 'month' => 'Mar 2026', 'amount' => 1050, 'method' => null, 'status' => 'pending', 'date' => null],
            ['tenant' => 'MO', 'unit' => 'B-205', 'month' => 'Mar 2026', 'amount' => 1380, 'method' => 'M-Pesa', 'status' => 'paid', 'date' => 'Mar 3, 2026'],
            ['tenant' => 'SR', 'unit' => 'A-101', 'month' => 'Feb 2026', 'amount' => 1200, 'method' => 'M-Pesa', 'status' => 'paid', 'date' => 'Feb 1, 2026'],
            ['tenant' => 'BK', 'unit' => 'A-102', 'month' => 'Feb 2026', 'amount' => 950, 'method' => 'Cash', 'status' => 'paid', 'date' => 'Feb 5, 2026'],
            ['tenant' => 'AM', 'unit' => 'B-201', 'month' => 'Feb 2026', 'amount' => 1400, 'method' => 'Bank Transfer', 'status' => 'paid', 'date' => 'Feb 1, 2026'],
            ['tenant' => 'JO', 'unit' => 'C-301', 'month' => 'Feb 2026', 'amount' => 1100, 'method' => null, 'status' => 'overdue', 'date' => null],
            ['tenant' => 'FN', 'unit' => 'C-302', 'month' => 'Feb 2026', 'amount' => 1300, 'method' => 'M-Pesa', 'status' => 'paid', 'date' => 'Feb 2, 2026'],
            ['tenant' => 'CK', 'unit' => 'A-103', 'month' => 'Feb 2026', 'amount' => 750, 'method' => 'M-Pesa', 'status' => 'paid', 'date' => 'Feb 1, 2026'],
            ['tenant' => 'LW', 'unit' => 'A-104', 'month' => 'Feb 2026', 'amount' => 1450, 'method' => 'Bank Transfer', 'status' => 'paid', 'date' => 'Feb 1, 2026'],
            ['tenant' => 'DK', 'unit' => 'B-203', 'month' => 'Feb 2026', 'amount' => 780, 'method' => 'Cash', 'status' => 'paid', 'date' => 'Feb 4, 2026'],
        ];

        $payments = [];
        $paymentId = 1;

        foreach ($paymentSeed as $seed) {
            $tenantId = $tenantIdByCode[$seed['tenant']];
            $unitId = $unitIdByNumber[$seed['unit']];

            $payment = [
                'id' => $paymentId,
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'month' => $seed['month'],
                'amount' => (float) $seed['amount'],
                'method' => $seed['method'],
                'status' => $seed['status'],
                'paid_date' => self::textDateToDate($seed['date']),
                'tenant' => self::tenantLite($tenantsById[$tenantId]),
                'unit' => self::unitLite($unitsById[$unitId]),
            ];

            $payments[] = $payment;
            $paymentId++;
        }

        $documentSeed = [
            ['name' => 'Lease Agreement - Sarah Rutto', 'unit' => 'A-101', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.2 MB', 'date' => 'Jan 1, 2024'],
            ['name' => 'Lease Agreement - Brian Kimani', 'unit' => 'A-102', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.1 MB', 'date' => 'Mar 1, 2024'],
            ['name' => 'Lease Agreement - Amina Musa', 'unit' => 'B-201', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.3 MB', 'date' => 'May 1, 2024'],
            ['name' => 'Lease Agreement - James Omondi', 'unit' => 'C-301', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.2 MB', 'date' => 'Nov 1, 2023'],
            ['name' => 'Lease Agreement - Fatima Ngugi', 'unit' => 'C-302', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.1 MB', 'date' => 'Dec 1, 2023'],
            ['name' => 'Lease Agreement - Charles Kiprop', 'unit' => 'A-103', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.0 MB', 'date' => 'Jun 1, 2024'],
            ['name' => 'Lease Agreement - Lydia Wambui', 'unit' => 'A-104', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.2 MB', 'date' => 'Feb 1, 2024'],
            ['name' => 'Lease Agreement - Peter Otieno', 'unit' => 'A-105', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.1 MB', 'date' => 'Apr 1, 2024'],
            ['name' => 'Lease Agreement - Nancy Mwende', 'unit' => 'A-106', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.0 MB', 'date' => 'Jul 1, 2024'],
            ['name' => 'Lease Agreement - David Kamau', 'unit' => 'B-203', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.1 MB', 'date' => 'Aug 1, 2024'],
            ['name' => 'Lease Agreement - Rose Njeri', 'unit' => 'B-204', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.0 MB', 'date' => 'Sep 1, 2024'],
            ['name' => 'Lease Agreement - Moses Odhiambo', 'unit' => 'B-205', 'tag' => 'lease', 'ext' => 'pdf', 'size' => '1.2 MB', 'date' => 'Oct 1, 2024'],
            ['name' => 'National ID - Sarah Rutto', 'unit' => 'A-101', 'tag' => 'id', 'ext' => 'img', 'size' => '420 KB', 'date' => 'Jan 1, 2024'],
            ['name' => 'National ID - Brian Kimani', 'unit' => 'A-102', 'tag' => 'id', 'ext' => 'img', 'size' => '380 KB', 'date' => 'Mar 1, 2024'],
            ['name' => 'National ID - Amina Musa', 'unit' => 'B-201', 'tag' => 'id', 'ext' => 'img', 'size' => '410 KB', 'date' => 'May 1, 2024'],
            ['name' => 'Late Rent Notice - Brian Kimani', 'unit' => 'A-102', 'tag' => 'notice', 'ext' => 'pdf', 'size' => '280 KB', 'date' => 'Mar 8, 2026'],
            ['name' => 'Overdue Notice - James Omondi', 'unit' => 'C-301', 'tag' => 'notice', 'ext' => 'pdf', 'size' => '290 KB', 'date' => 'Feb 28, 2026'],
            ['name' => 'Lease Renewal Letter - F. Ngugi', 'unit' => 'C-302', 'tag' => 'notice', 'ext' => 'word', 'size' => '310 KB', 'date' => 'Feb 15, 2026'],
            ['name' => 'Building Insurance Policy 2026', 'unit' => null, 'tag' => 'other', 'ext' => 'pdf', 'size' => '3.4 MB', 'date' => 'Jan 1, 2026'],
            ['name' => 'Fire Safety Certificate', 'unit' => null, 'tag' => 'other', 'ext' => 'pdf', 'size' => '1.8 MB', 'date' => 'Dec 1, 2025'],
            ['name' => 'Water Board Compliance Report', 'unit' => null, 'tag' => 'other', 'ext' => 'pdf', 'size' => '2.1 MB', 'date' => 'Nov 15, 2025'],
            ['name' => 'Lift Inspection Certificate', 'unit' => null, 'tag' => 'other', 'ext' => 'pdf', 'size' => '890 KB', 'date' => 'Oct 1, 2025'],
        ];

        $documents = [];
        $documentId = 1;

        foreach ($documentSeed as $seed) {
            $unitId = $seed['unit'] ? ($unitIdByNumber[$seed['unit']] ?? null) : null;
            $ext = $seed['ext'];
            $fileType = $ext === 'word' ? 'word' : ($ext === 'img' ? 'img' : ($ext === 'pdf' ? 'pdf' : 'other'));

            $documents[] = [
                'id' => $documentId,
                'name' => $seed['name'],
                'file_path' => 'mock/' . str_replace(' ', '-', strtolower($seed['name'])) . '.' . $ext,
                'file_type' => $fileType,
                'file_size' => $seed['size'],
                'tag' => $seed['tag'],
                'unit_ref' => $seed['unit'],
                'unit_id' => $unitId,
                'description' => null,
                'uploaded_by' => 'James Mwangi',
                'created_at' => self::textDateToDate($seed['date']) . ' 00:00:00',
                'unit' => $unitId ? self::unitLite($unitsById[$unitId]) : null,
            ];

            $documentId++;
        }

        $maintenanceSeed = [
            ['ticket_number' => 'TK-001', 'title' => 'Broken water pipe', 'unit_ref' => 'D-401', 'category' => 'Plumbing', 'priority' => 'high', 'status' => 'open', 'reported' => 'Mar 17, 2026', 'assignee' => 'Peter Ng.', 'cost' => null, 'description' => 'Tenant reported flooding in bathroom. Water main shut off. Urgent repair needed.', 'notes' => [['author' => 'James Mwangi', 'av' => 'JM', 'date' => 'Mar 17', 'text' => 'Contacted Peter Ng. Scheduled for Mar 22.'], ['author' => 'Peter Ng.', 'av' => 'PN', 'date' => 'Mar 18', 'text' => 'Parts ordered. Will attend Mar 22 morning.']]],
            ['ticket_number' => 'TK-002', 'title' => 'Flickering ceiling light', 'unit_ref' => 'B-204', 'category' => 'Electrical', 'priority' => 'med', 'status' => 'in-progress', 'reported' => 'Mar 15, 2026', 'assignee' => 'JK Electric', 'cost' => 180, 'description' => 'Ceiling light in living room flickers intermittently. Possible faulty wiring.', 'notes' => [['author' => 'JK Electric', 'av' => 'JK', 'date' => 'Mar 16', 'text' => 'Inspected. Faulty ballast. Replacement ordered.']]],
            ['ticket_number' => 'TK-003', 'title' => 'Broken door hinge', 'unit_ref' => 'A-105', 'category' => 'General', 'priority' => 'low', 'status' => 'open', 'reported' => 'Mar 12, 2026', 'assignee' => null, 'cost' => null, 'description' => 'Front door hinge is loose. Door does not close fully.', 'notes' => []],
            ['ticket_number' => 'TK-004', 'title' => 'AC unit not cooling', 'unit_ref' => 'C-303', 'category' => 'HVAC', 'priority' => 'med', 'status' => 'in-progress', 'reported' => 'Mar 10, 2026', 'assignee' => 'Cool Air Ltd', 'cost' => 350, 'description' => 'AC unit runs but blows warm air. Likely refrigerant issue.', 'notes' => [['author' => 'Cool Air Ltd', 'av' => 'CA', 'date' => 'Mar 12', 'text' => 'Diagnosed - refrigerant leak. Repair in progress.']]],
            ['ticket_number' => 'TK-005', 'title' => 'Faulty door lock', 'unit_ref' => 'E-502', 'category' => 'Security', 'priority' => 'high', 'status' => 'open', 'reported' => 'Mar 9, 2026', 'assignee' => null, 'cost' => null, 'description' => 'Main door lock is jammed. Tenant cannot lock apartment properly.', 'notes' => []],
            ['ticket_number' => 'TK-006', 'title' => 'Shower drain blocked', 'unit_ref' => 'A-103', 'category' => 'Plumbing', 'priority' => 'med', 'status' => 'resolved', 'reported' => 'Mar 5, 2026', 'assignee' => 'Peter Ng.', 'cost' => 90, 'description' => 'Shower drains very slowly.', 'notes' => [['author' => 'Peter Ng.', 'av' => 'PN', 'date' => 'Mar 6', 'text' => 'Cleared blockage with snake tool. Resolved.']]],
            ['ticket_number' => 'TK-007', 'title' => 'Power socket sparking', 'unit_ref' => 'F-601', 'category' => 'Electrical', 'priority' => 'high', 'status' => 'resolved', 'reported' => 'Mar 3, 2026', 'assignee' => 'JK Electric', 'cost' => 220, 'description' => 'Kitchen socket sparked when plugging in appliance.', 'notes' => [['author' => 'JK Electric', 'av' => 'JK', 'date' => 'Mar 4', 'text' => 'Replaced faulty socket and checked circuit. Safe.']]],
            ['ticket_number' => 'TK-008', 'title' => 'Window latch broken', 'unit_ref' => 'C-305', 'category' => 'General', 'priority' => 'low', 'status' => 'resolved', 'reported' => 'Feb 28, 2026', 'assignee' => 'In-house Team', 'cost' => 40, 'description' => 'Window latch is broken, window cannot be locked.', 'notes' => [['author' => 'In-house', 'av' => 'IH', 'date' => 'Mar 1', 'text' => 'Replaced latch. Window now locks correctly.']]],
            ['ticket_number' => 'TK-009', 'title' => 'Leaking tap - kitchen', 'unit_ref' => 'B-201', 'category' => 'Plumbing', 'priority' => 'low', 'status' => 'resolved', 'reported' => 'Feb 25, 2026', 'assignee' => 'Peter Ng.', 'cost' => 60, 'description' => 'Kitchen tap drips continuously.', 'notes' => [['author' => 'Peter Ng.', 'av' => 'PN', 'date' => 'Feb 26', 'text' => 'Replaced washer. No more drip.']]],
            ['ticket_number' => 'TK-010', 'title' => 'Heating not working', 'unit_ref' => 'D-402', 'category' => 'HVAC', 'priority' => 'med', 'status' => 'resolved', 'reported' => 'Feb 20, 2026', 'assignee' => 'Cool Air Ltd', 'cost' => 280, 'description' => 'Heater does not turn on.', 'notes' => [['author' => 'Cool Air Ltd', 'av' => 'CA', 'date' => 'Feb 22', 'text' => 'Faulty thermostat replaced. Unit working.']]],
            ['ticket_number' => 'TK-011', 'title' => 'Toilet flush broken', 'unit_ref' => 'E-504', 'category' => 'Plumbing', 'priority' => 'med', 'status' => 'resolved', 'reported' => 'Feb 18, 2026', 'assignee' => 'Peter Ng.', 'cost' => 110, 'description' => 'Toilet flush mechanism is broken.', 'notes' => [['author' => 'Peter Ng.', 'av' => 'PN', 'date' => 'Feb 19', 'text' => 'Cistern mechanism replaced. Flush working.']]],
            ['ticket_number' => 'TK-012', 'title' => 'Stairwell light out', 'unit_ref' => 'Common', 'category' => 'Electrical', 'priority' => 'low', 'status' => 'resolved', 'reported' => 'Feb 15, 2026', 'assignee' => 'JK Electric', 'cost' => 50, 'description' => 'Floor 3 stairwell light not working.', 'notes' => [['author' => 'JK Electric', 'av' => 'JK', 'date' => 'Feb 16', 'text' => 'Bulb and fitting replaced.']]],
        ];

        $maintenance = [];
        $maintenanceId = 1;

        foreach ($maintenanceSeed as $seed) {
            $unitId = ($seed['unit_ref'] && $seed['unit_ref'] !== 'Common') ? ($unitIdByNumber[$seed['unit_ref']] ?? null) : null;

            $maintenance[] = [
                'id' => $maintenanceId,
                'ticket_number' => $seed['ticket_number'],
                'title' => $seed['title'],
                'description' => $seed['description'],
                'unit_ref' => $seed['unit_ref'],
                'unit_id' => $unitId,
                'category' => $seed['category'],
                'priority' => $seed['priority'],
                'status' => $seed['status'],
                'assignee' => $seed['assignee'],
                'cost' => $seed['cost'],
                'reported_date' => self::textDateToDate($seed['reported']),
                'notes' => json_encode($seed['notes']),
                'unit' => $unitId ? self::unitLite($unitsById[$unitId]) : null,
            ];

            $maintenanceId++;
        }

        $invoices = [];
        $invoiceId = 1;
        $invoiceNumber = 1000;

        foreach ($payments as $payment) {
            if ($payment['month'] !== 'Mar 2026') {
                continue;
            }

            $invoiceNumber++;
            $leaseKey = $payment['tenant_id'] . '-' . $payment['unit_id'];
            $status = $payment['status'] === 'pending' ? 'unpaid' : $payment['status'];

            $invoices[] = [
                'id' => $invoiceId,
                'invoice_number' => 'INV-' . str_pad((string) $invoiceNumber, 4, '0', STR_PAD_LEFT),
                'type' => 'invoice',
                'lease_id' => $leaseIdByTenantUnit[$leaseKey] ?? null,
                'tenant_name' => $payment['tenant']['name'],
                'tenant_email' => $payment['tenant']['email'],
                'unit_ref' => $payment['unit']['unit_number'],
                'issued_date' => $payment['paid_date'] ?? '2026-03-01',
                'due_date' => '2026-03-05',
                'period' => 'Mar 2026',
                'status' => $status,
                'notes' => 'Please reference invoice number when making payment.\nBank: Equity Bank Kenya | A/C: 0123456789',
                'items' => [
                    [
                        'id' => 1,
                        'description' => 'Rental Payment - Unit ' . $payment['unit']['unit_number'],
                        'sub_description' => 'Mar 2026',
                        'quantity' => 1,
                        'unit_price' => $payment['amount'],
                        'total' => $payment['amount'],
                    ],
                ],
            ];

            $invoiceId++;
        }

        $invoiceNumber++;
        $invoices[] = [
            'id' => $invoiceId++,
            'invoice_number' => 'PF-' . str_pad((string) $invoiceNumber, 4, '0', STR_PAD_LEFT),
            'type' => 'proforma',
            'lease_id' => null,
            'tenant_name' => 'Brian Kimani',
            'tenant_email' => 'b.kimani@outlook.com',
            'unit_ref' => 'B-202',
            'issued_date' => '2026-03-19',
            'due_date' => '2026-04-01',
            'period' => 'Apr 2026 - Mar 2027',
            'status' => 'proforma',
            'notes' => 'This is a proforma invoice. Final tax invoice will be issued after lease activation.',
            'items' => [
                ['id' => 1, 'description' => 'Proforma - Annual Lease (B-202)', 'sub_description' => 'Apr 2026 - Mar 2027', 'quantity' => 1, 'unit_price' => 1250 * 12, 'total' => 1250 * 12],
                ['id' => 2, 'description' => 'Security Deposit', 'sub_description' => 'Refundable', 'quantity' => 1, 'unit_price' => 2500, 'total' => 2500],
            ],
        ];

        $invoiceNumber++;
        $invoices[] = [
            'id' => $invoiceId++,
            'invoice_number' => 'INV-' . str_pad((string) $invoiceNumber, 4, '0', STR_PAD_LEFT),
            'type' => 'invoice',
            'lease_id' => null,
            'tenant_name' => 'Rose Njeri',
            'tenant_email' => 'rose.njeri@gmail.com',
            'unit_ref' => 'D-404',
            'issued_date' => '2026-03-19',
            'due_date' => null,
            'period' => 'Apr 2026',
            'status' => 'draft',
            'notes' => 'Draft invoice pending tenant confirmation.',
            'items' => [
                ['id' => 1, 'description' => 'Reservation Deposit - Unit D-404', 'sub_description' => null, 'quantity' => 1, 'unit_price' => 1100, 'total' => 1100],
            ],
        ];

        usort($invoices, static fn($a, $b) => strcmp($b['invoice_number'], $a['invoice_number']));

        $occupiedUnits = array_filter($units, static fn($u) => in_array($u['status'], ['occupied', 'overdue'], true));
        $vacantUnits = array_filter($units, static fn($u) => $u['status'] === 'vacant');
        $overdueUnits = array_filter($units, static fn($u) => $u['status'] === 'overdue');

        $monthlyRevenue = array_reduce(
            $leases,
            static fn($sum, $lease) => in_array($lease['status'], ['active', 'expiring', 'overdue'], true) ? $sum + $lease['monthly_rent'] : $sum,
            0
        );

        $overdueBalance = array_reduce(
            $payments,
            static fn($sum, $payment) => $payment['status'] === 'overdue' ? $sum + $payment['amount'] : $sum,
            0
        );

        $occupancyByFloorMap = [];
        foreach ($units as $unit) {
            $floor = $unit['floor'];
            if (!isset($occupancyByFloorMap[$floor])) {
                $occupancyByFloorMap[$floor] = ['floor' => $floor, 'total' => 0, 'occupied' => 0];
            }
            $occupancyByFloorMap[$floor]['total']++;
            if (in_array($unit['status'], ['occupied', 'overdue'], true)) {
                $occupancyByFloorMap[$floor]['occupied']++;
            }
        }

        ksort($occupancyByFloorMap);

        $dashboardUnits = array_slice($units, 0, 7);

        $recentPayments = $payments;
        usort($recentPayments, static fn($a, $b) => $b['id'] <=> $a['id']);
        $recentPayments = array_slice($recentPayments, 0, 5);

        $maintenanceItems = array_values(array_filter($maintenance, static fn($t) => in_array($t['status'], ['open', 'in-progress'], true)));
        usort($maintenanceItems, static fn($a, $b) => strcmp($b['reported_date'], $a['reported_date']));
        $maintenanceItems = array_slice($maintenanceItems, 0, 4);

        $cache = [
            'tenants' => $tenants,
            'units' => $units,
            'leases' => $leases,
            'payments' => $payments,
            'documents' => $documents,
            'maintenance' => $maintenance,
            'invoices' => $invoices,
            'stats' => [
                'totalUnits' => count($units),
                'occupiedUnits' => count($occupiedUnits),
                'vacantUnits' => count($vacantUnits),
                'overdueUnits' => count($overdueUnits),
                'monthlyRevenue' => $monthlyRevenue,
                'overdueBalance' => $overdueBalance,
            ],
            'recentPayments' => $recentPayments,
            'maintenanceItems' => $maintenanceItems,
            'dashboardUnits' => $dashboardUnits,
            'occupancyByFloor' => array_values($occupancyByFloorMap),
        ];

        return $cache;
    }

    private static function tenantLite(array $tenant): array
    {
        return [
            'id' => $tenant['id'],
            'name' => $tenant['name'],
            'email' => $tenant['email'],
            'phone' => $tenant['phone'],
            'initials' => $tenant['initials'],
            'color' => $tenant['color'],
            'text_color' => $tenant['text_color'],
        ];
    }

    private static function unitLite(array $unit): array
    {
        return [
            'id' => $unit['id'],
            'unit_number' => $unit['unit_number'],
            'number' => $unit['number'],
            'floor' => $unit['floor'],
            'type' => $unit['type'],
            'rent' => $unit['rent'],
            'size_sqft' => $unit['size_sqft'],
            'size_sqm' => $unit['size_sqm'] ?? null,
            'rate_per_sqm' => $unit['rate_per_sqm'] ?? null,
            'currency' => $unit['currency'] ?? 'USD',
            'status' => $unit['status'],
        ];
    }

    private static function deriveLeaseStatus(string $unitStatus, string $endDate): string
    {
        if ($unitStatus === 'overdue') {
            return 'overdue';
        }

        $daysLeft = Carbon::parse('2026-03-19')->diffInDays(Carbon::parse($endDate), false);
        if ($daysLeft >= 0 && $daysLeft <= 60) {
            return 'expiring';
        }

        return 'active';
    }

    private static function monthYearToDate(?string $monthYear): ?string
    {
        if (!$monthYear) {
            return null;
        }

        return Carbon::createFromFormat('M Y', $monthYear)->startOfMonth()->toDateString();
    }

    private static function textDateToDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::createFromFormat('M j, Y', $date)->toDateString();
    }

    private static function monthsBetween(?string $startDate, ?string $endDate): int
    {
        if (!$startDate || !$endDate) {
            return 0;
        }

        return Carbon::parse($startDate)->diffInMonths(Carbon::parse($endDate));
    }
}
