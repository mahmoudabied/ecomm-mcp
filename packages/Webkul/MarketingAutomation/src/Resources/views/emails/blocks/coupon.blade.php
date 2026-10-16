<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px; border: 1px dashed #0f172a; border-radius: 6px;">
    <tr>
        <td style="padding: 16px; text-align: center;">
            @if (! empty($label))
                <div style="font-size: 14px; color: #333333; margin-bottom: 6px;">{{ $label }}</div>
            @endif

            <div style="font-size: 20px; font-weight: bold; letter-spacing: 1px; color: #0f172a;">{{ $code }}</div>
        </td>
    </tr>
</table>
