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

    @auth('customer')
        <div class="mx-4 mt-4">
            <div class="mx-auto max-w-[400px] rounded border border-border-color py-3 text-center">
                <x-shop::form
                    method="DELETE"
                    action="{{ route('shop.customer.session.destroy') }}"
                    id="customerLogout"
                />

                <a
                    class="flex items-center justify-center gap-2 text-base text-primary font-medium hover:bg-bg-secondary transition px-4 py-1"
                    href="{{ route('shop.customer.session.destroy') }}"
                    onclick="event.preventDefault(); document.getElementById('customerLogout').submit();"
                >
                    @lang('shop::app.components.layouts.header.desktop.bottom.logout')
                </a>
            </div>
        </div>
    @endauth
</x-shop::layouts.account>
