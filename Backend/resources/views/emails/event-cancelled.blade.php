<!doctype html>
<html>
<body style="margin:0;padding:0;background:#faf9f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#14141f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:480px;background:#ffffff;border-radius:16px;border:1px solid #e2dfda;overflow:hidden;">
<tr><td style="height:6px;background:linear-gradient(90deg,#e94560,#b3760a);"></td></tr>
<tr><td style="padding:20px 28px 0;">
<img src="{{ config('app.url') }}/logo-email.png" width="28" height="28" alt="QRMeets" style="border-radius:7px;vertical-align:middle;">
<span style="font-size:14px;font-weight:800;color:#1a1a2e;vertical-align:middle;margin-left:8px;">QRMeets</span>
</td></tr>
<tr><td style="padding:16px 28px 8px;">
<p style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#e94560;margin:0 0 8px;">Event cancelled</p>
<h1 style="font-size:20px;margin:0 0 4px;">{{ $event->title }}</h1>
<p style="font-size:13px;color:#5b5b6b;margin:0;">Hi {{ $registration->name }}, the organizer has cancelled this event. Your pass for it is no longer valid - there's nothing further you need to do.</p>
</td></tr>
<tr><td style="padding:8px 28px 28px;">
<table role="presentation" width="100%" style="background:#f3f1ee;border-radius:12px;">
<tr><td style="padding:14px 16px;font-size:13px;">
<p style="margin:0 0 6px;"><strong>Was scheduled:</strong> {{ \Carbon\Carbon::parse($event->date)->format('l, F j, Y') }}</p>
@if ($event->venue)
<p style="margin:0;"><strong>Venue:</strong> {{ $event->venue }}</p>
@endif
</td></tr>
</table>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
