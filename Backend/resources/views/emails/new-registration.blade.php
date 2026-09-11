<!doctype html>
<html>
<body style="margin:0;padding:0;background:#faf9f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#14141f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:480px;background:#ffffff;border-radius:16px;border:1px solid #e2dfda;overflow:hidden;">
<tr><td style="height:6px;background:linear-gradient(90deg,#1a1a2e,#e94560);"></td></tr>
<tr><td style="padding:20px 28px 0;">
<img src="{{ config('app.url') }}/logo-email.png" width="28" height="28" alt="QRMeets" style="border-radius:7px;vertical-align:middle;">
<span style="font-size:14px;font-weight:800;color:#1a1a2e;vertical-align:middle;margin-left:8px;">QRMeets</span>
</td></tr>
<tr><td style="padding:16px 28px 8px;">
<p style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#e94560;margin:0 0 8px;">New registration</p>
<h1 style="font-size:20px;margin:0 0 4px;">{{ $event->title }}</h1>
<p style="font-size:13px;color:#5b5b6b;margin:0;">{{ $registration->name }} ({{ $registration->email }}) just {{ $registration->waitlisted ? 'joined the waitlist' : 'registered' }}.</p>
</td></tr>
@if ($registration->payment_status === 'pending')
<tr><td style="padding:0 28px 8px;">
<p style="font-size:12px;background:#fbf1e0;color:#b3760a;border-radius:8px;padding:10px 12px;margin:0;">Paid registration - payment is awaiting your verification.</p>
</td></tr>
@endif
<tr><td style="padding:0 28px 8px;">
<table role="presentation" width="100%" style="background:#f3f1ee;border-radius:12px;">
<tr><td style="padding:14px 16px;font-size:13px;">
<p style="margin:0;"><strong>Registered:</strong> {{ $registration->created_at->format('l, F j, Y g:i A') }}</p>
</td></tr>
</table>
</td></tr>
<tr><td style="padding:20px 28px 28px;text-align:center;">
<a href="{{ $eventUrl }}" style="display:inline-block;background:#1a1a2e;color:#fff;font-size:13px;font-weight:700;padding:11px 22px;border-radius:10px;text-decoration:none;">View event</a>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
