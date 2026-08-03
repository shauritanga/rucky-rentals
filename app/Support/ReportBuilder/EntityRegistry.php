<?php

namespace App\Support\ReportBuilder;

/**
 * Single source of truth for every entity the custom report builder can query:
 * its table/columns (exposed to the frontend pickers) and its join edges to
 * other entities (used by ReportQueryBuilder to resolve join paths).
 */
class EntityRegistry
{
    public static function entities(): array
    {
        return [
            'properties' => [
                'label' => 'Properties',
                'group' => 'Core',
                'table' => 'properties',
                'property_key' => 'id',
                'columns' => [
                    'name' => ['label' => 'Property Name', 'type' => 'string'],
                    'code' => ['label' => 'Code', 'type' => 'string'],
                    'address' => ['label' => 'Address', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'enum'],
                    'country' => ['label' => 'Country', 'type' => 'enum'],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'options' => ['active', 'trial', 'inactive']],
                    'unit_count' => ['label' => 'Unit Count', 'type' => 'number'],
                    'occupied_units' => ['label' => 'Occupied Units', 'type' => 'number'],
                    'created_at' => ['label' => 'Created At', 'type' => 'date'],
                ],
            ],
            'units' => [
                'label' => 'Units',
                'group' => 'Core',
                'table' => 'units',
                'property_key' => 'property_id',
                'columns' => [
                    'unit_number' => ['label' => 'Unit Number', 'type' => 'enum'],
                    'floor' => ['label' => 'Floor', 'type' => 'enum'],
                    'type' => ['label' => 'Type', 'type' => 'enum'],
                    'size_sqm' => ['label' => 'Size (m²)', 'type' => 'number'],
                    'rate_per_sqm' => ['label' => 'Rate per m²', 'type' => 'currency'],
                    'service_charge_per_sqm' => ['label' => 'Service Charge per m²', 'type' => 'currency'],
                    'currency' => ['label' => 'Currency', 'type' => 'enum', 'options' => ['TZS', 'USD']],
                    'rent' => ['label' => 'Rent', 'type' => 'currency'],
                    'deposit' => ['label' => 'Deposit', 'type' => 'currency'],
                    'service_charge' => ['label' => 'Service Charge', 'type' => 'currency'],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'options' => ['vacant', 'occupied', 'overdue', 'maintenance']],
                    'approval_status' => ['label' => 'Approval Status', 'type' => 'enum', 'options' => ['pending_approval', 'approved', 'rejected']],
                    'electricity_type' => ['label' => 'Electricity Type', 'type' => 'enum'],
                    'created_at' => ['label' => 'Created At', 'type' => 'date'],
                ],
            ],
            'tenants' => [
                'label' => 'Tenants',
                'group' => 'Core',
                'table' => 'tenants',
                'property_key' => 'property_id',
                'columns' => [
                    'name' => ['label' => 'Tenant Name', 'type' => 'string'],
                    'email' => ['label' => 'Email', 'type' => 'string'],
                    'phone' => ['label' => 'Phone', 'type' => 'string'],
                    'national_id' => ['label' => 'National ID', 'type' => 'string'],
                    'tenant_type' => ['label' => 'Tenant Type', 'type' => 'enum'],
                    'company_name' => ['label' => 'Company Name', 'type' => 'string'],
                    'tin' => ['label' => 'TIN', 'type' => 'string'],
                    'vrn' => ['label' => 'VRN', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'enum'],
                    'country' => ['label' => 'Country', 'type' => 'enum'],
                    'created_at' => ['label' => 'Created At', 'type' => 'date'],
                    'total_deposit' => [
                        'label' => 'Total Security Deposit (all leases)',
                        'type' => 'currency',
                        'sql' => "SELECT COALESCE(SUM(rl.deposit), 0) FROM leases rl WHERE rl.tenant_id = tenants.id AND rl.deleted_at IS NULL",
                    ],
                ],
            ],
            'leases' => [
                'label' => 'Leases',
                'group' => 'Core',
                'table' => 'leases',
                'property_key' => 'property_id',
                'soft_deletes' => true,
                'columns' => [
                    'start_date' => ['label' => 'Start Date', 'type' => 'date'],
                    'end_date' => ['label' => 'End Date', 'type' => 'date'],
                    'duration_months' => ['label' => 'Duration (months)', 'type' => 'number'],
                    'payment_cycle' => ['label' => 'Payment Cycle', 'type' => 'number'],
                    'currency' => ['label' => 'Currency', 'type' => 'enum', 'options' => ['TZS', 'USD']],
                    'monthly_rent' => ['label' => 'Monthly Rent', 'type' => 'currency'],
                    'deposit' => ['label' => 'Deposit', 'type' => 'currency'],
                    'wht_rate' => ['label' => 'WHT Rate', 'type' => 'number'],
                    'service_charge_rate' => ['label' => 'Service Charge Rate', 'type' => 'number'],
                    'vat_rate' => ['label' => 'VAT Rate', 'type' => 'number'],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'options' => ['pending_accountant', 'pending_pm', 'active', 'expiring', 'overdue', 'rejected', 'terminated']],
                    'created_at' => ['label' => 'Created At', 'type' => 'date'],
                ],
            ],
            'invoices' => [
                'label' => 'Invoices',
                'group' => 'Core',
                'table' => 'invoices',
                'property_key' => 'property_id',
                'columns' => [
                    'invoice_number' => ['label' => 'Invoice Number', 'type' => 'string'],
                    'type' => ['label' => 'Type', 'type' => 'enum', 'options' => ['invoice', 'proforma']],
                    'tenant_name' => ['label' => 'Tenant Name', 'type' => 'string'],
                    'tenant_email' => ['label' => 'Tenant Email', 'type' => 'string'],
                    'unit_ref' => ['label' => 'Unit Ref', 'type' => 'enum'],
                    'issued_date' => ['label' => 'Issued Date', 'type' => 'date'],
                    'due_date' => ['label' => 'Due Date', 'type' => 'date'],
                    'period' => ['label' => 'Period', 'type' => 'enum'],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'options' => ['draft', 'proforma', 'unpaid', 'paid', 'overdue', 'partially_paid']],
                    'approval_status' => ['label' => 'Approval Status', 'type' => 'enum'],
                    'currency' => ['label' => 'Currency', 'type' => 'enum', 'options' => ['TZS', 'USD']],
                    'exchange_rate' => ['label' => 'Exchange Rate', 'type' => 'number'],
                    'total_in_base' => ['label' => 'Total (base currency)', 'type' => 'currency'],
                    'created_at' => ['label' => 'Created At', 'type' => 'date'],
                    'vat_amount' => [
                        'label' => 'VAT Amount',
                        'type' => 'currency',
                        // VAT applies to rent + service charge line items only (mirrors
                        // PaymentController::isLeaseVatEligibleItem), at the lease's vat_rate.
                        'sql' => "
                            COALESCE((
                                SELECT SUM(ii.total) FROM invoice_items ii
                                WHERE ii.invoice_id = invoices.id AND ii.item_type IN ('rent', 'service_charge')
                            ), 0)
                            * COALESCE((SELECT l.vat_rate FROM leases l WHERE l.id = invoices.lease_id), 0) / 100
                        ",
                    ],
                    'wht_amount' => [
                        'label' => 'WHT Amount (expected)',
                        'type' => 'currency',
                        // Mirrors AccountingService::postPayment's WHT calc: rent at the
                        // lease's wht_rate, service charge at its service_charge_rate —
                        // only meaningful for tax invoices, not proformas.
                        'sql' => "
                            CASE WHEN invoices.type = 'invoice' THEN
                                COALESCE((
                                    SELECT SUM(ii.total) FROM invoice_items ii
                                    WHERE ii.invoice_id = invoices.id AND ii.item_type = 'rent'
                                ), 0) * COALESCE((SELECT l.wht_rate FROM leases l WHERE l.id = invoices.lease_id), 0) / 100
                                +
                                COALESCE((
                                    SELECT SUM(ii.total) FROM invoice_items ii
                                    WHERE ii.invoice_id = invoices.id AND ii.item_type = 'service_charge'
                                ), 0) * COALESCE((SELECT l.service_charge_rate FROM leases l WHERE l.id = invoices.lease_id), 0) / 100
                            ELSE 0 END
                        ",
                    ],
                ],
            ],
            'payments' => [
                'label' => 'Payments',
                'group' => 'Core',
                'table' => 'payments',
                'property_key' => 'property_id',
                'columns' => [
                    'month' => ['label' => 'Month', 'type' => 'enum'],
                    'amount' => ['label' => 'Amount', 'type' => 'currency'],
                    'currency' => ['label' => 'Currency', 'type' => 'enum', 'options' => ['TZS', 'USD']],
                    'amount_in_base' => ['label' => 'Amount (base currency)', 'type' => 'currency'],
                    'method' => ['label' => 'Method', 'type' => 'enum'],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'options' => ['paid', 'overdue', 'pending']],
                    'reference' => ['label' => 'Reference', 'type' => 'string'],
                    'paid_date' => ['label' => 'Paid Date', 'type' => 'date'],
                    'breakdown_rent' => ['label' => 'Rent Portion', 'type' => 'currency'],
                    'breakdown_service_charge' => ['label' => 'Service Charge Portion', 'type' => 'currency'],
                    'breakdown_electricity' => ['label' => 'Electricity Portion', 'type' => 'currency'],
                    'created_at' => ['label' => 'Created At', 'type' => 'date'],
                ],
            ],
            'maintenance_records' => [
                'label' => 'Maintenance',
                'group' => 'Maintenance',
                'table' => 'maintenance_tickets',
                'property_key' => 'property_id',
                'columns' => [
                    'ticket_number' => ['label' => 'Ticket Number', 'type' => 'string'],
                    'title' => ['label' => 'Title', 'type' => 'string'],
                    'unit_ref' => ['label' => 'Unit Ref', 'type' => 'enum'],
                    'category' => ['label' => 'Category', 'type' => 'enum'],
                    'priority' => ['label' => 'Priority', 'type' => 'enum', 'options' => ['high', 'med', 'low']],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'options' => ['open', 'in-progress', 'resolved']],
                    'assignee' => ['label' => 'Assignee', 'type' => 'enum'],
                    'cost' => ['label' => 'Cost', 'type' => 'currency'],
                    'currency' => ['label' => 'Currency', 'type' => 'enum', 'options' => ['TZS', 'USD']],
                    'reported_date' => ['label' => 'Reported Date', 'type' => 'date'],
                    'resolved_date' => ['label' => 'Resolved Date', 'type' => 'date'],
                ],
            ],
            'accounts' => [
                'label' => 'GL Accounts',
                'group' => 'Accounting',
                'table' => 'accounts',
                'property_key' => 'property_id',
                'columns' => [
                    'code' => ['label' => 'Code', 'type' => 'string'],
                    'name' => ['label' => 'Account Name', 'type' => 'string'],
                    'type' => ['label' => 'Type', 'type' => 'enum', 'options' => ['asset', 'liability', 'equity', 'revenue', 'expense', 'contra']],
                    'category' => ['label' => 'Category', 'type' => 'enum'],
                    'balance' => ['label' => 'Balance', 'type' => 'currency'],
                    'ytd_activity' => ['label' => 'YTD Activity', 'type' => 'currency'],
                ],
            ],
            'journal_entries' => [
                'label' => 'Journal Entries',
                'group' => 'Accounting',
                'table' => 'journal_entries',
                'property_key' => 'property_id',
                'columns' => [
                    'entry_number' => ['label' => 'Entry Number', 'type' => 'string'],
                    'entry_date' => ['label' => 'Entry Date', 'type' => 'date'],
                    'description' => ['label' => 'Description', 'type' => 'string'],
                    'reference' => ['label' => 'Reference', 'type' => 'string'],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'options' => ['draft', 'posted', 'void']],
                    'source_type' => ['label' => 'Source Type', 'type' => 'enum'],
                ],
            ],
            'journal_lines' => [
                'label' => 'Journal Lines',
                'group' => 'Accounting',
                'table' => 'journal_lines',
                'property_key' => null,
                'columns' => [
                    'account_code' => ['label' => 'Account Code', 'type' => 'string'],
                    'account_name' => ['label' => 'Account Name', 'type' => 'string'],
                    'debit' => ['label' => 'Debit', 'type' => 'currency'],
                    'credit' => ['label' => 'Credit', 'type' => 'currency'],
                ],
            ],
            'meter_readings' => [
                'label' => 'Meter Readings',
                'group' => 'Electricity',
                'table' => 'meter_readings',
                'property_key' => 'property_id',
                'columns' => [
                    'month' => ['label' => 'Month', 'type' => 'enum'],
                    'prev_reading' => ['label' => 'Previous Reading', 'type' => 'number'],
                    'curr_reading' => ['label' => 'Current Reading', 'type' => 'number'],
                    'gen_kwh' => ['label' => 'Generator kWh', 'type' => 'number'],
                    'reading_date' => ['label' => 'Reading Date', 'type' => 'date'],
                    'recorded_by' => ['label' => 'Recorded By', 'type' => 'enum'],
                ],
            ],
            'electricity_sales' => [
                'label' => 'Electricity Sales',
                'group' => 'Electricity',
                'table' => 'electricity_sales',
                'property_key' => 'property_id',
                'columns' => [
                    'sale_date' => ['label' => 'Sale Date', 'type' => 'date'],
                    'units_sold' => ['label' => 'Units Sold', 'type' => 'number'],
                    'unit_price' => ['label' => 'Unit Price', 'type' => 'currency'],
                    'amount' => ['label' => 'Amount', 'type' => 'currency'],
                    'recorded_by' => ['label' => 'Recorded By', 'type' => 'enum'],
                ],
            ],
        ];
    }

    /**
     * Plain FK edges: [fromEntity, fromKey, toEntity, toKey]. Bidirectional —
     * ReportQueryBuilder treats these as undirected graph edges for pathfinding.
     */
    public static function edges(): array
    {
        return [
            ['properties', 'id', 'units', 'property_id'],
            ['units', 'id', 'leases', 'unit_id'],
            ['leases', 'tenant_id', 'tenants', 'id'],
            ['leases', 'id', 'invoices', 'lease_id'],
            ['invoices', 'id', 'payments', 'invoice_id'],
            ['units', 'id', 'payments', 'unit_id'],
            ['tenants', 'id', 'payments', 'tenant_id'],
            ['properties', 'id', 'maintenance_records', 'property_id'],
            ['units', 'id', 'maintenance_records', 'unit_id'],
            ['properties', 'id', 'accounts', 'property_id'],
            ['properties', 'id', 'journal_entries', 'property_id'],
            ['journal_entries', 'id', 'journal_lines', 'journal_entry_id'],
            ['properties', 'id', 'meter_readings', 'property_id'],
            ['units', 'id', 'meter_readings', 'unit_id'],
            ['properties', 'id', 'electricity_sales', 'property_id'],
            ['units', 'id', 'electricity_sales', 'unit_id'],
        ];
    }

    /**
     * Edges that can't be expressed as a single column=column pair.
     * Each entry: [entityA, entityB, closure(joinClause, tableAliasA, tableAliasB)].
     */
    public static function customEdges(): array
    {
        return [
            [
                'accounts',
                'journal_lines',
                function ($join, string $accountsTable, string $journalLinesTable) {
                    $join->on($accountsTable . '.code', '=', $journalLinesTable . '.account_code');
                },
                // accounts <-> journal_lines is only meaningful once journal_entries is also
                // joined in the path (for the property_id match) — QueryBuilder adds that
                // extra condition when journal_entries is present in the resolved path.
            ],
        ];
    }

    public static function entity(string $key): ?array
    {
        return static::entities()[$key] ?? null;
    }
}
