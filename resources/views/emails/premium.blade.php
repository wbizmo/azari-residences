<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f7fcfe;color:#173846;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%">
<span style="display:none!important;max-height:0;max-width:0;overflow:hidden;opacity:0;color:transparent">{{ $preheader ?? $title }}</span>
@php
    $emailTone = $tone ?? 'default';
    $accent = match ($emailTone) {
        'success' => '#17845b',
        'warning' => '#a66c24',
        'danger' => '#9d3f35',
        'internal' => '#2596be',
        default => '#2596be',
    };
    $noticeBackground = match ($emailTone) {
        'success' => '#eef9f5',
        'warning' => '#fff6e8',
        'danger' => '#fff0ed',
        'internal' => '#eaf7fb',
        default => '#eaf7fb',
    };
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f7fcfe">
    <tr>
        <td align="center" style="padding:32px 12px">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:660px;background:#ffffff;border:1px solid #cfe8f1">
                <tr>
                    <td style="padding:24px 30px;background:#176b89;border-bottom:4px solid {{ $accent }}">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td valign="middle">
                                    @if(!empty($logoUrl))
                                        <img src="{{ $logoUrl }}" width="174" alt="Reserva" style="display:block;width:174px;max-width:100%;height:auto;border:0">
                                    @else
                                        <div style="color:#fff;font-family:Georgia,'Times New Roman',serif;font-size:22px;letter-spacing:1px">THE AZARI</div>
                                    @endif
                                </td>
                                <td align="right" valign="middle" style="color:#ffffff;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase">
                                    {{ $eyebrow ?? 'Reserva' }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:36px 30px 18px">
                        <div style="margin:0 0 10px;color:{{ $accent }};font-size:11px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase">
                            Exceptional stays, thoughtfully managed
                        </div>
                        <h1 style="margin:0 0 22px;color:#173846;font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:500;line-height:1.2">
                            {{ $title }}
                        </h1>

                        @foreach(($lines ?? []) as $line)
                            <p style="margin:0 0 15px;color:#506b76;font-size:15px;line-height:1.75">{{ $line }}</p>
                        @endforeach

                        @if(!empty($details))
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;border:1px solid #cfe8f1;background:#f7fcfe">
                                @foreach($details as $label => $value)
                                    @if(filled($value))
                                        <tr>
                                            <td valign="top" style="width:38%;padding:11px 14px;border-bottom:1px solid #dceef5;color:#607985;font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase">
                                                {{ $label }}
                                            </td>
                                            <td valign="top" style="padding:11px 14px;border-bottom:1px solid #dceef5;color:#173846;font-size:14px;line-height:1.55;overflow-wrap:anywhere">
                                                {{ $value }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>
                        @endif

                        @if(!empty($notice))
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0;background:{{ $noticeBackground }};border-left:4px solid {{ $accent }}">
                                <tr>
                                    <td style="padding:14px 16px;color:#506b76;font-size:13px;line-height:1.65">{{ $notice }}</td>
                                </tr>
                            </table>
                        @endif

                        @if(!empty($actionLabel) && !empty($actionUrl))
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0 18px">
                                <tr>
                                    <td style="background:#173846">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 22px;color:#fff;font-size:14px;font-weight:700;text-decoration:none">
                                            {{ $actionLabel }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @if(!empty($secondaryActionLabel) && !empty($secondaryActionUrl))
                            <p style="margin:0 0 18px">
                                <a href="{{ $secondaryActionUrl }}" style="color:#176b89;font-size:13px;font-weight:700;text-decoration:underline">
                                    {{ $secondaryActionLabel }}
                                </a>
                            </p>
                        @endif

                        @if(!empty($actionUrl))
                            <p style="margin:22px 0 0;color:#607985;font-size:11px;line-height:1.65;overflow-wrap:anywhere">
                                Button not working? Copy and paste this secure link into your browser:<br>
                                <a href="{{ $actionUrl }}" style="color:#176b89">{{ $actionUrl }}</a>
                            </p>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 30px;background:#eaf7fb;border-top:1px solid #cfe8f1;color:#607985;font-size:11px;line-height:1.65">
                        <div style="margin-bottom:7px;color:#173846;font-weight:700">Reserva</div>
                        <div>{{ $footerText ?? 'This is a transactional message from Reserva.' }}</div>
                        @if(!empty($supportEmail))
                            <div style="margin-top:7px">Need assistance? Contact <a href="mailto:{{ $supportEmail }}" style="color:#176b89">{{ $supportEmail }}</a>.</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
