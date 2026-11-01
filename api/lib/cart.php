<?php
/**
 * api/lib/cart.php
 * Builds the cart summary shape shared by cart/list.php, cart/add.php and
 * cart/update.php per the API contract. Always recomputed fresh from the
 * `cart` JOIN `products` tables - never trusts any cached total.
 */

function get_cart_summary($conn, $userId, $currency) {
    $stmt = $conn->prepare(
        "SELECT c.product_id, c.quantity, p.product_name, p.price, p.image, p.category
         FROM cart c JOIN products p ON c.product_id = p.product_id
         WHERE c.user_id = ?
         ORDER BY c.added_at ASC, c.id ASC"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    $totalUsd = 0.0;
    while ($row = $res->fetch_assoc()) {
        $price = (float) $row['price'];
        $qty = (int) $row['quantity'];
        $lineTotalUsd = $price * $qty;
        $totalUsd += $lineTotalUsd;

        $items[] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'price' => $price,
            'converted_price' => currency_convert($price, $currency),
            'quantity' => $qty,
            'line_total' => currency_convert($lineTotalUsd, $currency),
            'image_url' => product_image_url($row['category'], $row['image']),
        ];
    }
    $stmt->close();

    return [
        'items' => $items,
        'total_usd' => round($totalUsd, 2),
        'converted_total' => currency_convert($totalUsd, $currency),
        'currency' => currency_code($currency),
        'currency_symbol' => currency_symbol($currency),
    ];
}
