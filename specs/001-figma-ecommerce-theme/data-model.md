# Data Model: Figma E-Commerce Theme

**Date**: 2026-05-16

This theme is a presentation layer only — no new database entities are created. All data comes from Bagisto's existing models and APIs.

## Existing Entities Used

### Product (Webkul\Product\Models\Product)
- **Used in**: Product cards, product detail page, flash sales, best sellers, explore products
- **Key attributes accessed**: `name`, `url_key`, `base_image.medium_image_url`, `base_image.small_image_url`, `price`, `formatted_price`, `special_price`, `formatted_special_price`, `ratings.average`, `ratings.total`, `reviews.total`, `is_wishlisted`, `short_description`, `description`
- **API endpoint**: `GET /api/products` with filters (`new`, `featured`, `sort`, `limit`)

### Category (Webkul\Category\Models\Category)
- **Used in**: Sidebar navigation, Browse By Category section, category carousel
- **Key attributes accessed**: `name`, `slug`, `url_path`, `logo_url`, `children` (nested categories)
- **API endpoint**: `GET /api/categories` with filters
- **Also available as**: `$categories` JS variable set in home/index.blade.php via `localStorage`

### Cart (Webkul\Checkout\Models\Cart)
- **Used in**: Cart page, mini-cart in header, checkout
- **Key attributes accessed**: `items` (collection), `items_count`, `grand_total`, `formatted_grand_total`, `sub_total`
- **Cart Item attributes**: `name`, `quantity`, `price`, `formatted_price`, `base_image`, `product.url_key`
- **API endpoint**: `GET/POST/PUT/DELETE /api/checkout/cart`

### Customer (Webkul\Customer\Models\Customer)
- **Used in**: Account page, header account dropdown, wishlist, orders
- **Key attributes accessed**: `first_name`, `last_name`, `email`, `phone`, `image_url`
- **Auth guard**: `customer`

### Wishlist (Webkul\Customer\Models\Wishlist)
- **Used in**: Wishlist page, product card wishlist icon
- **Key attributes accessed**: `product` (relation), `product.name`, `product.base_image`
- **API endpoint**: `POST/DELETE /api/customers/account/wishlist`

### Channel (Webkul\Core\Models\Channel)
- **Used in**: Layout (logo, favicon, SEO meta, header offer)
- **Accessed via**: `core()->getCurrentChannel()`
- **Key attributes**: `logo_url`, `favicon_url`, `home_seo`, `name`

## Static Assets (Theme Package)

These are NOT database entities but static files bundled with the theme:

| Asset Category | File Location | Count |
|---------------|--------------|-------|
| Hero banner images | `src/Resources/assets/images/hero/` | 1-2 |
| Category icons (SVG) | `src/Resources/assets/images/categories/` | 6 |
| Promo banner images | `src/Resources/assets/images/promo/` | 1 |
| New arrival images | `src/Resources/assets/images/arrivals/` | 4 |
| Service icons (SVG) | `src/Resources/assets/images/services/` | 3 |
| Auth page side images | `src/Resources/assets/images/auth/` | 1 |
| About page images | `src/Resources/assets/images/about/` | 4 |
| Logo | `src/Resources/assets/images/logo.svg` | 1 |
| Favicon | `src/Resources/assets/images/favicon.ico` | 1 |
