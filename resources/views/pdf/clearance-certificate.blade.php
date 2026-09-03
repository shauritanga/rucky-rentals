<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $clearance->clearance_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            padding: 40px 50px;
        }

        .header-table { display: table; width: 100%; }
        .header-cell { display: table-cell; vertical-align: top; }
        .header-left { width: 60%; }
        .header-right { width: 40%; text-align: right; }
        .company-name { font-size: 15px; font-weight: 700; }
        .company-meta { font-size: 10px; color: #333; margin-top: 3px; }

        .header-title-wrap { text-align: center; margin: 16px 0 6px; }
        .cert-title { font-size: 20px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
        .cert-sub { font-size: 10.5px; color: #555; margin-top: 3px; }

        .header-meta { font-size: 10px; color: #333; line-height: 1.7; }
        .header-meta .hm-label { font-weight: 700; }

        .divider { border: none; border-top: 2px solid #2563eb; margin: 14px 0 16px; }
        .divider-thin { border: none; border-top: 1px solid #ccc; margin: 12px 0; }

        .parties { display: table; width: 100%; margin-bottom: 16px; }
        .party-col { display: table-cell; width: 50%; vertical-align: top; padding-right: 16px; }
        .party-col-right { padding-right: 0; padding-left: 16px; }
        .party-label { font-size: 10px; font-weight: 700; font-style: italic; letter-spacing: .5px; text-transform: uppercase; color: #666; margin-bottom: 5px; }
        .party-name { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
        .party-line { font-size: 10.5px; color: #333; line-height: 1.5; }

        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #2563eb; margin: 16px 0 8px; }

        table.checklist, table.items { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.checklist thead tr, table.items thead tr { background: #2563eb; color: #fff; }
        table.checklist th, table.items th { text-align: left; padding: 6px 8px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .3px; }
        table.checklist td, table.items td { padding: 6px 8px; font-size: 10px; border-bottom: 1px solid #eee; }
        table.items td.right, table.checklist td.right { text-align: right; }

        .cond-good { color: #16a34a; font-weight: 700; }
        .cond-fair { color: #d97706; font-weight: 700; }
        .cond-damaged { color: #dc2626; font-weight: 700; }

        .totals-wrap { display: table; width: 100%; margin-top: 10px; }
        .totals-spacer { display: table-cell; width: 55%; }
        .totals-table { display: table-cell; width: 45%; }
        .totals-table table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 5px 0; font-size: 11px; }
        .totals-table td.label { color: #555; }
        .totals-table td.amount { text-align: right; font-weight: 600; }
        .totals-table tr.total-row td { border-top: 2px solid #2563eb; padding-top: 8px; font-size: 13px; font-weight: 700; }
        .totals-table tr.shortfall-row td { color: #dc2626; font-weight: 700; }

        .footer-wrap { display: table; width: 100%; margin-top: 40px; }
        .footer-cell { display: table-cell; width: 50%; vertical-align: top; }
        .sig-line { border-top: 1px solid #333; width: 200px; margin-top: 36px; padding-top: 4px; font-size: 10px; color: #333; }
        .no-data { color: #888; font-style: italic; font-size: 10.5px; }
    </style>
</head>
<body>
    <div class="header-table">
        <div class="header-cell header-left">
            <div class="company-name">{{ $companyName }}</div>
            @if($companyEmail)<div class="company-meta">{{ $companyEmail }}</div>@endif
        </div>
        <div class="header-cell header-right">
            <div class="header-meta">
                <span class="hm-label">Clearance No:</span> {{ $clearance->clearance_number }}<br>
                <span class="hm-label">Status:</span> {{ ucfirst(str_replace('_',' ', $clearance->status)) }}<br>
                @if($clearance->finalized_at)
                    <span class="hm-label">Finalized:</span> {{ \Illuminate\Support\Carbon::parse($clearance->finalized_at)->format('d M Y') }}
                @else
                    <span class="hm-label">Date:</span> {{ now()->format('d M Y') }}
                @endif
            </div>
        </div>
    </div>

    <div class="header-title-wrap">
        <div class="cert-title">Move-Out Clearance Certificate</div>
        <div class="cert-sub">Unit inspection, damage assessment and security deposit settlement</div>
    </div>

    <hr class="divider">

    <div class="parties">
        <div class="party-col">
            <div class="party-label">Property / Unit</div>
            <div class="party-name">{{ $clearance->property?->name }}</div>
            <div class="party-line">Unit {{ $clearance->unit?->unit_number }}@if($clearance->unit?->floor) — Floor {{ $clearance->unit->floor }}@endif</div>
        </div>
        <div class="party-col party-col-right">
            <div class="party-label">Tenant</div>
            <div class="party-name">{{ $clearance->tenant?->name }}</div>
            <div class="party-line">{{ $clearance->tenant?->phone }}</div>
            <div class="party-line">{{ $clearance->tenant?->email }}</div>
        </div>
    </div>

    <div class="section-title">Inspection Checklist</div>
    @if(!empty($clearance->inspection_checklist))
        <table class="checklist">
            <thead><tr><th>Area</th><th>Condition</th><th>Notes</th></tr></thead>
            <tbody>
            @foreach($clearance->inspection_checklist as $row)
                <tr>
                    <td>{{ $row['category'] ?? '' }}</td>
                    <td class="cond-{{ $row['condition'] ?? 'good' }}">{{ ucfirst($row['condition'] ?? '') }}</td>
                    <td>{{ $row['note'] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">No inspection checklist recorded.</div>
    @endif

    <div class="section-title">Damage Items</div>
    @if($clearance->items->isNotEmpty())
        <table class="items">
            <thead><tr><th>Description</th><th>Category</th><th>Responsibility</th><th class="right">Cost</th></tr></thead>
            <tbody>
            @foreach($clearance->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->category }}</td>
                    <td>{{ ucfirst($item->responsible_party) }}</td>
                    <td class="right">{{ number_format((float) $item->cost, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">No damage recorded — unit returned in good condition.</div>
    @endif

    <div class="totals-wrap">
        <div class="totals-spacer"></div>
        <div class="totals-table">
            <table>
                <tr><td class="label">Security Deposit ({{ $clearance->currency }})</td><td class="amount">{{ number_format((float) $clearance->deposit_amount, 2) }}</td></tr>
                <tr><td class="label">Total Deductions</td><td class="amount">{{ number_format((float) $clearance->total_deductions, 2) }}</td></tr>
                <tr class="total-row"><td>Refund to Tenant</td><td class="amount">{{ number_format((float) $clearance->refund_amount, 2) }}</td></tr>
                @if((float) $clearance->shortfall_amount > 0)
                    <tr class="shortfall-row"><td>Shortfall (Tenant Owes)</td><td class="amount">{{ number_format((float) $clearance->shortfall_amount, 2) }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <hr class="divider-thin">

    <div class="footer-wrap">
        <div class="footer-cell">
            <div class="sig-line">Inspected By — {{ $clearance->inspectedBy?->name ?: 'N/A' }}</div>
        </div>
        <div class="footer-cell">
            <div class="sig-line">Finalized By — {{ $clearance->finalized_by ?: 'N/A' }}</div>
        </div>
    </div>
</body>
</html>
