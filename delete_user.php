<?php
// --- START: PHP Setup and Role Check ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$user_role = $_SESSION['role'] ?? 'guest'; 
$is_superadmin = isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';

// SECURITY CHECK: Only Superadmins can access this file
if (!$is_superadmin) {
    // Redirect to users list with an error status
    header("Location: users.php?delete_status=permission_denied");
    exit();
}

// --- DATABASE CONNECTION & VARIABLES ---
$user_id = $_GET['id'] ?? null;
$db_error = '';

if (file_exists('db1.php')) {
    require_once 'db1.php'; 
    
    if (!isset($conn) || $conn->connect_error) {
        $db_error = "Database connection failed. Error: " . ($conn->connect_error ?? 'Unknown error');
    }
} else {
    $db_error = "Missing 'db1.php' file. Cannot attempt database connection.";
}

// Check if user ID is provided and connection is good
if (!$user_id || $db_error) {
    // Redirect to users list with an error status
    header("Location: users.php?delete_status=invalid_id");
    exit();
}

if ($conn && !$conn->connect_error) {
    
    // Attempt to delete the user record
    // NOTE: In a real system, you would need to handle foreign key constraints
    // (e.g., deleting associated orders/transactions first, or setting a CASCADE DELETE rule).
    $delete_sql = "DELETE FROM users WHERE user_id = '{$user_id}' LIMIT 1";
    
    if (mysqli_query($conn, $delete_sql)) {
        // Successful deletion
        header("Location: users.php?delete_status=success&deleted_id=" . urlencode($user_id));
        exit();
    } else {
        // Deletion failed (e.g., foreign key constraint error)
        $error_message = "Deletion failed. Error: " . mysqli_error($conn);
        header("Location: users.php?delete_status=failed&error=" . urlencode($error_message));
        exit();
    }
    
    mysqli_close($conn);
} else {
    // Database connection error occurred
    header("Location: users.php?delete_status=db_error&error=" . urlencode($db_error));
    exit();
}
?>
<!-- No HTML output as this file only handles logic and redirection -->
