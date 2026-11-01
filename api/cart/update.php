<?php
/**
 * api/cart/update.php
 * action in {increase, decrease, delete, set}. increase/decrease default
 * to a step of products.minimum_quantity (fallback 100), unless the caller
 * passes an explicit `quantity` - in which case that's treated as the new
 * absolute quantity. This explicit-quantity override is a deliberate
 * mobile-UX improvement over the website's fixed-step-only +/- buttons
 * (see api_contract.md's cart/update.php section) - noted, not a deviation.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';
require_once __DIR__ . '/../lib/cart.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$productId = trim((string) ($input['product_id'] ?? ''));
$action = trim((string) ($input['action'] ?? ''));
$explicitQuantity = isset($input['quantity']) ? (int) $input['quantity'] : null;
$currency = currency_code($input['currency'] ?? ($_GET['currency'] ?? 'USD'));

if ($productId === '' || !in_array($action, ['increase', 'decrease', 'delete', 'set'], true)) {
    json_error('VALIDATION_ERROR', 'product_id and a valid action (increase|decrease|delete|set) are required.', 400);
}

$stmt = $conn->prepare(
    "SELECT c.quantity, p.minimum_quantity FROM cart c JOIN products p ON c.product_id = p.product_id
     WHERE c.user_id = ? AND c.product_id = ?"
);
$stmt->bind_param("is", $user['user_id'], $productId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    json_error('NOT_FOUND', 'This product is not in your cart.', 404);
}

$currentQty = (int) $row['quantity'];
$step = (int) ($row['minimum_quantity'] ?: 100);

function delete_cart_row($conn, $userId, $productId) {
    $del = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $del->bind_param("is", $userId, $productId);
    $del->execute();
    $del->close();
}

function set_cart_quantity($conn, $userId, $productId, $qty) {
    $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    $upd->bind_param("iis", $qty, $userId, $productId);
    $upd->execute();
    $upd->close();
}

if ($action === 'delete') {
    delete_cart_row($conn, $user['user_id'], $productId);
} elseif ($action === 'set') {
    if ($explicitQuantity === null || $explicitQuantity <= 0) {
        json_error('VALIDATION_ERROR', 'A positive quantity is required for action=set.', 400);
    }
    set_cart_quantity($conn, $user['user_id'], $productId, $explicitQuantity);
} elseif ($action === 'increase') {
    $newQty = $explicitQuantity !== null ? $explicitQuantity : ($currentQty + $step);
    if ($newQty <= 0) {
        json_error('VALIDATION_ERROR', 'Quantity must be positive.', 400);
    }
    set_cart_quantity($conn, $user['user_id'], $productId, $newQty);
} elseif ($action === 'decrease') {
    if ($explicitQuantity !== null) {
        if ($explicitQuantity <= 0) {
            delete_cart_row($conn, $user['user_id'], $productId);
        } else {
            set_cart_quantity($conn, $user['user_id'], $productId, $explicitQuantity);
        }
    } else {
        $newQty = $currentQty - $step;
        if ($newQty < $step) {
            // Would go below one step - delete the row instead, per contract.
            delete_cart_row($conn, $user['user_id'], $productId);
        } else {
            set_cart_quantity($conn, $user['user_id'], $productId, $newQty);
        }
    }
}

json_ok(get_cart_summary($conn, $user['user_id'], $currency));
