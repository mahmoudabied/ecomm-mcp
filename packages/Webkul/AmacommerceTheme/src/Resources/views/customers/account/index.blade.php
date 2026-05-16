<x-shop::layouts.account>
    <x-slot:title>
        @lang('shop::app.customers.account.orders.title')
    </x-slot>

    @if ((core()->getConfigData('general.general.breadcrumbs.shop')))
        @section('breadcrumbs')
            <x-shop::breadcrumbs name="orders" />
        @endSection
    @endif

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <span class="mb-5 mt-2 w-full border-t border-border-color"></span>

    @auth('customer')
        <div class="mx-4">
            <div class="mx-auto w-[400px] rounded-lg border border-border-color py-2.5 text-center max-sm:w-full max-sm:py-1.5">
                <x-shop::form
                    method="DELETE"
                    action="{{ route('shop.customer.session.destroy') }}"
                    id="customerLogout"
                />

                <a
                    class="flex items-center justify-center gap-1.5 text-base hover:bg-bg-secondary transition"
                    href="{{ route('shop.customer.session.destroy') }}"
                    onclick="event.preventDefault(); document.getElementById('customerLogout').submit();"
                >
                    @lang('shop::app.components.layouts.header.desktop.bottom.logout')
                </a>
            </div>
        </div>
    @endauth
</x-shop::layouts.account>
