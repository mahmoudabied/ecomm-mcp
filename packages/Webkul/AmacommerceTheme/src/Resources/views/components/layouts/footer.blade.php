{!! view_render_event('bagisto.shop.components.layouts.footer.before') !!}

<footer class="bg-black text-white">
    <div class="max-w-content mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 py-16 px-4">
            <div>
                <h3 class="text-2xl font-bold mb-4">Exclusive</h3>
                <p class="text-lg mb-4">Subscribe</p>
                <p class="text-sm text-text-secondary mb-4">Get 10% off your first order</p>
                <div class="flex border border-white/40 rounded">
                    <input type="email" placeholder="Enter your email" class="bg-transparent px-4 py-2 text-sm outline-none flex-grow text-white placeholder-gray-400" />
                    <button class="px-3">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
            </div>

            <div>
                <h3 class="text-xl font-medium mb-6">Support</h3>
                <p class="text-sm text-text-secondary mb-4">111 Bijoy sarani, Dhaka, DH 1515, Bangladesh.</p>
                <p class="text-sm text-text-secondary mb-4">exclusive@gmail.com</p>
                <p class="text-sm text-text-secondary">+88015-88888-9999</p>
            </div>

            <div>
                <h3 class="text-xl font-medium mb-6">Account</h3>
                <ul class="space-y-3 text-sm text-text-secondary">
                    <li><a href="{{ route('shop.customers.account.profile.index') }}">My Account</a></li>
                    <li><a href="{{ route('shop.customers.register.index') }}">Login / Register</a></li>
                    <li><a href="{{ route('shop.checkout.cart.index') }}">Cart</a></li>
                    <li><a href="{{ route('shop.customers.account.wishlist.index') }}">Wishlist</a></li>
                    <li><a href="{{ route('shop.home.index') }}">Shop</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-xl font-medium mb-6">Quick Link</h3>
                <ul class="space-y-3 text-sm text-text-secondary">
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms Of Use</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="{{ route('shop.home.contact_us') }}">Contact</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-xl font-medium mb-6">Download App</h3>
                <p class="text-xs text-text-secondary mb-3">Save $3 with App New User Only</p>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-[76px] h-[76px] bg-white/10 flex items-center justify-center rounded text-xs text-text-secondary">QR</div>
                    <div class="flex flex-col gap-2">
                        <div class="bg-white/10 px-3 py-2 text-xs rounded flex items-center gap-2">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M3 20.5v-17c0-.59.34-1.11.84-1.35L13.69 12l-9.85 9.85c-.5-.24-.84-.76-.84-1.35zm13.81-5.38L6.05 21.34l8.49-8.49 2.27 2.27zm.91-.91L19.59 12 17.72 9.79l-2.27 2.27 2.27 2.15zM6.05 2.66l10.76 6.22-2.27 2.27L6.05 2.66z"/></svg>
                            Google Play
                        </div>
                        <div class="bg-white/10 px-3 py-2 text-xs rounded flex items-center gap-2">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                            App Store
                        </div>
                    </div>
                </div>
                <div class="flex gap-6 mt-6">
                    <a href="#" aria-label="Facebook">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    </a>
                    <a href="#" aria-label="Twitter">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
                    </a>
                    <a href="#" aria-label="Instagram">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="none" stroke="white" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="white" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.5"/></svg>
                    </a>
                    <a href="#" aria-label="LinkedIn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="border-t border-white/20 mt-8 pt-8 text-center text-sm text-text-secondary pb-8">
            &copy; Copyright Rimel 2022. All right reserved
        </div>
    </div>
</footer>

{!! view_render_event('bagisto.shop.components.layouts.footer.after') !!}
