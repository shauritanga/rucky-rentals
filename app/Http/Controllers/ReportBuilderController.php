<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\ReportTemplate;
use App\Support\ReportBuilder\EntityRegistry;
use App\Support\ReportBuilder\ReportBuilderException;
use App\Support\ReportBuilder\ReportQueryBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        ]);
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
            ])->all();

            $title = $request->input('title') ?: 'Custom Report';

            if ($format === 'pdf') {
                $rows = $builder->query($config, $propertyId)->limit(1000)->get();

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
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_shared' => 'boolean',
            'config' => 'required|array',
        ]);

        $user = $request->user();

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
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_shared' => 'boolean',
            'config' => 'required|array',
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

    private function resolvePropertyId(Request $request): ?int
    {
        if ($this->shouldScopeToProperty($request)) {
            return $this->effectivePropertyId($request);
        }

        return $request->filled('property_id') ? (int) $request->input('property_id') : null;
    }
}
