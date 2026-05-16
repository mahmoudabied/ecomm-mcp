# Tasks: Figma E-Commerce Theme for Bagisto

**Input**: Design documents from `/specs/001-figma-ecommerce-theme/`  
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, quickstart.md  
**Tests**: Not requested — no test tasks included.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

**IMPORTANT FOR IMPLEMENTOR**: This theme is a Bagisto shop theme package at `packages/Webkul/AmacommerceTheme/`. All view files go under `packages/Webkul/AmacommerceTheme/src/Resources/views/` and override the default Shop theme via Bagisto's `shop::` view namespace. All asset files go under `packages/Webkul/AmacommerceTheme/src/Resources/assets/`. After creating/editing views, you MUST run `php artisan vendor:publish --provider="Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider" --force` and `npm run build` inside the package directory to see changes.

**Figma file**: `YPQEFbrM7Fgz6TRSvEmP44` — Use the Figma MCP `get_design_context` tool with `fileKey: "YPQEFbrM7Fgz6TRSvEmP44"` and the nodeId specified in each task to get the exact design code and download URLs for images.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)

## Path Conventions

All paths relative to project root `/var/www/html/bagisto/`:
- **Package root**: `packages/Webkul/AmacommerceTheme/`
- **Views**: `packages/Webkul/AmacommerceTheme/src/Resources/views/`
- **Assets**: `packages/Webkul/AmacommerceTheme/src/Resources/assets/`
- **Config**: `tailwind.config.js`, `vite.config.js` inside package root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configure the design system, create image asset directories, and set up the asset pipeline. No Blade views are created in this phase — only config and assets.

- [x] T001 Update Tailwind design tokens in `packages/Webkul/AmacommerceTheme/tailwind.config.js`. Replace the entire `theme.extend` object with:
  ```js
  colors: {
      primary: '#DB4444',
      'primary-hover': '#E07575',
      secondary: '#00FF66',
      'bg': '#FFFFFF',
      'bg-secondary': '#F5F5F5',
      'text1': '#FAFAFA',
      'text2': '#000000',
      'text-secondary': '#7D8184',
      'rating': '#FFAD33',
      'border-color': 'rgba(0,0,0,0.1)',
  },
  fontFamily: {
      'poppins': ['Poppins', 'sans-serif'],
  },
  maxWidth: {
      'content': '1170px',
  },
  screens: {
      '1440': '1440px',
  },
  ```

- [x] T002 Create image asset directory structure. Run these commands:
  ```bash
  mkdir -p packages/Webkul/AmacommerceTheme/src/Resources/assets/images/{hero,categories,promo,arrivals,services,auth,about}
  ```

- [x] T003 Extract hero banner image from Figma. Use Figma MCP `get_design_context` with `fileKey: "YPQEFbrM7Fgz6TRSvEmP44"` and `nodeId: "45:261"` to get the iPhone hero banner image. Download the image from the returned asset URL and save it to `packages/Webkul/AmacommerceTheme/src/Resources/assets/images/hero/iphone-banner.png`

- [x] T004 [P] Extract promo banner image from Figma. Use `get_design_context` with `nodeId: "79:1199"` to get the JBL speaker image. Save to `packages/Webkul/AmacommerceTheme/src/Resources/assets/images/promo/jbl-speaker.png`

- [x] T005 [P] Extract 4 new arrival images from Figma. For each, use `get_design_context` with the specified nodeId, download the image from the returned asset URL, and save to the specified path:
  - `nodeId: "101:1325"` → save to `images/arrivals/ps5.png` (PlayStation 5)
  - `nodeId: "115:1367"` → save to `images/arrivals/womens-collection.png` (woman posing)
  - `nodeId: "115:1377"` → save to `images/arrivals/speakers.png` (Amazon Echo)
  - `nodeId: "112:1349"` → save to `images/arrivals/perfume.png` (Gucci perfume)

- [x] T006 [P] Extract auth page side image from Figma. Use `get_design_context` with `nodeId: "142:2763"` (Sign Up page) to find the shopping cart/phone side image. Download and save to `packages/Webkul/AmacommerceTheme/src/Resources/assets/images/auth/shopping-side.png`

- [x] T007 [P] Create 6 category SVG icon files. Create simple, clean SVG icons (24x24 viewBox, stroke-based, currentColor for fill) for each category. Save each file in `packages/Webkul/AmacommerceTheme/src/Resources/assets/images/categories/`:
  - `phone.svg` — smartphone outline
  - `computer.svg` — desktop monitor outline
  - `smartwatch.svg` — watch outline
  - `camera.svg` — camera outline
  - `headphones.svg` — headphones outline
  - `gaming.svg` — game controller outline

- [x] T008 [P] Create 3 service SVG icon files. Create simple SVG icons (40x40 viewBox, white fill) for service guarantees. Save in `packages/Webkul/AmacommerceTheme/src/Resources/assets/images/services/`:
  - `delivery.svg` — delivery truck
  - `customer-service.svg` — headset/support
  - `money-back.svg` — shield with checkmark

- [x] T009 [P] Create a simple SVG logo file. Create `packages/Webkul/AmacommerceTheme/src/Resources/assets/images/logo.svg` with the text "Exclusive" in bold Poppins-style font, black color, approximately 131x29px viewBox.

- [x] T010 Update Vite config to include all new image assets. Edit `packages/Webkul/AmacommerceTheme/vite.config.js` and add ALL image files from `images/` subdirectories to the `input` array so they appear in the Vite build manifest. The input array should contain every `.png`, `.svg`, and `.ico` file path under `src/Resources/assets/images/`. Example:
  ```js
  input: [
      "src/Resources/assets/css/app.css",
      "src/Resources/assets/js/app.js",
      "src/Resources/assets/images/favicon.ico",
      "src/Resources/assets/images/logo.svg",
      "src/Resources/assets/images/hero/iphone-banner.png",
      "src/Resources/assets/images/promo/jbl-speaker.png",
      "src/Resources/assets/images/arrivals/ps5.png",
      "src/Resources/assets/images/arrivals/womens-collection.png",
      "src/Resources/assets/images/arrivals/speakers.png",
      "src/Resources/assets/images/arrivals/perfume.png",
      "src/Resources/assets/images/categories/phone.svg",
      "src/Resources/assets/images/categories/computer.svg",
      "src/Resources/assets/images/categories/smartwatch.svg",
      "src/Resources/assets/images/categories/camera.svg",
      "src/Resources/assets/images/categories/headphones.svg",
      "src/Resources/assets/images/categories/gaming.svg",
      "src/Resources/assets/images/services/delivery.svg",
      "src/Resources/assets/images/services/customer-service.svg",
      "src/Resources/assets/images/services/money-back.svg",
      "src/Resources/assets/images/auth/shopping-side.png",
  ],
  ```

- [x] T011 Run `cd packages/Webkul/AmacommerceTheme && npm run build` to verify all assets compile. Fix any build errors before proceeding.

**Checkpoint**: Design tokens configured, all images extracted and building successfully via Vite.

---

## Phase 2: Foundational (Layout Shell — Blocking Prerequisites)

**Purpose**: Create the shared layout components (main HTML wrapper, header, footer, services) that ALL pages depend on. No user story work can begin until this phase is complete.

**⚠️ CRITICAL**: Every page uses `<x-shop::layouts>` which renders `components/layouts/index.blade.php`, which includes the header and footer. These MUST be working first.

