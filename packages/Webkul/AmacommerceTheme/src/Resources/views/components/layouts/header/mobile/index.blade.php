{!! view_render_event('bagisto.shop.components.layouts.header.mobile.before') !!}

<v-mobile-menu>
    <div class="flex items-center justify-between px-4 py-3 border-b border-border-color">
        <button class="w-10 h-10 flex items-center justify-center" @click="open = true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <a href="{{ route('shop.home.index') }}">
            <img src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}" height="29" alt="{{ config('app.name') }}" />
        </a>
        <div class="flex items-center gap-4">
            @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                <a href="{{ route('shop.customers.account.wishlist.index') }}" class="relative">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </a>
            @endif
            @if(core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                <a href="{{ route('shop.checkout.cart.index') }}" class="relative">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </a>
            @endif
        </div>
    </div>

    <div v-if="open" class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="fixed top-0 left-0 h-full w-[280px] bg-white shadow-xl z-50 flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border-color">
                <h2 class="text-lg font-semibold">Menu</h2>
                <button class="w-8 h-8 flex items-center justify-center" @click="open = false">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                <nav class="py-4">
                    <a href="{{ route('shop.home.index') }}" class="block px-6 py-3 text-base hover:bg-bg-secondary transition">Home</a>
                    <a href="{{ route('shop.home.contact_us.index') }}" class="block px-6 py-3 text-base hover:bg-bg-secondary transition">Contact</a>
                    <a href="{{ route('shop.cms.page', 'about-us') }}" class="block px-6 py-3 text-base hover:bg-bg-secondary transition">About</a>

                    @auth('customer')
                        <a href="{{ route('shop.customers.account.index') }}" class="block px-6 py-3 text-base hover:bg-bg-secondary transition">My Account</a>
                        <a href="{{ route('shop.customers.account.wishlist.index') }}" class="block px-6 py-3 text-base hover:bg-bg-secondary transition">Wishlist</a>
                        <div class="border-t border-border-color my-2"></div>
                        <x-shop::form method="DELETE" action="{{ route('shop.customer.session.destroy') }}" id="mobileLogoutForm" />
                        <a
                            href="{{ route('shop.customer.session.destroy') }}"
                            onclick="event.preventDefault(); document.getElementById('mobileLogoutForm').submit();"
                            class="block px-6 py-3 text-base text-primary hover:bg-bg-secondary transition"
                        >
                            Log Out
                        </a>
                    @else
                        <div class="border-t border-border-color my-2"></div>
                        <a href="{{ route('shop.customer.session.index') }}" class="block px-6 py-3 text-base hover:bg-bg-secondary transition">Log In</a>
                        <a href="{{ route('shop.customers.register.index') }}" class="block px-6 py-3 text-base hover:bg-bg-secondary transition">Sign Up</a>
                    @endauth
                </nav>
            </div>
        </div>
    </div>
</v-mobile-menu>

{!! view_render_event('bagisto.shop.components.layouts.header.mobile.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-mobile-menu-template">
        <div>
            <slot></slot>
        </div>
    </script>

    <script type="module">
        app.component('v-mobile-menu', {
            template: '#v-mobile-menu-template',
            data() {
                return {
                    open: false,
                };
            },
        });
    </script>
@endpushOnce
