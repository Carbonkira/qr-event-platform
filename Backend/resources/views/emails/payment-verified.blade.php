<!doctype html>
<html>
<body style="margin:0;padding:0;background:#faf9f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#14141f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:480px;background:#ffffff;border-radius:16px;border:1px solid #e2dfda;overflow:hidden;">
<tr><td style="height:6px;background:linear-gradient(90deg,#0f9d8f,#1a1a2e);"></td></tr>
<tr><td style="padding:20px 28px 0;">
<img src="{{ config('app.url') }}/logo-email.png" width="28" height="28" alt="QRMeets" style="border-radius:7px;vertical-align:middle;">
<span style="font-size:14px;font-weight:800;color:#1a1a2e;vertical-align:middle;margin-left:8px;">QRMeets</span>
</td></tr>
<tr><td style="padding:16px 28px 8px;">
<p style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#0f9d8f;margin:0 0 8px;">Payment verified</p>
<h1 style="font-size:20px;margin:0 0 4px;">{{ $event->title }}</h1>
<p style="font-size:13px;color:#5b5b6b;margin:0;">Hi {{ $registration->name }}, your payment has been verified - here's your QR pass.</p>
</td></tr>
<tr><td style="padding:20px 28px;text-align:center;">
<img src="{{ $qrUrl }}" width="200" height="200" alt="QR pass" style="border:1px solid #e2dfda;border-radius:12px;padding:12px;background:#f3f1ee;">
<p style="font-family:monospace;font-size:11px;color:#8b8a99;margin:10px 0 0;">{{ $registration->qr_code }}</p>
</td></tr>
<tr><td style="padding:0 28px 8px;">
<table role="presentation" width="100%" style="background:#f3f1ee;border-radius:12px;">
<tr><td style="padding:14px 16px;font-size:13px;">
<p style="margin:0 0 8px;font-weight:700;">Official Receipt</p>
<p style="margin:0 0 6px;"><strong>Reference:</strong> {{ $registration->payment_ref }}</p>
<p style="margin:0 0 6px;"><strong>Amount paid:</strong> ₱{{ number_format($event->price, 2) }}</p>
<p style="margin:0;"><strong>Event:</strong> {{ $event->title }} - {{ \Carbon\Carbon::parse($event->date)->format('l, F j, Y') }}</p>
</td></tr>
</table>
</td></tr>
<tr><td style="padding:8px 28px 28px;">
<p style="font-size:11px;color:#8b8a99;margin:0;">Bring this email or your pass link to check in.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