- [x] T012 Update the main layout wrapper in `packages/Webkul/AmacommerceTheme/src/Resources/views/components/layouts/index.blade.php`. Make exactly 2 changes to the existing file:
  1. Replace the Google Fonts `<link>` tag: change `Inter` to `Poppins` so it reads: `<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />`
  2. Change the `<body>` class from `font-sans` to `font-poppins`
  Keep EVERYTHING else in this file exactly as-is (all the `view_render_event` calls, `x-shop::flash-group`, `x-shop::modal.confirm`, `@stack` directives, Vue mount script).

- [x] T013 Create the desktop top header bar at `packages/Webkul/AmacommerceTheme/src/Resources/views/components/layouts/header/desktop/top.blade.php`. This is a NEW file. First, use Figma MCP `get_design_context` with `fileKey: "YPQEFbrM7Fgz6TRSvEmP44"` and `nodeId: "145:1472"` to see the exact design of the "Top Header" component.
  
  **Required structure**:
  - Outer `<div>` with `bg-black text-white text-sm`
  - Inner `<div>` with `max-w-content mx-auto flex items-center justify-between py-3 px-4`
  - **Left side**: Empty spacer `<div>` for balance
  - **Center**: `<p>` tag showing: `{{ core()->getConfigData('general.content.header_offer.title') }}` followed by a `<a>` link with `href="{{ core()->getConfigData('general.content.header_offer.redirection_link') }}"` text "ShopNow" with `class="underline font-semibold ml-2"`
  - **Right side**: Language dropdown. Use a simple `<select>` or copy the `v-locale-switcher` Vue component pattern from the default theme at `packages/Webkul/Shop/src/Resources/views/components/layouts/header/desktop/top.blade.php`. Show `{{ core()->getCurrentChannel()->locales()->orderBy('name')->where('code', app()->getLocale())->value('name') }}` with a dropdown arrow.
  
  Wrap the entire file content in `{!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.before') !!}` and `{!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.after') !!}`

- [x] T014 Create the desktop main header/navigation bar at `packages/Webkul/AmacommerceTheme/src/Resources/views/components/layouts/header/desktop/bottom.blade.php`. This is a NEW file. First, use Figma MCP `get_design_context` with `nodeId: "149:1535"` to see the exact "Header" design.

  **Required structure** — a single row with 3 sections inside `max-w-content mx-auto`:
  
  1. **Left — Logo**: `<a href="{{ route('shop.home.index') }}">` containing `<img src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}" width="131" height="29" alt="{{ config('app.name') }}" />`
  
  2. **Center — Navigation links**: An unordered list `<ul class="flex items-center gap-12 text-base">` with these `<li>` items:
     - `<a href="{{ route('shop.home.index') }}">Home</a>`
     - `<a href="{{ route('shop.home.contact_us.index') }}">Contact</a>`
     - `<a href="{{ route('shop.cms.page', 'about-us') }}">About</a>`
     - `<a href="{{ route('shop.customers.register.index') }}">Sign Up</a>`
  
  3. **Right — Search + Icons**: A flex container with:
     - Search input: `<div class="flex items-center bg-bg-secondary rounded px-3 py-2">` containing `<input type="text" placeholder="What are you looking for?" class="bg-transparent text-sm outline-none w-48" />` and a search SVG icon (magnifying glass)
     - Wishlist icon: `<a href="{{ route('shop.customers.account.wishlist.index') }}" class="relative ml-6">` with a heart SVG icon. Show badge count using Bagisto's wishlist count if available.
     - Cart icon: `<a href="{{ route('shop.checkout.cart.index') }}" class="relative ml-4">` with a shopping cart SVG icon. For the cart count badge, use a Vue component or `{{ cart()->getCart()?->items_count ?? 0 }}`.
  
  Wrap in `{!! view_render_event('bagisto.shop.components.layouts.header.desktop.bottom.before') !!}` / `after`.
  
  The overall wrapper should be: `<div class="border-b border-border-color py-4"><div class="max-w-content mx-auto flex items-center justify-between px-4">...</div></div>`

- [x] T015 Update the header dispatcher file at `packages/Webkul/AmacommerceTheme/src/Resources/views/components/layouts/header.blade.php`. Replace the entire file content with:
  ```blade
  {!! view_render_event('bagisto.shop.components.layouts.header.before') !!}

  <!-- Desktop Header -->
  <div class="hidden lg:block">
      @include('shop::components.layouts.header.desktop.top')
      @include('shop::components.layouts.header.desktop.bottom')
  </div>

  <!-- Mobile Header (placeholder — will be implemented in Phase 8) -->
  <div class="lg:hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-border-color">
          <button class="text-2xl">☰</button>
          <a href="{{ route('shop.home.index') }}">
              <img src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}" height="29" alt="{{ config('app.name') }}" />
          </a>
          <a href="{{ route('shop.checkout.cart.index') }}" class="text-2xl">🛒</a>
      </div>
  </div>

  {!! view_render_event('bagisto.shop.components.layouts.header.after') !!}
  ```

- [x] T016 Rewrite the footer at `packages/Webkul/AmacommerceTheme/src/Resources/views/components/layouts/footer.blade.php`. First, use Figma MCP `get_design_context` with `nodeId: "142:1522"` to see the exact "Footer" design.

  **Required structure** — Black background (`bg-black text-white`), inside `max-w-content mx-auto`:
  
  A 5-column grid (`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 py-16 px-4`):
  
  **Column 1 — "Exclusive"**:
  - `<h3 class="text-2xl font-bold mb-4">Exclusive</h3>`
  - `<p class="text-lg mb-4">Subscribe</p>`
  - `<p class="text-sm text-text-secondary mb-4">Get 10% off your first order</p>`
  - Email input + send button: `<div class="flex border border-white/40 rounded">` containing `<input type="email" placeholder="Enter your email" class="bg-transparent px-4 py-2 text-sm outline-none flex-grow" />` and `<button class="px-3">` with a send arrow SVG icon
  
  **Column 2 — "Support"**:
  - `<h3 class="text-xl font-medium mb-6">Support</h3>`
  - `<p class="text-sm text-text-secondary mb-4">111 Bijoy sarani, Dhaka, DH 1515, Bangladesh.</p>`
  - `<p class="text-sm text-text-secondary mb-4">exclusive@gmail.com</p>`
  - `<p class="text-sm text-text-secondary">+88015-88888-9999</p>`
  
  **Column 3 — "Account"**:
  - `<h3 class="text-xl font-medium mb-6">Account</h3>`
  - List of links (`<ul class="space-y-3 text-sm text-text-secondary">`): My Account → `{{ route('shop.customers.account.profile.index') }}`, Login / Register → `{{ route('shop.customers.register.index') }}`, Cart → `{{ route('shop.checkout.cart.index') }}`, Wishlist → `{{ route('shop.customers.account.wishlist.index') }}`, Shop → `{{ route('shop.home.index') }}`
  
  **Column 4 — "Quick Link"**:
  - `<h3 class="text-xl font-medium mb-6">Quick Link</h3>`
  - Links: Privacy Policy, Terms Of Use, FAQ, Contact → `{{ route('shop.home.contact_us.index') }}`
  
  **Column 5 — "Download App"**:
  - `<h3 class="text-xl font-medium mb-6">Download App</h3>`
  - `<p class="text-xs text-text-secondary mb-3">Save $3 with App New User Only</p>`
  - Placeholder boxes for QR code and store badges (gray bg rectangles)
  - Social icons row: 4 SVG icons (Facebook, Twitter, Instagram, LinkedIn) in a `<div class="flex gap-6 mt-6">`
  
  **Below columns**: `<div class="border-t border-white/20 mt-8 pt-8 text-center text-sm text-text-secondary">© Copyright Rimel 2022. All right reserved</div>`
  
  Wrap in `{!! view_render_event('bagisto.shop.components.layouts.footer.before') !!}` / `after`.

