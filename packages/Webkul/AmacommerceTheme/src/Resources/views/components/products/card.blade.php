<v-product-card
    {{ $attributes }}
    :product="product"
>
</v-product-card>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-product-card-template"
    >
        <div class="w-full">
            <div class="bg-bg-secondary h-[250px] rounded relative flex items-center justify-center group">
                <span
                    v-if="product.special_price"
                    class="absolute top-3 left-3 bg-primary text-white text-xs px-3 py-1 rounded"
                >
                    -@{{ Math.round((1 - product.special_price / product.price) * 100) }}%
                </span>

                <div class="absolute top-3 right-3 flex flex-col gap-2">
                    @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                        <button
                            class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow hover:bg-gray-100"
                            @click="addToWishlist()"
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                :fill="product.is_wishlist ? '#DB4444' : 'none'"
                                :stroke="product.is_wishlist ? '#DB4444' : 'currentColor'"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </button>
                    @endif

                    @if (core()->getConfigData('catalog.products.settings.compare_option'))
                        <button
                            class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow hover:bg-gray-100"
                            @click="addToCompare(product.id)"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    @endif
                </div>

                @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                    <div
                        class="absolute bottom-0 left-0 right-0 bg-black text-white text-center py-2 text-sm opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                        @click="addToCart()"
                    >
                        Add To Cart
                    </div>
                @endif

                <a
                    :href="`{{ route('shop.product_or_category.index', '') }}/${product.url_key}`"
                    :aria-label="product.name"
                >
                    <img
                        :src="product.base_image.medium_image_url"
                        :alt="product.name"
                        class="max-h-[180px] object-contain"
                    />
                </a>
            </div>

            <div class="mt-4">
                <a
                    :href="`{{ route('shop.product_or_category.index', '') }}/${product.url_key}`"
                    class="font-medium block truncate"
                >
                    @{{ product.name }}
                </a>

                <div class="flex gap-3 mt-2">
                    <span class="text-primary font-medium">
                        <template v-if="product.formatted_special_price">
                            @{{ product.formatted_special_price }}
                        </template>
                        <template v-else>
                            @{{ product.formatted_price }}
                        </template>
                    </span>
                    <span
                        v-if="product.formatted_special_price"
                        class="text-text-secondary line-through"
                    >
                        @{{ product.formatted_price }}
                    </span>
                </div>

                <div class="flex items-center gap-2 mt-2">
                    <div class="flex">
                        <template v-for="i in 5" :key="i">
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                :fill="i <= Math.round(product.ratings.average) ? '#FFAD33' : '#D1D5DB'"
                                stroke="none"
                            >
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </template>
                    </div>
                    <span class="text-text-secondary text-sm">
                        (@{{ product.ratings.total }})
                    </span>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-product-card', {
            template: '#v-product-card-template',

            props: ['mode', 'product'],

            data() {
                return {
                    isCustomer: '{{ auth()->guard('customer')->check() }}',
                    isAddingToCart: false,
                }
            },

            methods: {
                addToWishlist() {
                    if (this.isCustomer) {
                        this.$axios.post(`{{ route('shop.api.customers.account.wishlist.store') }}`, {
                                product_id: this.product.id
                            })
                            .then(response => {
                                this.product.is_wishlist = ! this.product.is_wishlist;

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.data.message });
                            })
                            .catch(error => {});
                    } else {
                        window.location.href = "{{ route('shop.customer.session.index')}}";
                    }
                },

                addToCompare(productId) {
                    if (this.isCustomer) {
                        this.$axios.post('{{ route("shop.api.compare.store") }}', {
                                'product_id': productId
                            })
                            .then(response => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.data.message });
                            })
                            .catch(error => {
                                if ([400, 422].includes(error.response.status)) {
                                    this.$emitter.emit('add-flash', { type: 'warning', message: error.response.data.data.message });

                                    return;
                                }

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message});
                            });

                        return;
                    }

                    let items = this.getStorageValue() ?? [];

                    if (items.length) {
                        if (! items.includes(productId)) {
                            items.push(productId);

                            localStorage.setItem('compare_items', JSON.stringify(items));

                            this.$emitter.emit('add-flash', { type: 'success', message: "@lang('shop::app.components.products.card.add-to-compare-success')" });
                        } else {
                            this.$emitter.emit('add-flash', { type: 'warning', message: "@lang('shop::app.components.products.card.already-in-compare')" });
                        }
                    } else {
                        localStorage.setItem('compare_items', JSON.stringify([productId]));

                        this.$emitter.emit('add-flash', { type: 'success', message: "@lang('shop::app.components.products.card.add-to-compare-success')" });
                    }
                },

                getStorageValue(key) {
                    let value = localStorage.getItem('compare_items');

                    if (! value) {
                        return [];
                    }

                    return JSON.parse(value);
                },

                addToCart() {
                    this.isAddingToCart = true;

                    this.$axios.post('{{ route("shop.api.checkout.cart.store") }}', {
                            'quantity': 1,
                            'product_id': this.product.id,
                        })
                        .then(response => {
                            if (response.data.message) {
                                this.$emitter.emit('update-mini-cart', response.data.data );

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            } else {
                                this.$emitter.emit('add-flash', { type: 'warning', message: response.data.data.message });
                            }

                            this.isAddingToCart = false;
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });

                            if (error.response.data.redirect_uri) {
                                window.location.href = error.response.data.redirect_uri;
                            }

                            this.isAddingToCart = false;
                        });
                },
            },
        });
    </script>
@endpushOnce
