<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td style="font-size: 15px; line-height: 24px; padding-bottom: 24px;">
            @lang('marketing_automation::app.consent.confirmation.greeting')
        </td>
    </tr>
    <tr>
        <td style="font-size: 15px; line-height: 24px; padding-bottom: 24px;">
            @lang('marketing_automation::app.consent.confirmation.intro', [
                'channel' => trans('marketing_automation::app.consent.channels.'.$channel->value),
            ])
        </td>
    </tr>
    <tr>
        <td align="left" style="border-radius: 6px;" bgcolor="#0f172a">
            <a href="{{ $link }}" style="display: inline-block; padding: 12px 24px; font-size: 15px; font-weight: bold; color: #ffffff; text-decoration: none;">
                @lang('marketing_automation::app.consent.confirmation.button')
            </a>
        </td>
    </tr>
    <tr>
        <td style="font-size: 13px; line-height: 20px; color: #666666; padding-top: 24px;">
            @lang('marketing_automation::app.consent.confirmation.ignore')
        </td>
    </tr>
</table>