- [x] T017 [P] Create the services section at `packages/Webkul/AmacommerceTheme/src/Resources/views/components/layouts/services.blade.php`. This is a NEW file.

  **Required structure** — centered row of 3 service items inside `max-w-content mx-auto py-20 px-4`:
  
  ```blade
  <div class="max-w-content mx-auto py-20 px-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
          <!-- Item 1: Free Delivery -->
          <div class="flex flex-col items-center">
              <div class="w-20 h-20 bg-black rounded-full flex items-center justify-center mb-6 ring-[10px] ring-gray-300/40">
                  <img src="{{ bagisto_asset('images/services/delivery.svg') }}" alt="Delivery" class="w-10 h-10" />
              </div>
              <h3 class="font-bold text-lg mb-2">FREE AND FAST DELIVERY</h3>
              <p class="text-sm text-text-secondary">Free delivery for all orders over $140</p>
          </div>
          <!-- Item 2: Customer Service -->
          <div class="flex flex-col items-center">
              <div class="w-20 h-20 bg-black rounded-full flex items-center justify-center mb-6 ring-[10px] ring-gray-300/40">
                  <img src="{{ bagisto_asset('images/services/customer-service.svg') }}" alt="Support" class="w-10 h-10" />
              </div>
              <h3 class="font-bold text-lg mb-2">24/7 CUSTOMER SERVICE</h3>
              <p class="text-sm text-text-secondary">Friendly 24/7 customer support</p>
          </div>
          <!-- Item 3: Money Back -->
          <div class="flex flex-col items-center">
              <div class="w-20 h-20 bg-black rounded-full flex items-center justify-center mb-6 ring-[10px] ring-gray-300/40">
                  <img src="{{ bagisto_asset('images/services/money-back.svg') }}" alt="Guarantee" class="w-10 h-10" />
              </div>
              <h3 class="font-bold text-lg mb-2">MONEY BACK GUARANTEE</h3>
              <p class="text-sm text-text-secondary">We return money within 30 days</p>
          </div>
      </div>
  </div>
  ```

- [x] T018 Build assets and publish views. Run these commands in order:
  ```bash
  cd /var/www/html/bagisto/packages/Webkul/AmacommerceTheme && npm run build
  cd /var/www/html/bagisto && php artisan vendor:publish --provider="Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider" --force
  php artisan optimize:clear
  ```
  Verify in browser: The site should show the black top announcement bar, the navigation header with logo + nav links + search/icons, the black footer with 5 columns, and Poppins font. Page content area may still show old content — that's OK.

**Checkpoint**: Foundation ready — header, footer, services, design system all working. User story implementation can now begin.

---

## Phase 3: User Story 1 — Homepage Browsing Experience (Priority: P1) 🎯 MVP

**Goal**: Deliver the complete homepage with all 7 content sections (hero + sidebar, flash sales, browse by category, best selling, music promo, explore products, new arrivals) plus the services row. A visitor can land on the homepage and see the full branded e-commerce experience.

**Independent Test**: Navigate to the storefront root URL. Scroll from top to bottom. All 7 sections should render with correct layout, images, product data, and interactive elements (countdown timer, product carousels).

### Implementation for User Story 1

- [x] T019 [US1] Create the product card Blade component at `packages/Webkul/AmacommerceTheme/src/Resources/views/components/products/card.blade.php`. First, use Figma MCP `get_design_context` with `nodeId: "61:2061"` to see the exact "Cart With Flat Discount" component design.

  This is a Vue component that receives a `product` object via props. Follow the exact pattern from the default Shop theme at `packages/Webkul/Shop/src/Resources/views/components/products/card.blade.php` — keep the same Vue component name `v-product-card`, same props, same `@pushOnce('scripts')` pattern, same API calls for add-to-cart and wishlist. Only change the HTML template and CSS classes to match the Figma design.

  **Required card layout** (Vue template `#v-product-card-template`):
  - Outer container: `<div class="w-[270px]">`
  - **Image area**: `<div class="bg-bg-secondary h-[250px] rounded relative flex items-center justify-center group">`
    - **Discount badge** (top-left): `<span v-if="product.special_price" class="absolute top-3 left-3 bg-primary text-white text-xs px-3 py-1 rounded">` showing `-{{ Math.round((1 - product.special_price / product.price) * 100) }}%`
    - **Action icons** (top-right): `<div class="absolute top-3 right-3 flex flex-col gap-2">` containing a wishlist heart button (`<button class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow hover:bg-gray-100">` with heart SVG) and a quick-view eye button (same style with eye SVG)
    - **Add To Cart overlay** (bottom): `<div class="absolute bottom-0 left-0 right-0 bg-black text-white text-center py-2 text-sm opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer" @click="addToCart(product)">Add To Cart</div>`
    - **Product image**: `<a :href="'{{ route('shop.product_or_category.index', '') }}/' + product.url_key"><img :src="product.base_image.medium_image_url" :alt="product.name" class="max-h-[180px] object-contain" /></a>`
  - **Info area** (below image):
    - `<a :href="..." class="font-medium mt-4 block truncate">@{{ product.name }}</a>`
    - `<div class="flex gap-3 mt-2">` — `<span class="text-primary font-medium">@{{ product.formatted_special_price || product.formatted_price }}</span>` + `<span v-if="product.formatted_special_price" class="text-text-secondary line-through">@{{ product.formatted_price }}</span>`
    - `<div class="flex items-center gap-2 mt-2">` — Star rating using filled/empty star SVGs based on `product.ratings.average`, then `<span class="text-text-secondary text-sm">(@{{ product.ratings.total }})</span>`

  **JavaScript section** (`@pushOnce('scripts')`): Copy the methods from the default Shop theme's card component — `addToCart(product)`, `addToWishlist(product)`, `addToCompare(product)`. These make API calls to Bagisto's endpoints. Only change the template reference to `#v-product-card-template`.

- [x] T020 [US1] Rewrite the homepage at `packages/Webkul/AmacommerceTheme/src/Resources/views/home/index.blade.php`. This is the LARGEST task. Replace the entire file content. First get Figma screenshots for reference: use `get_screenshot` with `nodeId: "34:213"` and `maxDimension: 4096`.

  **File structure** — the file must contain these sections in order:

  ```blade
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

      <!-- SECTION 1: Hero with Category Sidebar (Task T021) -->
      <!-- SECTION 2: Flash Sales (Task T022) -->
      <!-- SECTION 3: Browse By Category (Task T023) -->
      <!-- SECTION 4: Best Selling Products (Task T024) -->
      <!-- SECTION 5: Music Promo Banner (Task T025) -->
      <!-- SECTION 6: Explore Our Products (Task T026) -->
      <!-- SECTION 7: New Arrivals (Task T027) -->
      <!-- SECTION 8: Services (Task T028) -->
      <!-- Scroll To Top Button (Task T028) -->

  </x-shop::layouts>
  ```

  Create this file skeleton with placeholder `<!-- Section N -->` comments. Each subsequent task fills in one section.

