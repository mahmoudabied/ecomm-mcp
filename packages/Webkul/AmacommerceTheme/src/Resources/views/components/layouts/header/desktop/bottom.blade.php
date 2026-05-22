{!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.before') !!}

<div class="border-b border-border-color py-4">
    <div class="max-w-content mx-auto flex items-center justify-between px-4">
        <a href="{{ route('shop.home.index') }}">
            <img
                src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}"
                width="131"
                height="29"
                alt="{{ config('app.name') }}"
            />
        </a>

        <ul class="flex items-center gap-12 text-base">
            <li><a href="{{ route('shop.home.index') }}">Home</a></li>
            <li><a href="{{ route('shop.home.contact_us') }}">Contact</a></li>
            <li><a href="{{ route('shop.cms.page', 'about-us') }}">About</a></li>
            <li><a href="{{ route('shop.customers.register.index') }}">Sign Up</a></li>
        </ul>

        <div class="flex items-center gap-6">
            <form action="{{ route('shop.search.index') }}" class="flex items-center bg-bg-secondary rounded px-3 py-2">
                <input
                    type="text"
                    name="query"
                    placeholder="What are you looking for?"
                    class="bg-transparent text-sm outline-none w-48"
                />
                <button type="submit">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 opacity-50 hover:opacity-100 transition"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
            </form>

            @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                <a href="{{ route('shop.customers.account.wishlist.index') }}" class="relative">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </a>
            @endif

            @if(core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                <a href="{{ route('shop.checkout.cart.index') }}" class="relative">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </a>
            @endif
        </div>
    </div>
</div>

{!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.after') !!}
