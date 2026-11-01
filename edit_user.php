<?php
// --- START: PHP Setup and Role Check ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$user_role = $_SESSION['role'] ?? 'guest'; 
$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin');

// Check for required admin access
if (!$is_admin) {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION & VARIABLES ---
$user_id = $_GET['id'] ?? null;
$user_data = null;
$message = '';
$message_type = 'success';
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
    $message = "Invalid customer ID or database error.";
    $message_type = 'error';
    $user_id = null; // Prevent execution of further DB logic
}

if ($user_id && $conn && !$conn->connect_error) {
    
    // 1. Handle form submission (Update logic)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phonenumber'] ?? '');
        $zipcode = trim($_POST['zipcode'] ?? '');

        // Comprehensive validation
        $errors = [];
        
        // First Name - only alphabets and spaces
        if (empty($firstname)) {
            $errors[] = "First name is required.";
        } elseif (!preg_match("/^[a-zA-Z\s]+$/", $firstname)) {
            $errors[] = "First name can only contain letters and spaces.";
        }
        
        // Last Name - only alphabets and spaces
        if (empty($lastname)) {
            $errors[] = "Last name is required.";
        } elseif (!preg_match("/^[a-zA-Z\s]+$/", $lastname)) {
            $errors[] = "Last name can only contain letters and spaces.";
        }
        
        // Email - must be @gmail.com
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@gmail\.com$/i", $email)) {
            $errors[] = "Email must be a valid @gmail.com address.";
        }
        
        // Phone - exactly 10 digits
        if (!empty($phone)) {
            $phone = preg_replace('/\D/', '', $phone); // Remove non-digits
            if (strlen($phone) !== 10) {
                $errors[] = "Phone number must be exactly 10 digits.";
            }
        }
        
        // Zipcode - exactly 6 digits
        if (!empty($zipcode)) {
            $zipcode = preg_replace('/\D/', '', $zipcode); // Remove non-digits
            if (strlen($zipcode) !== 6) {
                $errors[] = "Zip code must be exactly 6 digits.";
            }
        }

        if (!empty($errors)) {
            $message = implode("<br>", $errors);
            $message_type = 'error';
        } else {
            // Sanitize for SQL
            $firstname = mysqli_real_escape_string($conn, $firstname);
            $lastname = mysqli_real_escape_string($conn, $lastname);
            $email = mysqli_real_escape_string($conn, $email);
            $phone = mysqli_real_escape_string($conn, $phone);
            $zipcode = mysqli_real_escape_string($conn, $zipcode);
            
            $update_sql = "UPDATE users SET 
                            firstname = '{$firstname}', 
                            lastname = '{$lastname}', 
                            email = '{$email}', 
                            phonenumber = '{$phone}', 
                            zipcode = '{$zipcode}' 
                            WHERE user_id = '{$user_id}'";
            
            if (mysqli_query($conn, $update_sql)) {
                $message = "Customer details updated successfully for ID: " . htmlspecialchars($user_id);
                // Redirect after successful update to prevent form resubmission
                header("Location: edit_user.php?id=" . urlencode($user_id) . "&status=success");
                exit();
            } else {
                $message = "Error updating record: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
    
    // Handle URL status message
    if (isset($_GET['status']) && $_GET['status'] === 'success') {
        $message = "Customer details updated successfully.";
        $message_type = 'success';
    }

    // 2. Fetch existing data (for form pre-filling)
    $fetch_sql = "SELECT user_id, firstname, lastname, email, phonenumber, zipcode FROM users WHERE user_id = '{$user_id}' LIMIT 1";
    $result = @mysqli_query($conn, $fetch_sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $user_data = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
    } else {
        $message = "Customer not found for ID: " . htmlspecialchars($user_id);
        $message_type = 'error';
        $user_id = null;
    }
    
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7f7f7; }
        .sidebar { background-color: #1f2937; }
        .sidebar-link:hover { background-color: #374151; }
    </style>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], } } }
        }
        
        // Real-time input restriction for digits-only fields
        function allowOnlyDigits(event) {
            const key = event.key;
            // Allow: backspace, delete, tab, escape, enter, arrow keys
            if (['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(key)) {
                return true;
            }
            // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            if (event.ctrlKey || event.metaKey) {
                return true;
            }
            // Block if not a digit
            if (!/^\d$/.test(key)) {
                event.preventDefault();
                return false;
            }
        }
        
        // Real-time input restriction for letters-only fields
        function allowOnlyLetters(event) {
            const key = event.key;
            // Allow: backspace, delete, tab, escape, enter, arrow keys, space
            if (['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', ' '].includes(key)) {
                return true;
            }
            // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            if (event.ctrlKey || event.metaKey) {
                return true;
            }
            // Block if not a letter
            if (!/^[a-zA-Z]$/.test(key)) {
                event.preventDefault();
                return false;
            }
        }
        
        // Client-side validation
        function validateForm(event) {
            let errors = [];
            
            const firstname = document.getElementById('firstname').value.trim();
            const lastname = document.getElementById('lastname').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phonenumber').value.trim();
            const zipcode = document.getElementById('zipcode').value.trim();
            
            // Name validation
            const namePattern = /^[a-zA-Z\s]+$/;
            if (!firstname || !namePattern.test(firstname)) {
                errors.push("First name can only contain letters and spaces.");
            }
            if (!lastname || !namePattern.test(lastname)) {
                errors.push("Last name can only contain letters and spaces.");
            }
            
            // Email validation - @gmail.com only
            const emailPattern = /^[a-zA-Z0-9._%+-]+@gmail\.com$/i;
            if (!email || !emailPattern.test(email)) {
                errors.push("Email must be a valid @gmail.com address.");
            }
            
            // Phone validation - 10 digits
            const phoneDigits = phone.replace(/\D/g, '');
            if (phone && phoneDigits.length !== 10) {
                errors.push("Phone number must be exactly 10 digits.");
            }
            
            // Zipcode validation - 6 digits
            const zipcodeDigits = zipcode.replace(/\D/g, '');
            if (zipcode && zipcodeDigits.length !== 6) {
                errors.push("Zip code must be exactly 6 digits.");
            }
            
            if (errors.length > 0) {
                event.preventDefault();
                alert(errors.join("\n"));
                return false;
            }
            
            return true;
        }
        
        // Attach event listeners when page loads
        window.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phonenumber');
            const zipcodeInput = document.getElementById('zipcode');
            const firstnameInput = document.getElementById('firstname');
            const lastnameInput = document.getElementById('lastname');
            
            if (phoneInput) {
                phoneInput.addEventListener('keydown', allowOnlyDigits);
            }
            if (zipcodeInput) {
                zipcodeInput.addEventListener('keydown', allowOnlyDigits);
            }
            if (firstnameInput) {
                firstnameInput.addEventListener('keydown', allowOnlyLetters);
            }
            if (lastnameInput) {
                lastnameInput.addEventListener('keydown', allowOnlyLetters);
            }
        });
    </script>
