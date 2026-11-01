<?php
// display_errors OFF: this endpoint returns raw JSON, and any stray PHP
// warning/notice printed before it would corrupt the response body. See the
// matching note in add_to_cart.php for the full explanation.
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
session_start();
header('Content-Type: application/json');

// ---------------------------------------------------------------------
// ASSUMPTION: adjust this if your login system stores the customer's ID
// under a different session key (e.g. $_SESSION['customer_id']).
// ---------------------------------------------------------------------
$loggedInUserId = $_SESSION['user_id'] ?? null;

if (!$loggedInUserId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please log in to use your wishlist.']);
    exit;
}

$productId = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
if ($productId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing product_id.']);
    exit;
}

include 'db1.php';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// Check if this product is already wishlisted by this user
$stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("is", $loggedInUserId, $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Already wishlisted -> remove it
    $row = $result->fetch_assoc();
    $stmt->close();

    $del = $conn->prepare("DELETE FROM wishlist WHERE id = ?");
    $del->bind_param("i", $row['id']);
    $del->execute();
    $del->close();

    echo json_encode(['status' => 'removed', 'product_id' => $productId]);
} else {
    // Not wishlisted yet -> add it
    $stmt->close();

    $ins = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $ins->bind_param("is", $loggedInUserId, $productId);
    $ins->execute();
    $ins->close();

    echo json_encode(['status' => 'added', 'product_id' => $productId]);
}

$conn->close();