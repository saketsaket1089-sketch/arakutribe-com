<?php
session_start();
header('Content-Type: application/json');
include 'db1.php';

$itemCount = 0;
$cartItems = [];

// Mirrors the same logic header.php already uses, so the count/items shown
// here always match what header.php would render on a full page load.
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $conn = new mysqli($host, $user, $pass, $db);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("
            SELECT c.product_id, c.quantity, p.product_name, p.price, p.image
            FROM cart c
            JOIN products p ON c.product_id = p.product_id
            WHERE c.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $_SESSION['cart'] = [];
        while ($row = $result->fetch_assoc()) {
            $_SESSION['cart'][$row['product_id']] = [
                'product_name' => $row['product_name'],
                'price' => (float)$row['price'],
                'image' => $row['image'],
                'quantity' => (int)$row['quantity'],
            ];
        }
        $stmt->close();
        $conn->close();
    }
}

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartItems = $_SESSION['cart'];
    $itemCount = count($cartItems);
}

require_once 'currency_helper.php';
$currency_conn = new mysqli($host, $user, $pass, $db);
$displayCurrency = resolve_display_currency($currency_conn);
$currencyInfo    = $CURRENCY_INFO[$displayCurrency];
if (!$currency_conn->connect_error) {
    $currency_conn->close();
}

// Derive the category folder from the image filename prefix — the same
// logic cart.php already uses, since $_SESSION['cart'] items don't store
// category directly, only the raw image filename.
function categoryFromImageFilename($imageId) {
    if (strpos($imageId, 'ATCUP') === 0) return 'cup';
    if (strpos($imageId, 'ATMUG') === 0) return 'mug';
    if (strpos($imageId, 'ATBAG') === 0) return 'bag';
    if (strpos($imageId, 'ATTSH') === 0) return 'tshirt';
    return 'default';
}

$itemsOut = [];
foreach ($cartItems as $productId => $item) {
    $price = (float)($item['price'] ?? 0);
    $qty   = (int)($item['quantity'] ?? 0);
    $imageFile = $item['image'] ?? '';
    $category  = categoryFromImageFilename($imageFile);
    $imagePath = "img/shop/{$category}/{$imageFile}";
    if (!file_exists($imagePath)) {
        $imagePath = file_exists("img/shop/{$category}/default.png")
            ? "img/shop/{$category}/default.png"
            : "img/default.png";
    }

    $itemsOut[] = [
        'id'       => $productId,
        'name'     => $item['product_name'] ?? 'Item',
        'image'    => $imagePath,
        'price'    => number_format($price * $currencyInfo['rate'], 2),
        'quantity' => $qty,
        'subtotal' => number_format($price * $qty * $currencyInfo['rate'], 2),
    ];
}

echo json_encode([
    'itemCount'      => $itemCount,
    'items'          => $itemsOut,
    'currencySymbol' => $currencyInfo['symbol'],
]);