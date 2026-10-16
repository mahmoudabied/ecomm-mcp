<x-shop::layouts.account>
    <!-- Page Title -->
    <x-slot:title>
        @lang('marketing_automation::app.shop.preferences.title')
    </x-slot>

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <div class="mx-4 flex-auto max-md:mx-6 max-sm:mx-4">
        <div class="mb-8 flex items-center max-md:mb-5">
            <a
                class="grid md:hidden"
                href="{{ route('shop.customers.account.profile.index') }}"
            >
                <span class="icon-arrow-left rtl:icon-arrow-right text-2xl"></span>
            </a>

            <h2 class="text-2xl font-medium max-md:text-xl max-sm:text-base ltr:ml-2.5 md:ltr:ml-0 rtl:mr-2.5 md:rtl:mr-0">
                @lang('marketing_automation::app.shop.preferences.title')
            </h2>
        </div>

        <form
            action="{{ route('shop.customers.account.marketing-preferences.update') }}"
            method="POST"
            class="grid gap-4"
        >
            @csrf
            @method('PUT')

            @foreach (['email' => trans('marketing_automation::app.shop.preferences.email'), 'sms' => trans('marketing_automation::app.shop.preferences.sms'), 'whatsapp' => trans('marketing_automation::app.shop.preferences.whatsapp')] as $channel => $label)
                <label class="flex items-center gap-3 text-sm">
                    <input
                        type="checkbox"
                        name="consents[{{ $channel }}]"
                        value="1"
                        @checked($consents[$channel] ?? false)
                        class="accent-black dark:accent-white"
                    >

                    <span>{{ $label }}</span>
                </label>
            @endforeach

            <button
                type="submit"
                class="primary-button max-w-xs"
            >
                @lang('marketing_automation::app.shop.preferences.save')
            </button>
        </form>

        <div class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-800">
            <a
                href="{{ route('shop.customers.account.marketing-preferences.export') }}"
                class="text-sm font-medium underline"
            >
                @lang('marketing_automation::app.shop.preferences.export')
            </a>
        </div>
    </div>
</x-shop::layouts.account>