</head>
<body class="flex min-h-screen">
<!-- Sidebar -->
<div class="w-64 sidebar text-white h-screen fixed top-0 left-0 overflow-y-auto shadow-2xl z-10 transition-all duration-300">
    <div class="p-6 border-b border-gray-700">
        <h2 class="text-2xl font-extrabold tracking-tight text-white">ArakuTribe Admin</h2>
        <p class="text-xs text-gray-400 mt-1">Role: <span class="capitalize font-semibold"><?= htmlspecialchars($user_role) ?></span></p>
    </div>
    
    <ul class="py-4 space-y-1">
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="dashboard.php" class="block font-medium">🏠 Dashboard</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="orders.php" class="block font-medium">📦 Orders</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="admins.php" class="block font-medium">👥 Admins</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="products.php" class="block font-medium">🛒 Products</a>
        </li>
        <li class="sidebar-link px-4 py-2 bg-gray-700/50 transition duration-150">
            <a href="users.php" class="block font-medium">🧑‍💻 Customers</a>
        </li>
       
        <li class="px-4 py-2 mt-6 border-t border-gray-700 hover:bg-red-800 transition duration-150">
            <a href="logout_admin.php" class="block font-semibold text-red-300 hover:text-white">Logout</a>
        </li>
    </ul>
</div>

