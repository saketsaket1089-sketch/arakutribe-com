<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/currency.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$shippingAddress = trim((string) ($input['shipping_address'] ?? ''));
$billingAddress = trim((string) ($input['billing_address'] ?? ''));
if ($billingAddress === '') {
    $billingAddress = $shippingAddress;
}
$paymentMethod = trim((string) ($input['payment_method'] ?? 'cod'));
$currency = currency_code($input['currency'] ?? 'USD');

$validMethods = ['upi', 'card', 'amazonpay', 'netbanking', 'cod'];
if ($shippingAddress === '') {
    json_error('VALIDATION_ERROR', 'shipping_address is required.', 400);
}
if (!in_array($paymentMethod, $validMethods, true)) {
    json_error('VALIDATION_ERROR', 'Invalid payment_method.', 400);
}

// Never trust client-supplied items/totals - recompute from the server-side cart.
$stmt = $conn->prepare(
    "SELECT c.product_id, c.quantity, p.product_name, p.price
     FROM cart c JOIN products p ON c.product_id = p.product_id
     WHERE c.user_id = ?"
);
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$cartRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($cartRows)) {
    json_error('EMPTY_CART', 'Your cart is empty.', 400);
}

$totalUsd = 0.0;
foreach ($cartRows as $row) {
    $totalUsd += (float) $row['price'] * (int) $row['quantity'];
}
$totalUsd = round($totalUsd, 2);

$paymentStatus = $paymentMethod === 'cod' ? 'Pending' : 'Paid';
$orderStatus = 'Placed';

$conn->begin_transaction();
try {
    $insertOrder = $conn->prepare(
        "INSERT INTO orders (user_id, total_amount, shipping_address, billing_address, order_status, order_date, payment_method, payment_status, currency)
         VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)"
    );
    $insertOrder->bind_param(
        "idssssss",
        $user['user_id'],
        $totalUsd,
        $shippingAddress,
        $billingAddress,
        $orderStatus,
        $paymentMethod,
        $paymentStatus,
        $currency
    );
    $insertOrder->execute();
    $orderId = $conn->insert_id;
    $insertOrder->close();

    $insertItem = $conn->prepare(
        "INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($cartRows as $row) {
        $pid = $row['product_id'];
        $pname = $row['product_name'];
        $qty = (int) $row['quantity'];
        $price = (float) $row['price'];
        $insertItem->bind_param("issid", $orderId, $pid, $pname, $qty, $price);
        $insertItem->execute();
    }
    $insertItem->close();

    $clearCart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $clearCart->bind_param("i", $user['user_id']);
    $clearCart->execute();
    $clearCart->close();

    $conn->commit();
} catch (\Throwable $e) {
    $conn->rollback();
    error_log('orders/place failed: ' . $e->getMessage());
    json_error('SERVER_ERROR', 'Could not place your order. Please try again.', 500);
}

// Best-effort order-confirmation email - never blocks the response.
if (!empty($user['email'])) {
    $symbol = currency_symbol($currency);
    $convertedTotal = currency_convert($totalUsd, $currency);
    $itemsHtml = '';
    foreach ($cartRows as $row) {
        $lineTotal = currency_convert((float) $row['price'] * (int) $row['quantity'], $currency);
        $itemsHtml .= '<tr><td>' . htmlspecialchars($row['product_name']) . '</td><td>' . (int) $row['quantity'] . '</td><td>' . htmlspecialchars($symbol) . number_format($lineTotal, 2) . '</td></tr>';
    }
    $html = "<h2>Thank you, " . htmlspecialchars($user['firstname'] ?? '') . "!</h2>"
        . "<p>Your order #{$orderId} has been placed successfully and is being processed.</p>"
        . "<table border='1' cellpadding='6' cellspacing='0'><tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr>{$itemsHtml}</table>"
        . "<p><strong>Total: " . htmlspecialchars($symbol) . number_format($convertedTotal, 2) . "</strong></p>"
        . "<p>Payment method: " . htmlspecialchars($paymentMethod) . " (" . htmlspecialchars($paymentStatus) . ")</p>"
        . "<p>Shipping to: " . htmlspecialchars($shippingAddress) . "</p>";

    send_mail($user['email'], "Araku Tribe - Order Confirmation #{$orderId}", $html);
}

json_ok([
    'order_id' => (int) $orderId,
    'total_amount' => $totalUsd,
    'converted_total' => currency_convert($totalUsd, $currency),
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
    'order_status' => $orderStatus,
    'payment_status' => $paymentStatus,
]);