- [x] T021 [US1] Implement Section 1 — Hero with Category Sidebar — inside `packages/Webkul/AmacommerceTheme/src/Resources/views/home/index.blade.php`. Use Figma MCP `get_design_context` with `nodeId: "157:1748"` for the sidebar and `nodeId: "45:260"` for the hero banner.

  Replace `<!-- SECTION 1 -->` with:

  ```blade
  <div class="max-w-content mx-auto px-4 mt-10">
      <div class="flex gap-8">
          <!-- Category Sidebar -->
          <div class="w-[217px] hidden lg:block border-r border-border-color pr-6 pt-4">
              <v-category-sidebar></v-category-sidebar>
          </div>
          <!-- Hero Banner -->
          <div class="flex-grow pt-4">
              <div class="bg-black text-white rounded overflow-hidden relative" style="height: 344px;">
                  <div class="flex items-center h-full px-16">
                      <div class="flex-1">
                          <div class="flex items-center gap-4 mb-4">
                              <img src="data:image/svg+xml,..." alt="Apple" class="w-10 h-10" />
                              <!-- Use an Apple logo SVG inline or a small image -->
                              <span class="text-base">iPhone 14 Series</span>
                          </div>
                          <h2 class="text-5xl font-semibold leading-tight mb-6">Up to 10%<br>off Voucher</h2>
                          <a href="#" class="inline-flex items-center gap-2 border-b border-white pb-1 text-base font-medium">
                              Shop Now
                              <!-- Right arrow SVG icon -->
                          </a>
                      </div>
                      <div class="flex-1 flex justify-center">
                          <img src="{{ bagisto_asset('images/hero/iphone-banner.png') }}" alt="iPhone 14" class="max-h-[320px] object-contain" />
                      </div>
                  </div>
                  <!-- Pagination dots -->
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
  ```

  **Vue component for sidebar** — Add at the bottom of the file inside `@pushOnce('scripts')`:
  ```blade
  <script type="text/x-template" id="v-category-sidebar-template">
      <ul class="space-y-4 text-sm font-medium">
          <li v-for="category in categories" :key="category.id" class="flex items-center justify-between">
              <a :href="'{{ route('shop.product_or_category.index', '') }}/' + category.slug" class="hover:text-primary">
                  @{{ category.name }}
              </a>
              <span v-if="category.children && category.children.length" class="text-gray-400">›</span>
          </li>
      </ul>
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
  </script>
  ```

- [x] T022 [US1] Implement Section 2 — Flash Sales — inside `home/index.blade.php`. Use Figma MCP `get_design_context` with `nodeId: "142:1506"` for the Flash Sales section layout.

  Replace `<!-- SECTION 2 -->` with a section containing:
  1. **Section header** (same pattern used for all sections):
     ```html
     <div class="max-w-content mx-auto px-4 mt-20">
         <div class="flex items-center gap-4 mb-3">
             <div class="w-5 h-10 bg-primary rounded"></div>
             <span class="text-primary font-semibold">Today's</span>
         </div>
         <div class="flex items-end justify-between mb-10">
             <div class="flex items-end gap-20">
                 <h2 class="text-4xl font-semibold tracking-wide">Flash Sales</h2>
                 <v-countdown-timer></v-countdown-timer>
             </div>
             <div class="flex gap-2">
                 <button class="w-[46px] h-[46px] rounded-full bg-bg-secondary flex items-center justify-center" @click="scrollCarousel('flash-sales', -1)">←</button>
                 <button class="w-[46px] h-[46px] rounded-full bg-bg-secondary flex items-center justify-center" @click="scrollCarousel('flash-sales', 1)">→</button>
             </div>
         </div>
     ```
  2. **Product carousel**: A `<v-product-carousel>` Vue component that:
     - Fetches products from `{{ route('shop.api.products.index') }}?featured=1&limit=10`
     - Renders product cards in a horizontal scroll container (`flex gap-[30px] overflow-x-auto scroll-smooth`) using the `v-product-card` component from T019
     - Container has `id="flash-sales"` for the arrow scroll buttons
  3. **"View All Products" button**: `<div class="text-center mt-10"><a href="{{ route('shop.search.index') }}" class="inline-block bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition">View All Products</a></div>`

  **Vue components** — Add inside `@pushOnce('scripts')`:
  - `v-countdown-timer`: Shows days/hours/minutes/seconds, uses `setInterval` to tick every second. Countdown target: 3 days from current time (or configurable). Display: 4 boxes with small label on top ("Days", "Hours", "Minutes", "Seconds") and bold number below, separated by red `:`.
  - `v-product-carousel`: Fetches products via `axios.get(url)`, stores in `products` array, renders `<v-product-card>` for each in a flex row.

- [x] T023 [US1] Implement Section 3 — Browse By Category — inside `home/index.blade.php`. Use Figma `get_design_context` with `nodeId: "142:1509"`.

  Replace `<!-- SECTION 3 -->` with:
  ```html
  <div class="max-w-content mx-auto px-4 mt-20 border-t border-border-color pt-16">
      <div class="flex items-center gap-4 mb-3">
          <div class="w-5 h-10 bg-primary rounded"></div>
          <span class="text-primary font-semibold">Categories</span>
      </div>
      <div class="flex items-end justify-between mb-10">
          <h2 class="text-4xl font-semibold tracking-wide">Browse By Category</h2>
          <div class="flex gap-2">
              <!-- Left/Right arrow buttons same as flash sales -->
          </div>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-[30px]">
  ```
  Then 6 category boxes, each structured as:
  ```html
  <a href="{{ route('shop.product_or_category.index', 'phones') }}" class="border border-border-color rounded h-[145px] flex flex-col items-center justify-center gap-4 hover:bg-primary hover:text-white hover:border-primary transition group">
      <img src="{{ bagisto_asset('images/categories/phone.svg') }}" alt="Phones" class="w-14 h-14 group-hover:brightness-0 group-hover:invert" />
      <span class="text-base">Phones</span>
  </a>
  ```
  Repeat for: Phones (phone.svg), Computers (computer.svg), SmartWatch (smartwatch.svg), Camera (camera.svg, add `bg-primary text-white border-primary` as default active state), HeadPhones (headphones.svg), Gaming (gaming.svg).

- [x] T024 [US1] Implement Section 4 — Best Selling Products — inside `home/index.blade.php`. Use Figma `get_design_context` with `nodeId: "142:1511"`.

  Replace `<!-- SECTION 4 -->` with:
  ```html
  <div class="max-w-content mx-auto px-4 mt-20 border-t border-border-color pt-16">
      <div class="flex items-center gap-4 mb-3">
          <div class="w-5 h-10 bg-primary rounded"></div>
          <span class="text-primary font-semibold">This Month</span>
      </div>
      <div class="flex items-end justify-between mb-10">
          <h2 class="text-4xl font-semibold tracking-wide">Best Selling Products</h2>
          <a href="{{ route('shop.search.index') }}" class="bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition">View All</a>
      </div>
      <v-best-sellers></v-best-sellers>
  </div>
  ```
  **Vue component** `v-best-sellers`: Fetches 4 products from `{{ route('shop.api.products.index') }}?limit=4&sort=created_at&order=desc` and renders them in a `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[30px]` using `v-product-card`.

