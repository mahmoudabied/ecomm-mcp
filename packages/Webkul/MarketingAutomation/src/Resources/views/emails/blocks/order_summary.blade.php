<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px; border-top: 1px solid #eeeeee;">
    @forelse ($context['order']['items'] ?? [] as $item)
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee; font-size: 14px; color: #333333;">
                {{ $item['name'] ?? '' }}

                <span style="color: #888888;">x {{ (string) ($item['qty'] ?? '') }}</span>
            </td>
            <td align="end" style="padding: 12px 0; border-bottom: 1px solid #eeeeee; font-size: 14px; color: #333333;">
                {{ $item['price_formatted'] ?? '' }}
            </td>
        </tr>
    @empty
        <tr>
            <td style="padding: 12px 0; font-size: 14px; color: #888888;"></td>
        </tr>
    @endforelse

    <tr>
        <td style="padding: 12px 0; font-size: 15px; font-weight: bold; color: #333333;"></td>
        <td align="end" style="padding: 12px 0; font-size: 15px; font-weight: bold; color: #333333;">
            {{ $context['order']['total'] ?? '' }}
        </td>
    </tr>
</table>
