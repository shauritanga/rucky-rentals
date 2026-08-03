<?php

namespace App\Support\ReportBuilder;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a user-picked combination of entities/columns/filters into a single
 * joined query. Entities are joined by walking the shortest path (BFS) through
 * the relationship graph declared in EntityRegistry, anchored at a primary
 * entity — never an arbitrary/free join, so results stay predictable and
 * property-scoping stays correct.
 */
class ReportQueryBuilder
{
    private array $entities;

    public function __construct()
    {
        $this->entities = EntityRegistry::entities();
    }

    public function query(array $config, ?int $propertyId): Builder
    {
        [$query, $required] = $this->baseQuery($config, $propertyId);

        $columns = $config['columns'] ?? [];
        if (empty($columns)) {
            throw new ReportBuilderException('Select at least one column to include in the report.');
        }

        $select = [];
        foreach ($columns as $col) {
            $entityKey = $col['entity'] ?? null;
            $field = $col['field'] ?? null;
            $colDef = $this->entities[$entityKey]['columns'][$field] ?? null;
            if (!isset($required[$entityKey]) || !$colDef) {
                throw new ReportBuilderException("Unknown column \"{$field}\" for entity \"{$entityKey}\".");
            }
            $expr = $colDef['sql'] ?? "{$entityKey}.{$field}";
            $select[] = DB::raw("({$expr}) as {$entityKey}__{$field}");
        }
        $query->select($select);

        $sort = $config['sort'] ?? null;
        if ($sort && isset($sort['entity'], $sort['field']) && isset($required[$sort['entity']])) {
            $sortColDef = $this->entities[$sort['entity']]['columns'][$sort['field']] ?? null;
            if ($sortColDef) {
                $dir = strtolower($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                $expr = $sortColDef['sql'] ?? "{$sort['entity']}.{$sort['field']}";
                $query->orderByRaw("({$expr}) {$dir}");
            }
        }

        return $query;
    }

    public function count(array $config, ?int $propertyId): int
    {
        [$query] = $this->baseQuery($config, $propertyId);

        return $query->count();
    }

    public function columnLabel(string $entityKey, string $field): string
    {
        $entity = $this->entities[$entityKey] ?? null;
        $col = $entity['columns'][$field] ?? null;

        return $col ? "{$entity['label']} - {$col['label']}" : "{$entityKey}.{$field}";
    }

    public function columnType(string $entityKey, string $field): string
    {
        return $this->entities[$entityKey]['columns'][$field]['type'] ?? 'string';
    }

    /**
     * @return array{0: Builder, 1: array<string, true>} the joined query (no select/sort yet)
     *         and the map of entity keys that ended up part of the join.
     */
    private function baseQuery(array $config, ?int $propertyId): array
    {
        $primary = $config['primary_entity'] ?? null;
        $selectedEntities = array_values(array_unique($config['entities'] ?? []));

        if (!$primary || !isset($this->entities[$primary])) {
            throw new ReportBuilderException('Select a primary entity for the report.');
        }
        foreach ($selectedEntities as $entityKey) {
            if (!isset($this->entities[$entityKey])) {
                throw new ReportBuilderException("Unknown entity \"{$entityKey}\".");
            }
        }
        if (!in_array($primary, $selectedEntities, true)) {
            $selectedEntities[] = $primary;
        }

        $tree = $this->bfs($primary);
        foreach ($selectedEntities as $entityKey) {
            if ($entityKey !== $primary && !isset($tree[$entityKey])) {
                $primaryLabel = $this->entities[$primary]['label'];
                $otherLabel = $this->entities[$entityKey]['label'];
                throw new ReportBuilderException("\"{$otherLabel}\" cannot be combined with \"{$primaryLabel}\" — no relationship exists between them.");
            }
        }

        // Union of every node on the path from primary to each selected entity.
        $required = [$primary => true];
        foreach ($selectedEntities as $entityKey) {
            $node = $entityKey;
            while ($node !== $primary) {
                $required[$node] = true;
                $node = $tree[$node]['parent'];
            }
        }

        // Narrow edge case: accounts.code = journal_lines.account_code can collide
        // across properties, so whenever both are joined without journal_entries
        // already anchoring the property, pull journal_entries in too and pin the
        // property match explicitly (mirrors ReportController::expenseRowsForRange()).
        $needsJournalEntriesGuard = isset($required['accounts'], $required['journal_lines']) && !isset($required['journal_entries']);
        if ($needsJournalEntriesGuard) {
            $required['journal_entries'] = true;
        }

        $orderedNodes = array_keys($required);
        usort($orderedNodes, fn ($a, $b) => ($tree[$a]['distance'] ?? PHP_INT_MAX) <=> ($tree[$b]['distance'] ?? PHP_INT_MAX));

        $query = DB::table($this->entities[$primary]['table'] . ' as ' . $primary);

        foreach ($orderedNodes as $node) {
            if ($node === $primary || ($node === 'journal_entries' && $needsJournalEntriesGuard)) {
                // The guard node is joined explicitly below, off journal_lines directly,
                // regardless of what the BFS tree thinks its shortest path is — this
                // keeps the guard join independent of any other selected entities.
                continue;
            }

            $toTable = $this->entities[$node]['table'] . ' as ' . $node;
            $edge = $tree[$node]['edge'];
            $parent = $tree[$node]['parent'];

            if (!$edge['custom']) {
                $query->leftJoin($toTable, "{$parent}.{$edge['local']}", '=', "{$node}.{$edge['foreign']}");
            } else {
                $closure = $edge['closure'];
                if ($edge['direction'] === 'forward') {
                    $query->leftJoin($toTable, fn ($join) => $closure($join, $parent, $node));
                } else {
                    $query->leftJoin($toTable, fn ($join) => $closure($join, $node, $parent));
                }
            }
        }

        if ($needsJournalEntriesGuard) {
            $toTable = $this->entities['journal_entries']['table'] . ' as journal_entries';
            $query->leftJoin($toTable, 'journal_entries.id', '=', 'journal_lines.journal_entry_id');
            $query->whereColumn('accounts.property_id', 'journal_entries.property_id');
        }

        foreach ($orderedNodes as $node) {
            if (!empty($this->entities[$node]['soft_deletes'])) {
                $query->whereNull("{$node}.deleted_at");
            }
        }

        if ($propertyId !== null) {
            $scope = null;
            foreach ($orderedNodes as $node) {
                $key = $this->entities[$node]['property_key'] ?? null;
                if ($key) {
                    $scope = [$node, $key];
                    break;
                }
            }
            if (!$scope) {
                $primaryLabel = $this->entities[$primary]['label'];
                throw new ReportBuilderException("\"{$primaryLabel}\" alone can't be scoped to your property — include an entity like Units, Leases or Invoices too.");
            }
            $query->where("{$scope[0]}.{$scope[1]}", $propertyId);
        }

        $this->applyFilters($query, $config['filters'] ?? [], $required);

        return [$query, $required];
    }

    private function applyFilters(Builder $query, array $filters, array $required): void
    {
        foreach ($filters as $key => $filter) {
            if (!is_array($filter) || !str_contains($key, '.')) {
                continue;
            }
            [$entityKey, $field] = explode('.', $key, 2);
            if (!isset($required[$entityKey])) {
                continue;
            }
            $colDef = $this->entities[$entityKey]['columns'][$field] ?? null;
            if (!$colDef || !empty($colDef['sql'])) {
                // Computed (raw-expression) columns aren't filterable — they're
                // scalar subqueries, not plain columns you can compare directly.
                continue;
            }
            $column = "{$entityKey}.{$field}";

            switch ($colDef['type']) {
                case 'string':
                    if (!empty($filter['contains'])) {
                        $query->where($column, 'like', '%' . $filter['contains'] . '%');
                    }
                    break;
                case 'number':
                case 'currency':
                    if (isset($filter['min']) && $filter['min'] !== '') {
                        $query->where($column, '>=', $filter['min']);
                    }
                    if (isset($filter['max']) && $filter['max'] !== '') {
                        $query->where($column, '<=', $filter['max']);
                    }
                    break;
                case 'date':
                    if (!empty($filter['from'])) {
                        $query->whereDate($column, '>=', $filter['from']);
                    }
                    if (!empty($filter['to'])) {
                        $query->whereDate($column, '<=', $filter['to']);
                    }
                    break;
                case 'enum':
                case 'boolean':
                    if (!empty($filter['in']) && is_array($filter['in'])) {
                        $query->whereIn($column, $filter['in']);
                    }
                    break;
            }
        }
    }

    /**
     * BFS from $primary over the (undirected) relationship graph.
     * @return array<string, array{parent: ?string, edge: ?array, distance: int}>
     */
    private function bfs(string $primary): array
    {
        $adjacency = $this->adjacency();
        $visited = [$primary => ['parent' => null, 'edge' => null, 'distance' => 0]];
        $queue = [$primary];

        while ($queue) {
            $current = array_shift($queue);
            foreach ($adjacency[$current] ?? [] as $edge) {
                $to = $edge['to'];
                if (isset($visited[$to])) {
                    continue;
                }
                $visited[$to] = [
                    'parent' => $current,
                    'edge' => $edge,
                    'distance' => $visited[$current]['distance'] + 1,
                ];
                $queue[] = $to;
            }
        }

        return $visited;
    }

    private function adjacency(): array
    {
        $adjacency = [];

        foreach (EntityRegistry::edges() as [$a, $aKey, $b, $bKey]) {
            $adjacency[$a][] = ['to' => $b, 'local' => $aKey, 'foreign' => $bKey, 'custom' => false];
            $adjacency[$b][] = ['to' => $a, 'local' => $bKey, 'foreign' => $aKey, 'custom' => false];
        }

        foreach (EntityRegistry::customEdges() as [$a, $b, $closure]) {
            $adjacency[$a][] = ['to' => $b, 'custom' => true, 'closure' => $closure, 'direction' => 'forward'];
            $adjacency[$b][] = ['to' => $a, 'custom' => true, 'closure' => $closure, 'direction' => 'backward'];
        }

        return $adjacency;
    }
}
