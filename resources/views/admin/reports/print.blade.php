<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Resavar {{ str($type)->replace('_',' ')->title() }} Report</title>
<style>
@page { size: A4 landscape; margin: 14mm 12mm 17mm; }
* { box-sizing: border-box; }
body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; color: #052058; font-size: 9px; line-height: 1.5; background: #FFFFFF; }
.report-head { border-bottom: 2px solid #052058; padding-bottom: 10px; margin-bottom: 15px; }
.brand { color: #052058; font-size: 10px; font-weight: bold; letter-spacing: 1.2px; }
h1 { font-size: 18px; line-height: 1.3; margin: 5px 0; color: #052058; }
.meta { color: #526581; font-size: 9px; }
table { width: 100%; table-layout: fixed; border-collapse: collapse; }
thead { display: table-header-group; }
tfoot { display: table-footer-group; }
tr { page-break-inside: avoid; }
th, td { border-bottom: 1px solid #DCE5F0; padding: 7px 6px; text-align: left; vertical-align: top; word-wrap: break-word; }
th { background: #052058; color: #FFFFFF; font-size: 9px; font-weight: bold; }
tbody tr:nth-child(even) { background: #F5F8FC; }
.empty { text-align: center; padding: 18px; color: #526581; }
@media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<header class="report-head">
    <div class="brand">RESAVAR</div>
    <h1>{{ str($type)->replace('_',' ')->title() }} Report</h1>
    <div class="meta">
        Generated {{ now()->timezone(config('localization.platform_timezone','UTC'))->format('d M Y, g:i A') }}
        · Operational timezone: {{ config('localization.platform_timezone','UTC') }}
    </div>
</header>
<table>
    @if(count($rows))
        <thead><tr>
            @foreach(array_keys((array) $rows->first()) as $key)
                <th>{{ str($key)->replace('_',' ')->title() }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach((array) $row as $value)
                        <td>{{ is_scalar($value) || is_null($value) ? $value : json_encode($value) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    @else
        <tbody><tr><td class="empty">No records are available for this report.</td></tr></tbody>
    @endif
</table>
</body>
</html>
