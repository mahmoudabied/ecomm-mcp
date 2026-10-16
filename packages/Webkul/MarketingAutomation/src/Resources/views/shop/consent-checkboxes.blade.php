<div class="grid gap-2 text-sm">
    <label class="flex items-center gap-2">
        <input
            type="checkbox"
            name="marketing_consent[email]"
            value="1"
            class="accent-black dark:accent-white"
        >

        <span>@lang('marketing_automation::app.shop.preferences.email')</span>
    </label>

    <label class="flex items-center gap-2">
        <input
            type="checkbox"
            name="marketing_consent[sms]"
            value="1"
            class="accent-black dark:accent-white"
        >

        <span>@lang('marketing_automation::app.shop.preferences.sms')</span>
    </label>

    <label class="flex items-center gap-2">
        <input
            type="checkbox"
            name="marketing_consent[whatsapp]"
            value="1"
            class="accent-black dark:accent-white"
        >

        <span>@lang('marketing_automation::app.shop.preferences.whatsapp')</span>
    </label>
</div>
