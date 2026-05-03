@props(['url'])
<tr>
<td class="header" style="padding: 24px 0 8px;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none; color: #0a0a0a; font-size: 24px; font-weight: 700; letter-spacing: -0.025em; line-height: 1;">
{!! $slot !!}<span style="color: #ea580c; font-weight: 700;">.</span>
</a>
</td>
</tr>
