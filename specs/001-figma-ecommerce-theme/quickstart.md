# Quickstart: Figma E-Commerce Theme Implementation

**Date**: 2026-05-16  
**Branch**: `001-figma-ecommerce-theme`

## Prerequisites

- Bagisto 2.x installed and running at `/var/www/html/bagisto`
- Node.js and npm installed
- The `AmacommerceTheme` package exists at `packages/Webkul/AmacommerceTheme/`
- Theme is registered in `config/themes.php` as `amacommerce` (already done)
- Theme is registered in `config/bagisto-vite.php` as `amacommerce` (already done)
- Service provider registered in `bootstrap/app.php` (already done)

## How to Run

```bash
# Install theme dependencies
cd packages/Webkul/AmacommerceTheme && npm install

# Build theme assets
npm run build

# Publish views to the theme views path
cd /var/www/html/bagisto
php artisan vendor:publish --provider="Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider" --force

# Clear caches
php artisan optimize:clear

# Serve the app
php artisan serve
```

## How to Develop (Hot Reload)

```bash
cd packages/Webkul/AmacommerceTheme
npm run dev
```

This starts Vite dev server with HMR. The hot file (`amacommerce-theme-vite.hot`) tells Bagisto to load assets from the dev server.

## Key Files to Edit

All source views live in `packages/Webkul/AmacommerceTheme/src/Resources/views/`. After editing, run `php artisan vendor:publish --provider="Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider" --force` to copy to the theme views path, OR edit directly in `resources/themes/amacommerce/views/` during development.

## Architecture Overview

```
packages/Webkul/AmacommerceTheme/
├── package.json                          # npm dependencies (tailwindcss, vite, etc.)
├── vite.config.js                        # Vite build config
├── tailwind.config.js                    # Tailwind design tokens
├── postcss.config.js                     # PostCSS with Tailwind
└── src/
    ├── Providers/
    │   └── AmacommerceThemeServiceProvider.php  # Registers & publishes views
    └── Resources/
        ├── assets/
        │   ├── css/app.css               # Tailwind imports + custom styles
        │   ├── js/app.js                 # JS entry point
        │   └── images/                   # All static theme images
        │       ├── hero/                 # Hero banner images
        │       ├── categories/           # Category icon SVGs
        │       ├── promo/                # Promotional banner images
        │       ├── arrivals/             # New arrival feature images
        │       ├── services/             # Service guarantee icons
        │       ├── auth/                 # Login/signup side images
        │       ├── about/                # About page images
        │       ├── logo.svg              # Site logo
        │       └── favicon.ico           # Favicon
        └── views/                        # Blade templates (override shop:: namespace)
            ├── components/
            │   ├── layouts/
            │   │   ├── index.blade.php           # Main HTML layout
            │   │   ├── header.blade.php          # Header include dispatcher
            │   │   ├── footer.blade.php          # Footer
            │   │   ├── services.blade.php        # Service guarantees row
            │   │   └── header/
            │   │       ├── desktop/
            │   │       │   ├── top.blade.php     # Top announcement bar
            │   │       │   └── bottom.blade.php  # Main nav (logo, search, icons)
            │   │       └── mobile/
            │   │           └── index.blade.php   # Mobile header
            │   └── products/
            │       └── card.blade.php            # Product card component
            ├── home/
            │   ├── index.blade.php               # Homepage (all sections)
            │   └── contact-us.blade.php          # Contact page
            ├── products/
            │   └── view.blade.php                # Product detail page
            ├── checkout/
            │   ├── cart/
            │   │   └── index.blade.php           # Cart page
            │   └── onepage/
            │       └── index.blade.php           # Checkout page
            ├── customers/
            │   ├── sign-in.blade.php             # Login page
            │   ├── sign-up.blade.php             # Sign up page
            │   └── account/
            │       ├── index.blade.php           # Account dashboard
            │       └── wishlist/
            │           └── index.blade.php       # Wishlist page
            ├── errors/
            │   └── index.blade.php               # 404 error page
            └── cms/
                └── page.blade.php                # CMS/About page
```

## Design System Tokens

| Token | Value | Usage |
|-------|-------|-------|
| Primary (red) | `#DB4444` | Buttons, sale badges, active states, section labels |
| Secondary (green) | `#00FF66` | Success states, "Buy Now" button |
| Background | `#FFFFFF` | Page background |
| Background secondary | `#F5F5F5` | Product card image backgrounds |
| Text primary | `#000000` | Headings, body text |
| Text secondary | `#7D8184` | Descriptions, muted text |
| Rating stars | `#FFAD33` | Star rating color |
| Border | `#0000001A` | Subtle borders, dividers |
| Font | Poppins | All text (400, 500, 600, 700 weights) |
| Content width | 1170px | Max content area within 1440px viewport |

## Bagisto Integration Points

- `bagisto_asset('images/...')` — Resolves static images through Vite manifest
- `@bagistoVite([...], 'amacommerce')` — Loads CSS/JS from theme's Vite build
- `route('shop.api.products.index', [...])` — Fetch products for carousels
- `route('shop.api.categories.index', [...])` — Fetch categories
- `core()->getCurrentChannel()` — Channel logo, favicon, SEO, offer text
- `auth()->guard('customer')` — Check if customer is logged in
- `view_render_event('bagisto.shop...')` — Emit Bagisto extension hooks
- `$categories` / `$customizations` — Variables passed to home view by controller
