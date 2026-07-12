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
<p style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#e94560;margin:0 0 8px;">{{ $registration->waitlisted ? "You're on the waitlist" : "You're registered" }}</p>
<h1 style="font-size:20px;margin:0 0 4px;">{{ $event->title }}</h1>
<p style="font-size:13px;color:#5b5b6b;margin:0;">
@if ($registration->waitlisted)
Hi {{ $registration->name }}, this event is full - you're on the waitlist and we'll email you if a spot opens up.
@else
Hi {{ $registration->name }}, your spot is confirmed.
@endif
</p>
</td></tr>
@if ($registration->waitlisted)
<tr><td style="padding:0 28px 8px;">
<p style="font-size:12px;background:#f1ebfc;color:#6d28d9;border-radius:8px;padding:10px 12px;margin:0;">You don't need to do anything right now - hold onto this email. If you're promoted off the waitlist, your pass below becomes valid for check-in.</p>
</td></tr>
@endif
<tr><td style="padding:20px 28px;text-align:center;">
<img src="{{ $qrUrl }}" width="200" height="200" alt="QR pass" style="border:1px solid #e2dfda;border-radius:12px;padding:12px;background:#f3f1ee;">
<p style="font-family:monospace;font-size:11px;color:#8b8a99;margin:10px 0 0;">{{ $registration->qr_code }}</p>
</td></tr>
<tr><td style="padding:0 28px 8px;">
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
@if ($registration->payment_status === 'pending')
<tr><td style="padding:8px 28px;">
<p style="font-size:12px;background:#fbf1e0;color:#b3760a;border-radius:8px;padding:10px 12px;margin:0;">Payment under review - your pass works now, we'll confirm shortly.</p>
</td></tr>
@endif
<tr><td style="padding:20px 28px 28px;">
<p style="font-size:11px;color:#8b8a99;margin:0;">Bring this email or your pass link to check in. You'll get a reminder before the event.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
