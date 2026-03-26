<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\Outage;
use App\Models\SystemSetting;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ElectricityController extends Controller
{
    // ── Shared property scoping helper ──────────────────────────────
    private function scopeForUser(Builder $query, Request $request): void
    {
        $user = $request->user();
        if ($user?->role === 'manager') {
            empty($user->property_id)
                ? $query->whereRaw('1 = 0')
                : $query->where('property_id', $user->property_id);
        }
    }

    // ── Settings key prefix — scopes SystemSetting keys per property ─
    // Managers get "p{property_id}." prefix; superuser gets "p0." (global).
    private function settingPrefix(Request $request): string
    {
        $pid = $request->user()?->property_id;
        return 'p' . ($pid ?? 0) . '.';
    }

    public function index(Request $request)
    {
        $currentMonth = now()->format('Y-m');

        $readingsQ = MeterReading::with('unit.leases.tenant', 'invoice')->where('month', $currentMonth);
        $outagesQ  = Outage::query()->orderByDesc('outage_date');
        $fuelLogsQ = FuelLog::query()->orderByDesc('log_date');
        $unitsQ    = Unit::whereIn('status', ['occupied', 'overdue'])->orderBy('unit_number');

        $this->scopeForUser($readingsQ, $request);
        $this->scopeForUser($outagesQ,  $request);
        $this->scopeForUser($fuelLogsQ, $request);
        $this->scopeForUser($unitsQ,    $request);

        $readings = $readingsQ->get()->map(function ($r) {
            $activeLease = $r->unit?->leases->where('status', 'active')->first();
            $r->consumption    = $r->curr_reading - $r->prev_reading;
            $r->tenant_name    = $activeLease?->tenant?->name ?? null;
            $r->invoice_status = $r->invoice?->status ?? null;
            $r->invoice_number = $r->invoice?->invoice_number ?? null;
            return $r;
        });
        $outages  = $outagesQ->get();
        $fuelLogs = $fuelLogsQ->get();
        $units    = $unitsQ->get();

        $pfx = $this->settingPrefix($request);

        $gridSettings = [
            'energy_rate'  => (float) SystemSetting::get($pfx . 'elec.grid.energy_rate',  22.50),
            'fixed_charge' => (float) SystemSetting::get($pfx . 'elec.grid.fixed_charge', 1800),
            'fuel_levy'    => (float) SystemSetting::get($pfx . 'elec.grid.fuel_levy',    3.20),
            'erc_levy'     => (float) SystemSetting::get($pfx . 'elec.grid.erc_levy',     0.50),
            'reading_day'  => (int)   SystemSetting::get($pfx . 'elec.grid.reading_day',  1),
            'account_no'   =>         SystemSetting::get($pfx . 'elec.grid.account_no',   ''),
            'bill_amount'  => (float) SystemSetting::get($pfx . 'elec.grid.bill_amount',  0),
            'bill_kwh'     => (float) SystemSetting::get($pfx . 'elec.grid.bill_kwh',     0),
            'common_kwh'   => (float) SystemSetting::get($pfx . 'elec.grid.common_kwh',   0),
            'on'           => true,
        ];

        $genSettings = [
            'diesel_price' => (float) SystemSetting::get($pfx . 'elec.gen.diesel_price', 185),
            'l_per_hr'     => (float) SystemSetting::get($pfx . 'elec.gen.l_per_hr',     6),
            'output_kw'    => (float) SystemSetting::get($pfx . 'elec.gen.output_kw',    36),
            'maint_levy'   => (float) SystemSetting::get($pfx . 'elec.gen.maint_levy',   50),
            'tank_size'    => (float) SystemSetting::get($pfx . 'elec.gen.tank_size',    200),
            'status'       => 'standby',
            'fuel_pct'     => 68,
        ];

        // ── Monthly Runtime History (last 6 months, from real outage + fuel data) ──
        $runtimeOutageQ = Outage::where('generator_activated', true)
            ->selectRaw("TO_CHAR(outage_date, 'YYYY-MM') as month,
                         COUNT(*) as outage_count,
                         SUM(CASE WHEN end_time IS NOT NULL
                             THEN EXTRACT(EPOCH FROM (end_time::time - start_time::time))
                             ELSE 0 END) as runtime_seconds,
                         SUM(COALESCE(fuel_used, 0)) as fuel_used")
            ->groupByRaw("TO_CHAR(outage_date, 'YYYY-MM')")
            ->orderByDesc('month')
            ->limit(6);
        $this->scopeForUser($runtimeOutageQ, $request);

        $fuelCostQ = FuelLog::selectRaw("TO_CHAR(log_date, 'YYYY-MM') as month, SUM(total_cost) as fuel_cost")
            ->groupByRaw("TO_CHAR(log_date, 'YYYY-MM')");
        $this->scopeForUser($fuelCostQ, $request);

        $genKwhQ = MeterReading::selectRaw("month, SUM(COALESCE(gen_kwh, 0)) as gen_kwh")
            ->groupBy('month');
        $this->scopeForUser($genKwhQ, $request);

        $fuelCostByMonth  = $fuelCostQ->get()->keyBy('month');
        $genKwhByMonth    = $genKwhQ->get()->keyBy('month');

        $runtimeHistory = $runtimeOutageQ->get()->map(function ($row) use ($fuelCostByMonth, $genKwhByMonth) {
            return [
                'month'        => $row->month,
                'outage_count' => (int) $row->outage_count,
                'runtime_hrs'  => round($row->runtime_seconds / 3600, 1),
                'fuel_used'    => round((float) $row->fuel_used, 1),
                'fuel_cost'    => round((float) ($fuelCostByMonth[$row->month]->fuel_cost ?? 0), 2),
                'gen_kwh'      => round((float) ($genKwhByMonth[$row->month]->gen_kwh ?? 0), 1),
            ];
        })->values();

        return Inertia::render('Electricity/Index', compact(
            'readings', 'outages', 'fuelLogs', 'units',
            'gridSettings', 'genSettings', 'currentMonth', 'runtimeHistory'
        ));
    }

    public function storeReading(Request $request)
    {
        $data = $request->validate([
            'unit_id'      => 'required|exists:units,id',
            'month'        => 'required|string',
            'prev_reading' => 'required|numeric|min:0',
            'curr_reading' => 'required|numeric|gte:prev_reading',
            'gen_kwh'      => 'nullable|numeric|min:0',
            'reading_date' => 'required|date',
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $user = $request->user();

        if ($user?->role === 'manager') {
            abort_if(empty($user->property_id), 422, 'You are not assigned to any property.');
            abort_if(
                (int) $unit->property_id !== (int) $user->property_id,
                403,
                'This unit does not belong to your property.'
            );
        }

        MeterReading::updateOrCreate(
            ['unit_id' => $data['unit_id'], 'month' => $data['month']],
            array_merge($data, [
                'property_id' => $unit->property_id,
                'recorded_by' => $user->name,
            ])
        );

        return back()->with('success', 'Reading saved.');
    }

    public function storeOutage(Request $request)
    {
        $data = $request->validate([
            'outage_date'         => 'required|date',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'nullable|date_format:H:i',
            'type'                => 'required|in:major,minor,planned',
            'floors_affected'     => 'nullable|string|max:255',
            'generator_activated' => 'boolean',
            'fuel_used'           => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
        ]);

        $user = $request->user();
        if ($user?->role === 'manager') {
            abort_if(empty($user->property_id), 422, 'You are not assigned to any property.');
        }

        Outage::create(array_merge($data, [
            'property_id' => $user->property_id ?? null,
        ]));

        return back()->with('success', 'Outage logged.');
    }

    public function storeFuelLog(Request $request)
    {
        $data = $request->validate([
            'log_date'        => 'required|date',
            'litres'          => 'required|numeric|min:0.1',
            'price_per_litre' => 'required|numeric|min:0',
            'supplier'        => 'nullable|string|max:255',
            'level_after'     => 'required|integer|min:0|max:100',
        ]);

        $user = $request->user();
        if ($user?->role === 'manager') {
            abort_if(empty($user->property_id), 422, 'You are not assigned to any property.');
        }

        FuelLog::create(array_merge($data, [
            'property_id' => $user->property_id ?? null,
            'total_cost'  => $data['litres'] * $data['price_per_litre'],
            'recorded_by' => $user->name,
        ]));

        return back()->with('success', 'Fuel log saved.');
    }

    public function generateBills(Request $request)
    {
        $month = now()->format('Y-m');

        $readingsQ = MeterReading::with('unit.leases.tenant')
            ->where('month', $month)
            ->whereNull('invoice_id');
        $this->scopeForUser($readingsQ, $request);

        $tariff = (float) SystemSetting::get($this->settingPrefix($request) . 'elec.grid.energy_rate', 22.50);

        // Generator levy per unit = total fuel cost ÷ number of readings this month
        $fuelQ = FuelLog::query();
        $this->scopeForUser($fuelQ, $request);
        $fuelTotal    = $fuelQ->selectRaw('SUM(litres * price_per_litre) as total')->value('total') ?? 0;
        $readingCount = MeterReading::where('month', $month)->count();
        $genLevy      = $readingCount > 0 ? round((float) $fuelTotal / $readingCount, 2) : 0;

        DB::transaction(function () use ($readingsQ, $tariff, $genLevy, $month) {
            foreach ($readingsQ->get() as $r) {
                $activeLease = $r->unit?->leases->where('status', 'active')->first();
                if (! $activeLease) {
                    continue;
                }

                $consumption = $r->curr_reading - $r->prev_reading;
                $gridBill    = round($consumption * $tariff, 2);
                $total       = $gridBill + $genLevy;

                $count   = Invoice::count() + 1;
                $invoice = Invoice::create([
                    'invoice_number' => 'ELEC-' . str_pad($count, 4, '0', STR_PAD_LEFT),
                    'type'           => 'invoice',
                    'property_id'    => $r->property_id,
                    'lease_id'       => $activeLease->id,
                    'tenant_name'    => $activeLease->tenant?->name,
                    'tenant_email'   => $activeLease->tenant?->email ?? null,
                    'unit_ref'       => $r->unit?->unit_number,
                    'issued_date'    => now()->toDateString(),
                    'due_date'       => now()->addDays(14)->toDateString(),
                    'period'         => $month,
                    'status'         => 'draft',
                    'currency'       => 'TZS',
                    'exchange_rate'  => 1,
                    'total_in_base'  => $total,
                    'notes'          => 'Electricity bill for ' . $month,
                ]);

                InvoiceItem::create([
                    'invoice_id'      => $invoice->id,
                    'description'     => 'Electricity — Grid Charge',
                    'sub_description' => $consumption . ' kWh × TZS ' . $tariff,
                    'quantity'        => $consumption,
                    'unit_price'      => $tariff,
                    'total'           => $gridBill,
                ]);

                if ($genLevy > 0) {
                    InvoiceItem::create([
                        'invoice_id'      => $invoice->id,
                        'description'     => 'Electricity — Generator Levy',
                        'sub_description' => 'Shared generator cost for ' . $month,
                        'quantity'        => 1,
                        'unit_price'      => $genLevy,
                        'total'           => $genLevy,
                    ]);
                }

                $r->update(['invoice_id' => $invoice->id]);
            }
        });

        return back()->with('success', 'Bills generated successfully.');
    }

    public function issueInvoices(Request $request)
    {
        $month = now()->format('Y-m');

        $readingsQ = MeterReading::with('invoice')
            ->where('month', $month)
            ->whereNotNull('invoice_id');
        $this->scopeForUser($readingsQ, $request);

        $invoiceIds = $readingsQ->get()
            ->filter(fn ($r) => $r->invoice?->status === 'draft')
            ->pluck('invoice_id');

        if ($invoiceIds->isNotEmpty()) {
            Invoice::whereIn('id', $invoiceIds)->update(['status' => 'unpaid']);
        }

        return back()->with('success', 'Invoices issued successfully.');
    }

    public function updateGridSettings(Request $request)
    {
        $data = $request->validate([
            'energy_rate'  => 'required|numeric|min:0',
            'fixed_charge' => 'required|numeric|min:0',
            'fuel_levy'    => 'required|numeric|min:0',
            'erc_levy'     => 'required|numeric|min:0',
            'reading_day'  => 'required|integer|min:1|max:31',
            'account_no'   => 'nullable|string|max:100',
            'bill_amount'  => 'required|numeric|min:0',
            'bill_kwh'     => 'required|numeric|min:0',
            'common_kwh'   => 'required|numeric|min:0',
        ]);

        $pfx = $this->settingPrefix($request);
        foreach ($data as $key => $value) {
            SystemSetting::set($pfx . 'elec.grid.' . $key, $value);
        }

        return back()->with('success', 'Grid settings saved.');
    }

    public function updateGenSettings(Request $request)
    {
        $data = $request->validate([
            'diesel_price' => 'required|numeric|min:0',
            'l_per_hr'     => 'required|numeric|min:0',
            'output_kw'    => 'required|numeric|min:0',
            'maint_levy'   => 'required|numeric|min:0',
            'tank_size'    => 'required|numeric|min:0',
        ]);

        $pfx = $this->settingPrefix($request);
        foreach ($data as $key => $value) {
            SystemSetting::set($pfx . 'elec.gen.' . $key, $value);
        }

        return back()->with('success', 'Generator settings saved.');
    }
}
