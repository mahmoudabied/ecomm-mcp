@php
    $safeHref = str_starts_with($href ?? '', 'http://') || str_starts_with($href ?? '', 'https://') || str_starts_with($href ?? '', '{{') ? ($href ?? '#') : '#';
@endphp

<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
    <tr>
        <td style="border-radius: 6px;" bgcolor="#0f172a">
            <a href="{{ $safeHref }}" style="display: inline-block; padding: 12px 24px; font-size: 15px; font-weight: bold; color: #ffffff; text-decoration: none;">
                {{ $label ?? '' }}
            </a>
        </td>
    </tr>
</table>
