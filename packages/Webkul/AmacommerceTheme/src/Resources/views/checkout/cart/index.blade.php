@push('meta')
    <meta name="description" content="@lang('shop::app.checkout.cart.index.cart')"/>
    <meta name="keywords" content="@lang('shop::app.checkout.cart.index.cart')"/>
@endPush

<x-shop::layouts
    :has-header="false"
    :has-feature="false"
    :has-footer="false"
>
    <x-slot:title>
        @lang('shop::app.checkout.cart.index.cart')
    </x-slot>

    {!! view_render_event('bagisto.shop.checkout.cart.header.before') !!}

    <div class="flex flex-wrap">
        <div class="flex w-full justify-between border border-b border-l-0 border-r-0 border-t-0 px-[60px] py-4 max-lg:px-8 max-md:px-4">
            <div class="flex items-center gap-x-14 max-[1180px]:gap-x-9">
                {!! view_render_event('bagisto.shop.checkout.cart.logo.before') !!}

                <a
                    href="{{ route('shop.home.index') }}"
                    class="flex min-h-[30px]"
                    aria-label="@lang('shop::app.checkout.cart.index.bagisto')"
                >
                    <img
                        src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}"
                        alt="{{ config('app.name') }}"
                        width="131"
                        height="29"
                    >
                </a>

                {!! view_render_event('bagisto.shop.checkout.cart.logo.after') !!}
            </div>

            @guest('customer')
                @include('shop::checkout.login')
            @endguest
        </div>
    </div>

    {!! view_render_event('bagisto.shop.checkout.cart.header.after') !!}

    <div class="flex-auto">
        <div class="max-w-content mx-auto px-4">

            {!! view_render_event('bagisto.shop.checkout.cart.breadcrumbs.before') !!}

            @if (core()->getConfigData('general.general.breadcrumbs.shop'))
                <div class="flex items-center gap-2 text-sm text-text-secondary py-6">
                    <a href="{{ route('shop.home.index') }}" class="hover:text-primary">Home</a>
                    <span>/</span>
                    <span>Cart</span>
                </div>
            @endif

            {!! view_render_event('bagisto.shop.checkout.cart.breadcrumbs.after') !!}

            @php
                $errors = \Webkul\Checkout\Facades\Cart::getErrors();
            @endphp

            @if (! empty($errors) && $errors['error_code'] === 'MINIMUM_ORDER_AMOUNT')
                <div class="mb-5 rounded bg-[#FFF3CD] px-5 py-3 text-[#383D41]">
                    {{ $errors['message'] }}: {{ $errors['amount'] }}
                </div>
            @endif

            <v-cart ref="vCart">
                <div class="animate-pulse py-8">
                    <div class="h-8 bg-bg-secondary rounded w-32 mb-8"></div>
                    <div class="grid gap-4">
                        <div class="h-24 bg-bg-secondary rounded" v-for="i in 3"></div>
                    </div>
                </div>
            </v-cart>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-cart-template">
            <div>
                <template v-if="isLoading">
                    <div class="animate-pulse py-8">
                        <div class="h-8 bg-bg-secondary rounded w-32 mb-8"></div>
                        <div class="grid gap-4">
                            <div class="h-24 bg-bg-secondary rounded" v-for="i in 3"></div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div v-if="cart?.items?.length" class="pb-12">
                        {!! view_render_event('bagisto.shop.checkout.cart.item.listing.before') !!}

                        <table class="w-full hidden md:table">
                            <thead>
                                <tr class="border-b border-border-color text-sm text-text-secondary">
                                    <th class="text-left pb-4 font-medium">Product</th>
                                    <th class="text-left pb-4 font-medium">Price</th>
                                    <th class="text-left pb-4 font-medium">Quantity</th>
                                    <th class="text-left pb-4 font-medium">Subtotal</th>
                                    <th class="pb-4 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-border-color" v-for="item in cart?.items">
                                    <td class="py-6">
                                        <div class="flex items-center gap-4">
                                            <a :href="`{{ route('shop.product_or_category.index', '') }}/${item.product_url_key}`">
                                                <img
                                                    :src="item.base_image.small_image_url"
                                                    :alt="item.name"
                                                    class="w-[54px] h-[54px] rounded object-cover"
                                                />
                                            </a>
                                            <div>
                                                <a :href="`{{ route('shop.product_or_category.index', '') }}/${item.product_url_key}`" class="font-medium hover:text-primary block">
                                                    @{{ item.name }}
                                                </a>
                                                <div v-if="item.options && item.options.length" class="mt-1 text-xs text-text-secondary">
                                                    <template v-for="attr in item.options">
                                                        <span>@{{ attr.attribute_name }}: @{{ attr.option_label }} </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-6">
                                        <template v-if="displayTax.prices == 'including_tax'">
                                            <span class="font-medium">@{{ item.formatted_price_incl_tax }}</span>
                                        </template>
                                        <template v-else>
                                            <span class="font-medium">@{{ item.formatted_price }}</span>
                                        </template>
                                    </td>
                                    <td class="py-6">
                                        <div class="flex items-center border border-border-color rounded w-[72px]" v-if="item.can_change_qty">
                                            <button
                                                class="px-2 py-1.5 text-sm hover:bg-bg-secondary"
                                                @click="decrement(item)"
                                            >&minus;</button>
                                            <input
                                                type="number"
                                                :value="item.quantity"
                                                class="w-8 text-center text-sm border-x border-border-color py-1.5 outline-none"
                                                readonly
                                            />
                                            <button
                                                class="px-2 py-1.5 text-sm hover:bg-bg-secondary"
                                                @click="increment(item)"
                                            >+</button>
                                        </div>
                                        <span v-else class="text-sm text-text-secondary">@{{ item.quantity }}</span>
                                    </td>
                                    <td class="py-6">
                                        <template v-if="displayTax.prices == 'including_tax'">
                                            <span class="font-medium">@{{ item.formatted_total_incl_tax }}</span>
                                        </template>
                                        <template v-else-if="displayTax.prices == 'both'">
                                            <span class="font-medium">@{{ item.formatted_total_incl_tax }}</span>
                                            <span class="text-xs text-text-secondary block">excl. @{{ item.formatted_total }}</span>
                                        </template>
                                        <template v-else>
                                            <span class="font-medium">@{{ item.formatted_total }}</span>
                                        </template>
                                    </td>
                                    <td class="py-6 text-right">
                                        <button
                                            @click="removeItem(item.id)"
                                            class="text-text-secondary hover:text-primary transition"
                                            aria-label="Remove item"
                                        >
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="md:hidden space-y-4">
                            <div v-for="item in cart?.items" class="border border-border-color rounded p-4">
                                <div class="flex items-start gap-4">
                                    <a :href="`{{ route('shop.product_or_category.index', '') }}/${item.product_url_key}`">
                                        <img
                                            :src="item.base_image.small_image_url"
                                            :alt="item.name"
                                            class="w-[54px] h-[54px] rounded object-cover"
                                        />
                                    </a>
                                    <div class="flex-1">
                                        <div class="flex justify-between">
                                            <a :href="`{{ route('shop.product_or_category.index', '') }}/${item.product_url_key}`" class="font-medium hover:text-primary">
                                                @{{ item.name }}
                                            </a>
                                            <button
                                                @click="removeItem(item.id)"
                                                class="text-text-secondary hover:text-primary transition"
                                                aria-label="Remove item"
                                            >
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                        </div>
                                        <div v-if="item.options && item.options.length" class="mt-1 text-xs text-text-secondary">
                                            <template v-for="attr in item.options">
                                                <span>@{{ attr.attribute_name }}: @{{ attr.option_label }} </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-4">
                                    <div class="flex items-center border border-border-color rounded w-[72px]" v-if="item.can_change_qty">
                                        <button class="px-2 py-1.5 text-sm hover:bg-bg-secondary" @click="decrement(item)">&minus;</button>
                                        <input type="number" :value="item.quantity" class="w-8 text-center text-sm border-x border-border-color py-1.5 outline-none" readonly />
                                        <button class="px-2 py-1.5 text-sm hover:bg-bg-secondary" @click="increment(item)">+</button>
                                    </div>
                                    <span v-else class="text-sm text-text-secondary">@{{ item.quantity }}</span>
                                    <span class="font-medium">
                                        <template v-if="displayTax.prices == 'including_tax'">@{{ item.formatted_total_incl_tax }}</template>
                                        <template v-else>@{{ item.formatted_total }}</template>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {!! view_render_event('bagisto.shop.checkout.cart.item.listing.after') !!}

                        <div class="flex flex-col sm:flex-row justify-between mt-6 gap-4">
                            <a
                                href="{{ route('shop.home.index') }}"
                                class="border border-border-color rounded px-8 py-3 sm:px-12 sm:py-4 text-sm sm:text-base font-medium hover:bg-bg-secondary transition text-center"
                            >
                                Return To Shop
                            </a>

                            <button
                                class="border border-border-color rounded px-8 py-3 sm:px-12 sm:py-4 text-sm sm:text-base font-medium hover:bg-bg-secondary transition"
                                @click="update()"
                                :disabled="isStoring"
                            >
                                Update Cart
                            </button>
                        </div>

                        <div class="flex flex-col lg:flex-row justify-between mt-10 gap-8 lg:gap-12">
                            <div>
                                <div class="flex gap-4">
                                    <input
                                        type="text"
                                        v-model="couponCode"
                                        class="border border-border-color rounded px-4 py-3 w-72 outline-none focus:border-primary transition"
                                        placeholder="Coupon Code"
                                    />
                                    <button
                                        class="bg-primary text-white px-12 py-3 rounded text-base font-medium hover:bg-primary-hover transition"
                                        @click="applyCouponCode"
                                    >
                                        Apply Coupon
                                    </button>
                                </div>

                                <div v-if="cart.coupon_code" class="mt-3 flex items-center gap-2 text-sm text-green-600">
                                    <span>Coupon "@{{ cart.coupon_code }}" applied!</span>
                                    <button @click="removeCoupon()" class="text-primary underline">Remove</button>
                                </div>
                            </div>

                            <div class="border-2 border-black rounded p-6 w-full lg:w-[470px] shrink-0">
                                <h3 class="text-xl font-medium mb-6">Cart Total</h3>

                                <template v-if="displayTax.subtotal == 'including_tax'">
                                    <div class="flex justify-between py-3 border-b border-border-color">
                                        <span class="text-base">Subtotal:</span>
                                        <span class="text-base font-medium">@{{ cart.formatted_sub_total_incl_tax }}</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="flex justify-between py-3 border-b border-border-color">
                                        <span class="text-base">Subtotal:</span>
                                        <span class="text-base font-medium">@{{ cart.formatted_sub_total }}</span>
                                    </div>
                                </template>

                                <div
                                    v-if="cart.discount_amount && parseFloat(cart.discount_amount) > 0"
                                    class="flex justify-between py-3 border-b border-border-color"
                                >
                                    <span class="text-base">Discount:</span>
                                    <span class="text-base font-medium text-primary">-@{{ cart.formatted_discount_amount }}</span>
                                </div>

                                <template v-if="displayTax.shipping == 'including_tax'">
                                    <div class="flex justify-between py-3 border-b border-border-color">
                                        <span class="text-base">Shipping:</span>
                                        <span class="text-base font-medium">@{{ cart.formatted_shipping_amount_incl_tax }}</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="flex justify-between py-3 border-b border-border-color">
                                        <span class="text-base">Shipping:</span>
                                        <span class="text-base font-medium">@{{ cart.formatted_shipping_amount }}</span>
                                    </div>
                                </template>

                                <div class="flex justify-between py-3">
                                    <span class="text-base font-medium">Total:</span>
                                    <span class="text-base font-medium">@{{ cart.formatted_grand_total }}</span>
                                </div>

                                <a
                                    href="{{ route('shop.checkout.onepage.index') }}"
                                    class="block bg-primary text-white w-full py-4 rounded text-base font-medium text-center mt-4 hover:bg-primary-hover transition"
                                >
                                    Proceed to checkout
                                </a>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-20 md:py-32 text-center"
                    >
                        <img
                            class="w-[120px] h-[120px] md:w-[180px] md:h-[180px] opacity-60"
                            src="{{ bagisto_asset('images/thank-you.png') }}"
                            alt="Empty cart"
                            loading="lazy"
                            decoding="async"
                        />

                        <h2 class="text-2xl md:text-3xl font-semibold mt-8">Your cart is empty</h2>
                        <p class="text-text-secondary mt-2 text-sm md:text-base">Looks like you haven't added anything to your cart yet.</p>

                        <a
                            href="{{ route('shop.home.index') }}"
                            class="mt-8 inline-block bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition"
                        >
                            Continue Shopping
                        </a>
                    </div>
                </template>
            </div>
        </script>

        <script type="module">
            app.component("v-cart", {
                template: '#v-cart-template',

                data() {
                    return {
                        cart: [],

                        couponCode: '',

                        applied: {
                            quantity: {},
                        },

                        displayTax: {
                            prices: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_prices') }}",

                            subtotal: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_subtotal') }}",

                            shipping: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_shipping_amount') }}",
                        },

                        isLoading: true,

                        isStoring: false,
                    }
                },

                mounted() {
                    this.getCart();
                },

                methods: {
                    getCart() {
                        this.$axios.get('{{ route('shop.api.checkout.cart.index') }}')
                            .then(response => {
                                this.cart = response.data.data;

                                this.isLoading = false;

                                if (response.data.message) {
                                    this.$emitter.emit('add-flash', { type: 'info', message: response.data.message });
                                }
                            })
                            .catch(error => {});
                    },

                    increment(item) {
                        item.quantity++;

                        this.applied.quantity[item.id] = item.quantity;
                    },

                    decrement(item) {
                        if (item.quantity > 1) {
                            item.quantity--;

                            this.applied.quantity[item.id] = item.quantity;
                        }
                    },

                    update() {
                        this.isStoring = true;

                        this.$axios.put('{{ route('shop.api.checkout.cart.update') }}', { qty: this.applied.quantity })
                            .then(response => {
                                if (response.data.message) {
                                    this.cart = response.data.data;

                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                } else {
                                    this.$emitter.emit('add-flash', { type: 'warning', message: response.data.data.message });
                                }

                                this.isStoring = false;
                            })
                            .catch(error => {
                                this.isStoring = false;
                            });
                    },

                    removeItem(itemId) {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.$axios.post('{{ route('shop.api.checkout.cart.destroy') }}', {
                                        '_method': 'DELETE',
                                        'cart_item_id': itemId,
                                    })
                                    .then(response => {
                                        this.cart = response.data.data;

                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                    })
                                    .catch(error => {});
                            }
                        });
                    },

                    applyCouponCode() {
                        if (! this.couponCode) return;

                        this.$axios.post('{{ route('shop.api.checkout.cart.coupon.apply') }}', { code: this.couponCode })
                            .then(response => {
                                this.cart = response.data.data;

                                this.couponCode = '';

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            })
                            .catch(error => {
                                if (error.response) {
                                    this.$emitter.emit('add-flash', { type: 'warning', message: error.response.data.message });
                                }
                            });
                    },

                    removeCoupon() {
                        this.$axios.delete('{{ route('shop.api.checkout.cart.coupon.remove') }}')
                            .then(response => {
                                this.cart = response.data.data;

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            })
                            .catch(error => console.log(error));
                    },
                }
            });
        </script>
    @endPushOnce
</x-shop::layouts>
