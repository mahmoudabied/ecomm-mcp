<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px; border-top: 1px solid #eeeeee;">
    <tr>
        <td style="text-align: {{ $align }}; padding: 12px 0 4px; font-size: 16px; font-weight: bold; color: #0f172a;">
            {{ trans('marketing_automation::app.emails.blocks.product-grid-title') }}
        </td>
    </tr>
    <tr>
        <td>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    @forelse ($items ?? [] as $item)
                        <td width="33%" style="padding: 12px 8px 0 0; text-align: {{ $align }}; vertical-align: top;">
                            @if (! empty($item['image_url']))
                                <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" width="100" style="display: block; border-radius: 4px; margin-bottom: 8px;">
                            @endif
                            <a href="{{ $item['url'] }}" style="font-size: 14px; color: #333333; text-decoration: none;">{{ $item['name'] }}</a>
                            <div style="font-size: 14px; font-weight: bold; color: #0f172a;">{{ $item['price_formatted'] }}</div>
                        </td>
                    @empty
                        <td style="padding: 12px 0; font-size: 14px; color: #888888;"></td>
                    @endforelse
                </tr>
            </table>
        </td>
    </tr>
</table>
