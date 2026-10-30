<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\ReportTemplate;
use App\Support\ReportBuilder\EntityRegistry;
use App\Support\ReportBuilder\ReportBuilderException;
use App\Support\ReportBuilder\ReportQueryBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReportBuilderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $properties = $user?->role === 'superuser'
            ? Property::orderBy('name')->get(['id', 'name'])
            : [];

        $registry = EntityRegistry::entities();
        if ($this->shouldScopeToProperty($request)) {
            // A user tied to a single property only ever sees their own property
            // row through this entity — not useful as a report dimension for them.
            unset($registry['properties']);
        }

        return Inertia::render('Reports/Builder', [
            'registry' => $registry,
            'templates' => ReportTemplate::visibleTo($user)->latest()->get()
                ->map(fn (ReportTemplate $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'description' => $t->description,
                    'is_shared' => $t->is_shared,
                    'is_mine' => $t->user_id === $user->id,
                    'config' => $t->config,
                ]),
            'properties' => $properties,
            'presets' => $this->presets(),
        ]);
    }

    /**
     * Distinct values for a column marked as "enum" without a hardcoded options
     * list — used to populate its filter dropdown from real data instead of
     * making the user type a value that may not even exist.
     */
    public function columnOptions(Request $request)
    {
        $entityKey = (string) $request->query('entity');
        $field = (string) $request->query('field');

        $entity = EntityRegistry::entity($entityKey);
        $col = $entity['columns'][$field] ?? null;

        if (!$entity || !$col || $col['type'] !== 'enum' || !empty($col['options'])) {
            return response()->json(['options' => []]);
        }

        if ($entityKey === 'properties' && $this->shouldScopeToProperty($request)) {
            return response()->json(['options' => []]);
        }

        $query = DB::table($entity['table'])
            ->select($field)
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->distinct();

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where($field, 'like', '%' . $search . '%');
        }

        $propertyId = $this->resolvePropertyId($request);
        if ($propertyId !== null && !empty($entity['property_key'])) {
            $query->where($entity['property_key'], $propertyId);
        }

        $options = $query->orderBy($field)->limit(300)->pluck($field)->values();

        return response()->json(['options' => $options]);
    }

    public function preview(Request $request)
    {
        try {
            $config = $this->validatedConfig($request);
            $propertyId = $this->resolvePropertyId($request);
            $builder = new ReportQueryBuilder();
            $total = $builder->count($config, $propertyId);
            $rows = $builder->query($config, $propertyId)->limit(100)->get();

            $columns = collect($config['columns'])->map(fn ($c) => [
                'key' => "{$c['entity']}__{$c['field']}",
                'label' => $builder->columnLabel($c['entity'], $c['field']),
                'type' => $builder->columnType($c['entity'], $c['field']),
            ])->all();

            return response()->json([
                'columns' => $columns,
                'rows' => $rows,
                'total' => $total,
                'shown' => $rows->count(),
            ]);
        } catch (ReportBuilderException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function export(Request $request)
    {
        try {
            $config = $this->validatedConfig($request);
            $format = $request->input('format', 'csv');
            $propertyId = $this->resolvePropertyId($request);
            $builder = new ReportQueryBuilder();

            $columns = collect($config['columns'])->map(fn ($c) => [
                'key' => "{$c['entity']}__{$c['field']}",
                'label' => $builder->columnLabel($c['entity'], $c['field']),
                'type' => $builder->columnType($c['entity'], $c['field']),
            ])->all();

            $title = $request->input('title') ?: 'Custom Report';

            if ($format === 'pdf') {
                $rows = $builder->query($config, $propertyId)->limit(1000)->get()
                    ->map(function ($row) use ($columns) {
                        $formatted = [];
                        foreach ($columns as $col) {
                            $formatted[$col['key']] = $this->formatExportValue($row->{$col['key']} ?? null, $col['type']);
                        }
                        return $formatted;
                    });

                $pdfContent = Pdf::loadView('pdf.custom-report', [
                    'title' => $title,
                    'generatedAt' => now(),
                    'columns' => $columns,
                    'rows' => $rows,
                ])->setPaper('a4', 'landscape')->output();

                $filename = Str::slug($title) . '-' . now()->format('Y-m-d') . '.pdf';

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            }

            $filename = Str::slug($title) . '-' . now()->format('Y-m-d') . '.csv';

            return response()->stream(function () use ($builder, $config, $propertyId, $columns) {
                $out = fopen('php://output', 'w');
                fputcsv($out, array_column($columns, 'label'));

                $builder->query($config, $propertyId)
                    ->chunk(500, function ($chunk) use ($out, $columns) {
                        foreach ($chunk as $row) {
                            $line = [];
                            foreach ($columns as $col) {
                                $line[] = $row->{$col['key']} ?? '';
                            }
                            fputcsv($out, $line);
                        }
                    });

                fclose($out);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (ReportBuilderException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function storeTemplate(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('report_templates', 'name')->where(fn ($q) => $q->where('user_id', $user->id)),
            ],
            'description' => 'nullable|string|max:500',
            'is_shared' => 'boolean',
            'config' => 'required|array',
        ], [
            'name.unique' => 'You already have a template with this name.',
        ]);

        $template = ReportTemplate::create([
            'user_id' => $user->id,
            'property_id' => $user->role === 'superuser' ? null : $user->property_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_shared' => $data['is_shared'] ?? false,
            'config' => $data['config'],
        ]);

        return response()->json(['id' => $template->id]);
    }

    public function updateTemplate(Request $request, ReportTemplate $reportTemplate)
    {
        abort_if($reportTemplate->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('report_templates', 'name')
                    ->where(fn ($q) => $q->where('user_id', $request->user()->id))
                    ->ignore($reportTemplate->id),
            ],
            'description' => 'nullable|string|max:500',
            'is_shared' => 'boolean',
            'config' => 'required|array',
        ], [
            'name.unique' => 'You already have a template with this name.',
        ]);

        $reportTemplate->update($data);

        return response()->json(['id' => $reportTemplate->id]);
    }

    public function destroyTemplate(Request $request, ReportTemplate $reportTemplate)
    {
        abort_if($reportTemplate->user_id !== $request->user()->id, 403);

        $reportTemplate->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Accepts a "config" payload either as a real array (axios JSON body) or as
     * a JSON string (hidden-form export submit, where nested objects can't be
     * expressed as plain form fields).
     */
    /** Ready-made reports for the owner: money owed, and withholding tax paid and outstanding. */
    private function presets(): array
    {
        $col = fn (string $entity, string $field) => ['entity' => $entity, 'field' => $field];

        return [
            [
                'key' => 'unpaid-rent',
                'name' => 'Unpaid rent and service charge',
                'description' => 'Every approved invoice that still has a balance, oldest due date first.',
                'config' => [
                    'primary_entity' => 'invoices',
                    'entities' => ['invoices'],
                    'columns' => [
                        $col('invoices', 'invoice_number'), $col('invoices', 'tenant_name'), $col('invoices', 'unit_ref'),
                        $col('invoices', 'period'), $col('invoices', 'due_date'), $col('invoices', 'rent_amount'),
                        $col('invoices', 'service_charge_amount'), $col('invoices', 'vat_amount'), $col('invoices', 'total_due'),
                        $col('invoices', 'amount_paid'), $col('invoices', 'balance_due'),
                    ],
                    'filters' => [
                        'invoices.balance_due' => ['min' => '0.01'],
                        'invoices.approval_status' => ['in' => ['approved']],
                    ],
                    'sort' => ['entity' => 'invoices', 'field' => 'due_date', 'dir' => 'asc'],
                ],
            ],
            [
                'key' => 'wht-outstanding',
                'name' => 'Withholding tax not yet confirmed',
                'description' => 'Invoices with payments received where the WHT has not been fully confirmed.',
                'config' => [
                    'primary_entity' => 'invoices',
                    'entities' => ['invoices'],
                    'columns' => [
                        $col('invoices', 'invoice_number'), $col('invoices', 'tenant_name'), $col('invoices', 'unit_ref'),
                        $col('invoices', 'period'), $col('invoices', 'amount_paid'), $col('invoices', 'wht_due'),
                        $col('invoices', 'wht_paid'), $col('invoices', 'wht_outstanding'),
                    ],
                    'filters' => [
                        'invoices.amount_paid' => ['min' => '0.01'],
                        'invoices.wht_outstanding' => ['min' => '0.01'],
                    ],
                    'sort' => ['entity' => 'invoices', 'field' => 'issued_date', 'dir' => 'asc'],
                ],
            ],
            [
                'key' => 'wht-paid',
                'name' => 'Withholding tax paid',
                'description' => 'Payments where the tenant has confirmed the WHT, with its reference.',
                'config' => [
                    'primary_entity' => 'payments',
                    'entities' => ['payments', 'invoices', 'tenants'],
                    'columns' => [
                        $col('tenants', 'name'), $col('invoices', 'invoice_number'), $col('invoices', 'unit_ref'),
                        $col('payments', 'paid_date'), $col('payments', 'amount'), $col('payments', 'wht_amount'),
                        $col('payments', 'wht_reference'),
                    ],
                    'filters' => [
                        'payments.wht_confirmed' => ['in' => [true]],
                        'payments.status' => ['in' => ['paid']],
                    ],
                    'sort' => ['entity' => 'payments', 'field' => 'paid_date', 'dir' => 'desc'],
                ],
            ],
        ];
    }

    private function validatedConfig(Request $request): array
    {
        $raw = $request->input('config');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $raw = is_array($raw) ? $raw : [];

        if (empty($raw['primary_entity']) || empty($raw['entities']) || empty($raw['columns'])) {
            throw new ReportBuilderException('Select at least one entity and one column before running this report.');
        }

        $entities = array_values((array) $raw['entities']);

        if ($this->shouldScopeToProperty($request) && in_array('properties', $entities, true)) {
            throw new ReportBuilderException('The "Properties" entity isn\'t available for a property-scoped account — it would only ever return your own property.');
        }

        return [
            'primary_entity' => $raw['primary_entity'],
            'entities' => $entities,
            'columns' => array_values((array) $raw['columns']),
            'filters' => (array) ($raw['filters'] ?? []),
            'sort' => $raw['sort'] ?? null,
        ];
    }

    /**
     * Mirrors the on-screen preview's formatCell() (resources/js/Pages/Reports/Builder.jsx)
     * so the PDF export reads the same way instead of dumping raw DB values.
     */
    private function formatExportValue(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'currency' => is_numeric($value) ? number_format((float) $value, 2) : (string) $value,
            'number' => is_numeric($value) ? number_format((float) $value) : (string) $value,
            'date' => (string) $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '',
            'boolean' => in_array($value, [true, 1, '1', 'true'], true) ? 'Yes' : 'No',
            default => (string) $value,
        };
    }

    private function resolvePropertyId(Request $request): ?int
    {
        if ($this->shouldScopeToProperty($request)) {
            return $this->effectivePropertyId($request);
        }

        return $request->filled('property_id') ? (int) $request->input('property_id') : null;
    }
}
