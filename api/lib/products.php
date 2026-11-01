<?php
/**
 * api/lib/products.php
 * Shared shaping helpers for the product/cart/wishlist JSON shapes defined
 * in the API contract. Depends on api/lib/currency.php being loaded first.
 */

/** Public image URL per the contract's image convention, or null if the column is empty. */
function product_image_url($category, $image) {
    if (empty($image)) return null;
    return 'http://localhost/arakutribe.com/img/shop/' . rawurlencode((string) $category) . '/' . $image;
}

/** Shapes one `products` row into the contract's per-item product object. */
function format_product(array $row, $currency, $isWishlisted = false) {
    return [
        'product_id' => $row['product_id'],
        'product_name' => $row['product_name'],
        'price' => (float) $row['price'],
        'converted_price' => currency_convert($row['price'], $currency),
        'currency' => currency_code($currency),
        'currency_symbol' => currency_symbol($currency),
        'quantity' => (int) $row['quantity'],
        'minimum_quantity' => (int) ($row['minimum_quantity'] ?? 100),
        'category' => $row['category'],
        'image_url' => product_image_url($row['category'], $row['image'] ?? null),
        'is_wishlisted' => (bool) $isWishlisted,
    ];
}
