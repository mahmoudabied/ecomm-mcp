<x-shop::layouts
    :has-feature="false"
>
    <x-slot:title>
        @lang('marketing_automation::app.consent.confirmation.title')
    </x-slot>

    <div class="container mt-12 max-1180:px-5">
        <div class="m-auto w-full max-w-[600px] rounded-xl border border-zinc-200 p-10 max-md:p-6">
            <h1 class="text-2xl font-medium">
                @lang('marketing_automation::app.consent.confirmation.title')
            </h1>

            @if ($confirmed)
                <p class="mt-4 text-zinc-600">
                    @lang('marketing_automation::app.consent.confirmation.done', ['channel' => trans('marketing_automation::app.consent.channels.'.$channel->value)])
                </p>
            @else
                <p class="mt-4 text-zinc-600">
                    @lang('marketing_automation::app.consent.confirmation.confirm-text', ['channel' => trans('marketing_automation::app.consent.channels.'.$channel->value)])
                </p>

                <form
                    method="POST"
                    action="{{ route('shop.automation.consent.confirm', $token) }}"
                    class="mt-6"
                >
                    @csrf

                    <button
                        type="submit"
                        class="primary-button rounded-2xl px-11 py-3"
                    >
                        @lang('marketing_automation::app.consent.confirmation.button')
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-shop::layouts>
