<?php

namespace App\Http\Controllers;

use App\Models\LeaseInstallment;
use App\Models\Property;
use App\Services\FloorScheduleReport;
use App\Traits\LogsAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FloorScheduleController extends Controller
{
    use LogsAudit;

    /** Column key => [heading, is money]. Shared by the CSV and PDF exports. */
    public const COLUMNS = [
        'tenant' => ['Tenant', false],
        'unit' => ['Unit', false],
        'period' => ['Invoice date', false],
        'terms' => ['No. of terms', false],
        'rent_per_month' => ['Rent per month', true],
        'sc_per_month' => ['Service charge', true],
        'total_rent' => ['Total rent', true],
        'total_sc' => ['Total service charge', true],
        'excl' => ['Invoice amount (exclusive)', true],
        'vat' => ['VAT amount', true],
        'incl' => ['Invoice amount (inclusive)', true],
        'wht_rent' => ['WHT on rent', true],
        'wht_sc' => ['WHT on service charge', true],
        'wht_total' => ['Total WHT amount', true],
        'net_after_wht' => ['Invoice net of WHT', true],
        'wht_paid' => ['WHT paid amount', true],
        'paid' => ['Invoice amount paid', true],
        'balance' => ['Invoice balance amount', true],
        'efd' => ['EFD receipt', false],
        'remark' => ['Remarks', false],
    ];

    public function index(Request $request, FloorScheduleReport $report)
    {
        [$propertyId, $properties] = $this->propertyContext($request);
        [$from, $to] = $this->range($request);
        $floor = $request->input('floor');

        $data = $report->build($propertyId, $floor, $from, $to);

        return Inertia::render('Reports/FloorSchedule', [
            'report' => $data,
            'properties' => $properties,
            'floors' => $report->floorOptions($propertyId),
            'filters' => [
                'property_id' => $propertyId,
                'floor' => $floor ?? '',
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'canPickProperty' => $properties !== [],
        ]);
    }

    public function export(Request $request, FloorScheduleReport $report)
    {
        [$propertyId] = $this->propertyContext($request);
        [$from, $to] = $this->range($request);
        $data = $report->build($propertyId, $request->input('floor'), $from, $to);

        $property = $propertyId ? Property::where('id', $propertyId)->value('name') : 'All properties';
        $title = 'Floor Tenant Schedule - ' . $property;
        $base = Str::slug($title) . '-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d');

        if ($request->input('format') === 'pdf') {
            $pdf = Pdf::loadView('pdf.floor-schedule', [
                'title' => $title,
                'generatedAt' => now(),
                'from' => $from,
                'to' => $to,
                'data' => $data,
                'columns' => self::COLUMNS,
            ])->setPaper('a3', 'landscape')->output();

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $base . '.pdf"',
            ]);
        }

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads it correctly
            fputcsv($out, array_merge(['Floor', 'SN'], array_map(fn ($c) => $c[0], self::COLUMNS)));
            foreach ($data['sections'] as $section) {
                foreach ($section['rows'] as $row) {
                    fputcsv($out, array_merge([$section['label'], $row['sn']], $this->csvCells($row)));
                }
                fputcsv($out, array_merge([$section['label'], 'TOTAL'], $this->csvCells($section['totals'], true)));
            }
            if (count($data['sections']) > 1) {
                fputcsv($out, array_merge(['ALL FLOORS', 'TOTAL'], $this->csvCells($data['grand'], true)));
            }
            fclose($out);
        }, $base . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function updateRemark(Request $request, LeaseInstallment $installment)
    {
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $installment->property_id !== $effectiveId, 403);
        }

        $data = $request->validate(['remark' => 'nullable|string|max:255']);
        $installment->update(['remark' => ($data['remark'] ?? '') === '' ? null : $data['remark']]);

        $this->logAudit(
            request: $request,
            action: 'Schedule remark updated',
            resource: 'Installment #' . $installment->id,
            propertyName: Property::where('id', $installment->property_id)->value('name'),
            category: 'lease',
            propertyId: $installment->property_id ? (int) $installment->property_id : null,
        );

        return back()->with('success', 'Remark saved.');
    }

    /** @return array{0: ?int, 1: array} the property to report on, and the list a superuser can choose from */
    private function propertyContext(Request $request): array
    {
        if ($this->shouldScopeToProperty($request)) {
            return [$this->effectivePropertyId($request), []];
        }

        $properties = Property::orderBy('name')->get(['id', 'name'])->all();
        $chosen = $request->filled('property_id') ? (int) $request->input('property_id') : ($properties[0]->id ?? null);

        return [$chosen, $properties];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : Carbon::now()->startOfYear();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : Carbon::now()->endOfYear();

        return $to->lt($from) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from->startOfDay(), $to->endOfDay()];
    }

    private function csvCells(array $row, bool $totalsOnly = false): array
    {
        return array_map(function ($key) use ($row, $totalsOnly) {
            [, $money] = self::COLUMNS[$key];
            if (! array_key_exists($key, $row)) {
                return '';
            }

            return $money ? number_format((float) $row[$key], 2, '.', '') : ($totalsOnly ? '' : $row[$key]);
        }, array_keys(self::COLUMNS));
    }
}