- [x] T025 [US1] Implement Section 5 — Music Promo Banner — inside `home/index.blade.php`. Use Figma `get_design_context` with `nodeId: "79:1201"`.

  Replace `<!-- SECTION 5 -->` with:
  ```html
  <div class="max-w-content mx-auto px-4 mt-20">
      <div class="bg-black rounded overflow-hidden relative" style="height: 500px;">
          <div class="flex items-center h-full">
              <div class="flex-1 pl-14">
                  <span class="text-secondary text-base font-semibold">Categories</span>
                  <h2 class="text-5xl font-semibold text-white leading-tight mt-6 mb-8">Enhance Your<br>Music Experience</h2>
                  <!-- Countdown timer: 4 white circles with numbers -->
                  <div class="flex gap-6 mb-10">
                      <div class="w-[62px] h-[62px] bg-white rounded-full flex flex-col items-center justify-center">
                          <span class="text-base font-semibold leading-none">23</span>
                          <span class="text-[11px]">Hours</span>
                      </div>
                      <div class="w-[62px] h-[62px] bg-white rounded-full flex flex-col items-center justify-center">
                          <span class="text-base font-semibold leading-none">05</span>
                          <span class="text-[11px]">Days</span>
                      </div>
                      <div class="w-[62px] h-[62px] bg-white rounded-full flex flex-col items-center justify-center">
                          <span class="text-base font-semibold leading-none">59</span>
                          <span class="text-[11px]">Minutes</span>
                      </div>
                      <div class="w-[62px] h-[62px] bg-white rounded-full flex flex-col items-center justify-center">
                          <span class="text-base font-semibold leading-none">35</span>
                          <span class="text-[11px]">Seconds</span>
                      </div>
                  </div>
                  <a href="#" class="inline-block bg-secondary text-white px-12 py-4 rounded text-base font-medium hover:opacity-90 transition">Buy Now!</a>
              </div>
              <div class="flex-1 flex justify-center relative">
                  <!-- Faded circle gradient behind speaker -->
                  <div class="absolute w-[504px] h-[500px] rounded-full bg-white/10 blur-3xl"></div>
                  <img src="{{ bagisto_asset('images/promo/jbl-speaker.png') }}" alt="JBL Speaker" class="relative z-10 max-h-[420px] object-contain" />
              </div>
          </div>
      </div>
  </div>
  ```
  NOTE: The countdown numbers are hardcoded. Optionally, make them dynamic by reusing the `v-countdown-timer` Vue component from T022.

- [x] T026 [US1] Implement Section 6 — Explore Our Products — inside `home/index.blade.php`. Use Figma `get_design_context` with `nodeId: "142:1516"`.

  Replace `<!-- SECTION 6 -->` with:
  ```html
  <div class="max-w-content mx-auto px-4 mt-20">
      <div class="flex items-center gap-4 mb-3">
          <div class="w-5 h-10 bg-primary rounded"></div>
          <span class="text-primary font-semibold">Our Products</span>
      </div>
      <div class="flex items-end justify-between mb-10">
          <h2 class="text-4xl font-semibold tracking-wide">Explore Our Products</h2>
          <div class="flex gap-2">
              <!-- Left/Right arrow buttons -->
          </div>
      </div>
      <v-explore-products></v-explore-products>
      <div class="text-center mt-10">
          <a href="{{ route('shop.search.index') }}" class="inline-block bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition">View All Products</a>
      </div>
  </div>
  ```
  **Vue component** `v-explore-products`: Fetches 8 products from `{{ route('shop.api.products.index') }}?new=1&limit=8` and renders them in a `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[30px] gap-y-[60px]` (2 rows of 4) using `v-product-card`. Some product cards in the Figma show color variant dots — add colored circles below the rating row if `product.variants` exist.

- [x] T027 [US1] Implement Section 7 — New Arrivals — inside `home/index.blade.php`. Use Figma `get_design_context` with `nodeId: "142:1520"`.

  Replace `<!-- SECTION 7 -->` with:
  ```html
  <div class="max-w-content mx-auto px-4 mt-20">
      <div class="flex items-center gap-4 mb-3">
          <div class="w-5 h-10 bg-primary rounded"></div>
          <span class="text-primary font-semibold">Featured</span>
      </div>
      <h2 class="text-4xl font-semibold tracking-wide mb-10">New Arrival</h2>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-[30px]">
          <!-- Left: PS5 large box -->
          <div class="bg-black rounded overflow-hidden relative" style="height: 600px;">
              <img src="{{ bagisto_asset('images/arrivals/ps5.png') }}" alt="PlayStation 5" class="absolute bottom-0 left-1/2 -translate-x-1/2 max-h-[500px] object-contain" />
              <div class="absolute bottom-8 left-8 text-white">
                  <h3 class="text-2xl font-semibold mb-4">PlayStation 5</h3>
                  <p class="text-sm text-text-secondary mb-4">Black and White version of the PS5<br>coming out on sale.</p>
                  <a href="#" class="border-b border-white pb-1 text-base font-medium">Shop Now</a>
              </div>
          </div>
          <!-- Right: 3 boxes stacked -->
          <div class="grid grid-rows-2 gap-[30px]">
              <!-- Top: Women's Collection -->
              <div class="bg-black rounded overflow-hidden relative" style="height: 284px;">
                  <img src="{{ bagisto_asset('images/arrivals/womens-collection.png') }}" alt="Women's Collections" class="absolute right-0 top-0 h-full object-cover" />
                  <div class="absolute bottom-6 left-6 text-white">
                      <h3 class="text-2xl font-semibold mb-2">Women's Collections</h3>
                      <p class="text-sm text-text-secondary mb-4">Featured woman collections that<br>give you another vibe.</p>
                      <a href="#" class="border-b border-white pb-1 text-base font-medium">Shop Now</a>
                  </div>
              </div>
              <!-- Bottom: 2 boxes side by side -->
              <div class="grid grid-cols-2 gap-[30px]">
                  <!-- Speakers -->
                  <div class="bg-black rounded overflow-hidden relative" style="height: 284px;">
                      <div class="absolute inset-0 flex items-center justify-center">
                          <div class="w-48 h-48 rounded-full bg-white/10 blur-2xl absolute"></div>
                          <img src="{{ bagisto_asset('images/arrivals/speakers.png') }}" alt="Speakers" class="relative z-10 max-h-[200px] object-contain" />
                      </div>
                      <div class="absolute bottom-6 left-6 text-white">
                          <h3 class="text-2xl font-semibold mb-1">Speakers</h3>
                          <p class="text-sm text-text-secondary mb-2">Amazon wireless speakers</p>
                          <a href="#" class="border-b border-white pb-1 text-base font-medium">Shop Now</a>
                      </div>
                  </div>
                  <!-- Perfume -->
                  <div class="bg-black rounded overflow-hidden relative" style="height: 284px;">
                      <div class="absolute inset-0 flex items-center justify-center">
                          <div class="w-48 h-48 rounded-full bg-white/10 blur-2xl absolute"></div>
                          <img src="{{ bagisto_asset('images/arrivals/perfume.png') }}" alt="Perfume" class="relative z-10 max-h-[200px] object-contain" />
                      </div>
                      <div class="absolute bottom-6 left-6 text-white">
                          <h3 class="text-2xl font-semibold mb-1">Perfume</h3>
                          <p class="text-sm text-text-secondary mb-2">GUCCI INTENSE OUD EDP</p>
                          <a href="#" class="border-b border-white pb-1 text-base font-medium">Shop Now</a>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>
  ```

