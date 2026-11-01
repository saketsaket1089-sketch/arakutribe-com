<?php
// display_errors is intentionally OFF here: this endpoint returns raw JSON
// when called via AJAX (ajax=1), and any stray PHP warning/notice printed
// before that JSON would corrupt it — causing the browser's fetch().json()
// to fail silently, even though the actual cart write already succeeded.
// That mismatch (item really added, but the page never finds out) is exactly
// why the UI only ever showed the correct state after a manual refresh.
// Errors are still logged server-side via error_log(), just not echoed.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
session_start();
include 'db1.php';

// Save the previous page (used for "Back to products" later)
$_SESSION['last_product_page'] = $_SERVER['HTTP_REFERER'] ?? 'coffee_products.php';

// Check login status once
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// --- AJAX MODE: when called via fetch() (index2.php / wishlist.php), the
// caller sends ajax=1 and expects a real JSON success/failure response
// instead of a redirect, since a redirect can't tell JS whether the add
// actually worked or silently no-op'd (e.g. product not found).
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

function respond($isAjax, $success, $message, $redirectUrl) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => $success ? 'success' : 'error', 'message' => $message]);
        exit;
    }
    header("Location: $redirectUrl");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? '';
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $redirect_flag = $_POST['redirect_to'] ?? ''; 
    
    // Determine the success redirect URL based on the flag
    $final_redirect_url = ($redirect_flag === 'cart') ? 'cart.php' : ($_SESSION['last_product_page'] ?? 'coffee_products.php');

    // 1. Basic validation
    if (!$product_id || $quantity <= 0) {
        respond($isAjax, false, 'Missing or invalid product_id/quantity.', $_SESSION['last_product_page'] ?? 'coffee_products.php');
    }

    // 2. Connect to DB to get product price/name (needed for both logged-in and guest cart)
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        respond($isAjax, false, 'Database connection failed.', 'index.php');
    }

    $stmt = $conn->prepare("SELECT product_name, price, image FROM products WHERE product_id = ?");
    $stmt->bind_param("s", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $product_data = $result->fetch_assoc();
        $stmt->close();

        if ($is_logged_in) {
            // =======================================================
            // A. LOGGED-IN USER: Update Database and Session Cache
            // =======================================================
            
            // Check if item exists in DB cart
            $check = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $check->bind_param("is", $user_id, $product_id);
            $check->execute();
            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {
                // Update quantity in DB
                $update = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
                $update->bind_param("iis", $quantity, $user_id, $product_id);
                $update->execute();
                $update->close();
            } else {
                // Insert new item into DB
                $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $insert->bind_param("isi", $user_id, $product_id, $quantity);
                $insert->execute();
                $insert->close();
            }
            $check->close();

            // Refresh Session Cart Cache (Pull entire cart from DB into session)
            $refresh_stmt = $conn->prepare("
                SELECT c.product_id, c.quantity, p.product_name, p.price, p.image
                FROM cart c
                JOIN products p ON c.product_id = p.product_id
                WHERE c.user_id = ?
            ");
            $refresh_stmt->bind_param("i", $user_id);
            $refresh_stmt->execute();
            $res = $refresh_stmt->get_result();

            $_SESSION['cart'] = []; // Clear the old session cart array
            while ($row = $res->fetch_assoc()) {
                $_SESSION['cart'][$row['product_id']] = [
                    'product_name' => $row['product_name'],
                    'price' => (float)$row['price'],
                    'image' => $row['image'],
                    'quantity' => (int)$row['quantity']
                ];
            }
            $refresh_stmt->close();

        } else {
            // =======================================================
            // B. GUEST USER: Update Session Cart Only
            // =======================================================
            
            // Initialize session cart array if needed
            if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            if (isset($_SESSION['cart'][$product_id])) {
                // Item exists, update quantity in session
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                // New item, add all necessary data to session
                $_SESSION['cart'][$product_id] = [
                    'product_name' => $product_data['product_name'],
                    'price' => (float)$product_data['price'],
                    'image' => $product_data['image'],
                    'quantity' => $quantity
                ];
            }
        }

        $conn->close();
        respond($isAjax, true, 'Added to cart.', $final_redirect_url);

    } else {
         // Product not found - this is the silent-failure case that was
         // previously indistinguishable from success when called via fetch().
         $stmt->close();
         $conn->close();
         respond($isAjax, false, "Product ID '$product_id' not found in the products table.", $_SESSION['last_product_page'] ?? 'coffee_products.php');
    }
}

// Handle non-POST requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
header("Location: coffee_products.php");
exit;
?>