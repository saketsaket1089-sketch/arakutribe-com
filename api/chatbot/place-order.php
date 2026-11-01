<?php
/**
 * api/chatbot/place-order.php
 * Mirrors chatbot_api.php's `place_order` case: uses
 * chatbot_sessions.agent_cart (never the regular `cart` table), recomputes
 * totals server-side, inserts orders+order_items, clears the session's
 * cart, and sends the confirmation email with the ORIGINAL copy. Auth
 * optional - guests get payment_method='pending' exactly like the
 * original; logged-in users get a real method (default 'cod').
 *
 * order_items.product_id is VARCHAR(20) in this DB (already fixed vs. the
 * original's `(int)$oi['product_id']` truncation bug), so the real
 * product id string is stored, not cast to int.
 *
 * POST {session_id, shipping_address, billing_address?, payment_method?, email?, currency?}.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/chatbot.php';

$user = current_user($conn); // auth optional
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$sessionId = trim((string) ($input['session_id'] ?? ''));
$shippingAddress = trim((string) ($input['shipping_address'] ?? ''));
$billingAddress = trim((string) ($input['billing_address'] ?? ''));
if ($billingAddress === '') {
    $billingAddress = $shippingAddress;
}
$guestEmail = trim((string) ($input['email'] ?? ''));
$currency = currency_code($input['currency'] ?? 'USD');

// Guests never choose a payment method - the order goes in as "pending"
// and the confirmation email explains someone will follow up to arrange
// payment. Logged-in users pick a real method (default 'cod').
$paymentMethod = $user ? trim((string) ($input['payment_method'] ?? 'cod')) : 'pending';

if ($sessionId === '') {
    json_error('VALIDATION_ERROR', 'session_id is required.', 400);
}
if ($shippingAddress === '') {
    json_error('VALIDATION_ERROR', 'Shipping address is required.', 400);
}
if (!$user) {
    if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        json_error('VALIDATION_ERROR', 'A valid email is required.', 400);
    }
}

$cart = chatbot_get_cart($conn, $sessionId);
if (empty($cart)) {
    json_error('EMPTY_CART', 'Your cart is empty.', 400);
}

$orderItems = [];
$total = 0.0;
foreach ($cart as $pid => $item) {
    $price = (float) ($item['price'] ?? 0);
    $qty = (int) ($item['quantity'] ?? 0);
    $orderItems[] = [
        'product_id' => (string) $pid,
        'product_name' => $item['product_name'] ?? 'Item',
        'price' => $price,
        'quantity' => $qty,
    ];
    $total += $price * $qty;
}
$total = round($total, 2);

// Work out who the confirmation email goes to.
$recipientEmail = $guestEmail;
$recipientName = 'Customer';
if ($user) {
    $recipientEmail = $user['email'] ?? null;
    $recipientName = $user['firstname'] ?? 'Customer';
}
if (!$recipientEmail) {
    json_error('VALIDATION_ERROR', 'Could not determine an email to send the confirmation to.', 400);
}

$orderUserId = $user ? (int) $user['user_id'] : null;
$orderGuestEmail = $user ? null : $guestEmail;

$conn->begin_transaction();
try {
    // Records the currency the customer was actually viewing at checkout
    // (e.g. 'INR', 'USD') - purely a record of what was shown to them.
    // total_amount itself always stays in USD, same as the original.
    $stmt = $conn->prepare(
        "INSERT INTO orders (user_id, guest_email, total_amount, shipping_address, billing_address, order_status, order_date, payment_method, payment_status, currency)
         VALUES (?, ?, ?, ?, ?, 'Processing', NOW(), ?, 'Pending', ?)"
    );
    $stmt->bind_param("isdssss", $orderUserId, $orderGuestEmail, $total, $shippingAddress, $billingAddress, $paymentMethod, $currency);
    $stmt->execute();
    $orderId = $conn->insert_id;
    $stmt->close();

    $itemStmt = $conn->prepare(
        "INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($orderItems as $oi) {
        $itemStmt->bind_param("issid", $orderId, $oi['product_id'], $oi['product_name'], $oi['quantity'], $oi['price']);
        $itemStmt->execute();
    }
    $itemStmt->close();

    $conn->commit();
} catch (\Throwable $e) {
    $conn->rollback();
    error_log('chatbot/place-order failed: ' . $e->getMessage());
    json_error('SERVER_ERROR', 'Could not place your order. Please try again.', 500);
}

// Clear the agent's own cart - never touches the site's regular cart table.
chatbot_clear_cart($conn, $sessionId);

// ---- Confirmation email - copy is verbatim from chatbot_api.php's place_order case ----
global $CURRENCY_INFO;
$currencyInfo = $CURRENCY_INFO[$currency];
$itemsHtml = '';
foreach ($orderItems as $oi) {
    $lineTotal = $oi['price'] * $oi['quantity'] * $currencyInfo['rate'];
    $itemsHtml .= "<tr>
        <td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($oi['product_name']) . "</td>
        <td style='padding:8px;border-bottom:1px solid #eee;text-align:center;'>" . $oi['quantity'] . "</td>
        <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>" . htmlspecialchars($currencyInfo['symbol']) . number_format($lineTotal, 2) . "</td>
    </tr>";
}
if ($user) {
    $paymentLabel = ($paymentMethod === 'cod') ? 'Cash on Delivery' : ucfirst($paymentMethod);
    $paymentNote = "We'll notify you once your order ships.";
} else {
    $paymentLabel = 'To be arranged';
    $paymentNote = "Please complete your payment so we can process and ship your order — our team will reach out to you shortly to arrange payment.";
}

$emailHtml = "
    <h2>Thanks for your order, " . htmlspecialchars($recipientName) . "!</h2>
    <p>Your order <strong>#{$orderId}</strong> has been placed successfully.</p>
    <table style='width:100%;border-collapse:collapse;'>
        <thead><tr style='background:#6b3e26;color:#fff;'>
            <th style='padding:8px;text-align:left;'>Product</th>
            <th style='padding:8px;text-align:center;'>Qty</th>
            <th style='padding:8px;text-align:right;'>Price</th>
        </tr></thead>
        <tbody>{$itemsHtml}</tbody>
    </table>
    <p style='font-size:16px;margin-top:10px;'><strong>Order Total: " . htmlspecialchars($currencyInfo['symbol']) . number_format($total * $currencyInfo['rate'], 2) . "</strong></p>
    <hr>
    <p>
        <strong>Payment Method:</strong> {$paymentLabel}<br>
        <strong>Payment Status:</strong> Pending<br>
        <strong>Order Status:</strong> Processing
    </p>
    <p><strong>Shipping to:</strong><br>" . htmlspecialchars($shippingAddress) . "</p>
    <p><strong>Billing address:</strong><br>" . htmlspecialchars($billingAddress) . "</p>
    <p>{$paymentNote}</p>
";

$emailSent = send_mail($recipientEmail, "Order #{$orderId} Confirmed - Araku Tribe", $emailHtml);

json_ok([
    'order_id' => (int) $orderId,
    'total' => $total,
    'converted_total' => currency_convert($total, $currency),
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
    'email_sent' => $emailSent,
    'email' => $recipientEmail,
]);
