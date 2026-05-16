@push('meta')
    <meta name="description" content="@lang('shop::app.checkout.onepage.index.checkout')"/>
    <meta name="keywords" content="@lang('shop::app.checkout.onepage.index.checkout')"/>
@endPush

<x-shop::layouts
    :has-header="false"
    :has-feature="false"
    :has-footer="false"
>
    <x-slot:title>
        @lang('shop::app.checkout.onepage.index.checkout')
    </x-slot>

    {!! view_render_event('bagisto.shop.checkout.onepage.header.before') !!}

    <div class="flex-wrap">
        <div class="flex w-full justify-between border border-b border-l-0 border-r-0 border-t-0 px-[60px] py-4 max-lg:px-8 max-sm:px-4">
            <div class="flex items-center gap-x-14 max-[1180px]:gap-x-9">
                <a
                    href="{{ route('shop.home.index') }}"
                    class="flex min-h-[30px]"
                    aria-label="@lang('shop::checkout.onepage.index.bagisto')"
                >
                    <img
                        src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}"
                        alt="{{ config('app.name') }}"
                        width="131"
                        height="29"
                    >
                </a>
            </div>

            @guest('customer')
                @include('shop::checkout.login')
            @endguest
        </div>
    </div>

    {!! view_render_event('bagisto.shop.checkout.onepage.header.after') !!}

    <div class="max-w-content mx-auto px-4">

        {!! view_render_event('bagisto.shop.checkout.onepage.breadcrumbs.before') !!}

        <div class="flex items-center gap-2 text-sm text-text-secondary py-6 flex-wrap">
            <a href="{{ route('shop.home.index') }}" class="hover:text-primary">Account</a>
            <span>/</span>
            <a href="{{ route('shop.customers.account.index') }}" class="hover:text-primary">My Account</a>
            <span>/</span>
            <a href="{{ route('shop.home.index') }}" class="hover:text-primary">Product</a>
            <span>/</span>
            <a href="{{ route('shop.checkout.cart.index') }}" class="hover:text-primary">View Cart</a>
            <span>/</span>
            <span class="font-medium text-text2">CheckOut</span>
        </div>

        {!! view_render_event('bagisto.shop.checkout.onepage.breadcrumbs.after') !!}

        <v-checkout>
            <div class="animate-pulse py-8">
                <div class="flex gap-[170px] max-lg:flex-col max-lg:gap-8">
                    <div class="flex-1">
                        <div class="h-10 bg-bg-secondary rounded w-48 mb-10"></div>
                        <div class="grid gap-4">
                            <div class="h-16 bg-bg-secondary rounded" v-for="i in 5"></div>
                        </div>
                    </div>
                    <div class="w-[470px] max-w-full">
                        <div class="h-8 bg-bg-secondary rounded w-40 mb-6"></div>
                        <div class="grid gap-3">
                            <div class="h-12 bg-bg-secondary rounded" v-for="i in 4"></div>
                        </div>
                    </div>
                </div>
            </div>
        </v-checkout>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-checkout-template">
            <template v-if="! cart">
                <div class="animate-pulse py-8">
                    <div class="flex gap-[170px] max-lg:flex-col max-lg:gap-8">
                        <div class="flex-1">
                            <div class="h-10 bg-bg-secondary rounded w-48 mb-10"></div>
                            <div class="grid gap-4">
                                <div class="h-16 bg-bg-secondary rounded" v-for="i in 5"></div>
                            </div>
                        </div>
                        <div class="w-[470px] max-w-full">
                            <div class="h-8 bg-bg-secondary rounded w-40 mb-6"></div>
                            <div class="grid gap-3">
                                <div class="h-12 bg-bg-secondary rounded" v-for="i in 4"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="flex gap-[170px] max-lg:flex-col max-lg:gap-8 pb-12">
                    <div class="flex-1" id="steps-container">
                        <h2 class="text-4xl font-medium mb-10">Billing Details</h2>

                        <template v-if="['address', 'shipping', 'payment', 'review'].includes(currentStep)">
                            @include('shop::checkout.onepage.address')
                        </template>

                        <template v-if="cart.have_stockable_items && ['shipping', 'payment', 'review'].includes(currentStep)">
                            @include('shop::checkout.onepage.shipping')
                        </template>

                        <template v-if="['payment', 'review'].includes(currentStep)">
                            @include('shop::checkout.onepage.payment')
                        </template>
                    </div>

                    <div class="w-[470px] max-w-full">
                        <h3 class="text-xl font-medium mb-6">Order Summary</h3>

                        <div v-for="item in cart.items" class="flex justify-between items-center py-3 border-b border-border-color">
                            <div class="flex items-center gap-4">
                                <img
                                    :src="item.base_image.small_image_url"
                                    :alt="item.name"
                                    class="w-[54px] h-[32px] object-contain rounded"
                                />
                                <span class="text-sm font-medium max-w-[180px] truncate">@{{ item.name }}</span>
                            </div>
                            <span class="text-sm font-medium whitespace-nowrap">
                                <template v-if="displayTax.prices == 'including_tax'">
                                    @{{ item.formatted_total_incl_tax }}
                                </template>
                                <template v-else>
                                    @{{ item.formatted_total }}
                                </template>
                            </span>
                        </div>

                        <div class="flex justify-between py-3 border-b border-border-color">
                            <span class="text-base">Subtotal:</span>
                            <template v-if="displayTax.subtotal == 'including_tax'">
                                <span class="text-base font-medium">@{{ cart.formatted_sub_total_incl_tax }}</span>
                            </template>
                            <template v-else>
                                <span class="text-base font-medium">@{{ cart.formatted_sub_total }}</span>
                            </template>
                        </div>

                        <div
                            v-if="cart.discount_amount && parseFloat(cart.discount_amount) > 0"
                            class="flex justify-between py-3 border-b border-border-color"
                        >
                            <span class="text-base">Discount:</span>
                            <span class="text-base font-medium text-primary">-@{{ cart.formatted_discount_amount }}</span>
                        </div>

                        <div class="flex justify-between py-3 border-b border-border-color">
                            <span class="text-base">Shipping:</span>
                            <template v-if="displayTax.shipping == 'including_tax'">
                                <span class="text-base font-medium">@{{ cart.formatted_shipping_amount_incl_tax }}</span>
                            </template>
                            <template v-else>
                                <span class="text-base font-medium">@{{ cart.formatted_shipping_amount }}</span>
                            </template>
                        </div>

                        <div class="flex justify-between py-4">
                            <span class="text-base font-medium">Total:</span>
                            <span class="text-base font-medium">@{{ cart.formatted_grand_total }}</span>
                        </div>

                        <div class="flex gap-3 mt-4">
                            <input
                                type="text"
                                v-model="couponCode"
                                class="border border-border-color rounded px-4 py-3 flex-grow outline-none focus:border-primary transition"
                                placeholder="Coupon Code"
                            />
                            <button
                                class="bg-primary text-white px-8 py-3 rounded text-base font-medium hover:bg-primary-hover transition"
                                @click="applyCouponCode"
                            >
                                Apply Coupon
                            </button>
                        </div>

                        <div v-if="cart.coupon_code" class="mt-2 flex items-center gap-2 text-sm text-green-600">
                            <span>Coupon "@{{ cart.coupon_code }}" applied!</span>
                            <button @click="removeCoupon()" class="text-primary underline">Remove</button>
                        </div>

                        <div
                            class="flex justify-end mt-6"
                            v-if="canPlaceOrder"
                        >
                            <template v-if="cart.payment_method == 'paypal_smart_button'">
                                {!! view_render_event('bagisto.shop.checkout.onepage.summary.paypal_smart_button.before') !!}

                                <v-paypal-smart-button></v-paypal-smart-button>

                                {!! view_render_event('bagisto.shop.checkout.onepage.summary.paypal_smart_button.after') !!}
                            </template>

                            <template v-else>
                                <button
                                    class="bg-primary text-white w-full py-4 rounded text-base font-medium hover:bg-primary-hover transition"
                                    :disabled="isPlacingOrder"
                                    @click="placeOrder"
                                >
                                    Place Order
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </script>

        <script type="module">
            app.component('v-checkout', {
                template: '#v-checkout-template',

                data() {
                    return {
                        cart: null,

                        couponCode: '',

                        displayTax: {
                            prices: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_prices') }}",

                            subtotal: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_subtotal') }}",

                            shipping: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_shipping_amount') }}",
                        },

                        isPlacingOrder: false,

                        currentStep: 'address',

                        shippingMethods: null,

                        paymentMethods: null,

                        canPlaceOrder: false,
                    }
                },

                mounted() {
                    this.getCart();
                },

                methods: {
                    getCart() {
                        this.$axios.get("{{ route('shop.checkout.onepage.summary') }}")
                            .then(response => {
                                this.cart = response.data.data;

                                this.scrollToCurrentStep();
                            })
                            .catch(error => {});
                    },

                    stepForward(step) {
                        this.currentStep = step;

                        if (step == 'review') {
                            this.canPlaceOrder = true;

                            return;
                        }

                        this.canPlaceOrder = false;

                        if (this.currentStep == 'shipping') {
                            this.shippingMethods = null;
                        } else if (this.currentStep == 'payment') {
                            this.paymentMethods = null;
                        }
                    },

                    stepProcessed(data) {
                        if (this.currentStep == 'shipping') {
                            this.shippingMethods = data;
                        } else if (this.currentStep == 'payment') {
                            this.paymentMethods = data;
                        }

                        this.getCart();
                    },

                    scrollToCurrentStep() {
                        let container = document.getElementById('steps-container');

                        if (! container) {
                            return;
                        }

                        container.scrollIntoView({
                            behavior: 'smooth',
                            block: 'end'
                        });
                    },

                    placeOrder() {
                        this.isPlacingOrder = true;

                        this.$axios.post('{{ route('shop.checkout.onepage.orders.store') }}')
                            .then(response => {
                                if (response.data.data.redirect) {
                                    window.location.href = response.data.data.redirect_url;
                                } else {
                                    window.location.href = '{{ route('shop.checkout.onepage.success') }}';
                                }

                                this.isPlacingOrder = false;
                            })
                            .catch(error => {
                                this.isPlacingOrder = false

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
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
                },
            });
        </script>
    @endPushOnce
</x-shop::layouts>
