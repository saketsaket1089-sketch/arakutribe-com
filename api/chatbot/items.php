<?php
/**
 * api/chatbot/items.php
 * Mirrors chatbot_api.php's `get_items` case - 5 per page, offset-based
 * paging. Public, GET ?category=&offset=. Reuses lib/products.php's
 * product_image_url() for the image URL convention rather than porting
 * the original's file_exists()-based resolve_image_path().
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';
require_once __DIR__ . '/../lib/currency.php';

$category = trim((string) ($_GET['category'] ?? ''));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$currency = currency_code($_GET['currency'] ?? 'USD');
$limit = 5;

if ($category === '') {
    json_error('VALIDATION_ERROR', 'category is required.', 400);
}

$stmt = $conn->prepare(
    "SELECT product_id, product_name, price, quantity, minimum_quantity, image
     FROM products
     WHERE category = ?
     ORDER BY product_name ASC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param("sii", $category, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'product_id' => $row['product_id'],
        'product_name' => $row['product_name'],
        'price' => (float) $row['price'],
        'converted_price' => currency_convert($row['price'], $currency),
        'currency' => $currency,
        'currency_symbol' => currency_symbol($currency),
        'minimum_quantity' => (int) ($row['minimum_quantity'] ?: 100),
        'in_stock' => (int) $row['quantity'],
        'image' => $row['image'],
        'image_path' => product_image_url($category, $row['image']),
    ];
}
$stmt->close();

$stmt2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE category = ?");
$stmt2->bind_param("s", $category);
$stmt2->execute();
$total = (int) $stmt2->get_result()->fetch_assoc()['cnt'];
$stmt2->close();

json_ok([
    'category' => $category,
    'items' => $items,
    'has_more' => ($offset + $limit) < $total,
    'next_offset' => $offset + $limit,
    'total' => $total,
]);
