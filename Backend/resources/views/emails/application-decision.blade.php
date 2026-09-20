<!doctype html>
<html>
<body style="margin:0;padding:0;background:#faf9f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#14141f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:480px;background:#ffffff;border-radius:16px;border:1px solid #e2dfda;overflow:hidden;">
<tr><td style="height:6px;background:{{ $approved ? 'linear-gradient(90deg,#0f9d8f,#1a1a2e)' : 'linear-gradient(90deg,#1a1a2e,#e94560)' }};"></td></tr>
<tr><td style="padding:20px 28px 0;">
<img src="{{ config('app.url') }}/logo-email.png" width="28" height="28" alt="QRMeets" style="border-radius:7px;vertical-align:middle;">
<span style="font-size:14px;font-weight:800;color:#1a1a2e;vertical-align:middle;margin-left:8px;">QRMeets</span>
</td></tr>
<tr><td style="padding:16px 28px 8px;">
<p style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:{{ $approved ? '#0f9d8f' : '#e94560' }};margin:0 0 8px;">{{ $approved ? 'Application approved' : 'Application update' }}</p>
<h1 style="font-size:20px;margin:0 0 8px;">Hi {{ $recipientName }},</h1>
@if ($approved)
<p style="font-size:14px;color:#3b3b4b;line-height:1.55;margin:0;">Good news - your application for your <strong>{{ $applicationFor }}</strong> has been approved.</p>
@else
<p style="font-size:14px;color:#3b3b4b;line-height:1.55;margin:0;">We weren't able to approve your application for your <strong>{{ $applicationFor }}</strong> at this time.</p>
@if ($adminNumber)
<p style="font-size:14px;color:#3b3b4b;line-height:1.55;margin:12px 0 0;">If you have questions or think this was a mistake, you can contact us here: <strong>{{ $adminNumber }}</strong></p>
@endif
@endif
</td></tr>
@if (count($rows))
<tr><td style="padding:8px 28px 8px;">
<table role="presentation" width="100%" style="background:#f3f1ee;border-radius:12px;">
<tr><td style="padding:14px 16px;font-size:13px;">
@foreach ($rows as $label => $value)
<p style="margin:0 0 6px;"><strong>{{ $label }}:</strong> {{ $value }}</p>
@endforeach
</td></tr>
</table>
</td></tr>
@endif
@if ($ctaUrl)
<tr><td style="padding:16px 28px 8px;text-align:center;">
<a href="{{ $ctaUrl }}" style="display:inline-block;background:#1a1a2e;color:#fff;font-size:13px;font-weight:700;padding:11px 22px;border-radius:10px;text-decoration:none;">{{ $ctaLabel ?: 'Open QRMeets' }}</a>
</td></tr>
@endif
<tr><td style="padding:12px 28px 28px;">
<p style="font-size:11px;color:#8b8a99;margin:0;">This is an automated message - you don't need to reply to it.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
