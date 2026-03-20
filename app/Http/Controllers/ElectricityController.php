<?php

namespace App\Http\Controllers;

use App\Models\MeterReading;
use App\Models\Outage;
use App\Models\FuelLog;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ElectricityController extends Controller
{
    public function index()
    {
        $readings = MeterReading::with('unit')->where('month', '2026-03')->get();
        $outages  = Outage::orderByDesc('outage_date')->get();
        $fuelLogs = FuelLog::orderByDesc('log_date')->get();
        $units    = Unit::whereIn('status', ['occupied', 'overdue'])->orderBy('unit_number')->get();

        $gridSettings = [
            'tariff'       => 22.50,
            'fuel_levy'    => 3.20,
            'erc_levy'     => 0.50,
            'fixed_charge' => 1800,
            'bill_kes'     => 288900,
            'bill_kwh'     => 12840,
            'common_kwh'   => 420,
            'on'           => true,
        ];

        $genSettings = [
            'status'       => 'standby',
            'fuel_pct'     => 68,
            'fuel_litres'  => 136,
            'tank_size'    => 200,
            'diesel_price' => 185,
            'l_per_hr'     => 6,
            'output_kw'    => 36,
            'maint_levy'   => 50,
        ];

        return Inertia::render('Electricity/Index', compact('readings', 'outages', 'fuelLogs', 'units', 'gridSettings', 'genSettings'));
    }

    public function storeReading(Request $request)
    {
        $data = $request->validate([
            'unit_id'      => 'required|exists:units,id',
            'month'        => 'required|string',
            'prev_reading' => 'required|numeric',
            'curr_reading' => 'required|numeric|gte:prev_reading',
            'gen_kwh'      => 'nullable|numeric',
            'reading_date' => 'required|date',
        ]);
        MeterReading::updateOrCreate(
            ['unit_id' => $data['unit_id'], 'month' => $data['month']],
            $data
        );
        return back()->with('success', 'Reading saved.');
    }

    public function storeOutage(Request $request)
    {
        $data = $request->validate([
            'outage_date'         => 'required|date',
            'start_time'          => 'required',
            'end_time'            => 'required',
            'type'                => 'required|in:major,minor,planned',
            'floors_affected'     => 'nullable|string',
            'generator_activated' => 'boolean',
            'fuel_used'           => 'nullable|numeric',
            'notes'               => 'nullable|string',
        ]);
        Outage::create($data);
        return back()->with('success', 'Outage logged.');
    }

    public function storeFuelLog(Request $request)
    {
        $data = $request->validate([
            'log_date'        => 'required|date',
            'litres'          => 'required|numeric',
            'price_per_litre' => 'required|numeric',
            'supplier'        => 'nullable|string',
            'level_after'     => 'required|integer|min:0|max:100',
        ]);
        $data['total_cost']   = $data['litres'] * $data['price_per_litre'];
        $data['recorded_by']  = 'James Mwangi';
        FuelLog::create($data);
        return back()->with('success', 'Fuel log saved.');
    }
}
