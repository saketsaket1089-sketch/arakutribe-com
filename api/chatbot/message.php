<?php
/**
 * api/chatbot/message.php
 * SIMPLIFIED v1 assistant - deliberately NOT a full port of the 51KB
 * dynamic chatbot_api.php. This is rules-based only: keyword matching
 * against product names/categories, a handful of canned FAQ answers, and
 * an order-status lookup by order number (scoped to the authenticated
 * user, or to a guest_email mentioned in the message). No persistent chat
 * history. See api_contract.md's "Chatbot" section - this limitation is
 * intentional for v1, not an oversight.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';

$user = current_user($conn); // auth optional
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim((string) ($input['message'] ?? ''));

if ($message === '') {
    json_error('VALIDATION_ERROR', 'message is required.', 400);
}

$lower = strtolower($message);
$reply = null;
$products = [];

// ---- Order-status lookup: "order #123" / "order 123" ----
if (preg_match('/order\s*#?\s*(\d+)/i', $message, $m)) {
    $orderId = (int) $m[1];
    $order = null;

    if ($user) {
        $stmt = $conn->prepare("SELECT order_id, order_status, payment_status, total_amount FROM orders WHERE order_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $orderId, $user['user_id']);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } elseif (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $message, $em)) {
        $guestEmail = $em[0];
        $stmt = $conn->prepare("SELECT order_id, order_status, payment_status, total_amount FROM orders WHERE order_id = ? AND guest_email = ?");
        $stmt->bind_param("is", $orderId, $guestEmail);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($order) {
        $reply = "Order #{$order['order_id']} is currently '{$order['order_status']}' (payment: {$order['payment_status']}), total \${$order['total_amount']}.";
    } elseif ($user) {
        $reply = "I couldn't find order #{$orderId} on your account. Please double check the order number.";
    } else {
        $reply = "To check an order's status, please log in, or include the email address you used, e.g. \"order {$orderId} for you@example.com\".";
    }
}

// ---- Canned FAQ answers ----
if ($reply === null) {
    if (str_contains($lower, 'shipping') || str_contains($lower, 'delivery')) {
        $reply = "We ship across India and internationally. Standard delivery typically takes 5-7 business days domestically. You'll get tracking details by email once your order ships.";
    } elseif (str_contains($lower, 'return') || str_contains($lower, 'refund') || str_contains($lower, 'cancel')) {
        $reply = "You can cancel an order any time before it's out for delivery from the Orders page. Refunds for already-paid orders are initiated within 5-7 business days of cancellation.";
    } elseif (str_contains($lower, 'contact') || str_contains($lower, 'support') || str_contains($lower, 'help')) {
        $reply = "You can reach our support team via the Contact page on arakutribe.com, or reply to any of our order confirmation emails.";
    }
}

// ---- Product keyword matching ----
$categories = ['bag' => 'bags', 'cup' => 'cups', 'mug' => 'mugs', 'tshirt' => 't-shirts'];
$matchedCategory = null;
foreach ($categories as $key => $label) {
    if (str_contains($lower, $key) || str_contains($lower, $label) || str_contains($lower, str_replace('_', ' ', $key))) {
        $matchedCategory = $key;
        break;
    }
}

if ($matchedCategory) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE category = ? LIMIT 5");
    $stmt->bind_param("s", $matchedCategory);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $products[] = format_product($row, 'USD', false);
    }
    $stmt->close();
} else {
    // Fall back to a loose product-name match on any word of >=3 chars.
    $words = preg_split('/\s+/', $lower);
    foreach ($words as $word) {
        $word = trim($word, ".,!?");
        if (strlen($word) < 3) continue;
        $likeTerm = '%' . $word . '%';
        $stmt = $conn->prepare("SELECT * FROM products WHERE product_name LIKE ? LIMIT 5");
        $stmt->bind_param("s", $likeTerm);
        $stmt->execute();
        $res = $stmt->get_result();
        $found = false;
        while ($row = $res->fetch_assoc()) {
            $products[] = format_product($row, 'USD', false);
            $found = true;
        }
        $stmt->close();
        if ($found) break;
    }
}

if ($reply === null) {
    if (!empty($products)) {
        $reply = 'Here are a few products you might like:';
    } else {
        $reply = "I'm a simple assistant for now - I can help you find products (try mentioning 'bag', 'cup', 'mug', or 't-shirt'), answer shipping/returns questions, or look up an order status (e.g. 'order 123'). For anything else, please contact support.";
    }
}

json_ok(['reply' => $reply, 'products' => $products]);
