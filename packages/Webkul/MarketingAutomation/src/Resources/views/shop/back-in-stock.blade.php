@php
    $isSaleable = isset($product) && $product->isSaleable();
@endphp

@if (! $isSaleable)
    <div
        id="ma-back-in-stock"
        class="mt-4 rounded border border-gray-200 p-4 dark:border-gray-800"
    >
        <p class="mb-2 text-sm font-medium text-gray-800 dark:text-white">
            @lang('marketing_automation::app.shop.back-in-stock.title')
        </p>

        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
            @lang('marketing_automation::app.shop.back-in-stock.hint')
        </p>

        <form
            action="{{ route('shop.automation.back-in-stock') }}"
            method="POST"
            class="grid gap-3"
            data-ma-bis-form
        >
            @csrf

            <input
                type="hidden"
                name="product_id"
                value="{{ $product->id }}"
            >

            <input
                type="email"
                name="email"
                placeholder="@lang('marketing_automation::app.shop.back-in-stock.email')"
                class="flex w-full rounded border bg-white px-3 py-2 text-sm dark:bg-gray-900"
            >

            <input
                type="tel"
                name="phone"
                placeholder="@lang('marketing_automation::app.shop.back-in-stock.phone')"
                class="flex w-full rounded border bg-white px-3 py-2 text-sm dark:bg-gray-900"
            >

            <select
                name="consent_channel"
                class="flex w-full rounded border bg-white px-3 py-2 text-sm dark:bg-gray-900"
            >
                <option value="email">@lang('marketing_automation::app.shop.back-in-stock.via-email')</option>

                <option value="sms">@lang('marketing_automation::app.shop.back-in-stock.via-sms')</option>

                <option value="whatsapp">@lang('marketing_automation::app.shop.back-in-stock.via-whatsapp')</option>
            </select>

            <button
                type="submit"
                class="primary-button"
            >
                @lang('marketing_automation::app.shop.back-in-stock.submit')
            </button>
        </form>

        <p
            data-ma-bis-success
            class="mt-2 hidden text-sm font-medium text-green-600"
        >
            @lang('marketing_automation::app.shop.back-in-stock.created')
        </p>
    </div>

    @pushOnce('scripts')
        <script>
            document.querySelector('[data-ma-bis-form]')?.addEventListener('submit', function (event) {
                event.preventDefault();

                fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(Object.fromEntries(new FormData(this))),
                }).then((response) => {
                    if (! response.ok) {
                        return;
                    }

                    this.classList.add('hidden');

                    document.querySelector('[data-ma-bis-success]')?.classList.remove('hidden');
                });
            });
        </script>
    @endPushOnce
@endif
