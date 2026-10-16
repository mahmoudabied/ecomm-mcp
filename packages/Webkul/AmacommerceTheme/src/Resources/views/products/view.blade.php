@inject ('reviewHelper', 'Webkul\Product\Helpers\Review')
@inject ('productViewHelper', 'Webkul\Product\Helpers\View')

@php
    $avgRatings = $reviewHelper->getAverageRating($product);
    $percentageRatings = $reviewHelper->getPercentageRating($product);
    $customAttributeValues = $productViewHelper->getAdditionalData($product);
    $attributeData = collect($customAttributeValues)->filter(fn ($item) => ! empty($item['value']));
@endphp

@push('meta')
    <meta name="description" content="{{ trim($product->meta_description) != "" ? $product->meta_description : \Illuminate\Support\Str::limit(strip_tags($product->description), 120, '') }}"/>
    <meta name="keywords" content="{{ $product->meta_keywords }}"/>

    @if (core()->getConfigData('catalog.rich_snippets.products.enable'))
        <script type="application/ld+json">
            {!! app('Webkul\Product\Helpers\SEO')->getProductJsonLd($product) !!}
        </script>
    @endif

    <?php $productBaseImage = product_image()->getProductBaseImage($product); ?>

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $product->name }}" />
    <meta name="twitter:description" content="{!! htmlspecialchars(trim(strip_tags($product->description))) !!}" />
    <meta name="twitter:image:alt" content="" />
    <meta name="twitter:image" content="{{ $productBaseImage['medium_image_url'] }}" />
    <meta property="og:type" content="og:product" />
    <meta property="og:title" content="{{ $product->name }}" />
    <meta property="og:image" content="{{ $productBaseImage['medium_image_url'] }}" />
    <meta property="og:description" content="{!! htmlspecialchars(trim(strip_tags($product->description))) !!}" />
    <meta property="og:url" content="{{ route('shop.product_or_category.index', $product->url_key) }}" />
@endPush

