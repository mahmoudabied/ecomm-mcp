<x-shop::layouts.account>
    <x-slot:title>
        @lang('shop::app.customers.account.wishlist.page-title')
    </x-slot>

    @if ((core()->getConfigData('general.general.breadcrumbs.shop')))
        @section('breadcrumbs')
            <x-shop::breadcrumbs name="wishlist" />
        @endSection
    @endif

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <div class="mx-4 flex-auto">
        <v-wishlist-products>
            <x-shop::shimmer.customers.account.wishlist :count="4" />
        </v-wishlist-products>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-wishlist-products-template"
        >
            <div>
                <template v-if="isLoading">
                    <x-shop::shimmer.customers.account.wishlist :count="4" />
                </template>

                {!! view_render_event('bagisto.shop.customers.account.wishlist.list.before') !!}

                <template v-else>
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center">
                            <a
                                class="grid md:hidden"
                                href="{{ route('shop.customers.account.index') }}"
                            >
                                <span class="icon-arrow-left rtl:icon-arrow-right text-2xl"></span>
                            </a>

                            <h2 class="text-xl font-medium ltr:ml-2.5 md:ltr:ml-0 rtl:mr-2.5 md:rtl:mr-0">
                                Wishlist (@{{ wishlistItems.length }})
                            </h2>
                        </div>

                        {!! view_render_event('bagisto.shop.customers.account.wishlist.delete_all.before') !!}

                        <div
                            class="border border-border-color rounded px-5 py-3 text-sm cursor-pointer hover:bg-bg-secondary transition"
                            @click="removeAll"
                            v-if="wishlistItems.length"
                        >
                            Delete All
                        </div>

                        {!! view_render_event('bagisto.shop.customers.account.wishlist.delete_all.after') !!}
                    </div>

                    <template v-if="wishlistItems.length">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[30px]">
                            <div
                                v-for="(wishlist, index) in wishlistItems"
                                :key="wishlist.id"
                                class="w-full"
                            >
                                <v-wishlist-card
                                    :wishlist="wishlist"
                                    @wishlist-items="(items) => wishlistItems = items"
                                ></v-wishlist-card>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="m-auto grid w-full place-content-center items-center justify-items-center py-32 text-center">
                            <img
                                class="max-md:h-[100px] max-md:w-[100px]"
                                src="{{ bagisto_asset('images/wishlist.png') }}"
                                alt="Empty wishlist"
                            >

                            <p class="text-xl max-md:text-sm" role="heading">
                                @lang('shop::app.customers.account.wishlist.empty')
                            </p>
                        </div>
                    </template>
                </template>

                {!! view_render_event('bagisto.shop.customers.account.wishlist.list.after') !!}
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-wishlist-card-template"
        >
            <div class="w-full max-w-[270px]">
                <div class="bg-bg-secondary h-[250px] rounded relative flex items-center justify-center group">
                    <button
                        class="absolute top-3 right-3 w-8 h-8 bg-white rounded-full flex items-center justify-center shadow hover:bg-gray-100 z-10"
                        @click="remove"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>

                    @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                        <div
                            class="absolute bottom-0 left-0 right-0 bg-black text-white text-center py-2 text-sm opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer z-10"
                            @click="moveToCart"
                        >
                            Add To Cart
                        </div>
                    @endif

                    <a :href="`{{ route('shop.product_or_category.index', '') }}/${wishlist.product.url_key}`">
                        <img
                            :src="wishlist.product.base_image.small_image_url"
                            :alt="wishlist.product.name"
                            class="max-h-[180px] object-contain"
                        />
                    </a>
                </div>

                <div class="mt-4">
                    <a
                        :href="`{{ route('shop.product_or_category.index', '') }}/${wishlist.product.url_key}`"
                        class="font-medium block truncate"
                    >
                        @{{ wishlist.product.name }}
                    </a>

                    <div class="flex gap-3 mt-2">
                        <span class="text-primary font-medium" v-html="wishlist.product.price_html"></span>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component("v-wishlist-products", {
                template: '#v-wishlist-products-template',

                data() {
                    return {
                        isLoading: true,
                        wishlistItems: [],
                    };
                },

                mounted() {
                    this.get();
                },

                methods: {
                    get() {
                        this.$axios.get("{{ route('shop.api.customers.account.wishlist.index') }}")
                            .then(response => {
                                this.isLoading = false;
                                this.wishlistItems = response.data.data;
                            })
                            .catch(error => {});
                    },

                    removeAll() {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.$axios.post("{{ route('shop.api.customers.account.wishlist.destroy_all') }}", {
                                        '_method': 'DELETE',
                                    })
                                    .then(response => {
                                        this.wishlistItems = [];
                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.data.message });
                                    })
                                    .catch(error => {});
                            },
                        });
                    },
                },
            });

            app.component('v-wishlist-card', {
                template: '#v-wishlist-card-template',

                props: ['wishlist'],

                emits: ['wishlist-items'],

                data() {
                    return {
                        movingToCart: false,
                    };
                },

                methods: {
                    remove() {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.$axios
                                    .delete(`{{ route('shop.api.customers.account.wishlist.destroy', '') }}/${this.wishlist.id}`)
                                    .then(response => {
                                        this.$emit('wishlist-items', response.data.data);
                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                    })
                                    .catch(error => {});
                            },
                        });
                    },

                    moveToCart() {
                        this.movingToCart = true;

                        const endpoint = `{{ route('shop.api.customers.account.wishlist.move_to_cart', ':wishlistId:') }}`.replace(':wishlistId:', this.wishlist.id);

                        this.$axios.post(endpoint, {
                                quantity: this.wishlist.options?.quantity ?? 1,
                                product_id: this.wishlist.product.id,
                            })
                            .then(response => {
                                if (response.data?.redirect) {
                                    this.$emitter.emit('add-flash', { type: 'warning', message: response.data.message });
                                    window.location.href = response.data.data;
                                    return;
                                }

                                this.$emit('wishlist-items', response.data.data?.wishlist);
                                this.$emitter.emit('update-mini-cart', response.data.data.cart);
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                this.movingToCart = false;
                            })
                            .catch(error => {
                                this.movingToCart = false;
                                this.$emitter.emit('add-flash', { type: 'warning', message: error.response.data.message });
                            });
                    },
                },
            });
        </script>
    @endpushOnce
</x-shop::layouts.account>