- [x] T028 [US1] Implement Section 8 — Services + Scroll-to-Top — inside `home/index.blade.php`.

  Replace `<!-- SECTION 8 -->` with:
  ```blade
  @include('shop::components.layouts.services')
  ```
  
  Add a scroll-to-top button just before `</x-shop::layouts>`:
  ```html
  <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="fixed bottom-8 right-8 w-[46px] h-[46px] bg-bg-secondary rounded-full flex items-center justify-center shadow hover:bg-gray-200 transition z-50">
      <!-- Up arrow SVG -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
  </button>
  ```

- [x] T029 [US1] Build and verify the complete homepage. Run:
  ```bash
  cd /var/www/html/bagisto/packages/Webkul/AmacommerceTheme && npm run build
  cd /var/www/html/bagisto && php artisan vendor:publish --provider="Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider" --force && php artisan optimize:clear
  ```
  Open the storefront in a browser. Verify all 7 sections render top-to-bottom. Check that product cards load data from the API. Check the countdown timer ticks. Check that "Shop Now" links and product cards are clickable.

**Checkpoint**: Homepage fully functional. User Story 1 (MVP) complete and independently testable.

---

## Phase 4: User Story 2 — Product Discovery and Detail View (Priority: P1)

**Goal**: A shopper can click any product card and see a fully styled product detail page with image gallery, price, ratings, variant selection, quantity, add-to-cart, and related products.

**Independent Test**: Navigate to any product URL (e.g., `/products/test-product`). The page should show the Figma product detail layout with dynamic data from Bagisto.

### Implementation for User Story 2

- [x] T030 [US2] Create the product detail page at `packages/Webkul/AmacommerceTheme/src/Resources/views/products/view.blade.php`. First, use Figma MCP `get_design_context` with `nodeId: "250:4806"` to see the exact "Product Details page" design.

  **Required structure**: Copy the data-binding logic from the default Shop theme at `packages/Webkul/Shop/src/Resources/views/products/view.blade.php` — it receives `$product` from the controller and has Vue components for gallery, variant selection, and add-to-cart. Restyle the HTML/CSS to match Figma:

  - **Breadcrumb**: `Home / Category / Product Name` at top
  - **2-column layout** (`flex gap-[70px] max-w-content mx-auto px-4 py-10`):
    - **Left column** (width ~40%): Image gallery — 4 small thumbnails (120x120) in a vertical column on the left, 1 large image (500x500) on the right. Use `v-product-gallery` Vue component. Thumbnails show `product.images`, clicking one swaps the main image.
    - **Right column** (width ~60%): Product info stacked vertically:
      - Product name: `<h1 class="text-2xl font-semibold">{{ $product->name }}</h1>`
      - Rating + reviews + stock: `<div class="flex items-center gap-4 mt-2">` with star icons, `({{ $product->reviews->count() }} Reviews)`, `<span class="text-secondary">In Stock</span>`
      - Price: `<p class="text-2xl mt-4">{{ $product->getTypeInstance()->getPriceHtml() }}</p>`
      - Description: `<p class="text-sm text-text-secondary mt-4 pb-6 border-b border-border-color">{{ $product->short_description }}</p>`
      - **Colour options**: Colored circles (if configurable product has color attribute)
      - **Size options**: Buttons labeled S, M, L, XL with `border border-border-color rounded px-3 py-1.5` — active one gets `bg-primary text-white border-primary`
      - **Quantity + Buy Now**: `<div class="flex items-center gap-4 mt-6">` with quantity changer (minus/plus buttons around input), `<button class="bg-primary text-white px-12 py-3 rounded">Buy Now</button>`, heart icon button
      - **Delivery info**: 2 bordered boxes stacked: "Free Delivery" (truck icon) and "Return Delivery" (return icon) with descriptions
  - **Related Products section**: Below the main content, `<h2 class="text-2xl font-semibold mb-10">Related Item</h2>` with a `v-product-carousel` that fetches from `{{ route('shop.api.products.index') }}?category_id={{ $product->categories->first()?->id }}&limit=4`

  Keep all existing Bagisto form submissions (`addToCart`, `addToWishlist`) and Vue component logic from the default theme. Only change the templates and CSS classes.

- [x] T031 [US2] Build and verify the product detail page. Run build + publish. Navigate to a product page. Verify: image gallery works, price displays, ratings show, add-to-cart works, related products load.

**Checkpoint**: Product detail page complete. US1 + US2 both working.

---

## Phase 5: User Story 3 — Shopping Cart and Checkout Flow (Priority: P1)

**Goal**: Shopper can view cart with styled product table, update quantities, proceed to checkout with billing form and order summary.

**Independent Test**: Add items to cart, navigate to `/checkout/cart`, verify layout. Click "Proceed to checkout", verify checkout form renders.

### Implementation for User Story 3

- [x] T032 [US3] Create the cart page at `packages/Webkul/AmacommerceTheme/src/Resources/views/checkout/cart/index.blade.php`. Use Figma `get_design_context` with `nodeId: "178:3781"`.

  Copy the Vue component logic from `packages/Webkul/Shop/src/Resources/views/checkout/cart/index.blade.php` — it handles quantity updates, item removal, and cart totals via Bagisto's cart API. Restyle to match Figma:

  - Breadcrumb: `Home / Cart`
  - Cart table (`<table class="w-full">`): Headers: Product, Price, Quantity, Subtotal. Each row shows product thumbnail (54x54), product name, formatted price, quantity input with up/down arrows, row subtotal. "X" delete button at row end.
  - Below table: `<div class="flex justify-between mt-6">` with "Return To Shop" outlined button (left) and "Update Cart" outlined button (right)
  - Below: `<div class="flex justify-between mt-10 gap-[170px]">`
    - Left: Coupon input — `<div class="flex gap-4">` with `<input class="border border-border-color rounded px-4 py-3 w-72" placeholder="Coupon Code" />` and `<button class="bg-primary text-white px-12 py-3 rounded">Apply Coupon</button>`
    - Right: Cart Total box — `<div class="border-2 border-black rounded p-6 w-[470px]">` with `<h3 class="text-xl font-medium mb-6">Cart Total</h3>`, then rows for Subtotal, Shipping (Free), divider, Total, and `<button class="bg-primary text-white w-full py-4 rounded mt-4">Procees to checkout</button>`

- [x] T033 [US3] Create the checkout page at `packages/Webkul/AmacommerceTheme/src/Resources/views/checkout/onepage/index.blade.php`. Use Figma `get_design_context` with `nodeId: "193:4066"`.

  Copy the multi-step checkout logic from `packages/Webkul/Shop/src/Resources/views/checkout/onepage/index.blade.php`. Restyle to match Figma:

  - Breadcrumb: `Account / My Account / Product / View Cart / CheckOut`
  - 2-column layout (`flex gap-[170px] max-w-content mx-auto px-4 py-10`):
    - **Left** (~60%): `<h2 class="text-4xl font-medium mb-10">Billing Details</h2>` + form fields (each as `<div class="mb-8"><label class="text-text-secondary text-base mb-2 block">First Name*</label><input class="w-full bg-bg-secondary rounded px-4 py-3" /></div>`): First Name, Company Name, Street Address, Apartment (optional), Town/City, Phone Number, Email Address. Checkbox: "Save this information for faster check-out next time"
    - **Right** (~40%): Order summary — list of product rows (image 54x32 + name + price), then Subtotal row, Shipping row (Free), divider, Total row. Payment method radio buttons: "Bank" and "Cash on delivery". `<button class="bg-primary text-white w-full py-4 rounded">Place Order</button>`. Below: coupon input (same style as cart).
  
  IMPORTANT: Keep Bagisto's checkout JavaScript logic intact — it handles address validation, shipping methods, payment methods, and order placement. Only change the visual template.

