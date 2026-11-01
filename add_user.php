<?php
// --- START: PHP Setup and Role Check ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$user_role = $_SESSION['role'] ?? 'guest'; 
$is_admin_or_superadmin = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin');

// Check for required admin access
if (!$is_admin_or_superadmin) {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION & VARIABLES ---
$message = '';
$db_error = '';

if (file_exists('db1.php')) {
    require_once 'db1.php'; 
    
    if (!isset($conn) || $conn->connect_error) {
        $db_error = "Database connection failed. Error: " . ($conn->connect_error ?? 'Unknown error');
    }
} else {
    $db_error = "Missing 'db1.php' file. Cannot attempt database connection.";
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$db_error) {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    // NOTE: Password input has been removed. The user must set their own password via reset flow.
    $phonenumber = trim($_POST['phonenumber'] ?? '');
    $zipcode = trim($_POST['zipcode'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    
    // 1. Basic Validation (Password is no longer required in the form)
    if (empty($firstname) || empty($email)) {
        $message = "First Name and Email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        // 2. Check if email already exists
        $check_sql = "SELECT user_id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_sql);
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $message = "Error: An account with this email already exists.";
            } else {
                // 3. Generate Temporary Token (Secure placeholder instead of password)
                // This token prevents login until the user completes the password reset.
                $temp_password_token = bin2hex(random_bytes(32)); 
                
                // 4. Insert New User
                // FIX: Removed 'role' column from the field list to resolve the Fatal error.
                // Assuming 'password' column now stores the secure token for account initialization.
                $insert_sql = "INSERT INTO users 
                               (firstname, lastname, email, password, phonenumber, zipcode, area, landmark) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; 
                               
                $stmt_insert = $conn->prepare($insert_sql);
                if ($stmt_insert) {
                    $stmt_insert->bind_param("ssssssss", 
                        $firstname, 
                        $lastname, 
                        $email, 
                        $temp_password_token, // This variable holds the temporary token
                        $phonenumber, 
                        $zipcode, 
                        $area, 
                        $landmark
                    );
                    
                    if ($stmt_insert->execute()) {
                        $new_user_id = $stmt_insert->insert_id;
                        // Updated success message to reflect the new workflow
                        $message = "Success! New customer **" . htmlspecialchars($firstname) . " " . htmlspecialchars($lastname) . "** created (ID: " . $new_user_id . ").<br>
                                    **ACTION REQUIRED:** The application should now automatically send a password reset/setup link to " . htmlspecialchars($email) . ".";
                        // Clear POST data on success to prevent re-submission
                        $_POST = []; 
                    } else {
                        $message = "Error inserting user into database: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } else {
                    $message = "Database error preparing insert statement: " . $conn->error;
                }
            }
            $stmt->close();
        } else {
            $message = "Database error preparing check statement: " . $conn->error;
        }
    }
}

if ($conn && !$conn->connect_error) {
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Customer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7f7f7; }
        .sidebar { background-color: #1f2937; }
        .input-group { margin-bottom: 1rem; }
    </style>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], } } }
        }
    </script>
</head>
<body class="flex min-h-screen">
<!-- Sidebar placeholder -->
<div class="w-64 sidebar text-white h-screen fixed top-0 left-0 overflow-y-auto shadow-2xl z-10 transition-all duration-300">
    <!-- Sidebar Content -->
    <div class="p-6 border-b border-gray-700">
        <h2 class="text-2xl font-extrabold tracking-tight text-white">ArakuTribe Admin</h2>
        <p class="text-xs text-gray-400 mt-1">Role: <span class="capitalize font-semibold"><?= htmlspecialchars($user_role) ?></span></p>
    </div>
    
    <ul class="py-4 space-y-1">
        <li class="px-4 py-2"><a href="dashboard.php" class="block font-medium hover:bg-gray-700 rounded transition duration-150">🏠 Dashboard</a></li>
        <li class="px-4 py-2"><a href="users.php" class="block font-medium bg-gray-700 rounded">🧑‍💻 Customers</a></li>
        <li class="px-4 py-2"><a href="orders.php" class="block font-medium hover:bg-gray-700 rounded transition duration-150">📦 Orders</a></li>
         <li class="px-4 py-2 mt-6 border-t border-gray-700 hover:bg-red-800 transition duration-150">
            <a href="logout.php" class="block font-semibold text-red-300 hover:text-white">🚪 Logout</a>
        </li>
    </ul>
</div>

<!-- Content Area -->
<div class="flex-1 ml-64 p-8">
    <div class="bg-white p-8 rounded-xl shadow-lg min-h-[90vh]">
        
        <div class="flex justify-between items-center border-b pb-4 mb-6">
            <h1 class="text-4xl font-extrabold text-gray-800">Add New Customer</h1>
            <a href="users.php" 
               class="bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-150">
                Back to Customer List
            </a>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <?php 
                $alert_class = strpos($message, 'Success') !== false ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
            ?>
            <div class="p-4 rounded-lg shadow-md border <?= $alert_class ?> mb-6" role="alert">
                <strong class="font-bold"><?= strpos($message, 'Success') !== false ? 'Success!' : 'Attention!' ?></strong>
                <span class="block sm:inline"><?= nl2br(htmlspecialchars($message)) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($db_error): ?>
             <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Database Warning/Error:</strong>
                <span class="block sm:inline"><?= htmlspecialchars($db_error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Add User Form -->
        <form action="add_user.php" method="POST" class="space-y-6">
            
            <div class="bg-indigo-50 p-6 rounded-xl border border-indigo-200">
                <h2 class="text-xl font-semibold text-indigo-700 mb-4">Account Essentials</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- First Name -->
                    <div class="input-group">
                        <label for="firstname" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" id="firstname" name="firstname" required 
                               value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                    </div>

                    <!-- Last Name -->
                    <div class="input-group">
                        <label for="lastname" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input type="text" id="lastname" name="lastname" 
                               value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Email -->
                    <div class="input-group md:col-span-2">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                        <p class="mt-1 text-sm text-indigo-700 font-semibold">The user will receive a password setup link at this email.</p>
                    </div>

                    <!-- Password field removed for security -->
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Contact and Address Details (Optional)</h2>

                <!-- Phone Number -->
                <div class="input-group">
                    <label for="phonenumber" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input type="text" id="phonenumber" name="phonenumber" 
                           value="<?= htmlspecialchars($_POST['phonenumber'] ?? '') ?>"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Zipcode -->
                    <div class="input-group">
                        <label for="zipcode" class="block text-sm font-medium text-gray-700 mb-1">Zip Code</label>
                        <input type="text" id="zipcode" name="zipcode" 
                               value="<?= htmlspecialchars($_POST['zipcode'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                    </div>

                    <!-- Area/Street -->
                    <div class="input-group">
                        <label for="area" class="block text-sm font-medium text-gray-700 mb-1">Area/Street Address</label>
                        <input type="text" id="area" name="area" 
                               value="<?= htmlspecialchars($_POST['area'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                    </div>

                    <!-- Landmark -->
                    <div class="input-group">
                        <label for="landmark" class="block text-sm font-medium text-gray-700 mb-1">Landmark</label>
                        <input type="text" id="landmark" name="landmark" 
                               value="<?= htmlspecialchars($_POST['landmark'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                    </div>
                </div>
            </div>

            <div class="pt-5">
                <button type="submit" 
                        class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white font-extrabold py-3 px-8 rounded-lg shadow-lg transition duration-200 transform hover:scale-[1.01]">
                    Create Customer Account
                </button>
            </div>
        </form>

    </div>
</div>
</body>
</html>
