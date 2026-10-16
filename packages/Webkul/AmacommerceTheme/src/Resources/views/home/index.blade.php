@php
    $channel = core()->getCurrentChannel();
@endphp

@push('meta')
    <meta name="title" content="{{ $channel->home_seo['meta_title'] ?? '' }}" />
    <meta name="description" content="{{ $channel->home_seo['meta_description'] ?? '' }}" />
    <meta name="keywords" content="{{ $channel->home_seo['meta_keywords'] ?? '' }}" />
@endPush

@push('scripts')
    <script>
        localStorage.setItem('categories', JSON.stringify(@json($categories)));
    </script>
@endpush

<x-shop::layouts>
    <x-slot:title>
        {{ $channel->home_seo['meta_title'] ?? '' }}
    </x-slot>

    {{-- SECTION 1: Hero with Category Sidebar --}}
    <div class="max-w-content mx-auto px-4 mt-10">
        <div class="flex gap-8">
            <div class="w-[217px] hidden lg:block border-r border-border-color pr-6 pt-4">
                <v-category-sidebar></v-category-sidebar>
            </div>
            <div class="flex-grow pt-4">
                <div class="bg-black text-white rounded overflow-hidden relative h-[220px] md:h-[300px] lg:h-[344px]">
                    <div class="flex items-center h-full px-6 md:px-10 lg:px-16">
                        <div class="flex-1">
                            <div class="flex items-center gap-4 mb-4">
                                <svg width="40" height="40" viewBox="0 0 814 1000" fill="white">
                                    <path d="M788.1 340.9c-5.8 4.5-108.2 62.2-108.2 190.5 0 148.4 130.3 200.9 134.2 202.2-.6 3.2-20.7 71.9-68.7 141.9-42.8 61.6-87.5 123.1-155.5 123.1s-85.5-39.5-164-39.5c-76.5 0-103.7 40.8-165.9 40.8s-105.6-57.8-155.5-127.4c-58.3-81.5-105.6-207.3-105.6-327.7 0-192.8 125.3-295 248.7-295 65.6 0 120.2 43.4 161.3 43.4 39.2 0 100.3-46.1 175.3-46.1 28.3 0 130 2.6 197.3 98.5zM554.1 159.4c31.1-36.9 53.1-88.1 53.1-139.3 0-7.1-.6-14.3-1.9-20.1-50.6 1.9-110.4 33.7-146.5 75.8-26.5 29.7-53.1 80.9-53.1 132.7 0 7.8.6 15.6 1.3 18.2 2.6.6 6.4 1.3 10.2 1.3 45.4 0 102.5-30.4 136.9-68.6z"/>
                                </svg>
                                <span class="text-base">iPhone 14 Series</span>
                            </div>
                            <h2 class="text-2xl md:text-4xl lg:text-5xl font-semibold leading-tight mb-6">Up to 10%<br>off Voucher</h2>
                            <a href="#" class="inline-flex items-center gap-2 border-b border-white pb-1 text-base font-medium">
                                Shop Now
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </a>
                        </div>
                        <div class="flex-1 flex justify-center hidden md:flex">
                            <img src="{{ bagisto_asset('images/hero/iphone-banner.png') }}" alt="iPhone 14" class="max-h-[180px] md:max-h-[260px] lg:max-h-[320px] object-contain" />
                        </div>
                    </div>
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-3">
                        <span class="w-3 h-3 rounded-full bg-white/50"></span>
                        <span class="w-3 h-3 rounded-full bg-white/50"></span>
                        <span class="w-3.5 h-3.5 rounded-full bg-primary ring-2 ring-white"></span>
                        <span class="w-3 h-3 rounded-full bg-white/50"></span>
                        <span class="w-3 h-3 rounded-full bg-white/50"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: Flash Sales --}}
    <div class="max-w-content mx-auto px-4 mt-10 md:mt-20">
        <div class="flex items-center gap-4 mb-3">
            <div class="w-5 h-10 bg-primary rounded"></div>
            <span class="text-primary font-semibold">Today's</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 md:mb-10 gap-4">
            <div class="flex flex-col sm:flex-row sm:items-end gap-4 sm:gap-20">
                <h2 class="text-2xl md:text-4xl font-semibold tracking-wide">Flash Sales</h2>
                <v-countdown-timer></v-countdown-timer>
            </div>
            <div class="flex gap-2">
                <button class="w-[46px] h-[46px] rounded-full bg-bg-secondary flex items-center justify-center" onclick="document.getElementById('flash-sales').scrollBy({left: -290, behavior: 'smooth'})">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="w-[46px] h-[46px] rounded-full bg-bg-secondary flex items-center justify-center" onclick="document.getElementById('flash-sales').scrollBy({left: 290, behavior: 'smooth'})">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
        <v-product-carousel
            id="flash-sales"
            url="{{ route('shop.api.products.index', ['featured' => 1, 'limit' => 10]) }}"
        ></v-product-carousel>
        <div class="text-center mt-10">
            <a href="{{ route('shop.search.index') }}" class="inline-block bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition">View All Products</a>
        </div>
    </div>

    {{-- SECTION 3: Browse By Category --}}
    <div class="max-w-content mx-auto px-4 mt-10 md:mt-20 border-t border-border-color pt-8 md:pt-16">
        <div class="flex items-center gap-4 mb-3">
            <div class="w-5 h-10 bg-primary rounded"></div>
            <span class="text-primary font-semibold">Categories</span>
        </div>
        <div class="flex items-end justify-between mb-6 md:mb-10">
            <h2 class="text-2xl md:text-4xl font-semibold tracking-wide">Browse By Category</h2>
            <div class="flex gap-2">
                <button class="w-[46px] h-[46px] rounded-full bg-bg-secondary flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="w-[46px] h-[46px] rounded-full bg-bg-secondary flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-[30px]">
            <a href="{{ route('shop.product_or_category.index', 'phones') }}" class="border border-border-color rounded h-[145px] flex flex-col items-center justify-center gap-4 hover:bg-primary hover:text-white hover:border-primary transition group">
                <img src="{{ bagisto_asset('images/categories/phone.svg') }}" alt="Phones" class="w-14 h-14 group-hover:brightness-0 group-hover:invert" />
                <span class="text-base">Phones</span>
            </a>
            <a href="{{ route('shop.product_or_category.index', 'computers') }}" class="border border-border-color rounded h-[145px] flex flex-col items-center justify-center gap-4 hover:bg-primary hover:text-white hover:border-primary transition group">
                <img src="{{ bagisto_asset('images/categories/computer.svg') }}" alt="Computers" class="w-14 h-14 group-hover:brightness-0 group-hover:invert" />
                <span class="text-base">Computers</span>
            </a>
            <a href="{{ route('shop.product_or_category.index', 'smartwatch') }}" class="border border-border-color rounded h-[145px] flex flex-col items-center justify-center gap-4 hover:bg-primary hover:text-white hover:border-primary transition group">
                <img src="{{ bagisto_asset('images/categories/smartwatch.svg') }}" alt="SmartWatch" class="w-14 h-14 group-hover:brightness-0 group-hover:invert" />
                <span class="text-base">SmartWatch</span>
            </a>
            <a href="{{ route('shop.product_or_category.index', 'camera') }}" class="border border-border-color rounded h-[145px] flex flex-col items-center justify-center gap-4 bg-primary text-white border-primary transition group">
                <img src="{{ bagisto_asset('images/categories/camera.svg') }}" alt="Camera" class="w-14 h-14 brightness-0 invert" />
                <span class="text-base">Camera</span>
            </a>
            <a href="{{ route('shop.product_or_category.index', 'headphones') }}" class="border border-border-color rounded h-[145px] flex flex-col items-center justify-center gap-4 hover:bg-primary hover:text-white hover:border-primary transition group">
                <img src="{{ bagisto_asset('images/categories/headphones.svg') }}" alt="HeadPhones" class="w-14 h-14 group-hover:brightness-0 group-hover:invert" />
                <span class="text-base">HeadPhones</span>
            </a>
            <a href="{{ route('shop.product_or_category.index', 'gaming') }}" class="border border-border-color rounded h-[145px] flex flex-col items-center justify-center gap-4 hover:bg-primary hover:text-white hover:border-primary transition group">
                <img src="{{ bagisto_asset('images/categories/gaming.svg') }}" alt="Gaming" class="w-14 h-14 group-hover:brightness-0 group-hover:invert" />
                <span class="text-base">Gaming</span>
            </a>
        </div>
    </div>

    {{-- SECTION 4: Best Selling Products --}}
    <div class="max-w-content mx-auto px-4 mt-10 md:mt-20 border-t border-border-color pt-8 md:pt-16">
        <div class="flex items-center gap-4 mb-3">
            <div class="w-5 h-10 bg-primary rounded"></div>
            <span class="text-primary font-semibold">This Month</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 md:mb-10 gap-4">
            <h2 class="text-2xl md:text-4xl font-semibold tracking-wide">Best Selling Products</h2>
            <a href="{{ route('shop.search.index') }}" class="bg-primary text-white px-8 py-3 sm:px-12 sm:py-4 rounded text-sm sm:text-base font-medium hover:bg-primary-hover transition text-center">View All</a>
        </div>
        <v-best-sellers></v-best-sellers>
    </div>

    {{-- SECTION 5: Music Promo Banner --}}
    <div class="max-w-content mx-auto px-4 mt-10 md:mt-20">
        <div class="bg-black rounded overflow-hidden relative h-auto md:h-[500px]">
            <div class="flex flex-col md:flex-row items-center md:h-full">
                <div class="flex-1 p-8 md:pl-14 text-center md:text-left">
                    <span class="text-secondary text-base font-semibold">Categories</span>
                    <h2 class="text-3xl md:text-5xl font-semibold text-white leading-tight mt-4 md:mt-6 mb-6 md:mb-8">Enhance Your<br>Music Experience</h2>
                    <div class="flex gap-3 md:gap-6 mb-6 md:mb-10 justify-center md:justify-start">
                        <div class="w-[50px] h-[50px] md:w-[62px] md:h-[62px] bg-white rounded-full flex flex-col items-center justify-center">
                            <span class="text-sm md:text-base font-semibold leading-none">23</span>
                            <span class="text-[9px] md:text-[11px]">Hours</span>
                        </div>
                        <div class="w-[50px] h-[50px] md:w-[62px] md:h-[62px] bg-white rounded-full flex flex-col items-center justify-center">
                            <span class="text-sm md:text-base font-semibold leading-none">05</span>
                            <span class="text-[9px] md:text-[11px]">Days</span>
                        </div>
                        <div class="w-[50px] h-[50px] md:w-[62px] md:h-[62px] bg-white rounded-full flex flex-col items-center justify-center">
                            <span class="text-sm md:text-base font-semibold leading-none">59</span>
                            <span class="text-[9px] md:text-[11px]">Minutes</span>
                        </div>
                        <div class="w-[50px] h-[50px] md:w-[62px] md:h-[62px] bg-white rounded-full flex flex-col items-center justify-center">
                            <span class="text-sm md:text-base font-semibold leading-none">35</span>
                            <span class="text-[9px] md:text-[11px]">Seconds</span>
                        </div>
                    </div>
                    <a href="#" class="inline-block bg-secondary text-white px-8 py-3 md:px-12 md:py-4 rounded text-sm md:text-base font-medium hover:opacity-90 transition">Buy Now!</a>
                </div>
                <div class="flex-1 flex justify-center relative py-8">
                    <div class="absolute w-[300px] h-[300px] md:w-[504px] md:h-[500px] rounded-full bg-white/10 blur-3xl"></div>
                    <img src="{{ bagisto_asset('images/promo/jbl-speaker.png') }}" alt="JBL Speaker" class="relative z-10 max-h-[250px] md:max-h-[420px] object-contain" />
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 6: Explore Our Products --}}
    <div class="max-w-content mx-auto px-4 mt-10 md:mt-20">
        <div class="flex items-center gap-4 mb-3">
            <div class="w-5 h-10 bg-primary rounded"></div>
            <span class="text-primary font-semibold">Our Products</span>
        </div>
        <div class="flex items-end justify-between mb-6 md:mb-10">
            <h2 class="text-2xl md:text-4xl font-semibold tracking-wide">Explore Our Products</h2>
            <div class="flex gap-2">
                <button class="w-[46px] h-[46px] rounded-full bg-bg-secondary flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="w-[46px] h-[46px] rounded-full bg-bg-secondary flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
        <v-explore-products></v-explore-products>
        <div class="text-center mt-10">
            <a href="{{ route('shop.search.index') }}" class="inline-block bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition">View All Products</a>
        </div>
    </div>

    {{-- SECTION 7: New Arrivals --}}
    <div class="max-w-content mx-auto px-4 mt-10 md:mt-20">
        <div class="flex items-center gap-4 mb-3">
            <div class="w-5 h-10 bg-primary rounded"></div>
            <span class="text-primary font-semibold">Featured</span>
        </div>
        <h2 class="text-2xl md:text-4xl font-semibold tracking-wide mb-6 md:mb-10">New Arrival</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-[30px]">
            <div class="bg-black rounded overflow-hidden relative h-[300px] md:h-[500px] lg:h-[600px]">
                <img src="{{ bagisto_asset('images/arrivals/ps5.png') }}" alt="PlayStation 5" class="absolute bottom-0 left-1/2 -translate-x-1/2 max-h-[200px] md:max-h-[400px] lg:max-h-[500px] object-contain" />
                <div class="absolute bottom-4 md:bottom-8 left-4 md:left-8 text-white">
                    <h3 class="text-lg md:text-2xl font-semibold mb-2 md:mb-4">PlayStation 5</h3>
                    <p class="text-sm text-text-secondary mb-4">Black and White version of the PS5<br>coming out on sale.</p>
                    <a href="#" class="border-b border-white pb-1 text-base font-medium">Shop Now</a>
                </div>
            </div>
            <div class="grid grid-rows-2 gap-[30px]">
                <div class="bg-black rounded overflow-hidden relative h-[200px] md:h-[284px]">
                    <img src="{{ bagisto_asset('images/arrivals/womens-collection.png') }}" alt="Women's Collections" class="absolute right-0 top-0 h-full object-cover" />
                    <div class="absolute bottom-4 md:bottom-6 left-4 md:left-6 text-white">
                        <h3 class="text-lg md:text-2xl font-semibold mb-1 md:mb-2">Women's Collections</h3>
                        <p class="text-sm text-text-secondary mb-4">Featured woman collections that<br>give you another vibe.</p>
                        <a href="#" class="border-b border-white pb-1 text-base font-medium">Shop Now</a>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-[30px]">
                    <div class="bg-black rounded overflow-hidden relative h-[180px] md:h-[284px]">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-36 md:w-48 h-36 md:h-48 rounded-full bg-white/10 blur-2xl absolute"></div>
                            <img src="{{ bagisto_asset('images/arrivals/speakers.png') }}" alt="Speakers" class="relative z-10 max-h-[120px] md:max-h-[200px] object-contain" />
                        </div>
                        <div class="absolute bottom-3 md:bottom-6 left-3 md:left-6 text-white">
                            <h3 class="text-base md:text-2xl font-semibold mb-1">Speakers</h3>
                            <p class="text-sm text-text-secondary mb-2">Amazon wireless speakers</p>
                            <a href="#" class="border-b border-white pb-1 text-base font-medium">Shop Now</a>
                        </div>
                    </div>
                    <div class="bg-black rounded overflow-hidden relative h-[180px] md:h-[284px]">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-36 md:w-48 h-36 md:h-48 rounded-full bg-white/10 blur-2xl absolute"></div>
                            <img src="{{ bagisto_asset('images/arrivals/perfume.png') }}" alt="Perfume" class="relative z-10 max-h-[120px] md:max-h-[200px] object-contain" />
                        </div>
                        <div class="absolute bottom-3 md:bottom-6 left-3 md:left-6 text-white">
                            <h3 class="text-base md:text-2xl font-semibold mb-1">Perfume</h3>
                            <p class="text-sm text-text-secondary mb-2">GUCCI INTENSE OUD EDP</p>
                            <a href="#" class="border-b border-white pb-1 text-base font-medium">Shop Now</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 8: Services --}}
    @include('shop::components.layouts.services')

    {{-- Scroll To Top Button --}}
    <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="fixed bottom-8 right-8 w-[46px] h-[46px] bg-bg-secondary rounded-full flex items-center justify-center shadow hover:bg-gray-200 transition z-50">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
    </button>
