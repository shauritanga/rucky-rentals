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
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .invoice-title {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .company-meta {
            font-size: 11px;
            color: #444;
            line-height: 1.7;
        }

        .divider {
            border: none;
            border-top: 2px solid #111;
            margin: 16px 0;
        }
        .divider-thin {
            border: none;
            border-top: 1px solid #ccc;
            margin: 12px 0;
        }

        /* ── Date / Number row ── */
        .meta-row {
            display: table;
            width: 100%;
            background: #f2f2f2;
            margin-bottom: 20px;
        }
        .meta-cell {
            display: table-cell;
            padding: 10px 14px;
            width: 50%;
        }
        .meta-cell.right {
            text-align: right;
        }
        .meta-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 4px;
        }
        .meta-value {
            font-size: 14px;
            font-weight: 700;
        }

        /* ── Bill To ── */
        .bill-to {
            margin-bottom: 20px;
        }
        .bill-to-label {
            font-size: 11px;
            font-weight: 700;
            font-style: italic;
            margin-bottom: 5px;
        }
        .bill-to-name {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .bill-to-line {
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
            background: #111;
            color: #fff;
        }
        table.items thead th {
            padding: 9px 10px;
            font-size: 10px;
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
            border-top: 2px solid #111;
        }
        .totals-table table tr.total-row td {
            padding: 9px 10px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .clearfix::after { content: ''; display: table; clear: both; }

        /* ── Payment details ── */
        .payment-box {
            margin-top: 28px;
            border-left: 4px solid #111;
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
            font-weight: 600;
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

    {{-- ── Company Header ── --}}
    <div class="header">
        <div class="invoice-title">{{ $invoiceLabel }}</div>
        <div class="company-name">{{ $companyName }}</div>
        <div class="company-meta">
            {{ $property->address ?? '' }}{{ ($property->city ?? '') ? ', ' . $property->city : '' }}{{ ($property->country ?? '') ? ', ' . $property->country : '' }}<br>
            {{ $companyEmail }}{{ $companyPhone ? ' | ' . $companyPhone : '' }}
            @if($vatNumber)
                <br>VAT No: {{ $vatNumber }}
            @endif
            @if($companyReg)
                &nbsp;| Reg No: {{ $companyReg }}
            @endif
        </div>
    </div>

    <hr class="divider">

    {{-- ── Date / Invoice Number ── --}}
    <div class="meta-row">
        <div class="meta-cell">
            <div class="meta-label">Date</div>
            <div class="meta-value">{{ \Carbon\Carbon::parse($invoice->issued_date)->format('d M Y') }}</div>
        </div>
        <div class="meta-cell right">
            <div class="meta-label">Invoice Number</div>
            <div class="meta-value">{{ $invoice->invoice_number }}</div>
        </div>
    </div>

    {{-- ── Bill To ── --}}
    <div class="bill-to">
        <div class="bill-to-label">BILL TO</div>
        <div class="bill-to-name">{{ strtoupper($invoice->tenant_name) }}</div>
        @if($tenantUnit)
        <div class="bill-to-line">{{ $tenantUnit }}</div>
        @endif
        <div class="bill-to-line">
            {{ $invoice->tenant_email ?? '' }}
            @if($tenantPhone)
                {{ $invoice->tenant_email ? ' | ' : '' }}{{ $tenantPhone }}
            @endif
        </div>
        @if($invoice->period)
        <div class="bill-to-line" style="margin-top:4px;color:#555;">Period: {{ $invoice->period }}</div>
        @endif
        @if($invoice->due_date)
        <div class="bill-to-line" style="color:#555;">Due: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</div>
        @endif
    </div>

    {{-- ── Items Table ── --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:32px;">NO</th>
                <th style="text-align:left;">DESCRIPTION</th>
                <th class="center" style="width:60px;">QTY</th>
                <th class="right" style="width:120px;">RATE</th>
                <th class="right" style="width:130px;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lineItems = $items->filter(fn($i) => ($i->item_type ?? '') !== 'electricity_vat');
                $vatItems  = $items->filter(fn($i) => ($i->item_type ?? '') === 'electricity_vat');
                $rowNum = 1;
            @endphp
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
                <td class="right bold">{{ number_format((float)$item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Totals ── --}}
    @php
        $subtotal  = $lineItems->sum('total');
        $vatAmount = $vatItems->sum('total');
        $grandTotal = $subtotal + $vatAmount;
        $currency   = $invoice->currency ?? 'TZS';
    @endphp

    <div class="totals-wrap clearfix">
        <div class="totals-table">
            <table>
                <tr>
                    <td>Subtotal</td>
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
                    <td>Total Amount {{ $currency }}</td>
                    <td class="right">{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ── Payment Details ── --}}
    @if($bankName || $bankAccount)
    <div class="payment-box">
        <div class="payment-box-title">Payment Details</div>
        <div class="payment-grid">
            @if($bankName)
            <div class="payment-row">
                <div class="payment-key">Bank Name:</div>
                <div class="payment-val">{{ $bankName }}</div>
            </div>
            @endif
            @if($bankAccount)
            <div class="payment-row">
                <div class="payment-key">Account Number:</div>
                <div class="payment-val">{{ $bankAccount }}</div>
            </div>
            @endif
            @if($bankAccountName)
            <div class="payment-row">
                <div class="payment-key">Account Name:</div>
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
        <div class="footer-left">Approved by: {{ $companyName }}</div>
        <div class="footer-right">Date: {{ \Carbon\Carbon::now()->format('d/m/Y') }}</div>
    </div>

</body>
</html>