<!-- Content Area -->
<div class="flex-1 ml-64 p-8">
    <div class="bg-white p-8 rounded-xl shadow-lg min-h-[90vh]">
        
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
            <a href="users.php" class="hover:text-indigo-600 transition">🧑‍💻 Customers</a>
            <span>/</span>
            <span class="text-gray-600 font-medium">Edit Customer</span>
        </div>
        
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Edit Customer</h1>
        <p class="text-gray-500 mb-6">Customer ID: <span class="font-semibold text-indigo-600"><?= htmlspecialchars($user_id ?? 'N/A') ?></span></p>
        
        <!-- Messages -->
        <div class="mb-6">
        <?php if ($message): ?>
            <div class="p-4 rounded-lg shadow-md border 
                <?= $message_type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700' ?>" 
                role="alert">
                <strong class="font-bold"><?= ucfirst($message_type) ?>:</strong>
                <span class="block sm:inline"><?= $message ?></span>
            </div>
        <?php endif; ?>
        <?php if ($db_error): ?>
             <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4" role="alert">
                <strong class="font-bold">Database Error:</strong>
                <span class="block sm:inline"><?= htmlspecialchars($db_error) ?></span>
            </div>
        <?php endif; ?>
        </div>

        <!-- Validation Rules Info Box -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
                <span class="text-blue-500 text-xl">ℹ️</span>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">Field Requirements</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Name:</strong> Letters and spaces only</li>
                        <li><strong>Email:</strong> Must be @gmail.com address</li>
                        <li><strong>Phone:</strong> Exactly 10 digits</li>
                        <li><strong>Zip Code:</strong> Exactly 6 digits</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if ($user_data): ?>
            <form method="POST" onsubmit="return validateForm(event)" class="space-y-6 max-w-3xl">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- First Name -->
                    <div>
                        <label for="firstname" class="block text-sm font-semibold text-gray-700 mb-1">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="firstname" id="firstname" required
                               value="<?= htmlspecialchars($user_data['firstname'] ?? '') ?>"
                               pattern="[a-zA-Z\s]+"
                               title="Only letters and spaces allowed"
                               class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <p class="text-xs text-gray-400 mt-1">Letters and spaces only</p>
                    </div>

                    <!-- Last Name -->
                    <div>
                        <label for="lastname" class="block text-sm font-semibold text-gray-700 mb-1">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="lastname" id="lastname" required
                               value="<?= htmlspecialchars($user_data['lastname'] ?? '') ?>"
                               pattern="[a-zA-Z\s]+"
                               title="Only letters and spaces allowed"
                               class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <p class="text-xs text-gray-400 mt-1">Letters and spaces only</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" id="email" required
                               value="<?= htmlspecialchars($user_data['email'] ?? '') ?>"
                               pattern="[a-zA-Z0-9._%+-]+@gmail\.com"
                               title="Must be a @gmail.com address"
                               class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <p class="text-xs text-gray-400 mt-1">Must be @gmail.com address</p>
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label for="phonenumber" class="block text-sm font-semibold text-gray-700 mb-1">
                            Phone Number
                        </label>
                        <input type="tel" name="phonenumber" id="phonenumber"
                               value="<?= htmlspecialchars($user_data['phonenumber'] ?? '') ?>"
                               maxlength="10"
                               pattern="\d{10}"
                               title="Must be exactly 10 digits"
                               placeholder="e.g. 9876543210"
                               class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <p class="text-xs text-gray-400 mt-1">Exactly 10 digits</p>
                    </div>
                </div>

                <!-- Zip Code -->
                <div class="md:w-1/2">
                    <label for="zipcode" class="block text-sm font-semibold text-gray-700 mb-1">
                        Zip Code
                    </label>
                    <input type="text" name="zipcode" id="zipcode"
                           value="<?= htmlspecialchars($user_data['zipcode'] ?? '') ?>"
                           maxlength="6"
                           pattern="\d{6}"
                           title="Must be exactly 6 digits"
                           placeholder="e.g. 500001"
                           class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <p class="text-xs text-gray-400 mt-1">Exactly 6 digits</p>
                </div>

                <div class="border-t border-gray-100 pt-6"></div>

                <div class="flex space-x-4">
                    <button type="submit" class="inline-flex justify-center py-3 px-6 border border-transparent shadow-lg text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        💾 Update Customer
                    </button>
                    <a href="users.php" class="inline-flex justify-center py-3 px-6 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition duration-150">
                        ← Cancel
                    </a>
                </div>
            </form>
        <?php else: ?>
            <p class="text-lg text-red-500">Customer details could not be loaded.</p>
            <a href="users.php" class="inline-block mt-4 text-indigo-600 hover:text-indigo-800 font-semibold">← Back to Customers</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>