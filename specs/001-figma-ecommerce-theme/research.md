# Research: Figma E-Commerce Theme for Bagisto

**Date**: 2026-05-16

## R1: Bagisto Custom Theme Package Architecture

**Decision**: Build the theme as a Bagisto package at `packages/Webkul/AmacommerceTheme/` using the existing package structure. Override default Shop views by placing Blade files in `resources/themes/amacommerce/views/` (the `views_path` configured in `config/themes.php`).

**Rationale**: Bagisto's theme system works by prepending the active theme's `views_path` to Laravel's view finder paths. When a view like `shop::home.index` is requested, Bagisto looks in the theme's `views_path` first (`resources/themes/amacommerce/views/home/index.blade.php`), then falls back to the Shop package's views. The service provider's `publishes()` method copies package views to this location.

**How it works**:
1. `config/themes.php` registers `amacommerce` theme with `views_path => 'resources/themes/amacommerce/views'`
2. `Themes::set()` calls `Config::set('view.paths', $paths)` prepending theme views
3. The Shop package registers views under `shop` namespace: `$this->loadViewsFrom(__DIR__.'/../Resources/views', 'shop')`
4. When Blade resolves `shop::components.layouts.index`, it checks the theme's view path first
5. Any view NOT overridden falls through to the default Shop package views

**Alternatives considered**: Creating views directly in `resources/themes/amacommerce/views/` without a package — rejected because the package approach (with service provider, Vite config, Tailwind config) gives a portable, distributable theme.

## R2: View Override Strategy

**Decision**: Override only the views that differ from the default theme. Use `@include('shop::...')` syntax for shared components. The following views MUST be overridden to match the Figma design:

**Layout views** (in package at `src/Resources/views/`, published to `resources/themes/amacommerce/views/`):
- `components/layouts/index.blade.php` — Main layout wrapper (already exists)
- `components/layouts/header.blade.php` — Custom header (already exists)
- `components/layouts/footer.blade.php` — Custom footer (already exists)
- `components/layouts/header/desktop/top.blade.php` — Top announcement bar
- `components/layouts/header/desktop/bottom.blade.php` — Main nav with logo, search, icons
- `components/layouts/header/mobile/index.blade.php` — Mobile header
- `components/layouts/services.blade.php` — Service guarantees section

**Page views**:
- `home/index.blade.php` — Homepage with all sections (already exists, needs full rework)
- `home/contact-us.blade.php` — Contact page
- `products/view.blade.php` — Product detail page
- `checkout/cart/index.blade.php` — Cart page
- `checkout/onepage/index.blade.php` — Checkout page
- `customers/sign-in.blade.php` — Login page
- `customers/sign-up.blade.php` — Sign up page
- `customers/account/index.blade.php` — Account dashboard
- `customers/account/wishlist/index.blade.php` — Wishlist page
- `errors/index.blade.php` — 404 error page
- `cms/page.blade.php` — CMS pages (About)

**Component views**:
- `components/products/card.blade.php` — Product card component
- `components/categories/carousel.blade.php` — Category carousel
- `components/products/carousel.blade.php` — Product carousel

**Rationale**: Overriding only what changes means the theme inherits all default functionality (forms, modals, dropdowns, shimmer effects) while customizing the visual layer.

## R3: Asset Management Strategy

**Decision**: Use Figma MCP `get_design_context` tool to extract images per section. Store images in `packages/Webkul/AmacommerceTheme/src/Resources/assets/images/`. Reference via `bagisto_asset('images/...')` which resolves through the Vite manifest.

**Rationale**: The `bagisto_asset()` helper calls `Vite::asset()` under the hood, looking up the file in the theme's Vite build manifest. All image assets must be listed in `vite.config.js` input array OR imported from JS/CSS to appear in the manifest.

**Image categories to extract**:
- Hero banner (iPhone promotional image)
- Category icons (Phone, Computer, SmartWatch, Camera, HeadPhones, Gaming)
- Promo banner (JBL speaker image)
- New arrival images (PS5, Women's collection model, Amazon Echo speaker, Gucci perfume)
- Service icons (delivery truck, headset, shield)
- Auth page side images (shopping cart/phone image)
- About page images (team photos, stat icons)

**Alternative approach for product images**: Product-specific images (in product cards) come from Bagisto's product catalog, not static assets. The theme only provides the card layout; actual product images are served dynamically via `product.base_image.medium_image_url`.

## R4: Vite and Tailwind Configuration

**Decision**: Keep the existing Vite + Tailwind setup. Extend `tailwind.config.js` with the full design system from the Figma file.

**Current state** (already configured):
- `vite.config.js`: Compiles CSS/JS, outputs to `public/themes/shop/amacommerce/build/`
- `tailwind.config.js`: Has `primary: '#DB4444'`, `secondary: '#00FF66'`, basic colors
- `config/bagisto-vite.php`: Has `amacommerce` entry (we added this)
- `config/themes.php`: Has `amacommerce` theme registered as default shop theme

**Needs extension**:
- Add Poppins font (Figma design uses Poppins, not Inter)
- Add additional color tokens: `button1: '#00FF66'`, `button2: '#DB4444'`, `hoverButton: '#E07575'`, `bg-secondary: '#F5F5F5'`, `text-secondary: '#7D8184'`
- Add spacing/sizing utilities matching Figma's 1170px content width

## R5: Vue.js Component Pattern in Bagisto

**Decision**: Follow Bagisto's existing pattern of inline Vue components using `<script type="text/x-template">` blocks with `@pushOnce('scripts')`.

**Rationale**: Bagisto's Shop theme uses Vue 3 with inline templates rather than SFC (Single File Components). Components are defined in Blade files using `<script type="text/x-template" id="v-component-template">` and registered with `app.component('v-component', {...})`. This pattern is used for:
- Flash sale countdown timers
- Product card interactions (add to cart, wishlist toggle)
- Category dropdowns
- Search functionality
- Cart updates

**Key pattern**:
```blade
<v-component-name></v-component-name>

@pushOnce('scripts')
    <script type="text/x-template" id="v-component-name-template">
        <!-- HTML template -->
    </script>

    <script type="module">
        app.component('v-component-name', {
            template: '#v-component-name-template',
            data() { return {...} },
            methods: {...}
        });
    </script>
@endPushOnce
```

## R6: Bagisto Data Integration Points

**Decision**: Use Bagisto's existing APIs and helpers for all dynamic data. No backend modifications needed.

**Key data sources**:
- **Products**: `route('shop.api.products.index', $filters)` — JSON API for product listings
- **Categories**: `route('shop.api.categories.index', $filters)` — JSON API for categories; also `$categories` variable passed to home view
- **Cart**: `route('shop.api.checkout.cart.index')` — Cart API; mini-cart component exists
- **Customer**: `auth()->guard('customer')` — Customer authentication state
- **Wishlist**: `route('shop.api.customers.account.wishlist.index')` — Wishlist API
- **Channel config**: `core()->getCurrentChannel()` — Logo, favicon, SEO meta, header offer text
- **Theme customizations**: `$customizations` variable on home page — Dynamic homepage sections from admin

**Important**: The home page in the default theme uses `$customizations` (admin-configured sections). Our theme will hardcode the Figma layout sections but still pull dynamic product data from Bagisto's APIs using Vue components that fetch from `route('shop.api.products.index')`.