<x-shop::layouts>
    <x-slot:title>
        {{ trim($product->meta_title) != "" ? $product->meta_title : $product->name }}
    </x-slot>

    {!! view_render_event('bagisto.shop.products.view.before', ['product' => $product]) !!}

    {{-- Breadcrumbs --}}
    @if ((core()->getConfigData('general.general.breadcrumbs.shop')))
        <div class="max-w-content mx-auto px-4 py-4">
            <x-shop::breadcrumbs
                name="product"
                :entity="$product"
            />
        </div>
    @endif

    {{-- Product Detail Vue Component --}}
    <v-product>
        <div class="max-w-content mx-auto px-4 py-10 text-center text-text-secondary">Loading product details...</div>
    </v-product>

    {{-- Description, Additional Info, Reviews Tabs --}}
    <div class="mt-10">
        <div class="max-w-content mx-auto px-4">
            <x-shop::tabs
                position="center"
                ref="productTabs"
            >
                {!! view_render_event('bagisto.shop.products.view.description.before', ['product' => $product]) !!}

                <x-shop::tabs.item
                    id="description-tab"
                    class="mt-[60px] !p-0"
                    :title="trans('shop::app.products.view.description')"
                    :is-selected="true"
                >
                    <div class="mt-[60px]">
                        <p class="text-lg text-text-secondary">
                            {!! $product->description !!}
                        </p>
                    </div>
                </x-shop::tabs.item>

                {!! view_render_event('bagisto.shop.products.view.description.after', ['product' => $product]) !!}

                @if(count($attributeData))
                    <x-shop::tabs.item
                        id="information-tab"
                        class="mt-[60px] !p-0"
                        :title="trans('shop::app.products.view.additional-information')"
                        :is-selected="false"
                    >
                        <div class="mt-[60px]">
                            <div class="grid max-w-max grid-cols-[auto_1fr] gap-4">
                                @foreach ($customAttributeValues as $customAttributeValue)
                                    @if (! empty($customAttributeValue['value']))
                                        <div class="grid">
                                            <p class="text-base text-black">
                                                {!! $customAttributeValue['label'] !!}
                                            </p>
                                        </div>

                                        @if ($customAttributeValue['type'] == 'file')
                                            <a href="{{ Storage::url($product[$customAttributeValue['code']]) }}" download="{{ $customAttributeValue['label'] }}">
                                                <span class="icon-download text-2xl"></span>
                                            </a>
                                        @elseif ($customAttributeValue['type'] == 'image')
                                            <a href="{{ Storage::url($product[$customAttributeValue['code']]) }}" download="{{ $customAttributeValue['label'] }}">
                                                <img class="h-5 min-h-5 w-5 min-w-5" src="{{ Storage::url($customAttributeValue['value']) }}" />
                                            </a>
                                        @else
                                            <div class="grid">
                                                <p class="text-base text-text-secondary">
                                                    {!! $customAttributeValue['value'] !!}
                                                </p>
                                            </div>
                                        @endif
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </x-shop::tabs.item>
                @endif

                <x-shop::tabs.item
                    id="review-tab"
                    class="mt-[60px] !p-0"
                    :title="trans('shop::app.products.view.review')"
                    :is-selected="false"
                >
                    @include('shop::products.view.reviews')
                </x-shop::tabs.item>
            </x-shop::tabs>
        </div>
    </div>

    {{-- Related Products --}}
    <v-product-associations></v-product-associations>

    {!! view_render_event('bagisto.shop.products.view.after', ['product' => $product]) !!}

    @pushOnce('scripts')
        <script type="text/x-template" id="v-product-template">
            <x-shop::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
            >
                <form
                    ref="formData"
                    @submit="handleSubmit($event, addToCart)"
                >
                    <input type="hidden" name="product_id" value="{{ $product->id }}" />
                    <input type="hidden" name="is_buy_now" v-model="is_buy_now" />

                    <div class="max-w-content mx-auto px-4">
                        <div class="flex flex-col lg:flex-row gap-8 lg:gap-[70px] py-6 md:py-10">
                            {{-- Left: Image Gallery --}}
                            <div class="w-full lg:w-[45%]">
                                <div class="flex flex-col-reverse md:flex-row gap-4">
                                    <div class="flex md:flex-col gap-3 overflow-x-auto md:overflow-x-visible md:overflow-y-auto md:max-h-[500px]" ref="swiperContainer">
                                        <template v-for="(media, index) in [...galleryImages]">
                                            <img
                                                :src="media.small_image_url"
                                                :alt="'{{ $product->name }}'"
                                                width="120"
                                                height="120"
                                                class="w-[80px] h-[80px] md:w-[120px] md:h-[120px] object-contain rounded cursor-pointer border p-2 shrink-0"
                                                :class="activeIndex === index ? 'border-primary' : 'border-border-color'"
                                                @click="changeMainImage(media, index)"
                                            />
                                        </template>
                                    </div>
                                    <div class="flex-grow flex items-center justify-center bg-bg-secondary rounded min-h-[300px] md:min-h-[500px]">
                                        <img
                                            :src="mainImage"
                                            :alt="'{{ $product->name }}'"
                                            class="max-h-[280px] md:max-h-[450px] object-contain"
                                            v-if="mainImage"
                                        />
                                    </div>
                                </div>
                            </div>

                            {{-- Right: Product Info --}}
                            <div class="w-full lg:w-[55%]">
                                {!! view_render_event('bagisto.shop.products.name.before', ['product' => $product]) !!}

                                <h1 class="text-2xl font-semibold text-text2">
                                    {{ $product->name }}
                                </h1>

                                {!! view_render_event('bagisto.shop.products.name.after', ['product' => $product]) !!}

                                {{-- Rating + Reviews + Stock --}}
                                <div class="flex items-center gap-4 mt-2">
                                    <div class="flex">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="{{ $i <= round($avgRatings) ? '#FFAD33' : '#D1D5DB' }}" stroke="none">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                            </svg>
                                        @endfor
                                    </div>
                                    <span class="text-text-secondary text-sm">({{ $product->reviews->count() }} Reviews)</span>
                                    @if ($product->isSaleable())
                                        <span class="text-secondary text-sm">In Stock</span>
                                    @endif
                                </div>

                                {{-- Pricing --}}
                                {!! view_render_event('bagisto.shop.products.price.before', ['product' => $product]) !!}

                                <p class="text-2xl mt-4">
                                    {!! $product->getTypeInstance()->getPriceHtml() !!}
                                </p>

                                {!! view_render_event('bagisto.shop.products.price.after', ['product' => $product]) !!}

                                {{-- Short Description --}}
                                {!! view_render_event('bagisto.shop.products.short_description.before', ['product' => $product]) !!}

                                <p class="text-sm text-text-secondary mt-4 pb-6 border-b border-border-color">
                                    {!! $product->short_description !!}
                                </p>

                                {!! view_render_event('bagisto.shop.products.short_description.after', ['product' => $product]) !!}

                                {{-- Product Types --}}
                                @include('shop::products.view.types.simple')
                                @include('shop::products.view.types.configurable')
                                @include('shop::products.view.types.grouped')
                                @include('shop::products.view.types.bundle')
                                @include('shop::products.view.types.downloadable')
                                @include('shop::products.view.types.booking')

                                {{-- Quantity + Buy Now --}}
                                <div class="flex flex-wrap items-center gap-4 mt-6">
                                    @if ($product->getTypeInstance()->showQuantityBox())
                                        <div class="flex items-center border border-border-color rounded">
                                            <button type="button" class="px-4 py-3 text-lg" @click="quantity > 1 && quantity--">-</button>
                                            <input type="number" name="quantity" v-model="quantity" class="w-14 text-center py-3 outline-none border-x border-border-color" min="1" />
                                            <button type="button" class="px-4 py-3 text-lg" @click="quantity++">+</button>
                                        </div>
                                    @endif

                                    @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                                        <button
                                            type="submit"
                                            class="bg-primary text-white px-12 py-3 rounded font-medium hover:bg-primary-hover transition"
                                            @click="is_buy_now=0;"
                                        >
                                            Add To Cart
                                        </button>

                                        @if (core()->getConfigData('catalog.products.storefront.buy_now_button_display'))
                                            <button
                                                type="submit"
                                                class="bg-primary text-white px-12 py-3 rounded font-medium hover:bg-primary-hover transition"
                                                @click="is_buy_now=1;"
                                            >
                                                Buy Now
                                            </button>
                                        @endif
                                    @endif

                                    @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                                        <button
                                            type="button"
                                            class="w-12 h-12 border border-border-color rounded flex items-center justify-center hover:bg-bg-secondary transition"
                                            @click="addToWishlist"
                                        >
                                            <svg width="24" height="24" viewBox="0 0 24 24" :fill="isWishlist ? '#DB4444' : 'none'" :stroke="isWishlist ? '#DB4444' : 'currentColor'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Delivery Info --}}
                                <div class="mt-8 border-t border-border-color pt-6 space-y-4">
                                    <div class="flex items-center gap-4 border border-border-color rounded p-4">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                                        </svg>
                                        <div>
                                            <p class="font-medium text-sm">Free Delivery</p>
                                            <p class="text-xs text-text-secondary">Enter your postal code for Delivery Availability</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4 border border-border-color rounded p-4">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                                        </svg>
                                        <div>
                                            <p class="font-medium text-sm">Return Delivery</p>
                                            <p class="text-xs text-text-secondary">Free 30 Days Delivery Returns. <a href="#" class="font-medium underline">Details</a></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </x-shop::form>
        </script>

        <script type="module">
            app.component('v-product', {
                template: '#v-product-template',

                data() {
                    return {
                        isWishlist: Boolean("{{ (boolean) auth()->guard()->user()?->wishlist_items->where('channel_id', core()->getCurrentChannel()->id)->where('product_id', $product->id)->count() }}"),

                        isCustomer: '{{ auth()->guard('customer')->check() }}',

                        is_buy_now: 0,

                        quantity: 1,

                        isStoring: {
                            addToCart: false,
                            buyNow: false,
                        },

                        galleryImages: @json(product_image()->getGalleryImages($product)),

                        mainImage: null,

                        activeIndex: 0,
                    }
                },

                mounted() {
                    if (this.galleryImages.length) {
                        this.mainImage = this.galleryImages[0].large_image_url;
                    }
                },

                methods: {
                    changeMainImage(media, index) {
                        this.mainImage = media.large_image_url;
                        this.activeIndex = index;
                    },

                    addToCart(params) {
                        const operation = this.is_buy_now ? 'buyNow' : 'addToCart';

                        this.isStoring[operation] = true;

                        let formData = new FormData(this.$refs.formData);

                        if (! formData.has('quantity')) {
                            formData.append('quantity', this.quantity);
                        }

                        this.$axios.post('{{ route("shop.api.checkout.cart.store") }}', formData, {
                                headers: {
                                    'Content-Type': 'multipart/form-data'
                                }
                            })
                            .then(response => {
                                if (response.data.message) {
                                    this.$emitter.emit('update-mini-cart', response.data.data);

                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                    if (response.data.redirect) {
                                        window.location.href= response.data.redirect;
                                    }
                                } else {
                                    this.$emitter.emit('add-flash', { type: 'warning', message: response.data.data.message });
                                }

                                this.isStoring[operation] = false;
                            })
                            .catch(error => {
                                this.isStoring[operation] = false;

                                this.$emitter.emit('add-flash', { type: 'warning', message: error.response.data.message });
                            });
                    },

                    addToWishlist() {
                        if (this.isCustomer) {
                            this.$axios.post('{{ route('shop.api.customers.account.wishlist.store') }}', {
                                    product_id: "{{ $product->id }}"
                                })
                                .then(response => {
                                    this.isWishlist = ! this.isWishlist;

                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.data.message });
                                })
                                .catch(error => {});
                        } else {
                            window.location.href = "{{ route('shop.customer.session.index')}}";
                        }
                    },
                },
            });
        </script>

        <script type="text/x-template" id="v-product-associations-template">
            <div ref="carouselWrapper">
                <template v-if="isVisible">
                    <div class="max-w-content mx-auto px-4 mt-20">
                        <h2 class="text-2xl font-semibold mb-10">Related Item</h2>
                        <div class="flex gap-[30px] overflow-x-auto scroll-smooth pb-4" style="scrollbar-width: none;">
                            <template v-for="product in relatedProducts" :key="product.id">
                                <v-product-card :product="product"></v-product-card>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </script>

        <script type="module">
            app.component('v-product-associations', {
                template: '#v-product-associations-template',

                data() {
                    return {
                        isVisible: false,
                        relatedProducts: [],
                    };
                },

                mounted() {
                    const observer = new IntersectionObserver(
                        (entries) => {
                            entries.forEach((entry) => {
                                if (entry.isIntersecting) {
                                    this.isVisible = true;
                                    observer.unobserve(entry.target);

                                    this.$axios.get('{{ route('shop.api.products.related.index', ['id' => $product->id]) }}')
                                        .then(response => {
                                            this.relatedProducts = response.data.data;
                                        })
                                        .catch(error => {});
                                }
                            });
                        },
                        { threshold: 0.1 }
                    );

                    observer.observe(this.$refs.carouselWrapper);
                }
            });
        </script>
    @endPushOnce
</x-shop::layouts>
