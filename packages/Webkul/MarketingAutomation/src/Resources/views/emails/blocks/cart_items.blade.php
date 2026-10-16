<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px; border-top: 1px solid #eeeeee;">
    @forelse ($context['cart']['items'] ?? [] as $item)
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee; width: 64px;">
                @if (! empty($item['image_url']))
                    <img src="{{ $item['image_url'] }}" alt="" width="56" height="56" style="display: block; border-radius: 4px;">
                @endif
            </td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #eeeeee; font-size: 14px; color: #333333; text-align: {{ $align }};">
                {{ $item['name'] ?? '' }}

                <div style="color: #888888;">x {{ $item['qty'] ?? '' }}</div>
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
</table>
