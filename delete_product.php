<?php
include 'db1.php';

if (!isset($_GET['id'])) {
    die('Product ID is required.');
}

$id = mysqli_real_escape_string($conn, $_GET['id']); // Escape for safety

// Note the single quotes around $id because it is a string
$sql = "DELETE FROM products WHERE product_id = '$id'";

if (mysqli_query($conn, $sql)) {
    header("Location: products.php");
    exit;
} else {
    echo "Error deleting product: " . mysqli_error($conn);
}
?>
