<?php

namespace App\Http\Controllers;

use App\Mail\ProformaInvoiceMail;
use App\Models\ElectricitySale;
use App\Models\FuelLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Outage;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Services\InvoiceNumberService;
use App\Support\AccountingAutoPoster;
use App\Support\FloorConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ElectricityController extends Controller
{
    private const DIRECT_GENERATOR_RATE = 1400.00;
    private const DIRECT_GENERATOR_VAT_PERCENT = 18.00;
    private const SUBMETER_UNIT_PRICE = 500.00;
    private const SUBMETER_VAT_PERCENT = 18.00;

    public function __construct(private InvoiceNumberService $invoiceNumberService) {}

    private function scopeForUser(Builder $query, Request $request): void
    {
        if (! $this->shouldScopeToProperty($request)) {
            return;
        }

        $propertyId = $this->effectivePropertyId($request);

        if ($propertyId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('property_id', $propertyId);
    }

    private function settingPrefix(Request $request): string
    {
        $pid = $this->shouldScopeToProperty($request)
            ? $this->effectivePropertyId($request)
            : $request->user()?->property_id;

        return 'p' . ($pid ?? 0) . '.';
    }

    private function generatorSettings(Request $request): array
    {
        $prefix = $this->settingPrefix($request);

        return [
            'generator_rate_per_kwh' => (float) SystemSetting::get($prefix . 'elec.direct.generator_rate_per_kwh', self::DIRECT_GENERATOR_RATE),
            'generator_vat_percent' => (float) SystemSetting::get($prefix . 'elec.direct.generator_vat_percent', self::DIRECT_GENERATOR_VAT_PERCENT),
            'diesel_price' => (float) SystemSetting::get($prefix . 'elec.gen.diesel_price', 185),
            'l_per_hr' => (float) SystemSetting::get($prefix . 'elec.gen.l_per_hr', 6),
            'output_kw' => (float) SystemSetting::get($prefix . 'elec.gen.output_kw', 36),
            'maint_levy' => (float) SystemSetting::get($prefix . 'elec.gen.maint_levy', 50),
            'tank_size' => (float) SystemSetting::get($prefix . 'elec.gen.tank_size', 200),
        ];
    }

    private function submeterSettings(Request $request): array
    {
        $prefix = $this->settingPrefix($request);

        return [
            'unit_price'  => (float) SystemSetting::get($prefix . 'elec.submeter.unit_price', self::SUBMETER_UNIT_PRICE),
            'vat_percent' => (float) SystemSetting::get($prefix . 'elec.submeter.vat_percent', self::SUBMETER_VAT_PERCENT),
        ];
    }

    private function hasElectricitySalesTable(): bool
    {
        return Schema::hasTable('electricity_sales');
    }

    public function index(Request $request)
    {
        $currentMonth = now()->format('Y-m');
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $generatorSettings = $this->generatorSettings($request);
        $submeterSettings = $this->submeterSettings($request);

        $unitsQ = Unit::with([
            'leases' => fn ($query) => $query->where('status', 'active')->with('tenant'),
        ])
            ->whereIn('status', ['occupied', 'overdue'])
            ->orderBy('unit_number');
        $this->scopeForUser($unitsQ, $request);
        $units = $unitsQ->get();

        $directReadingsQ = MeterReading::with([
            'unit.leases' => fn ($query) => $query->where('status', 'active')->with('tenant'),
            'invoice.items',
        ])
            ->whereBetween('reading_date', [$monthStart, $monthEnd])
            ->whereHas('unit', fn ($query) => $query->where('electricity_type', 'direct'))
            ->orderBy('reading_date')
            ->orderBy('unit_id');
        $this->scopeForUser($directReadingsQ, $request);

        $submeterModuleReady = $this->hasElectricitySalesTable();
        $salesQ = null;
        if ($submeterModuleReady) {
            $salesQ = ElectricitySale::with([
                'unit.leases' => fn ($query) => $query->where('status', 'active')->with('tenant'),
                'invoice.items',
            ])
                ->whereHas('unit', fn ($query) => $query->where('electricity_type', 'submeter'))
                ->orderByDesc('sale_date')
                ->orderByDesc('id');
            $this->scopeForUser($salesQ, $request);
        }

        $outagesQ = Outage::query()->orderByDesc('outage_date')->orderByDesc('id');
        $fuelLogsQ = FuelLog::query()->orderByDesc('log_date')->orderByDesc('id');
        $this->scopeForUser($outagesQ, $request);
        $this->scopeForUser($fuelLogsQ, $request);

        $directReadings = $directReadingsQ->get()->map(fn (MeterReading $reading) => $this->transformDirectReading($reading, $generatorSettings))->values();
        $submeterSales = $submeterModuleReady
            ? $salesQ->limit(50)->get()->map(fn (ElectricitySale $sale) => $this->transformSubmeterSale($sale))->values()
            : collect();

        $directUnits = $units
            ->where('electricity_type', 'direct')
            ->map(fn (Unit $unit) => $this->transformUnit($unit))
            ->values();
        $submeterUnits = $units
            ->where('electricity_type', 'submeter')
            ->map(fn (Unit $unit) => $this->transformUnit($unit))
            ->values();

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

        $genKwhQ = MeterReading::whereHas('unit', fn ($query) => $query->where('electricity_type', 'direct'))
            ->selectRaw('month, SUM(COALESCE(gen_kwh, 0)) as gen_kwh')
            ->groupBy('month');
        $this->scopeForUser($genKwhQ, $request);

        $fuelCostByMonth = $fuelCostQ->get()->keyBy('month');
        $genKwhByMonth = $genKwhQ->get()->keyBy('month');

        $runtimeHistory = $runtimeOutageQ->get()->map(function ($row) use ($fuelCostByMonth, $genKwhByMonth) {
            return [
                'month' => $row->month,
                'outage_count' => (int) $row->outage_count,
                'runtime_hrs' => round($row->runtime_seconds / 3600, 1),
                'fuel_used' => round((float) $row->fuel_used, 1),
                'fuel_cost' => round((float) ($fuelCostByMonth[$row->month]->fuel_cost ?? 0), 2),
                'gen_kwh' => round((float) ($genKwhByMonth[$row->month]->gen_kwh ?? 0), 2),
            ];
        })->values();

        $floorOptions = $this->floorOptions($request);

        return Inertia::render('Electricity/Index', [
            'currentMonth' => $currentMonth,
            'directReadings' => $directReadings,
            'submeterSales' => $submeterSales,
            'directUnits' => $directUnits,
            'submeterUnits' => $submeterUnits,
            'submeterModuleReady' => $submeterModuleReady,
            'outages' => $outagesQ->get(),
            'fuelLogs' => $fuelLogsQ->get(),
            'generatorSettings' => $generatorSettings,
            'submeterSettings' => $submeterSettings,
            'runtimeHistory' => $runtimeHistory,
            'floorOptions' => $floorOptions,
        ]);
    }

    public function storeReading(Request $request)
    {
        $data = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'prev_reading' => 'required|numeric|min:0',
            'curr_reading' => 'required|numeric|gte:prev_reading',
            'reading_date' => 'required|date',
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $this->assertUnitBelongsToRequestProperty($unit, $request);
        if ($unit->electricity_type !== 'direct') {
            throw ValidationException::withMessages([
                'direct_reading' => 'Only direct-meter units can use the reading workflow.',
            ]);
        }

        $activeLease = $this->activeLeaseForUnit($unit);
        if (! $activeLease) {
            throw ValidationException::withMessages([
                'direct_reading' => 'This unit has no active lease.',
            ]);
        }

        $readingDate = Carbon::parse($data['reading_date'])->toDateString();
        $readingMonth = Carbon::parse($readingDate)->format('Y-m');

        $existing = MeterReading::with('invoice.items')
            ->where('unit_id', $unit->id)
            ->whereDate('reading_date', $readingDate)
            ->first();

        if ($existing?->invoice && $existing->invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'direct_reading' => 'This reading already has an issued invoice and cannot be edited.',
            ]);
        }

        $user = $request->user();
        $consumption = $this->readingConsumption($data['prev_reading'], $data['curr_reading']);

        $reading = DB::transaction(function () use ($data, $unit, $user, $activeLease, $request, $consumption, $readingDate, $readingMonth) {
            $reading = MeterReading::updateOrCreate(
                ['unit_id' => $data['unit_id'], 'reading_date' => $readingDate],
                array_merge($data, [
                    'property_id' => $unit->property_id,
                    'month' => $readingMonth,
                    'reading_date' => $readingDate,
                    'recorded_by' => $user?->name,
                    'gen_kwh' => $consumption,
                ])
            );

            $reading->load('invoice.items');

            $this->syncDirectInvoice($reading, $unit, $activeLease, $this->generatorSettings($request));

            return $reading->fresh('invoice.items');
        });

        $message = (float) ($reading->gen_kwh ?? 0) > 0
            ? 'Reading saved and generator draft invoice prepared.'
            : 'Reading saved. No generator invoice created because generator usage is zero.';

        return back()->with('success', $message);
    }

    public function storeSale(Request $request)
    {
        if (! $this->hasElectricitySalesTable()) {
            return back()->with('error', 'Submeter sales table is missing. Run php artisan migrate to enable this workflow.');
        }

        $settings = $this->submeterSettings($request);

        $data = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'sale_date' => 'required|date',
            'amount_paid' => 'nullable|numeric|min:0.01',
            'units_sold' => 'nullable|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $this->assertUnitBelongsToRequestProperty($unit, $request);
        if ($unit->electricity_type !== 'submeter') {
            throw ValidationException::withMessages([
                'submeter_sale' => 'Only submeter units can use the sale workflow.',
            ]);
        }

        $activeLease = $this->activeLeaseForUnit($unit);
        if (! $activeLease) {
            throw ValidationException::withMessages([
                'submeter_sale' => 'This unit has no active lease.',
            ]);
        }

        $user = $request->user();
        $unitPrice = (float) ($data['unit_price'] ?? $settings['unit_price']);
        if ($unitPrice <= 0) {
            throw ValidationException::withMessages([
                'submeter_sale' => 'Unit price must be greater than zero.',
            ]);
        }

        $amountPaid = isset($data['amount_paid']) ? round((float) $data['amount_paid'], 2) : null;
        $manualUnits = isset($data['units_sold']) ? round((float) $data['units_sold'], 2) : null;

        if ($amountPaid === null && $manualUnits === null) {
            throw ValidationException::withMessages([
                'submeter_sale' => 'Amount paid is required.',
            ]);
        }

        $amount = $amountPaid ?? round($manualUnits * $unitPrice, 2);
        $unitsSold = round($amount / $unitPrice, 2);

        DB::transaction(function () use ($data, $unit, $user, $activeLease, $unitPrice, $unitsSold, $amount, $settings) {
            $sale = ElectricitySale::create([
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'sale_date' => $data['sale_date'],
                'units_sold' => $unitsSold,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user?->name,
            ]);

            $invoice = $this->createSubmeterInvoice($sale, $unit, $activeLease, $settings);
            $sale->update(['invoice_id' => $invoice->id]);
        });

        return back()->with('success', 'Submeter sale saved and draft invoice prepared.');
    }

    public function storeOutage(Request $request)
    {
        $data = $request->validate([
            'outage_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'type' => 'required|in:major,minor,planned',
            'floors_affected' => 'nullable|string|max:255',
            'generator_activated' => 'boolean',
            'fuel_used' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $outagePropertyId = $this->shouldScopeToProperty($request)
            ? $this->effectivePropertyId($request)
            : ($user->property_id ?? null);

        Outage::create(array_merge($data, [
            'property_id' => $outagePropertyId,
        ]));

        return back()->with('success', 'Outage logged.');
    }

    public function storeFuelLog(Request $request, AccountingAutoPoster $poster)
    {
        $data = $request->validate([
            'log_date' => 'required|date',
            'litres' => 'required|numeric|min:0.1',
            'price_per_litre' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'level_after' => 'required|integer|min:0|max:100',
        ]);

        $user = $request->user();
        $propertyId = $this->shouldScopeToProperty($request)
            ? $this->effectivePropertyId($request)
            : ($user->property_id ?? null);

        if ($user?->role === 'manager') {
            abort_if(empty($propertyId), 422, 'You are not assigned to any property.');
        }

        $totalCost = $data['litres'] * $data['price_per_litre'];

        $log = FuelLog::create(array_merge($data, [
            'property_id' => $propertyId,
            'total_cost' => $totalCost,
            'recorded_by' => $user->name,
        ]));

        if ($totalCost > 0) {
            $poster->post(
                propertyId: $propertyId,
                entryDate: $data['log_date'],
                description: 'Fuel/generator purchase' . ($data['supplier'] ? ' — ' . $data['supplier'] : ''),
                reference: 'FUEL-' . $log->id,
                lines: [
                    ['account_code' => '5100', 'account_name' => 'Utilities Expense', 'debit' => $totalCost, 'credit' => 0],
                    ['account_code' => '1000', 'account_name' => 'Cash at Bank', 'debit' => 0, 'credit' => $totalCost],
                ],
                sourceType: 'fuel_log',
                sourceId: $log->id,
            );
        }

        return back()->with('success', 'Fuel log saved.');
    }

    public function generateBills(): \Illuminate\Http\RedirectResponse
    {
        return back()->with('success', 'Generator invoices are now created when direct readings are saved, and submeter invoices are created when sales are recorded.');
    }

    public function issueInvoices(Request $request)
    {
        $data = $request->validate([
            'kind'       => 'nullable|in:direct,submeter,all',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
        ]);

        // Single-invoice issue (per-row button)
        if (!empty($data['invoice_id'])) {
            $invoice = Invoice::with('items')->findOrFail($data['invoice_id']);

            // Generator proformas: already ready to send — just email and confirm
            if ($invoice->type === 'proforma' || $invoice->status === 'proforma') {
                $this->sendProformaEmail($invoice);
                return back()->with('success', 'Proforma invoice ' . $invoice->invoice_number . ' sent to tenant.');
            }

            if ($invoice->status !== 'draft') {
                return back()->with('warning', 'Invoice is already issued.');
            }

            $invoice->update(['status' => 'unpaid']);
            return back()->with('success', 'Invoice ' . $invoice->invoice_number . ' issued successfully.');
        }

        // Batch issue by kind
        $kind = $data['kind'] ?? 'all';
        $directEmailCount  = 0;
        $submeterIssueIds  = collect();

        // Generator (direct): proformas are already issued — send email to tenants
        if (in_array($kind, ['direct', 'all'], true)) {
            $directQ = MeterReading::with(['invoice.items'])
                ->whereNotNull('invoice_id')
                ->whereHas('unit', fn ($query) => $query->where('electricity_type', 'direct'));
            $this->scopeForUser($directQ, $request);

            $directQ->get()
                ->filter(fn (MeterReading $r) => $r->invoice?->status === 'proforma')
                ->each(function (MeterReading $r) use (&$directEmailCount) {
                    $this->sendProformaEmail($r->invoice);
                    $directEmailCount++;
                });
        }

        // Submeter: draft → unpaid (tax invoice, triggers GL via observer)
        if (in_array($kind, ['submeter', 'all'], true) && $this->hasElectricitySalesTable()) {
            $salesQ = ElectricitySale::with('invoice')
                ->whereNotNull('invoice_id')
                ->whereHas('unit', fn ($query) => $query->where('electricity_type', 'submeter'));
            $this->scopeForUser($salesQ, $request);

            $submeterIssueIds = $salesQ->get()
                ->filter(fn (ElectricitySale $sale) => $sale->invoice?->status === 'draft')
                ->pluck('invoice_id')
                ->filter()
                ->unique()
                ->values();

            if ($submeterIssueIds->isNotEmpty()) {
                Invoice::whereIn('id', $submeterIssueIds)->get()->each(function (Invoice $inv) {
                    $inv->update(['status' => 'unpaid']);
                });
            }
        }

        $total = $directEmailCount + $submeterIssueIds->count();

        if ($total === 0) {
            return back()->with('success', 'No electricity invoices were available to process.');
        }

        $parts = [];
        if ($directEmailCount > 0) {
            $parts[] = "{$directEmailCount} generator proforma(s) emailed to tenants";
        }
        if ($submeterIssueIds->isNotEmpty()) {
            $parts[] = $submeterIssueIds->count() . ' submeter invoice(s) issued';
        }

        return back()->with('success', implode(', ', $parts) . '.');
    }

    public function updateSubmeterSettings(Request $request)
    {
        if (! $this->hasElectricitySalesTable()) {
            return back()->with('error', 'Submeter sales table is missing. Run php artisan migrate to enable this workflow.');
        }

        $data = $request->validate([
            'unit_price'  => 'required|numeric|min:0',
            'vat_percent' => 'required|numeric|min:0|max:100',
        ]);

        $prefix = $this->settingPrefix($request);
        SystemSetting::set($prefix . 'elec.submeter.unit_price', $data['unit_price']);
        SystemSetting::set($prefix . 'elec.submeter.vat_percent', $data['vat_percent']);

        return back()->with('success', 'Submeter pricing saved.');
    }

    public function updateGenSettings(Request $request)
    {
        $data = $request->validate([
            'generator_rate_per_kwh' => 'required|numeric|min:0',
            'generator_vat_percent' => 'required|numeric|min:0',
            'diesel_price' => 'required|numeric|min:0',
            'l_per_hr' => 'required|numeric|min:0',
            'output_kw' => 'required|numeric|min:0',
            'maint_levy' => 'required|numeric|min:0',
            'tank_size' => 'required|numeric|min:0',
        ]);

        $prefix = $this->settingPrefix($request);
        SystemSetting::set($prefix . 'elec.direct.generator_rate_per_kwh', $data['generator_rate_per_kwh']);
        SystemSetting::set($prefix . 'elec.direct.generator_vat_percent', $data['generator_vat_percent']);
        SystemSetting::set($prefix . 'elec.gen.diesel_price', $data['diesel_price']);
        SystemSetting::set($prefix . 'elec.gen.l_per_hr', $data['l_per_hr']);
        SystemSetting::set($prefix . 'elec.gen.output_kw', $data['output_kw']);
        SystemSetting::set($prefix . 'elec.gen.maint_levy', $data['maint_levy']);
        SystemSetting::set($prefix . 'elec.gen.tank_size', $data['tank_size']);

        return back()->with('success', 'Generator settings saved.');
    }

    private function transformUnit(Unit $unit): array
    {
        $lease = $unit->leases->first();

        return [
            'id' => $unit->id,
            'unit_number' => $unit->unit_number,
            'floor' => $unit->floor,
            'status' => $unit->status,
            'electricity_type' => $unit->electricity_type,
            'tenant_name' => $lease?->tenant?->name,
            'tenant_email' => $lease?->tenant?->email,
            'lease_id' => $lease?->id,
        ];
    }

    private function floorOptions(Request $request): array
    {
        if ($this->shouldScopeToProperty($request)) {
            $propertyId = $this->effectivePropertyId($request);
            if ($propertyId) {
                $property = Property::find($propertyId);
                if ($property) {
                    return array_map(fn($floor) => $floor['label'], $property->floorList());
                }
            }
        }

        return array_map(fn($floor) => $floor['label'], FloorConfig::floors(FloorConfig::parse(null)));
    }

    private function transformDirectReading(MeterReading $reading, array $generatorSettings): array
    {
        $lease = $reading->unit?->leases->first();
        $amounts = $this->generatorAmounts((float) $reading->gen_kwh, $generatorSettings);
        $invoiceItems = $reading->invoice?->items ?? collect();
        $consumption = $this->readingConsumption($reading->prev_reading, $reading->curr_reading);

        if ($reading->invoice) {
            $baseItem = $invoiceItems->firstWhere('description', 'Electricity — Generator Charge');
            $vatItem = $invoiceItems->firstWhere('description', 'Electricity — Generator VAT');

            $amounts = [
                'base_amount' => $baseItem ? (float) $baseItem->total : $amounts['base_amount'],
                'vat_amount' => $vatItem ? (float) $vatItem->total : $amounts['vat_amount'],
                'total_amount' => (float) $invoiceItems->sum('total') ?: $amounts['total_amount'],
            ];
        }

        return [
            'id' => $reading->id,
            'unit_id' => $reading->unit_id,
            'unit_number' => $reading->unit?->unit_number,
            'tenant_name' => $lease?->tenant?->name,
            'month' => $reading->month,
            'reading_date' => Carbon::parse($reading->reading_date)->toDateString(),
            'prev_reading' => (float) $reading->prev_reading,
            'curr_reading' => (float) $reading->curr_reading,
            'consumption' => $consumption,
            'gen_kwh' => (float) $reading->gen_kwh,
            'generator_base_amount' => round($amounts['base_amount'], 2),
            'generator_vat_amount' => round($amounts['vat_amount'], 2),
            'generator_total_amount' => round($amounts['total_amount'], 2),
            'invoice_status' => $reading->invoice?->status,
            'invoice_number' => $reading->invoice?->invoice_number,
        ];
    }

    private function readingConsumption(float|int|string|null $prevReading, float|int|string|null $currReading): float
    {
        return round((float) $currReading - (float) $prevReading, 2);
    }

    private function transformSubmeterSale(ElectricitySale $sale): array
    {
        $lease = $sale->unit?->leases->first();

        return [
            'id' => $sale->id,
            'unit_id' => $sale->unit_id,
            'unit_number' => $sale->unit?->unit_number,
            'tenant_name' => $lease?->tenant?->name,
            'sale_date' => optional($sale->sale_date)->toDateString(),
            'units_sold' => (float) $sale->units_sold,
            'unit_price' => (float) $sale->unit_price,
            'amount' => (float) $sale->amount,
            'notes' => $sale->notes,
            'recorded_by' => $sale->recorded_by,
            'invoice_status' => $sale->invoice?->status,
            'invoice_number' => $sale->invoice?->invoice_number,
        ];
    }

    private function activeLeaseForUnit(Unit $unit): ?Lease
    {
        return $unit->leases()
            ->where('status', 'active')
            ->with('tenant')
            ->latest('id')
            ->first();
    }

    private function assertUnitBelongsToRequestProperty(Unit $unit, Request $request): void
    {
        if (! $this->shouldScopeToProperty($request)) {
            return;
        }

        $effectiveId = $this->effectivePropertyId($request);
        abort_if($effectiveId === null, 422, 'No property context available.');
        abort_if((int) $unit->property_id !== $effectiveId, 403, 'This unit does not belong to your property.');
    }

    private function generatorAmounts(float $genKwh, array $settings): array
    {
        $baseAmount = round($genKwh * (float) $settings['generator_rate_per_kwh'], 2);
        $vatAmount = round($baseAmount * ((float) $settings['generator_vat_percent'] / 100), 2);

        return [
            'base_amount' => $baseAmount,
            'vat_amount' => $vatAmount,
            'total_amount' => round($baseAmount + $vatAmount, 2),
        ];
    }

    /**
     * Back-calculate net and VAT from a VAT-inclusive gross amount.
     * Submeter price (e.g. 500 TZS/unit) already contains VAT.
     * net = gross / (1 + rate/100),  vat = gross − net
     */
    private function submeterAmounts(float $grossAmount, array $settings): array
    {
        $vatRate   = (float) $settings['vat_percent'];
        $netAmount = round($grossAmount / (1 + $vatRate / 100), 2);
        $vatAmount = round($grossAmount - $netAmount, 2);

        return [
            'net_amount'   => $netAmount,
            'vat_amount'   => $vatAmount,
            'gross_amount' => $grossAmount,
        ];
    }

    private function syncDirectInvoice(MeterReading $reading, Unit $unit, Lease $lease, array $generatorSettings): void
    {
        $invoice = $reading->invoice;
        $genKwh = (float) ($reading->gen_kwh ?? 0);

        if ($genKwh <= 0) {
            if ($invoice) {
                if (!in_array($invoice->status, ['draft', 'proforma'])) {
                    throw ValidationException::withMessages([
                        'direct_reading' => 'Issued generator invoices cannot be removed from a reading.',
                    ]);
                }
                $invoice->items()->delete();
                $invoice->delete();
            }

            $reading->update(['invoice_id' => null]);

            return;
        }

        $amounts = $this->generatorAmounts($genKwh, $generatorSettings);
        $tenant = $lease->tenant;
        $invoiceData = [
            'type'         => 'proforma',
            'property_id'  => $reading->property_id,
            'lease_id'     => $lease->id,
            'tenant_name'  => $tenant?->name ?? 'Unknown Tenant',
            'tenant_email' => $tenant?->email,
            'unit_ref'     => $unit->unit_number,
            'issued_date'  => Carbon::parse($reading->reading_date)->toDateString(),
            'due_date'     => Carbon::parse($reading->reading_date)->addDays(14)->toDateString(),
            'period'       => Carbon::parse($reading->reading_date)->toDateString(),
            'status'       => 'proforma',
            'currency'     => 'TZS',
            'exchange_rate' => 1,
            'total_in_base' => $amounts['total_amount'],
            'notes'        => 'Electricity module: generator bill for ' . Carbon::parse($reading->reading_date)->toDateString(),
        ];

        if (! $invoice) {
            $invoice = Invoice::create(array_merge($invoiceData, [
                'invoice_number' => $this->invoiceNumberService->generateNumber('PF'),
            ]));
        } else {
            if (!in_array($invoice->status, ['draft', 'proforma'])) {
                throw ValidationException::withMessages([
                    'direct_reading' => 'This reading already has an issued invoice and cannot be edited.',
                ]);
            }
            $invoice->update($invoiceData);
            $invoice->items()->delete();
        }

        InvoiceItem::create([
            'invoice_id'      => $invoice->id,
            'description'     => 'Electricity — Generator Charge',
            'item_type'       => 'electricity_charge',
            'sub_description' => number_format($genKwh, 2, '.', '') . ' kWh × TZS ' . number_format((float) $generatorSettings['generator_rate_per_kwh'], 2, '.', ''),
            'quantity'        => 1,
            'unit_price'      => $amounts['base_amount'],
            'total'           => $amounts['base_amount'],
        ]);

        InvoiceItem::create([
            'invoice_id'      => $invoice->id,
            'description'     => 'Electricity — Generator VAT',
            'item_type'       => 'electricity_vat',
            'sub_description' => number_format((float) $generatorSettings['generator_vat_percent'], 2, '.', '') . '% VAT on generator charge',
            'quantity'        => 1,
            'unit_price'      => $amounts['vat_amount'],
            'total'           => $amounts['vat_amount'],
        ]);

        $reading->update(['invoice_id' => $invoice->id]);
    }

    private function createSubmeterInvoice(ElectricitySale $sale, Unit $unit, Lease $lease, array $submeterSettings): Invoice
    {
        $tenant  = $lease->tenant;
        $gross   = (float) $sale->amount;
        $amounts = $this->submeterAmounts($gross, $submeterSettings);
        $vatPct  = number_format((float) $submeterSettings['vat_percent'], 2, '.', '');

        $invoice = Invoice::create([
            'invoice_number' => $this->invoiceNumberService->generateNumber('ELEC'),
            'type'           => 'invoice',
            'property_id'    => $sale->property_id,
            'lease_id'       => $lease->id,
            'tenant_name'    => $tenant?->name ?? 'Unknown Tenant',
            'tenant_email'   => $tenant?->email,
            'unit_ref'       => $unit->unit_number,
            'issued_date'    => Carbon::parse($sale->sale_date)->toDateString(),
            'due_date'       => Carbon::parse($sale->sale_date)->addDays(14)->toDateString(),
            'period'         => Carbon::parse($sale->sale_date)->format('Y-m'),
            'status'         => 'draft',
            'currency'       => 'TZS',
            'exchange_rate'  => 1,
            'total_in_base'  => $gross,
            'notes'          => 'Electricity module: submeter sale on ' . Carbon::parse($sale->sale_date)->toDateString(),
        ]);

        // Net electricity charge (VAT stripped out via back-calculation)
        InvoiceItem::create([
            'invoice_id'      => $invoice->id,
            'description'     => 'Electricity — Submeter Charge',
            'item_type'       => 'electricity_charge',
            'sub_description' => number_format((float) $sale->units_sold, 2, '.', '') . ' units × TZS '
                               . number_format($amounts['net_amount'] / max(1, (float) $sale->units_sold), 2, '.', '') . '/unit',
            'quantity'        => 1,
            'unit_price'      => $amounts['net_amount'],
            'total'           => $amounts['net_amount'],
        ]);

        // VAT portion (inclusive back-calculation)
        InvoiceItem::create([
            'invoice_id'      => $invoice->id,
            'description'     => 'Electricity — Submeter VAT',
            'item_type'       => 'electricity_vat',
            'sub_description' => $vatPct . '% VAT on TZS ' . number_format($gross, 2, '.', '') . ' electricity charge',
            'quantity'        => 1,
            'unit_price'      => $amounts['vat_amount'],
            'total'           => $amounts['vat_amount'],
        ]);

        return $invoice;
    }

    private function sendProformaEmail(Invoice $invoice): void
    {
        if (empty($invoice->tenant_email)) {
            return;
        }
        try {
            if ($invoice->items->isEmpty()) {
                $invoice->load('items');
            }
            Mail::to($invoice->tenant_email)->send(new ProformaInvoiceMail($invoice, $invoice->items));
        } catch (\Exception $e) {
            \Log::warning('Generator proforma email failed for invoice #' . $invoice->id . ': ' . $e->getMessage());
        }
    }
}
