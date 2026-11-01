<?php
session_start();  // Start session

// Check if the admin session exists, otherwise deny access
if (!isset($_SESSION['admin'])) {
    die("Unauthorized access. Please login first.");
}

// Database configuration
$host = '68.178.145.156';
$user = 'arcoffee';       // your MySQL username
$pass = 'OnnkLG2cd2-Z';           // your MySQL password
$db   = 'arakucoffee';

// Create database connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Product details (could be obtained from a form or other source)
$category = 'cup';
$product_name = 'Admin Added Tribal Bag';
$price = 8.99;
$quantity = 200;

// Set prefix and starting base number per category
$prefixes = [
    'cup' => ['prefix' => 'ATCUP', 'base' => 50000],
    'mug' => ['prefix' => 'ATMUG', 'base' => 60000],
    't_shirt' => ['prefix' => 'ATTSH', 'base' => 30000],
    'bag' => ['prefix' => 'ATBAG', 'base' => 40000],
];

// Check if the provided category is valid
if (!isset($prefixes[$category])) {
    die("Invalid category.");
}

$prefix = $prefixes[$category]['prefix'];
$base = $prefixes[$category]['base'];

// Get highest existing product_id for the category
$sql = "SELECT product_id FROM products 
        WHERE category = ? AND product_id LIKE CONCAT(?, '%') 
        ORDER BY product_id DESC LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $category, $prefix);
$stmt->execute();
$result = $stmt->get_result();

// Calculate new product ID
if ($row = $result->fetch_assoc()) {
    // Extract the numeric part and increment
    $last_id = (int)substr($row['product_id'], strlen($prefix));
    $new_number = $last_id + 1;
} else {
    // First entry for this category
    $new_number = $base + 1;
}

$new_product_id = $prefix . str_pad($new_number, 5, '0', STR_PAD_LEFT);

// Insert the new product into the database
$insert_sql = "INSERT INTO products (product_id, category, product_name, price, quantity)
               VALUES (?, ?, ?, ?, ?)";

$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("sssdi", $new_product_id, $category, $product_name, $price, $quantity);

// Check if insertion was successful
if ($insert_stmt->execute()) {
    echo "Product inserted successfully with ID: $new_product_id";
} else {
    echo "Error: " . $insert_stmt->error;
}

// Close the prepared statements and database connection
$stmt->close();
$insert_stmt->close();
$conn->close();
?>
