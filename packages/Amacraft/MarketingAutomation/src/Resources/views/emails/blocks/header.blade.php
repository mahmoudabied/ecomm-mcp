<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
    <tr>
        @if (! empty($logo_url))
            <td style="padding-bottom: 12px;">
                <img src="{{ $logo_url }}" alt="{{ $title ?? '' }}" width="140" style="display: block;">
            </td>
        @endif
        <td style="text-align: {{ $align }}; font-size: 22px; font-weight: bold; color: #0f172a;">
            {{ $title ?? '' }}
        </td>
    </tr>
</table>
