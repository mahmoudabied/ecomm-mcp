# Implementation Plan: Figma E-Commerce Theme for Bagisto

**Branch**: `001-figma-ecommerce-theme` | **Date**: 2026-05-16 | **Spec**: [spec.md](spec.md)  
**Input**: Feature specification from `/specs/001-figma-ecommerce-theme/spec.md`

## Summary

Implement a complete e-commerce storefront theme for Bagisto based on a Figma design with 14 screens. The theme is built as a Laravel Blade package (`AmacommerceTheme`) using Tailwind CSS, Vite, and Bagisto's view override system. It overrides the default Shop theme's views to match the Figma design while integrating with Bagisto's existing product catalog, cart, checkout, customer, and wishlist systems.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11.x / Blade templates  
**Primary Dependencies**: Bagisto 2.x core, Tailwind CSS 3.x, Vite 5.x, Vue 3 (inline components)  
**Storage**: N/A (presentation layer only — uses Bagisto's existing database)  
**Testing**: Browser testing (visual verification against Figma), PHPUnit for smoke tests  
**Target Platform**: Web (desktop 1440px primary, tablet 768px, mobile < 768px)  
**Project Type**: Bagisto theme package (Laravel package with Blade views + assets)  
**Performance Goals**: Homepage loads all sections within 3 seconds; product images lazy-loaded  
**Constraints**: Must not modify Bagisto core; all customization via view overrides and theme config  
**Scale/Scope**: 14 pages, ~25 Blade view files, ~20 static image assets, 1 Tailwind config, 1 Vite config

## Constitution Check

*Constitution file is a blank template — no project-specific gates defined. Proceeding with standard Laravel/Bagisto best practices.*

## Project Structure

### Documentation (this feature)

```text
specs/001-figma-ecommerce-theme/
├── plan.md              # This file
├── spec.md              # Feature specification
├── research.md          # Phase 0: Architecture research
├── data-model.md        # Phase 1: Data model (existing entities)
├── quickstart.md        # Phase 1: How to build and run
└── tasks.md             # Phase 2 output (created by /speckit.tasks)
```

### Source Code (repository root)

```text
packages/Webkul/AmacommerceTheme/
├── package.json
├── package-lock.json
├── vite.config.js
├── tailwind.config.js
├── postcss.config.js
└── src/
    ├── Providers/
    │   └── AmacommerceThemeServiceProvider.php
    └── Resources/
        ├── assets/
        │   ├── css/app.css
        │   ├── js/app.js
        │   └── images/
        │       ├── hero/
        │       │   └── iphone-banner.png
        │       ├── categories/
        │       │   ├── phone.svg
        │       │   ├── computer.svg
        │       │   ├── smartwatch.svg
        │       │   ├── camera.svg
        │       │   ├── headphones.svg
        │       │   └── gaming.svg
        │       ├── promo/
        │       │   └── jbl-speaker.png
        │       ├── arrivals/
        │       │   ├── ps5.png
        │       │   ├── womens-collection.png
        │       │   ├── speakers.png
        │       │   └── perfume.png
        │       ├── services/
        │       │   ├── delivery.svg
        │       │   ├── customer-service.svg
        │       │   └── money-back.svg
        │       ├── auth/
        │       │   └── shopping-side.png
        │       ├── about/
        │       │   ├── portrait-1.png
        │       │   ├── portrait-2.png
        │       │   └── portrait-3.png
        │       ├── logo.svg
        │       └── favicon.ico
        └── views/
            ├── components/
            │   ├── layouts/
            │   │   ├── index.blade.php
            │   │   ├── header.blade.php
            │   │   ├── footer.blade.php
            │   │   ├── services.blade.php
            │   │   └── header/
            │   │       ├── desktop/
            │   │       │   ├── top.blade.php
            │   │       │   └── bottom.blade.php
            │   │       └── mobile/
            │   │           └── index.blade.php
            │   └── products/
            │       └── card.blade.php
            ├── home/
            │   ├── index.blade.php
            │   └── contact-us.blade.php
            ├── products/
            │   └── view.blade.php
            ├── checkout/
            │   ├── cart/
            │   │   └── index.blade.php
            │   └── onepage/
            │       └── index.blade.php
            ├── customers/
            │   ├── sign-in.blade.php
            │   ├── sign-up.blade.php
            │   └── account/
            │       ├── index.blade.php
            │       └── wishlist/
            │           └── index.blade.php
            ├── errors/
            │   └── index.blade.php
            └── cms/
                └── page.blade.php
```

**Structure Decision**: Uses the existing `packages/Webkul/AmacommerceTheme/` package. Views in the package are published to `resources/themes/amacommerce/views/` which is configured as the `views_path` in `config/themes.php`. Bagisto's theme engine prepends this path to Laravel's view finder, so any view file present here overrides the matching `shop::` namespaced view from the default Shop package.

---

## Implementation Phases

### Phase 1: Foundation — Design System, Layout, and Asset Pipeline

**Goal**: Establish the design system (Tailwind config), main layout shell (header/footer), extract all Figma static images, and configure the asset pipeline so every subsequent phase can focus purely on page content.

**Why first**: Every other page depends on the layout wrapper, design tokens, and image assets being in place.

#### Task 1.1: Update Tailwind Config with Full Design System

**File**: `packages/Webkul/AmacommerceTheme/tailwind.config.js`

**What to do**:
1. Open `tailwind.config.js`
2. Replace the `theme.extend` section with the complete design tokens from the Figma design:

```js
/** @type {import('tailwindcss').Config} */
export default {
    content: ["./src/Resources/**/*.blade.php", "./src/Resources/**/*.js"],
    theme: {
        extend: {
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
        },
    },
    plugins: [],
};
```

3. Update the Google Fonts link in `components/layouts/index.blade.php` to load Poppins instead of Inter:
```html
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
```

4. Update the `<body>` class to use `font-poppins`

#### Task 1.2: Extract All Static Images from Figma

**What to do**: Use the Figma MCP `get_design_context` tool on each relevant node to get image download URLs. Download each image and save to the appropriate directory under `src/Resources/assets/images/`.

**Figma nodes to extract images from**:

| Image | Figma Node to inspect | Save to |
|-------|----------------------|---------|
| Hero banner (iPhone) | `34:213` → Frame 560 area (node `45:261`) | `images/hero/iphone-banner.png` |
| JBL Speaker promo | `79:1201` → Frame 600 (node `79:1199`) | `images/promo/jbl-speaker.png` |
| PS5 new arrival | `95:2549` (node `101:1325`) | `images/arrivals/ps5.png` |
| Women's collection | `95:2550` (node `115:1367`) | `images/arrivals/womens-collection.png` |
| Amazon Echo speakers | `95:2552` (node `115:1377`) | `images/arrivals/speakers.png` |
| Gucci perfume | `95:2553` (node `112:1349`) | `images/arrivals/perfume.png` |
| Auth page side image | From Sign Up page (node `142:2763`) | `images/auth/shopping-side.png` |
| About page portraits | From About page (node `205:4904`) | `images/about/portrait-*.png` |

For **category icons** and **service icons**: Extract SVGs from the Figma Components page (node `1:10`) or create simple SVG icons matching the Figma design (phone, computer, smartwatch, camera, headphones, gaming controller, delivery truck, headset, shield).

After extracting all images, add them to the Vite config `input` array in `vite.config.js` so they appear in the build manifest:

```js
input: [
    "src/Resources/assets/css/app.css",
    "src/Resources/assets/js/app.js",
    "src/Resources/assets/images/favicon.ico",
    // Add all new image paths here
],
```

**Alternative for images**: Instead of adding every image to Vite's input array, you can import them from `app.js` or `app.css` using `url()` references, OR place them directly in `public/themes/shop/amacommerce/images/` and reference them with absolute URLs. The Vite input approach is cleanest for the `bagisto_asset()` helper.

#### Task 1.3: Implement Main Layout (index.blade.php)

**File**: `src/Resources/views/components/layouts/index.blade.php`

**What to do**: Update the existing layout file. It already has the correct structure. Changes needed:
1. Change Google Fonts from Inter to Poppins (per Task 1.1)
2. Change `<body>` class from `font-sans` to `font-poppins`
3. Keep all existing Bagisto integration (`view_render_event`, `x-shop::flash-group`, `x-shop::modal.confirm`, `@stack('scripts')`, Vue app mount)

**Reference**: The existing file at `src/Resources/views/components/layouts/index.blade.php` is already well-structured. Only font changes needed.

#### Task 1.4: Implement Top Header Bar

**File**: `src/Resources/views/components/layouts/header/desktop/top.blade.php` (NEW)

**Figma reference**: Top black bar with announcement text, visible at node `145:1472` ("Top Header" instance)

**What to do**: Create this file. The Figma design shows a black top bar with:
- Center: Announcement text "Summer Sale For All Swim Suits And Free Express Delivery - OFF 50%! ShopNow"
- Right: Language selector dropdown

Use Bagisto's `core()->getConfigData('general.content.header_offer.title')` for the offer text. Follow the default Shop theme's top bar pattern (`v-topbar` Vue component) but restyle with:
- Black background (`bg-black`)
- White text (`text-white`)
- Centered layout
- Font size 14px

#### Task 1.5: Implement Main Header/Navigation Bar

**File**: `src/Resources/views/components/layouts/header/desktop/bottom.blade.php` (NEW)

**Figma reference**: Node `149:1535` ("Header" instance)

**What to do**: Create this file. The Figma header has a 1170px content area with:
- **Left**: "Exclusive" text logo (or channel logo via `core()->getCurrentChannel()->logo_url`)
- **Center**: Navigation links: Home, Contact, About, Sign Up
- **Right**: Search input (gray bg, no border, with search icon), heart/wishlist icon with badge, cart icon with badge

Follow the default Shop theme's `v-desktop-category` pattern for category navigation. Use the existing `x-shop::dropdown` component for dropdowns.

Key CSS: `max-w-content mx-auto`, flex layout, items centered, 16px Poppins font.

#### Task 1.6: Implement Header Dispatcher

**File**: `src/Resources/views/components/layouts/header.blade.php` (EXISTS — update)

**What to do**: This file dispatches to desktop or mobile header. It should include:
```blade
@include('shop::components.layouts.header.desktop.top')
@include('shop::components.layouts.header.desktop.bottom')
```

For mobile, add a responsive breakpoint that shows the mobile header instead.

#### Task 1.7: Implement Footer

**File**: `src/Resources/views/components/layouts/footer.blade.php` (EXISTS — update)

**Figma reference**: Node `142:1522` ("Footer" instance)

**What to do**: Rewrite the footer to match Figma. The design shows a black background footer with 5 columns:
1. **Exclusive**: Logo/brand name, "Subscribe" text, "Get 10% off your first order", email input + send button
2. **Support**: Address, email, phone
3. **Account**: My Account, Login/Register, Cart, Wishlist, Shop links
4. **Quick Link**: Privacy Policy, Terms Of Use, FAQ, Contact links
5. **Download App**: "Save $3 with App New User Only" text, QR code, App Store/Google Play badges, social media icons (Facebook, Twitter, Instagram, LinkedIn)

Below columns: Copyright text centered, gray separator line.

#### Task 1.8: Implement Services Section

**File**: `src/Resources/views/components/layouts/services.blade.php` (NEW)

**Figma reference**: Node `120:1579` ("Frame 702" instance)

**What to do**: Create the service guarantees row with 3 items:
1. Delivery truck icon + "FREE AND FAST DELIVERY" + "Free delivery for all orders over $140"
2. Headset icon + "24/7 CUSTOMER SERVICE" + "Friendly 24/7 customer support"
3. Shield icon + "MONEY BACK GUARANTEE" + "We return money within 30 days"

Each item: circular icon container (black bg, white icon) with gray ring border, centered text below.

#### Task 1.9: Build Assets and Verify

**What to do**:
```bash
cd packages/Webkul/AmacommerceTheme
npm run build
cd /var/www/html/bagisto
php artisan vendor:publish --provider="Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider" --force
php artisan optimize:clear
```

Verify in browser: The site should show the new header (black top bar + nav), footer (black with columns), and Poppins font. The page content area will still be the old homepage — that's Phase 2.

---

### Phase 2: Homepage — All Sections

**Goal**: Implement the complete homepage with all 7 content sections matching the Figma design.

**Depends on**: Phase 1 (layout, design system, images, header/footer must be working)

#### Task 2.1: Homepage — Hero Section with Category Sidebar

**File**: `src/Resources/views/home/index.blade.php`

**Figma reference**: Nodes `157:1748` (sidebar) + `45:260` (hero banner)

**What to do**: Rewrite `home/index.blade.php`. Start with the SEO meta from the default theme, then build the hero section:

**Left sidebar** (width ~217px, border-right divider):
- List of category links from `$categories` variable
- "Woman's Fashion" and "Men's Fashion" have dropdown arrows (expandable subcategories)
- Other items: Electronics, Home & Lifestyle, Medicine, Sports & Outdoor, Baby's & Toys, Groceries & Pets, Health & Beauty
- Use a Vue component `v-category-sidebar` that reads from `localStorage.getItem('categories')` (set by default controller)

**Right hero banner** (flex-grow):
- Black background, rounded corners
- Left side: Apple logo icon + "iPhone 14 Series" text, large "Up to 10% off Voucher" heading, "Shop Now →" link with underline
- Right side: Hero image (from `images/hero/iphone-banner.png`)
- Bottom center: 5 pagination dots (3rd active/larger, red)
- Make this a carousel if multiple banners exist, or static for now

```blade
@push('scripts')
    <script>
        localStorage.setItem('categories', JSON.stringify(@json($categories)));
    </script>
@endpush

<x-shop::layouts>
    <x-slot:title>
        {{ core()->getCurrentChannel()->home_seo['meta_title'] ?? '' }}
    </x-slot>

    <!-- Hero Section -->
    <div class="max-w-content mx-auto px-4 mt-10">
        <div class="flex gap-8">
            <!-- Category Sidebar -->
            <div class="w-[217px] hidden lg:block border-r border-border-color pr-6 pt-4">
                ...categories...
            </div>
            <!-- Hero Banner -->
            <div class="flex-grow pt-4">
                ...banner...
            </div>
        </div>
    </div>
    
    <!-- remaining sections follow -->
</x-shop::layouts>
```

#### Task 2.2: Homepage — Flash Sales Section

**Figma reference**: Node `142:1506` ("Frame 728")

**What to do**: Add below the hero section in `home/index.blade.php`:

Structure:
- Red pill + "Today's" label
- "Flash Sales" heading + countdown timer (Days:Hours:Minutes:Seconds) + left/right arrows
- Horizontal scrollable row of product cards (5 visible on desktop)

**Countdown timer**: Create a `v-flash-sale-timer` Vue component that counts down to a configurable end date. Each time unit in a small box: label on top ("Days", "Hours", etc.), large number below. Red `:` separators between units.

**Product cards**: Use `v-product-carousel` Vue component that fetches from `route('shop.api.products.index', ['featured' => 1, 'limit' => 10])`. Each card has:
- Gray bg (`bg-secondary`) container
- Discount badge (top-left, red): "-40%"
- Wishlist heart + eye/quick-view icons (top-right, white circles)
- "Add To Cart" overlay bar (bottom, black bg, appears on hover)
- Product image centered
- Below card: Product name, price (red) + original price (strikethrough gray), star rating + review count

#### Task 2.3: Homepage — Browse By Category Section

**Figma reference**: Node `142:1509` ("Frame 730")

**What to do**: Add section with:
- Red pill + "Categories" label
- "Browse By Category" heading + left/right arrows
- 6 category boxes in a row (170x145px each):
  - Each box: border, rounded, centered icon (SVG from `images/categories/`) + category name below
  - Hover: red background, white text/icon
  - One box (Camera) shown as active/red in Figma

Use category SVG icons extracted in Task 1.2. Link each to its Bagisto category page.

#### Task 2.4: Homepage — Best Selling Products Section

**Figma reference**: Node `142:1511` ("Frame 732")

**What to do**: Add section with:
- Red pill + "This Month" label
- "Best Selling Products" heading + "View All" red button (right side)
- 4 product cards in a row

Fetch products from `route('shop.api.products.index', ['sort' => 'quantity_sold', 'order' => 'desc', 'limit' => 4])` or use featured products. Reuse the same product card component from Task 2.2 but WITHOUT the discount badge or "Add To Cart" hover (these are regular cards, not flash sale cards — check Figma for differences).

#### Task 2.5: Homepage — Music Promo Banner Section

**Figma reference**: Node `79:1201` ("Frame 600")

**What to do**: Add a full-width promotional banner:
- Black background, 500px height
- Left side: "Categories" label (green), "Enhance Your Music Experience" large heading, countdown timer (same style as flash sales), green "Buy Now!" button
- Right side: Large JBL speaker image (from `images/promo/jbl-speaker.png`) with faded circular gradient

#### Task 2.6: Homepage — Explore Our Products Section

**Figma reference**: Node `142:1516` ("Frame 736")

**What to do**: Add section with:
- Red pill + "Our Products" label
- "Explore Our Products" heading + left/right arrows
- 2 rows of 4 product cards each (8 total)
- Below: centered "View All Products" red button

Fetch from `route('shop.api.products.index', ['limit' => 8])`. Product cards include color variant dots below the card for products with color options.

#### Task 2.7: Homepage — New Arrivals Section

**Figma reference**: Node `142:1520` ("Frame 740")

**What to do**: Add section with:
- Red pill + "Featured" label
- "New Arrival" heading
- Asymmetric grid layout (NOT a product carousel — these are feature boxes):
  - **Left large box** (570x600px): PS5 image on black bg, "PlayStation 5" title, description, "Shop Now" link
  - **Right top box** (570x284px): Women's collection image on black bg, "Women's Collections" title, description, "Shop Now" link
  - **Right bottom left box** (270x284px): Amazon Echo speakers on black bg with circular gradient, "Speakers" title, "Amazon wireless speakers", "Shop Now" link
  - **Right bottom right box** (270x284px): Gucci perfume on black bg with circular gradient, "Perfume" title, "GUCCI INTENSE OUD EDP", "Shop Now" link

All boxes have white text, "Shop Now" link with underline.

#### Task 2.8: Homepage — Services and Final Assembly

**What to do**:
1. Include the services section (from Task 1.8) before the footer: `@include('shop::components.layouts.services')`
2. Add a "scroll to top" arrow button (fixed, bottom-right corner) — Figma node `165:4508`
3. Add horizontal line separators between sections (matching Figma spacing)
4. Verify the complete homepage top-to-bottom matches Figma

---

### Phase 3: Product Detail Page

**Goal**: Implement the product view page matching the Figma design.

**Depends on**: Phase 1 (layout)

#### Task 3.1: Product Detail Page

**File**: `src/Resources/views/products/view.blade.php`

**Figma reference**: Node `250:4806` ("Product Details page")

**What to do**: Get the Figma design context for node `250:4806` to see exact layout. The typical e-commerce product page has:

**Top section** (2 columns):
- **Left**: Product image gallery — 4 small thumbnails vertically on the left, 1 large main image on the right. Clicking a thumbnail swaps it into the main view.
- **Right**: Product info:
  - Product name (24px Poppins semibold)
  - Star rating + review count + "In Stock" status (green)
  - Price (24px)
  - Description text (14px, gray)
  - Horizontal divider
  - Color options: labeled circles (selectable)
  - Size options: labeled buttons (S, M, L, XL) — one active with red bg
  - Quantity selector (- number +) + "Buy Now" red button
  - Wishlist heart icon button
  - Delivery info boxes: "Free Delivery" with icon, "Return Delivery" with icon

**Bottom section**: "Related Items" heading + product card row (reuse card component).

Integrate with Bagisto's existing product view data: `$product` variable has all attributes, images, variants. Use `x-shop::media` for image gallery, `v-product-card` for related products.

---

### Phase 4: Cart and Checkout Pages

**Goal**: Implement cart and checkout pages matching Figma.

**Depends on**: Phase 1 (layout), Phase 2 partially (product card component)

#### Task 4.1: Cart Page

**File**: `src/Resources/views/checkout/cart/index.blade.php`

**Figma reference**: Node `178:3781` ("Cart")

**What to do**: Get Figma design context for cart page. Expected layout:
- Breadcrumb: Home / Cart
- Cart table with columns: Product (image + name), Price, Quantity (input with arrows), Subtotal
- Below table: "Return To Shop" button (left), "Update Cart" button (right)
- Below: 2-column layout — Coupon code input (left), Cart Total box (right) with Subtotal, Shipping (Free), Total, "Process to checkout" red button

Use Bagisto's existing cart Vue components but restyle. The cart page is heavily interactive (quantity changes, item removal) so keep the existing Vue logic from the default theme and override only the template markup and CSS classes.

#### Task 4.2: Checkout Page

**File**: `src/Resources/views/checkout/onepage/index.blade.php`

**Figma reference**: Node `193:4066` ("CheckOut")

**What to do**: Get Figma design context. Expected layout:
- Breadcrumb: Account / My Account / Product / View Cart / CheckOut
- 2-column layout:
  - **Left** (wider): "Billing Details" form — First Name, Company Name, Street Address, Apartment, Town/City, Phone Number, Email Address, "Save this information" checkbox
  - **Right** (narrower): Order summary — product images + names + prices, Subtotal, Shipping (Free), Total, payment method radio buttons (Bank, Cash on delivery), "Place Order" red button, coupon input

Use Bagisto's existing checkout multi-step logic but restyle the form fields and layout.

---

### Phase 5: Auth Pages (Sign Up, Login)

**Goal**: Implement registration and login pages.

**Depends on**: Phase 1 (layout)

#### Task 5.1: Sign Up Page

**File**: `src/Resources/views/customers/sign-up.blade.php`

**Figma reference**: Node `142:2763` ("Sign Up")

**What to do**: Get Figma design context. Expected layout:
- 2-column: Left side has a large shopping image (from `images/auth/shopping-side.png`), right side has the form
- Right side: "Create an account" heading, "Enter your details below" subtitle
- Form fields: Name, Email or Phone Number, Password — all with bottom-border-only style (no full borders)
- "Create Account" red button (full width)
- "Already have account? Log in" link
- "Sign up with Google" button

Use Bagisto's existing registration form logic (`@csrf`, validation, `route('shop.customers.register.store')`) but restyle.

#### Task 5.2: Login Page

**File**: `src/Resources/views/customers/sign-in.blade.php`

**Figma reference**: Node `155:1711` ("Log In")

**What to do**: Similar layout to Sign Up:
- 2-column: Left side image, right side form
- "Log in to Exclusive" heading, "Enter your details below" subtitle
- Form fields: Email or Phone Number, Password — bottom-border style
- "Log In" red button + "Forgot Password?" link (same row)

---

### Phase 6: Account, Wishlist, and Static Pages

**Goal**: Implement remaining pages.

**Depends on**: Phase 1 (layout)

#### Task 6.1: Wishlist Page

**File**: `src/Resources/views/customers/account/wishlist/index.blade.php`

**Figma reference**: Node `164:4451` ("Wishlist")

**What to do**: Get Figma context. Expected: "Wishlist (4)" heading, grid of product cards, each with a trash/remove icon (top-right) and "Add To Cart" black button overlaying the bottom of the image.

#### Task 6.2: Account Page

**File**: `src/Resources/views/customers/account/index.blade.php`

**Figma reference**: Node `193:4067` ("Account")

**What to do**: Get Figma context. Expected: "My Account" sidebar nav (Manage My Account, My Profile, Address Book, My Payment Options; My Orders, My Returns, My Cancellations; My WishList) + right content area showing "Edit Your Profile" form with first/last name, email, address, current/new password fields + Save/Cancel buttons.

#### Task 6.3: Contact Page

**File**: `src/Resources/views/home/contact-us.blade.php`

**Figma reference**: Node `208:4308` ("Contact")

**What to do**: Get Figma context. Expected: 2-column layout:
- **Left**: Phone icon + "Call To Us" + phone number + "We are available..." text; Email icon + "Write To Us" + email addresses
- **Right**: Contact form — Your Name, Your Email, Your Phone inputs + large Message textarea + "Send Message" red button

#### Task 6.4: About Page (CMS)

**File**: `src/Resources/views/cms/page.blade.php`

**Figma reference**: Node `205:4904` ("About")

**What to do**: Get Figma context. This is a CMS page template. The About page in Figma has:
- "Our Story" heading + paragraph text
- Stats row: 4 boxes with icons (shop, dollar, bag, coins) + numbers (10.5k, 33k, 45.5k, 25k) + labels
- Team section: 3 portrait cards with name, role, social icons
- Service guarantees row (reuse services component)

Since this is CMS content, the template should render `{!! $page->html_content !!}` from Bagisto's CMS system. But add the Figma-specific styling around it.

#### Task 6.5: 404 Error Page

**File**: `src/Resources/views/errors/index.blade.php`

**Figma reference**: Node `175:3732` ("404 Error")

**What to do**: Get Figma context. Expected: Centered layout with large "404 Not Found" text, "Your visited page not found..." description, "Back to home page" red button.

---

### Phase 7: Mobile Responsive and Polish

**Goal**: Ensure responsive behavior and fix visual details.

**Depends on**: All previous phases

#### Task 7.1: Mobile Header

**File**: `src/Resources/views/components/layouts/header/mobile/index.blade.php` (NEW)

**Figma reference**: Nodes `135:1508` / `135:1514` ("phone_android")

**What to do**: Get mobile Figma screenshots. Implement a mobile header with hamburger menu, logo, cart icon. Sliding drawer for category navigation.

#### Task 7.2: Responsive Breakpoints

**What to do**: Go through each page and add Tailwind responsive classes:
- `lg:` prefix for desktop layouts (>= 1024px)
- `md:` prefix for tablet (>= 768px)
- Default (mobile-first) for small screens
- Key responsive changes: sidebar hides on mobile, product grids go from 4-col to 2-col to 1-col, hero banner stacks vertically, new arrivals grid simplifies

#### Task 7.3: Final Visual QA

**What to do**: Compare each page side-by-side with the Figma screenshots. Fix spacing, font sizes, colors, border radii, shadows, and any visual discrepancies. Test all interactive elements: countdown timers, add-to-cart, wishlist toggle, dropdown menus, mobile menu.

---

### Phase 8: Build, Publish, and Verify

**Goal**: Final build and deployment verification.

#### Task 8.1: Final Build and Publish

```bash
cd packages/Webkul/AmacommerceTheme
npm run build
cd /var/www/html/bagisto
php artisan vendor:publish --provider="Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider" --force
php artisan optimize:clear
```

#### Task 8.2: Smoke Test All Pages

Navigate to each page in the browser and verify:
1. Homepage — all 7 sections render
2. Product detail — gallery, info, related products
3. Cart — add/remove items, quantity changes
4. Checkout — form submission works
5. Login/Register — forms submit correctly
6. Wishlist — add/remove items
7. Account — profile displays
8. Contact — form renders
9. 404 — error page shows
10. Mobile — responsive on small viewport

## Complexity Tracking

No constitution violations — no entries needed.
