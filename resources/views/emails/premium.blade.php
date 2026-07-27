<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }}</title></head>
<body style="margin:0;padding:0;background:#f3ecdf;color:#16382f;font-family:Arial,Helvetica,sans-serif">
<span style="display:none!important;max-height:0;overflow:hidden;opacity:0">{{ $preheader ?? $title }}</span>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3ecdf"><tr><td align="center" style="padding:36px 14px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#fffdf8;border:1px solid #dfd4c2;border-radius:20px;overflow:hidden">
<tr><td style="padding:28px 34px;background:#123f37;color:#fff"><div style="color:#d8b877;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase">The Azari Residences</div><div style="margin-top:8px;color:#dfe9e4;font-size:13px">Exceptional stays, thoughtfully managed.</div></td></tr>
<tr><td style="padding:36px 34px"><h1 style="margin:0 0 22px;color:#123f37;font-family:Georgia,serif;font-size:30px;font-weight:500;line-height:1.2">{{ $title }}</h1>
@foreach($lines as $line)<p style="margin:0 0 15px;color:#44554f;font-size:15px;line-height:1.75">{{ $line }}</p>@endforeach
@if($actionLabel && $actionUrl)<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0"><tr><td style="border-radius:10px;background:#123f37"><a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 22px;color:#fff;font-size:14px;font-weight:700;text-decoration:none">{{ $actionLabel }}</a></td></tr></table><p style="margin:0 0 15px;color:#7a817e;font-size:12px;line-height:1.6;overflow-wrap:anywhere">Button not working? Copy this link:<br><a href="{{ $actionUrl }}" style="color:#8a612b">{{ $actionUrl }}</a></p>@endif
</td></tr><tr><td style="padding:22px 34px;background:#f8f3e9;border-top:1px solid #e8dfd1;color:#737b77;font-size:12px;line-height:1.65">This transactional message was sent by The Azari Residences. Please do not share verification, password-reset or booking links.</td></tr>
</table></td></tr></table></body></html>
