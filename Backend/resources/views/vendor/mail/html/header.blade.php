@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ config('app.url') }}/logo-email.png" width="28" height="28" alt="{{ config('app.name') }}" style="border-radius:7px;vertical-align:middle;">
<span style="font-size:16px;font-weight:800;color:#1a1a2e;vertical-align:middle;margin-left:8px;">{{ $slot }}</span>
</a>
</td>
</tr>