- [x] T034 [US3] Build and verify cart + checkout. Add items, test cart table, update quantities, navigate to checkout. Verify the form renders correctly.

**Checkpoint**: Full purchase flow (browse → cart → checkout) working.

---

## Phase 6: User Story 4 — User Authentication Pages (Priority: P2)

**Goal**: Registration and login pages with the Figma design (side image + form layout).

**Independent Test**: Navigate to `/customer/register` and `/customer/login`. Verify the 2-column layout with side image renders correctly and forms submit.

### Implementation for User Story 4

- [x] T035 [P] [US4] Create the sign-up page at `packages/Webkul/AmacommerceTheme/src/Resources/views/customers/sign-up.blade.php`. Use Figma `get_design_context` with `nodeId: "142:2763"`.

  Copy form submission logic from `packages/Webkul/Shop/src/Resources/views/customers/sign-up.blade.php` (CSRF token, form action `{{ route('shop.customers.register.store') }}`, validation). Restyle:

  - 2-column layout: `<div class="flex min-h-screen">`
    - **Left** (50%): `<div class="w-1/2 hidden lg:block bg-[#CBE4E8]"><img src="{{ bagisto_asset('images/auth/shopping-side.png') }}" class="w-full h-full object-cover" /></div>`
    - **Right** (50%): `<div class="w-full lg:w-1/2 flex items-center justify-center px-20">`
      - `<h2 class="text-4xl font-medium mb-3">Create an account</h2>`
      - `<p class="text-base mb-10">Enter your details below</p>`
      - Form fields (each as `<input class="w-full border-b border-border-color py-3 outline-none mb-6 text-base" placeholder="Name" />`): Name, Email or Phone Number, Password
      - `<button type="submit" class="w-full bg-primary text-white py-4 rounded text-base font-medium mt-4">Create Account</button>`
      - `<button type="button" class="w-full border border-border-color py-4 rounded text-base mt-4 flex items-center justify-center gap-4">` Google icon + "Sign up with Google" `</button>`
      - `<p class="text-center mt-8">Already have account? <a href="{{ route('shop.customers.login.index') }}" class="border-b border-border-color font-medium ml-2">Log in</a></p>`

- [x] T036 [P] [US4] Create the login page at `packages/Webkul/AmacommerceTheme/src/Resources/views/customers/sign-in.blade.php`. Use Figma `get_design_context` with `nodeId: "155:1711"`.

  Same 2-column layout as sign-up. Copy form logic from default theme's `sign-in.blade.php` (action `{{ route('shop.customers.login.store') }}`). Right side:
  - `<h2 class="text-4xl font-medium mb-3">Log in to Exclusive</h2>`
  - `<p class="text-base mb-10">Enter your details below</p>`
  - Form fields: Email or Phone Number, Password (same bottom-border style)
  - `<div class="flex items-center justify-between mt-10"><button type="submit" class="bg-primary text-white px-12 py-4 rounded text-base font-medium">Log In</button><a href="{{ route('shop.customers.forgot-password.create') }}" class="text-primary">Forgot Password?</a></div>`

- [x] T037 [US4] Build and verify auth pages. Navigate to `/customer/register` and `/customer/login`. Verify side image, form layout, and form submission.

**Checkpoint**: Auth pages complete.

---

## Phase 7: User Story 5 — Wishlist, Account, and Static Pages (Priority: P2)

**Goal**: Wishlist, account dashboard, contact, and about pages all styled per Figma.

**Independent Test**: Navigate to each page URL and verify visual layout matches Figma.

### Implementation for User Story 5

- [x] T038 [P] [US5] Create the wishlist page at `packages/Webkul/AmacommerceTheme/src/Resources/views/customers/account/wishlist/index.blade.php`. Use Figma `get_design_context` with `nodeId: "164:4451"`.

  Use Bagisto's account layout wrapper `<x-shop::layouts.account>`. Copy wishlist logic from default theme. Restyle:
  - `<h2 class="text-xl mb-6">Wishlist (@{{ wishlistCount }})</h2>`
  - Grid of product cards (`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[30px]`), each card like the product card from T019 but with a trash/delete icon (top-right) instead of heart, and an "Add To Cart" button as the hover overlay.

- [x] T039 [P] [US5] Create the account dashboard at `packages/Webkul/AmacommerceTheme/src/Resources/views/customers/account/index.blade.php`. Use Figma `get_design_context` with `nodeId: "193:4067"`.

  Use `<x-shop::layouts.account>`. Restyle:
  - Breadcrumb: `Home / My Account`
  - 2-column: left sidebar nav + right content
  - **Left nav**: "Welcome! [Name]" greeting, then `Manage My Account` group (My Profile, Address Book, My Payment Options), `My Orders` group (My Returns, My Cancellations), `My WishList` link. Active link: text-primary.
  - **Right content**: "Edit Your Profile" heading, form with First Name, Last Name (row), Email, Address (row), Password Changes section with Current Password, New Password, Confirm Password fields. "Cancel" text button + "Save Changes" red button at bottom.
  
  Keep Bagisto's existing account update form logic.

- [x] T040 [P] [US5] Create the contact page at `packages/Webkul/AmacommerceTheme/src/Resources/views/home/contact-us.blade.php`. Use Figma `get_design_context` with `nodeId: "208:4308"`.

  Copy form logic from default theme (`route('shop.home.contact_us.store')`). Restyle:
  - Breadcrumb: `Home / Contact`
  - 2-column (`flex gap-[30px] max-w-content mx-auto px-4 py-10`):
    - **Left** (~30%): Two info cards with shadow:
      1. Phone icon (red circle bg) + "Call To Us" heading + "We are available 24/7, 7 days a week." + "Phone: +8801611112222"
      2. Divider line
      3. Email icon (red circle bg) + "Write To Us" heading + "Fill out our form and we will contact you within 24 hours." + "Emails: customer@exclusive.com" + "Emails: support@exclusive.com"
    - **Right** (~70%): Contact form with 3 inputs in a row (Your Name, Your Email, Your Phone — each `bg-bg-secondary rounded px-4 py-3`) + large textarea (`bg-bg-secondary rounded px-4 py-3 h-48 w-full mt-8`) + "Send Message" button (`bg-primary text-white px-12 py-4 rounded mt-8 float-right`)

- [x] T041 [P] [US5] Create the CMS/About page at `packages/Webkul/AmacommerceTheme/src/Resources/views/cms/page.blade.php`. Use Figma `get_design_context` with `nodeId: "205:4904"`.

  This is a generic CMS page template. Wrap Bagisto's `{!! $page->html_content !!}` in the theme layout:
  ```blade
  <x-shop::layouts>
      <x-slot:title>{{ $page->meta_title ?? $page->page_title }}</x-slot>
      <div class="max-w-content mx-auto px-4 py-10">
          <nav class="text-sm text-text-secondary mb-8">
              <a href="{{ route('shop.home.index') }}">Home</a> / <span>{{ $page->page_title }}</span>
          </nav>
          <div class="prose max-w-none">
              {!! $page->html_content !!}
          </div>
      </div>
      @include('shop::components.layouts.services')
  </x-shop::layouts>
  ```
  NOTE: The Figma "About" page has specific sections (Our Story, stats, team). These would be authored as HTML content in Bagisto's CMS admin, not hardcoded in the template. The template just provides the wrapper.