</x-shop::layouts>

@pushOnce('scripts')
    <script type="text/x-template" id="v-category-sidebar-template">
        <ul class="space-y-4 text-sm font-medium">
            <li v-for="category in categories" :key="category.id" class="flex items-center justify-between">
                <a :href="'{{ route('shop.product_or_category.index', '') }}/' + category.slug" class="hover:text-primary">
                    @{{ category.name }}
                </a>
                <span v-if="category.children && category.children.length" class="text-gray-400">&#8250;</span>
            </li>
        </ul>
    </script>

    <script type="text/x-template" id="v-countdown-timer-template">
        <div class="flex gap-4">
            <div class="text-center">
                <span class="text-xs font-medium block mb-1">Days</span>
                <span class="text-3xl font-bold">@{{ String(days).padStart(2, '0') }}</span>
            </div>
            <span class="text-primary text-3xl font-bold">:</span>
            <div class="text-center">
                <span class="text-xs font-medium block mb-1">Hours</span>
                <span class="text-3xl font-bold">@{{ String(hours).padStart(2, '0') }}</span>
            </div>
            <span class="text-primary text-3xl font-bold">:</span>
            <div class="text-center">
                <span class="text-xs font-medium block mb-1">Minutes</span>
                <span class="text-3xl font-bold">@{{ String(minutes).padStart(2, '0') }}</span>
            </div>
            <span class="text-primary text-3xl font-bold">:</span>
            <div class="text-center">
                <span class="text-xs font-medium block mb-1">Seconds</span>
                <span class="text-3xl font-bold">@{{ String(seconds).padStart(2, '0') }}</span>
            </div>
        </div>
    </script>

    <script type="text/x-template" id="v-product-carousel-template">
        <div :id="id" class="flex gap-[30px] overflow-x-auto scroll-smooth pb-4" style="scrollbar-width: none; -ms-overflow-style: none;">
            <template v-for="product in products" :key="product.id">
                <v-product-card :product="product"></v-product-card>
            </template>
        </div>
    </script>

    <script type="text/x-template" id="v-best-sellers-template">
        <div>
            <template v-if="isLoading">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[30px]">
                    <div v-for="i in 4" :key="i" class="max-w-[270px]">
                        <div class="bg-bg-secondary h-[250px] rounded animate-pulse"></div>
                        <div class="mt-4 space-y-2">
                            <div class="h-4 bg-bg-secondary rounded animate-pulse w-3/4"></div>
                            <div class="h-4 bg-bg-secondary rounded animate-pulse w-1/2"></div>
                        </div>
                    </div>
                </div>
            </template>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[30px]">
                <template v-for="product in products" :key="product.id">
                    <v-product-card :product="product"></v-product-card>
                </template>
            </div>
        </div>
    </script>

    <script type="text/x-template" id="v-explore-products-template">
        <div>
            <template v-if="isLoading">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[30px]" style="row-gap: 60px;">
                    <div v-for="i in 8" :key="i" class="max-w-[270px]">
                        <div class="bg-bg-secondary h-[250px] rounded animate-pulse"></div>
                        <div class="mt-4 space-y-2">
                            <div class="h-4 bg-bg-secondary rounded animate-pulse w-3/4"></div>
                            <div class="h-4 bg-bg-secondary rounded animate-pulse w-1/2"></div>
                        </div>
                    </div>
                </div>
            </template>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[30px]" style="row-gap: 60px;">
                <template v-for="product in products" :key="product.id">
                    <v-product-card :product="product"></v-product-card>
                </template>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-category-sidebar', {
            template: '#v-category-sidebar-template',
            data() {
                return {
                    categories: JSON.parse(localStorage.getItem('categories') || '[]'),
                };
            },
        });

        app.component('v-countdown-timer', {
            template: '#v-countdown-timer-template',
            data() {
                const now = new Date();
                const target = new Date(now.getTime() + 3 * 24 * 60 * 60 * 1000);
                return {
                    targetDate: target,
                    days: 0,
                    hours: 0,
                    minutes: 0,
                    seconds: 0,
                };
            },
            mounted() {
                this.updateTimer();
                this.timer = setInterval(this.updateTimer, 1000);
            },
            beforeUnmount() {
                clearInterval(this.timer);
            },
            methods: {
                updateTimer() {
                    const now = new Date();
                    const diff = this.targetDate - now;
                    if (diff <= 0) {
                        this.days = 0;
                        this.hours = 0;
                        this.minutes = 0;
                        this.seconds = 0;
                        return;
                    }
                    this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    this.hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    this.minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    this.seconds = Math.floor((diff % (1000 * 60)) / 1000);
                },
            },
        });

        app.component('v-product-carousel', {
            template: '#v-product-carousel-template',
            props: ['id', 'url'],
            data() {
                return {
                    products: [],
                };
            },
            mounted() {
                this.$axios.get(this.url)
                    .then(response => {
                        this.products = response.data.data;
                    })
                    .catch(error => {});
            },
        });

        app.component('v-best-sellers', {
            template: '#v-best-sellers-template',
            data() {
                return {
                    products: [],
                    isLoading: true,
                };
            },
            mounted() {
                this.$axios.get('{{ route('shop.api.products.index', ['limit' => 4, 'sort' => 'created_at', 'order' => 'desc']) }}')
                    .then(response => {
                        this.products = response.data.data;
                        this.isLoading = false;
                    })
                    .catch(error => {
                        this.isLoading = false;
                    });
            },
        });

        app.component('v-explore-products', {
            template: '#v-explore-products-template',
            data() {
                return {
                    products: [],
                    isLoading: true,
                };
            },
            mounted() {
                this.$axios.get('{{ route('shop.api.products.index', ['new' => 1, 'limit' => 8]) }}')
                    .then(response => {
                        this.products = response.data.data;
                        this.isLoading = false;
                    })
                    .catch(error => {
                        this.isLoading = false;
                    });
            },
        });
    </script>
@endpushOnce
