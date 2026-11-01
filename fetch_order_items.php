<?php
session_start();
include 'db1.php'; 

// Set the content type header for correct response handling
header('Content-Type: text/html; charset=utf-8');

// Check 1: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); 
    echo '<p style="color:red;">Error 401: Authentication failed. Please log in.</p>';
    exit;
}

// Check 2: Ensure order_id is provided via AJAX
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400); 
    echo '<p style="color:red;">Error 400: Missing or invalid Order ID in request.</p>';
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = (int)$_GET['order_id'];

// New database connection needed as the main page closed its connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500); 
    echo '<p style="color:red;">Error 500: Database connection error in fetch_order_items.php.</p>';
    exit;
}

// --- Security Check: Verify the order belongs to the logged-in user ---
$stmt_check = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
if (!$stmt_check) {
    http_response_code(500);
    echo '<p style="color:red;">Error 500: SQL statement preparation failed.</p>';
    $conn->close();
    exit;
}

$stmt_check->bind_param("ii", $order_id, $user_id);
$stmt_check->execute();
$check_result = $stmt_check->get_result();

if ($check_result->num_rows === 0) {
    // This is the error you might be seeing if the user_id doesn't match the order's user_id
    http_response_code(403); 
    echo '<p style="color:red;">Error 403: Access denied. Order not found or does not belong to your account.</p>';
    $stmt_check->close();
    $conn->close();
    exit;
}
$stmt_check->close();
// --- End Security Check ---


// Fetch Order Items
$items = [];
$stmt = $conn->prepare("
    SELECT product_name, quantity, price
    FROM order_items 
    WHERE order_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

// Build HTML response with simple styling
if (!empty($items)) {
    $output = '<table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="background:#f9f9f9;">
                        <th style="padding:8px; border:1px solid #ddd; text-align:left;">Product Name</th>
                        <th style="padding:8px; border:1px solid #ddd; text-align:right;">Quantity</th>
                        <th style="padding:8px; border:1px solid #ddd; text-align:right;">Unit Price</th>
                        <th style="padding:8px; border:1px solid #ddd; text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>';
    $orderTotal = 0;
    foreach ($items as $item) {
        $subtotal = $item['quantity'] * $item['price'];
        $orderTotal += $subtotal;
        $output .= '<tr>
                        <td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($item['product_name']) . '</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:right;">' . htmlspecialchars($item['quantity']) . '</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:right;">₹' . number_format($item['price'], 2) . '</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:right;">₹' . number_format($subtotal, 2) . '</td>
                    </tr>';
    }
    $output .= '</tbody></table>';
    
    $output .= '<div style="text-align:right; margin-top:15px; padding-top:10px; border-top: 1px dashed #ddd;">
                    <p style="font-size: 1.2em; font-weight: bold; color: #3d220e;">Order Total: ₹' . number_format($orderTotal, 2) . '</p>
                </div>';
    
    echo $output;
} else {
    echo '<p style="color: #777;">No items found for this order in the **order_items** table.</p>';
}
?>