- [x] T042 [US5] Build and verify all pages. Run build + publish. Navigate to wishlist, account, contact, and about pages.

**Checkpoint**: All secondary pages complete.

---

## Phase 8: User Story 6 — 404 Error Page and Navigation Components (Priority: P3)

**Goal**: Custom 404 error page and polished navigation dropdowns.

**Independent Test**: Navigate to a non-existent URL. Verify 404 page shows.

### Implementation for User Story 6

- [x] T043 [US6] Create the 404 error page at `packages/Webkul/AmacommerceTheme/src/Resources/views/errors/index.blade.php`. Use Figma `get_design_context` with `nodeId: "175:3732"`.

  ```blade
  <x-shop::layouts>
      <x-slot:title>404 Not Found</x-slot>
      <div class="max-w-content mx-auto px-4 py-20">
          <nav class="text-sm text-text-secondary mb-20">
              <a href="{{ route('shop.home.index') }}">Home</a> / <span>404 Error</span>
          </nav>
          <div class="text-center">
              <h1 class="text-[110px] font-medium mb-10">404 Not Found</h1>
              <p class="text-base mb-20">Your visited page not found. You may go home page.</p>
              <a href="{{ route('shop.home.index') }}" class="inline-block bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition">Back to home page</a>
          </div>
      </div>
  </x-shop::layouts>
  ```

- [x] T044 [US6] Build and verify. Run build + publish. Navigate to `/nonexistent-page`. Verify 404 page renders.

**Checkpoint**: All user stories complete.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Mobile responsiveness, final build, and cross-page QA.

- [x] T045 Create the mobile header at `packages/Webkul/AmacommerceTheme/src/Resources/views/components/layouts/header/mobile/index.blade.php`. Use Figma `get_design_context` with `nodeId: "135:1508"` to see the mobile design.

  Implement a mobile-first header with:
  - Hamburger menu button (left), centered logo, cart icon (right)
  - Sliding drawer (`v-mobile-menu` Vue component): full-screen overlay with category list, search bar, account/wishlist links
  - Close button on the drawer

  Then update the header dispatcher (`header.blade.php`) to show this component for `lg:hidden` instead of the current placeholder.

- [x] T046 Add responsive Tailwind classes to all pages. Go through each view file and ensure:
  - Homepage sections: grids change from 4-col (`lg:grid-cols-4`) to 2-col (`sm:grid-cols-2`) to 1-col on mobile
  - Hero sidebar hides on mobile (`hidden lg:block` — already done)
  - Hero banner adjusts height on mobile
  - New arrivals grid stacks vertically on mobile
  - Product detail page: 2-col becomes single-col on mobile
  - Cart table: becomes card-based layout on mobile (each item as a card)
  - Auth pages: side image hides on mobile (`hidden lg:block`), form takes full width
  - Footer: 5-col grid becomes stacked on mobile (`grid-cols-1 md:grid-cols-2 lg:grid-cols-5`)

- [x] T047 Final build, publish, and comprehensive verification:
  ```bash
  cd /var/www/html/bagisto/packages/Webkul/AmacommerceTheme && npm run build
  cd /var/www/html/bagisto && php artisan vendor:publish --provider="Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider" --force && php artisan optimize:clear
  ```
  Test ALL pages at desktop (1440px) and mobile (375px) viewport widths:
  1. Homepage — all 7 sections, countdown timer, product cards load
  2. Product detail — gallery, variants, add-to-cart
  3. Cart — quantity changes, coupon, totals
  4. Checkout — form fields, order summary
  5. Login / Register — form submission
  6. Wishlist — remove items, add to cart
  7. Account — profile form
  8. Contact — form renders
  9. 404 — error page
  10. Mobile — hamburger menu, responsive grids

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 (needs design tokens and images)
- **Phase 3 (US1 Homepage)**: Depends on Phase 2 (needs header/footer/layout)
- **Phase 4 (US2 Product)**: Depends on Phase 2 only (header/footer). Can run parallel to US1 if the product card component from T019 is complete.
- **Phase 5 (US3 Cart/Checkout)**: Depends on Phase 2 only
- **Phase 6 (US4 Auth)**: Depends on Phase 2 only. Can run parallel to US1-US3.
- **Phase 7 (US5 Wishlist/Account/Static)**: Depends on Phase 2 only
- **Phase 8 (US6 404/Nav)**: Depends on Phase 2 only
- **Phase 9 (Polish)**: Depends on all previous phases

### User Story Dependencies

- **US1 (Homepage)**: Produces the product card component (T019) which is reused by US2, US5
- **US2 (Product Detail)**: Independent after T019 is complete
- **US3 (Cart/Checkout)**: Fully independent after Phase 2
- **US4 (Auth Pages)**: Fully independent after Phase 2
- **US5 (Wishlist/Account/Static)**: Uses product card from US1; otherwise independent
- **US6 (404/Nav)**: Fully independent after Phase 2

### Within Each User Story

- Get Figma design context first (visual reference)
- Build the Blade template with correct Bagisto data bindings
- Add Vue components for interactive elements
- Build + publish + verify in browser

### Parallel Opportunities

- T003, T004, T005, T006, T007, T008, T009 (all image extractions) can run in parallel
- T035, T036 (sign-up, login) can run in parallel
- T038, T039, T040, T041 (wishlist, account, contact, about) can ALL run in parallel
- US3, US4, US5, US6 can all start once Phase 2 is complete (they only need layout)

---

## Parallel Example: Phase 1 (Setup)

```
# These tasks create different files and can ALL run in parallel:
T003: Extract hero banner image
T004: Extract promo banner image
T005: Extract arrival images
T006: Extract auth side image
T007: Create category SVG icons
T008: Create service SVG icons
T009: Create logo SVG
```

## Parallel Example: After Phase 2

```
# These phases work on different page files and can run in parallel:
Phase 6 (US4): Sign-up + Login pages
Phase 7 (US5): Wishlist + Account + Contact + About pages
Phase 8 (US6): 404 error page
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (design tokens, images)
2. Complete Phase 2: Foundational (header, footer, layout)
3. Complete Phase 3: User Story 1 (Homepage)
4. **STOP and VALIDATE**: Open storefront, scroll full homepage
5. Homepage is live and branded — deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → Shell ready (header/footer/fonts)
2. Add US1 (Homepage) → **MVP live!**
3. Add US2 (Product Detail) → Product pages branded
4. Add US3 (Cart/Checkout) → Full purchase flow branded
5. Add US4 (Auth) → Login/register branded
6. Add US5 (Secondary pages) → Full site branded
7. Add US6 (404/Nav) → Polish complete
8. Phase 9 → Mobile responsive + final QA

---

## Notes

- Every Blade file MUST use `{!! view_render_event('bagisto.shop...') !!}` hooks to maintain Bagisto extension compatibility
- Product data comes from Bagisto APIs — never hardcode product information
- Static images (hero, promo, arrivals) are theme assets referenced via `bagisto_asset()`
- Vue components follow Bagisto's inline `<script type="text/x-template">` pattern — NOT Single File Components
- After every batch of changes: `npm run build` + `vendor:publish --force` + `optimize:clear`
- The `shop::` view namespace resolves to the theme's `views_path` first, then falls back to default Shop package
