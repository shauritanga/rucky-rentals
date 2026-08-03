<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111;
            background: #fff;
            padding: 30px 36px;
        }

        .header {
            margin-bottom: 16px;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .meta {
            font-size: 10px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background: #f2f2f2;
            text-align: left;
            padding: 6px 8px;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: .3px;
            border-bottom: 2px solid #111;
        }
        tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e2e2;
        }
        tbody tr:nth-child(even) {
            background: #fafafa;
        }
        .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $title }}</div>
        <div class="meta">Generated {{ $generatedAt->format('d M Y, H:i') }} &middot; {{ count($rows) }} row(s)</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    <th class="{{ in_array($col['type'], ['currency', 'number']) ? 'num' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $col)
                        <td class="{{ in_array($col['type'], ['currency', 'number']) ? 'num' : '' }}">{{ $row[$col['key']] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}">No rows matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
