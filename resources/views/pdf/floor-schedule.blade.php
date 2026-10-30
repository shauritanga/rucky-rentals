<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.5px; color: #111; padding: 18px 20px; }
        .title { font-size: 14px; font-weight: 700; margin-bottom: 3px; }
        .meta { font-size: 8px; color: #555; margin-bottom: 10px; }
        .floor { font-size: 11px; font-weight: 700; text-align: center; text-transform: uppercase; background: #e8eef7; padding: 5px; border: 1px solid #999; border-bottom: none; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 3px 4px; vertical-align: middle; word-wrap: break-word; }
        th { background: #f6efdc; font-size: 7px; text-align: center; }
        td.num { text-align: right; }
        td.c { text-align: center; }
        tr.total td { background: #f7e9e3; font-weight: 700; }
        tr.grand td { background: #e6e6e6; font-weight: 700; }
    </style>
</head>
<body>
    <div class="title">{{ $title }}</div>
    <div class="meta">Billing periods {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }} &middot; all amounts in TZS &middot; generated {{ $generatedAt->format('d M Y, H:i') }}</div>

    @php $money = fn ($v) => number_format((float) $v, 2); @endphp

    @forelse ($data['sections'] as $section)
        <div class="floor">{{ $section['label'] }} tenants</div>
        <table>
            <thead>
                <tr>
                    <th style="width:2.5%">SN</th>
                    @foreach ($columns as $key => [$label, $isMoney])
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($section['rows'] as $row)
                    <tr>
                        <td class="c">{{ $row['first_of_lease'] ? $row['sn'] : '' }}</td>
                        @foreach ($columns as $key => [$label, $isMoney])
                            @if ($key === 'tenant')
                                <td>{{ $row['first_of_lease'] ? $row['tenant'] : '' }}</td>
                            @elseif ($isMoney)
                                <td class="num">{{ $money($row[$key]) }}</td>
                            @else
                                <td class="{{ $key === 'terms' ? 'c' : '' }}">{{ $row[$key] ?? '' }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                <tr class="total">
                    <td></td>
                    @foreach ($columns as $key => [$label, $isMoney])
                        <td class="num">{{ $isMoney ? $money($section['totals'][$key]) : '' }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @empty
        <p>No billing periods fall in the selected range.</p>
    @endforelse

    @if (count($data['sections']) > 1)
        <table style="margin-top: 14px">
            <tbody>
                <tr class="grand">
                    <td style="width:2.5%"></td>
                    @foreach ($columns as $key => [$label, $isMoney])
                        <td class="num">{{ $isMoney ? $money($data['grand'][$key]) : ($key === 'tenant' ? 'ALL FLOORS' : '') }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
