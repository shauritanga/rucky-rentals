<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">

                    {{-- Header --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f766e,#0b5f58);padding:24px 28px;color:#ffffff;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td>
                                        <h1 style="margin:0;font-size:22px;font-weight:700;line-height:1.3;">PROFORMA INVOICE</h1>
                                        <p style="margin:6px 0 0;font-size:14px;opacity:.9;">Mwamba Properties</p>
                                    </td>
                                    <td align="right" style="font-size:20px;font-weight:800;letter-spacing:1px;opacity:.85;">
                                        {{ $invoice->invoice_number }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Notice banner --}}
                    <tr>
                        <td style="background:#fef9c3;border-bottom:1px solid #fde68a;padding:10px 28px;font-size:13px;color:#92400e;">
                            <strong>Note:</strong> This is a <strong>Proforma Invoice</strong> — a preliminary estimate, not a legally binding tax invoice.
                            A Tax Invoice will be issued automatically upon receipt of your payment.
                        </td>
                    </tr>

                    {{-- Details grid --}}
                    <tr>
                        <td style="padding:24px 28px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="50%" style="vertical-align:top;padding-right:12px;">
                                        <p style="margin:0 0 4px;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;">Billed To</p>
                                        <p style="margin:0 0 3px;font-size:15px;font-weight:600;">{{ $invoice->tenant_name }}</p>
                                        @if($invoice->tenant_email)
                                        <p style="margin:0 0 3px;font-size:13px;color:#475569;">{{ $invoice->tenant_email }}</p>
                                        @endif
                                        <p style="margin:0;font-size:13px;color:#475569;">Unit {{ $invoice->unit_ref }}</p>
                                    </td>
                                    <td width="50%" style="vertical-align:top;padding-left:12px;">
                                        <p style="margin:0 0 4px;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;">Invoice Details</p>
                                        <p style="margin:0 0 3px;font-size:13px;color:#334155;"><strong>Number:</strong> {{ $invoice->invoice_number }}</p>
                                        <p style="margin:0 0 3px;font-size:13px;color:#334155;"><strong>Issue Date:</strong> {{ \Carbon\Carbon::parse($invoice->issued_date)->format('d/m/Y') }}</p>
                                        @if($invoice->due_date)
                                        <p style="margin:0 0 3px;font-size:13px;color:#334155;"><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}</p>
                                        @endif
                                        @if($invoice->period)
                                        <p style="margin:0;font-size:13px;color:#334155;"><strong>Period:</strong> {{ $invoice->period }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Items table --}}
                    <tr>
                        <td style="padding:20px 28px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:2px solid #e2e8f0;">
                                        <th style="text-align:left;padding:8px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:700;">Description</th>
                                        <th style="text-align:center;padding:8px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:700;">Qty</th>
                                        <th style="text-align:right;padding:8px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:700;">Unit Price</th>
                                        <th style="text-align:right;padding:8px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:700;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $grandTotal = 0; @endphp
                                    @foreach($items->filter(fn($i) => ($i->item_type ?? '') !== 'electricity_vat') as $item)
                                    @php $grandTotal += (float)$item->total; @endphp
                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:10px 10px;font-size:13px;vertical-align:top;">
                                            <div style="font-weight:500;">{{ $item->description }}</div>
                                            @if($item->sub_description)
                                            <div style="font-size:11px;color:#64748b;margin-top:2px;">{{ $item->sub_description }}</div>
                                            @endif
                                        </td>
                                        <td style="padding:10px 10px;font-size:13px;text-align:center;vertical-align:top;">{{ $item->quantity }}</td>
                                        <td style="padding:10px 10px;font-size:13px;text-align:right;vertical-align:top;">
                                            {{ $invoice->currency ?? 'TZS' }} {{ number_format((float)$item->unit_price, 2) }}
                                        </td>
                                        <td style="padding:10px 10px;font-size:13px;text-align:right;font-weight:600;vertical-align:top;">
                                            {{ $invoice->currency ?? 'TZS' }} {{ number_format((float)$item->total, 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    @php
                                        $vatTotal = $items->filter(fn($i) => ($i->item_type ?? '') === 'electricity_vat')->sum('total');
                                        $grandTotal += (float)$vatTotal;
                                    @endphp
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    {{-- Totals --}}
                    <tr>
                        <td style="padding:0 28px 24px;">
                            <table role="presentation" width="280" cellspacing="0" cellpadding="0" style="margin-left:auto;">
                                @if($vatTotal > 0)
                                <tr>
                                    <td style="padding:5px 10px;font-size:13px;color:#475569;border-top:1px solid #f1f5f9;">VAT (18% inclusive)</td>
                                    <td style="padding:5px 10px;font-size:13px;color:#475569;text-align:right;border-top:1px solid #f1f5f9;">
                                        {{ $invoice->currency ?? 'TZS' }} {{ number_format((float)$vatTotal, 2) }}
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:10px 10px;font-size:16px;font-weight:700;border-top:2px solid #e2e8f0;">Total Due</td>
                                    <td style="padding:10px 10px;font-size:16px;font-weight:700;text-align:right;border-top:2px solid #e2e8f0;color:#0f766e;">
                                        {{ $invoice->currency ?? 'TZS' }} {{ number_format($grandTotal, 2) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if($invoice->notes)
                    {{-- Notes --}}
                    <tr>
                        <td style="padding:0 28px 20px;font-size:13px;color:#475569;line-height:1.6;border-top:1px solid #f1f5f9;">
                            <strong>Notes:</strong><br>
                            {!! nl2br(e($invoice->notes)) !!}
                        </td>
                    </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;line-height:1.6;">
                            This proforma invoice is issued by <strong>Mwamba Properties</strong>.
                            Payment converts this document into a legally binding Tax Invoice automatically.
                            For queries, contact your property manager.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
