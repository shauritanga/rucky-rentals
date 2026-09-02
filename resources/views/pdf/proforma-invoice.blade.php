<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            padding: 40px 50px;
        }

        /* ── Header ── */
        .header-table {
            display: table;
            width: 100%;
        }
        .header-cell {
            display: table-cell;
            vertical-align: top;
        }
        .header-logo-cell {
            width: 60%;
            text-align: left;
        }
        .header-meta-cell {
            width: 40%;
            text-align: right;
        }
        .logo-img {
            max-height: 64px;
            max-width: 220px;
        }
        .header-title-wrap {
            text-align: center;
            margin: 10px 0 4px;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .header-meta {
            font-size: 10px;
            color: #333;
            line-height: 1.7;
            text-align: right;
        }
        .header-meta .hm-label {
            font-weight: 700;
        }

        .company-name {
            font-size: 15px;
            font-weight: 700;
        }

        .divider {
            border: none;
            border-top: 2px solid #111;
            margin: 12px 0 16px;
        }
        .divider-thin {
            border: none;
            border-top: 1px solid #ccc;
            margin: 12px 0;
        }

        /* ── Landlord / Tenant ── */
        .parties {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .party-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 16px;
        }
        .party-col-right {
            padding-right: 0;
            padding-left: 16px;
        }
        .party-label {
            font-size: 10px;
            font-weight: 700;
            font-style: italic;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
        }
        .party-name {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .party-line {
            font-size: 11px;
            color: #333;
            margin-bottom: 2px;
        }

        /* ── Items table ── */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        table.items thead tr {
            background: #2563eb;
            color: #fff;
        }
        table.items thead th {
            padding: 6px 10px;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        table.items thead th.right { text-align: right; }
        table.items thead th.center { text-align: center; }

        table.items tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }
        table.items tbody tr:nth-child(even) {
            background: #fafafa;
        }
        table.items tbody td {
            padding: 9px 10px;
            font-size: 11px;
            vertical-align: top;
        }
        table.items tbody td.right { text-align: right; }
        table.items tbody td.center { text-align: center; }
        table.items tbody td.bold { font-weight: 700; }

        /* ── Totals ── */
        .totals-wrap {
            width: 100%;
            margin-top: 0;
        }
        .totals-table {
            float: right;
            width: 320px;
            margin-top: 8px;
        }
        .totals-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table table tr td {
            padding: 5px 10px;
            font-size: 11px;
        }
        .totals-table table tr td.right { text-align: right; }
        .totals-table table tr.total-row {
            border-top: 2px solid #2563eb;
            background: rgba(37,99,235,.08);
        }
        .totals-table table tr.total-row td {
            padding: 9px 10px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .clearfix::after { content: ''; display: table; clear: both; }

        /* ── Account details ── */
        .payment-box {
            margin-top: 28px;
            border-left: 4px solid #2563eb;
            padding: 12px 16px;
            background: #f8f8f8;
        }
        .payment-box-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 10px;
        }
        .payment-grid {
            display: table;
            width: 100%;
        }
        .payment-row {
            display: table-row;
        }
        .payment-key {
            display: table-cell;
            font-size: 11px;
            color: #555;
            padding: 3px 12px 3px 0;
            width: 130px;
            white-space: nowrap;
        }
        .payment-val {
            display: table-cell;
            font-size: 11px;
            font-weight: 400;
            padding: 3px 0;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 28px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            display: table;
            width: 100%;
            font-size: 10px;
            color: #666;
        }
        .footer-left { display: table-cell; }
        .footer-right { display: table-cell; text-align: right; }

        .notes {
            margin-top: 16px;
            font-size: 10px;
            color: #555;
            line-height: 1.6;
            font-style: italic;
        }
    </style>
</head>
<body>

    @php
        $lineItems = $items->filter(fn($i) => ($i->item_type ?? '') !== 'electricity_vat');
        $vatItems  = $items->filter(fn($i) => ($i->item_type ?? '') === 'electricity_vat');
        $currency  = $invoice->currency ?? 'TZS';
    @endphp

    {{-- ── Header: logo top-left, meta top-right, title centered below ── --}}
    <div class="header-table">
        <div class="header-cell header-logo-cell">
            @if($companyLogoBase64)
                <img src="{{ $companyLogoBase64 }}" class="logo-img" alt="{{ $companyName }}">
            @else
                <div class="company-name">{{ $companyName }}</div>
            @endif
        </div>
        <div class="header-cell header-meta-cell">
            <div class="header-meta">
                <div><span class="hm-label">Date:</span> {{ \Carbon\Carbon::parse($invoice->issued_date)->format('d/m/Y') }}</div>
                <div><span class="hm-label">Invoice No:</span> {{ $invoice->invoice_number }}</div>
                @if($vatNumber)
                <div><span class="hm-label">VRN No:</span> {{ $vatNumber }}</div>
                @endif
                @if($tinNumber)
                <div><span class="hm-label">TIN No:</span> {{ $tinNumber }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="header-title-wrap">
        <div class="invoice-title">{{ $invoiceLabel }}</div>
    </div>

    <hr class="divider">

    {{-- ── Landlord / Tenant ── --}}
    <div class="parties">
        <div class="party-col">
            <div class="party-label">Landlord</div>
            <div class="party-name">{{ $companyName }}</div>
            @if($property?->address || $property?->city || $property?->country)
            <div class="party-line">
                {{ $property->address ?? '' }}{{ ($property->city ?? '') ? ', ' . $property->city : '' }}{{ ($property->country ?? '') ? ', ' . $property->country : '' }}
            </div>
            @endif
            @if($companyPhone || $companyEmail)
            <div class="party-line">
                {{ $companyPhone ? 'Tel: ' . $companyPhone : '' }}{{ ($companyPhone && $companyEmail) ? ' | ' : '' }}{{ $companyEmail }}
            </div>
            @endif
            @if($companyReg)
            <div class="party-line">Reg No: {{ $companyReg }}</div>
            @endif
        </div>
        <div class="party-col party-col-right">
            <div class="party-label">Tenant</div>
            <div class="party-name">{{ strtoupper($invoice->tenant_name) }}</div>
            @if($tenantTin || $tenantVrn)
            <div class="party-line">
                {{ $tenantTin ? 'TIN No: ' . $tenantTin : '' }}{{ ($tenantTin && $tenantVrn) ? ' | ' : '' }}{{ $tenantVrn ? 'VRN No: ' . $tenantVrn : '' }}
            </div>
            @endif
            @if($tenantUnit || $property?->name)
            <div class="party-line">
                {{ $property?->name }}{{ ($property?->name && $tenantUnit) ? ' — Unit ' : ($tenantUnit ? 'Unit ' : '') }}{{ $tenantUnit }}
            </div>
            @endif
            @if($tenantAddress || $tenantCity)
            <div class="party-line">
                {{ $tenantAddress }}{{ ($tenantAddress && $tenantCity) ? ', ' : '' }}{{ $tenantCity }}
            </div>
            @endif
            @if($invoice->tenant_email || $tenantPhone)
            <div class="party-line">
                {{ $invoice->tenant_email }}{{ ($invoice->tenant_email && $tenantPhone) ? ' | ' : '' }}{{ $tenantPhone }}
            </div>
            @endif
            @if($invoice->period)
            <div class="party-line" style="margin-top:4px;color:#555;">Period: {{ $invoice->period }}</div>
            @endif
            @if($invoice->due_date)
            <div class="party-line" style="color:#555;">Due: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}</div>
            @endif
        </div>
    </div>

    {{-- ── Items Table ── --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:32px;">NO</th>
                <th style="text-align:left;">DESCRIPTION</th>
                @if($isRentServiceOnly)
                    <th class="center" style="width:90px;">NO OF MONTHS</th>
                    <th class="right" style="width:130px;">RENT/{{ $currency }}/MONTH</th>
                    <th class="right" style="width:130px;">TOTAL {{ $currency }}</th>
                @else
                    <th class="center" style="width:60px;">QTY</th>
                    <th class="right" style="width:120px;">RATE</th>
                    <th class="right" style="width:130px;">AMOUNT</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @if($unitLine)
            <tr>
                <td colspan="5" class="bold" style="background:#f2f2f2;">{{ strtoupper($unitLine) }}</td>
            </tr>
            @endif
            @php $rowNum = 1; @endphp
            @foreach($lineItems as $item)
            <tr>
                <td class="center">{{ $rowNum++ }}</td>
                <td>
                    {{ strtoupper($item->description) }}
                    @if($item->sub_description)
                        <br><span style="font-size:10px;color:#666;">{{ $item->sub_description }}</span>
                    @endif
                </td>
                <td class="center">{{ number_format((float)$item->quantity, 2) }}</td>
                <td class="right">{{ number_format((float)$item->unit_price, 2) }}</td>
                <td class="right">{{ number_format((float)$item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Totals ── --}}
    @php
        $subtotal   = $lineItems->sum('total');
        $vatAmount  = $vatItems->sum('total');
        $grandTotal = $subtotal + $vatAmount;
    @endphp

    <div class="totals-wrap clearfix">
        <div class="totals-table">
            <table>
                <tr>
                    <td>Sub Total</td>
                    <td class="right">{{ number_format($subtotal, 2) }}</td>
                </tr>
                @if($vatAmount > 0)
                <tr>
                    <td>VAT (18%)</td>
                    <td class="right">{{ number_format($vatAmount, 2) }}</td>
                </tr>
                @elseif($vatRate > 0)
                @php $calcVat = round($subtotal * $vatRate / 100, 2); @endphp
                <tr>
                    <td>VAT ({{ $vatRate }}%)</td>
                    <td class="right">{{ number_format($calcVat, 2) }}</td>
                </tr>
                @php $grandTotal = $subtotal + $calcVat; @endphp
                @endif
                <tr class="total-row">
                    <td>Invoice Total {{ $currency }}</td>
                    <td class="right">{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ── Account Details ── --}}
    @if($bankName || $bankAccount)
    <div class="payment-box">
        <div class="payment-box-title">Account Details</div>
        <div class="payment-grid">
            @if($bankName)
            <div class="payment-row">
                <div class="payment-key">Bank:</div>
                <div class="payment-val">{{ $bankName }}</div>
            </div>
            @endif
            @if($bankAccount)
            <div class="payment-row">
                <div class="payment-key">Acct No:</div>
                <div class="payment-val">{{ $bankAccount }}</div>
            </div>
            @endif
            @if($bankAccountName)
            <div class="payment-row">
                <div class="payment-key">A/C Name:</div>
                <div class="payment-val">{{ $bankAccountName }}</div>
            </div>
            @endif
            @if($swiftCode)
            <div class="payment-row">
                <div class="payment-key">Swift Code:</div>
                <div class="payment-val">{{ $swiftCode }}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Footer ── --}}
    <div class="footer">
        <div class="footer-left">Approved by: {{ $approvedByName ?: $companyName }}</div>
        <div class="footer-right">Date: {{ \Carbon\Carbon::now()->format('d/m/Y') }}</div>
    </div>

</body>
</html>
