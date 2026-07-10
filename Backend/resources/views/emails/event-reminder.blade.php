<!doctype html>
<html>
<body style="margin:0;padding:0;background:#faf9f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#14141f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:480px;background:#ffffff;border-radius:16px;border:1px solid #e2dfda;overflow:hidden;">
<tr><td style="height:6px;background:linear-gradient(90deg,#1a1a2e,#e94560);"></td></tr>
<tr><td style="padding:28px 28px 8px;">
<p style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#e94560;margin:0 0 8px;">Coming up soon</p>
<h1 style="font-size:20px;margin:0 0 4px;">{{ $event->title }}</h1>
<p style="font-size:13px;color:#5b5b6b;margin:0;">Hi {{ $registration->name }}, just a reminder - this is happening soon.</p>
</td></tr>
<tr><td style="padding:20px 28px;text-align:center;">
<img src="{{ $qrUrl }}" width="200" height="200" alt="QR pass" style="border:1px solid #e2dfda;border-radius:12px;padding:12px;background:#f3f1ee;">
<p style="font-family:monospace;font-size:11px;color:#8b8a99;margin:10px 0 0;">{{ $registration->qr_code }}</p>
</td></tr>
<tr><td style="padding:0 28px 28px;">
<table role="presentation" width="100%" style="background:#f3f1ee;border-radius:12px;">
<tr><td style="padding:14px 16px;font-size:13px;">
<p style="margin:0 0 6px;"><strong>Date:</strong> {{ \Carbon\Carbon::parse($event->date)->format('l, F j, Y') }}</p>
<p style="margin:0 0 6px;"><strong>Time:</strong> {{ $event->start_time }} - {{ $event->end_time }}</p>
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
