<?php
// --- START: PHP Setup and Role Check ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Define user roles for access control
$user_role = $_SESSION['role'] ?? 'guest'; 
$is_superadmin = isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_admin_or_superadmin = $is_admin || $is_superadmin;

// Check for overall admin access to this page
if (!$is_admin_or_superadmin) {
    $access_denied_message = "Access Denied: You must be an Admin or Superadmin to view this page.";
}

// Helper function to format date
function formatDate($dateString) {
    return date('M j, Y H:i', strtotime($dateString));
}

// Helper function to get status badge color
function getStatusBadgeClass($status) {
    $status = strtolower($status);
    if (strpos($status, 'complete') !== false || strpos($status, 'delivered') !== false) {
        return 'bg-green-100 text-green-800';
    } elseif (strpos($status, 'pending') !== false || strpos($status, 'processing') !== false) {
        return 'bg-yellow-100 text-yellow-800';
    } elseif (strpos($status, 'cancelled') !== false || strpos($status, 'failed') !== false) {
        return 'bg-red-100 text-red-800';
    }
    return 'bg-gray-100 text-gray-800';
}

/**
 * Fetches actual order items from the database.
 * This function uses the established schema: id, order_id, product_id, product_name, quantity, price.
 * * @param mysqli $conn The active database connection object.
 * @param int $orderId The ID of the order to fetch items for.
 * @return array List of items structured like a database result.
 */
function fetchOrderItemsFromDB($conn, $orderId) {
    // Sanitize order ID for safety
    $safe_order_id = mysqli_real_escape_string($conn, $orderId);

    // Query to fetch all items for the given order ID
    $sql = "SELECT 
                id, 
                order_id, 
                product_id, 
                product_name, 
                quantity, 
                price 
            FROM order_items 
            WHERE order_id = '{$safe_order_id}' 
            ORDER BY id ASC";
    
    $result = @mysqli_query($conn, $sql);
    $items = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
        mysqli_free_result($result);
    } 
    // If query fails, it returns an empty array, which the front-end handles gracefully.
    return $items;
}

// --- DATABASE CONNECTION & DATA FETCHING LOGIC ---

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_data = null;
$orders_data = [];
$total_orders = 0;
$db_error = '';

// Check the view mode: 'all' to show all, or default (which is limited)
$is_view_all = isset($_GET['view']) && $_GET['view'] === 'all';
$order_limit = $is_view_all ? 99999 : 5; // Use a high number for 'all' to effectively remove the limit
$order_heading_suffix = $is_view_all ? " (All Orders)" : " (Last 5)";


if ($is_admin_or_superadmin && $user_id > 0) {
    if (file_exists('db1.php')) {
        require_once 'db1.php'; 
        
        if (isset($conn) && is_object($conn) && property_exists($conn, 'connect_error') && !$conn->connect_error) {
            
            // Sanitize ID
            $safe_user_id = mysqli_real_escape_string($conn, $user_id);

            // 1. Fetch User Details
            $user_sql = "SELECT 
                            user_id, firstname, lastname, email, 
                            phonenumber, area, landmark, zipcode, 
                            profile_picture 
                         FROM users 
                         WHERE user_id = '{$safe_user_id}' LIMIT 1";

            $user_result = @mysqli_query($conn, $user_sql);
            
            if ($user_result && mysqli_num_rows($user_result) > 0) {
                $user_data = mysqli_fetch_assoc($user_result);
                mysqli_free_result($user_result);
            } else {
                 $db_error .= "User ID '{$user_id}' not found. " . mysqli_error($conn);
            }

            // 2. Fetch Order Details (Count and Orders)
            if ($user_data) {
                try {
                    // Count total orders (always fetch total count)
                    $count_sql = "SELECT COUNT(*) as total FROM orders WHERE user_id = '{$safe_user_id}'";
                    $count_result = @mysqli_query($conn, $count_sql);
                    if ($count_result) {
                        $total_orders = mysqli_fetch_assoc($count_result)['total'];
                        mysqli_free_result($count_result);
                    }
                    
                    // Fetch orders based on the calculated limit
                    $orders_sql = "SELECT order_id, total_amount, order_date, order_status, payment_method 
                                   FROM orders 
                                   WHERE user_id = '{$safe_user_id}' 
                                   ORDER BY order_date DESC LIMIT $order_limit";
                    
                    $orders_result = @mysqli_query($conn, $orders_sql);
                    if ($orders_result) {
                        while ($row = mysqli_fetch_assoc($orders_result)) {
                            // Fetch items for each order
                            $row['items'] = fetchOrderItemsFromDB($conn, $row['order_id']); 
                            $orders_data[] = $row;
                        }
                        mysqli_free_result($orders_result);
                    } else {
                        throw new Exception("Error fetching orders list. Query error details: " . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    $db_error = "Warning: Could not fetch order details. Details: " . $e->getMessage();
                    $total_orders = "N/A";
                    $orders_data = [];
                }
            }


            mysqli_close($conn); // Close connection after all data is fetched

        } else if (isset($conn) && property_exists($conn, 'connect_error') && $conn->connect_error) {
             $db_error = "Database connection failed via db1.php. Error: " . $conn->connect_error;
        } else {
            $db_error = "Database connection object (\$conn) was not available after including db1.php.";
        }

    } else {
        $db_error = "Missing 'db1.php' file. Cannot attempt database connection.";
    }
}
// If user ID is missing
if (!$user_id && $is_admin_or_superadmin) {
    $db_error = "Error: User ID is missing from the URL.";
}

// --- END DATABASE CONNECTION & DATA FETCHING LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customer #<?= htmlspecialchars($user_id) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f7f7;
        }
        .sidebar {
            background-color: #1f2937;
        }
        .sidebar-link:hover {
            background-color: #374151;
        }
        /* Style for the profile card sections */
        .detail-card {
            border-left: 4px solid #4f46e5; /* indigo-600 */
            background-color: #f9fafb; /* gray-50 */
        }
        .stat-box {
            background-color: #eef2ff; /* indigo-50 */
        }
        /* Custom styles for anchors within the content */
        a.content-link {
            color: #4f46e5;
            text-decoration: none;
            transition: color 0.15s;
        }
        a.content-link:hover {
            color: #3730a3; /* indigo-800 */
            text-decoration: underline;
        }
        /* Custom style for the modal overlay */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.7);
        }
        .modal-content {
            max-height: 90vh;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
        
        /**
         * Utility function to display non-intrusive messages (replaces alert()).
         */
        function displayMessage(message, isSuccess = true) {
            const container = document.getElementById('message-container');
            if (!container) return;

            const colorClass = isSuccess ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `p-4 mb-4 rounded-lg shadow-md border ${colorClass} transition-opacity duration-300 ease-in-out opacity-100`;
            messageDiv.innerHTML = `<strong class="font-bold">${isSuccess ? 'Success:' : 'Access Denied:'}</strong> <span class="block sm:inline">${message}</span>`;
            
            container.innerHTML = '';
            container.appendChild(messageDiv);

            // Automatically remove the message after 7 seconds
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.addEventListener('transitionend', () => messageDiv.remove());
            }, 7000);
        }

        // Simple confirmation replacement for delete
        function confirmDelete(event, deleteUrl) {
            event.preventDefault(); 
            const userRole = "<?= htmlspecialchars($user_role) ?>"; 
            if (userRole !== 'superadmin') {
                displayMessage("🚫 You do not have permission to delete users. This action is restricted to Superadmins only.", false);
                return;
            }
            if (window.confirm("Are you absolutely sure you want to delete this user? This action cannot be undone.")) {
                 window.location.href = deleteUrl;
            }
        }

        /**
         * --- START: ORDER ITEMS VIEW LOGIC ---
         */

        /**
         * Closes the order items modal.
         */
        function closeModal() {
            document.getElementById('order-items-modal').classList.add('hidden');
        }

        /**
         * Displays the order item data provided by PHP in the modal.
         * Now uses the keys from the user's schema: product_name, product_id, quantity, price.
         * @param {number|string} orderId The ID of the order.
         * @param {string} base64EncodedItems Base64 encoded JSON string of the order items.
         */
        function showOrderItemsModal(orderId, base64EncodedItems) {
            const modal = document.getElementById('order-items-modal');
            const itemsContainer = document.getElementById('modal-items-content');
            
            // Decode the data passed from PHP
            let items = [];
            try {
                const jsonString = atob(base64EncodedItems);
                items = JSON.parse(jsonString);
            } catch (e) {
                console.error("Failed to decode order items data:", e);
                itemsContainer.innerHTML = `<p class="text-center text-red-500 py-4">Error loading item details.</p>`;
                modal.classList.remove('hidden');
                return;
            }

            // Set the modal title
            document.getElementById('modal-title').textContent = `Items for Order #${orderId}`;
            
            let htmlContent = `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product ID</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;

            let grandTotal = 0;

            if (items.length === 0) {
                 htmlContent += `
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No items found for this order.</td>
                    </tr>
                `;
            } else {
                items.forEach(item => {
                    // Accessing data strictly via the requested schema keys
                    const itemPrice = parseFloat(item.price);
                    const itemQuantity = parseInt(item.quantity);
                    const subtotal = itemPrice * itemQuantity;
                    grandTotal += subtotal;
                    
                    // Generate a placeholder image based on product ID
                    // NOTE: Placeholder used as product image URL is not in the schema
                    const image = `https://placehold.co/60x60/8B4513/FFF?text=P%23${item.product_id || 'N/A'}`; 

                    htmlContent += `
                        <tr class="hover:bg-gray-50 transition duration-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <div class="flex items-center">
                                    <img src="${image}" alt="${item.product_name}" class="h-10 w-10 rounded-lg object-cover mr-3" onerror="this.onerror=null;this.src='https://placehold.co/60x60/8B4513/FFF?text=P';">
                                    <span>${item.product_name}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.product_id || 'N/A'}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">${itemQuantity}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">$${itemPrice.toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right">$${subtotal.toFixed(2)}</td>
                        </tr>
                    `;
                });
                
                // Add the grand total row
                htmlContent += `
                    <tr class="bg-gray-50">
                        <td colspan="4" class="px-6 py-4 text-right text-base font-bold text-gray-800">Grand Total:</td>
                        <td class="px-6 py-4 whitespace-nowrap text-base font-bold text-right text-indigo-600">$${grandTotal.toFixed(2)}</td>
                    </tr>
                `;
            }

            htmlContent += `</tbody></table></div>`;
            
            itemsContainer.innerHTML = htmlContent;
            
            // Show the modal
            modal.classList.remove('hidden');
        }
        
        /**
         * --- END: ORDER ITEMS VIEW LOGIC ---
         */
    </script>
</head>
<body class="flex min-h-screen">

<div class="w-64 sidebar text-white h-screen fixed top-0 left-0 overflow-y-auto shadow-2xl z-10 transition-all duration-300">
    <div class="p-6 border-b border-gray-700">
        <h2 class="text-2xl font-extrabold tracking-tight text-white">ArakuTribe Admin</h2>
        <p class="text-xs text-gray-400 mt-1">Role: <span class="capitalize font-semibold"><?= htmlspecialchars($user_role) ?></span></p>
    </div>
    
    <ul class="py-4 space-y-1">
        
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="dashboard.php" class="block font-medium"> Dashboard</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="orders.php" class="block font-medium"> Orders</a>
        </li>
        
        <?php if ($is_admin_or_superadmin): ?>
            <li class="sidebar-link px-4 py-2 transition duration-150">
                <a href="admins.php" class="block font-medium">👥 Admins</a>
            </li>
            <li class="sidebar-link px-4 py-2 transition duration-150">
                <a href="products.php" class="block font-medium">🛒 Products</a>
            </li>
        <?php endif; ?>
        <li class="sidebar-link px-4 py-2 bg-gray-700/50 transition duration-150">
            <a href="users.php" class="block font-medium">Customers</a>
        </li>
       

        <li class="px-4 py-2 mt-6 border-t border-gray-700 hover:bg-red-800 transition duration-150">
            <a href="logout_admin.php" class="block font-semibold text-red-300 hover:text-white">🚪 Logout</a>
        </li>
    </ul>
</div>

<div class="flex-1 ml-64 p-8">
    <div class="bg-white p-8 rounded-xl shadow-lg min-h-[90vh]">
        <div class="flex justify-between items-start mb-6 border-b pb-4">
            <h1 class="text-3xl font-bold text-gray-800">
                Customer Details <span class="text-indigo-600">#<?= htmlspecialchars($user_id) ?></span>
            </h1>
            <div class="space-x-2">
                <a href="users.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg shadow transition duration-150 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to List
                </a>
                <?php if ($user_data): ?>
                    <a href="edit_user.php?id=<?= urlencode($user_id) ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-150 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        Edit
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="message-container" class="fixed top-2 right-2 z-50 w-full max-w-sm"></div>

        <?php if (isset($access_denied_message)): ?>
             <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-8 rounded-xl relative mb-6 text-center shadow-md">
                <strong class="font-bold text-xl block mb-2">🚫 Access Denied!</strong>
                <span class="block text-lg"><?= htmlspecialchars($access_denied_message) ?></span>
                <p class="mt-4 text-sm text-red-600">Please log in with an Admin or Superadmin account to view this content.</p>
            </div>
        <?php elseif ($db_error || !$user_data): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-8 rounded-xl relative mb-6 text-center shadow-md">
                <strong class="font-bold text-xl block mb-2">⚠️ Error!</strong>
                <span class="block text-lg"><?= htmlspecialchars($db_error ?: "Customer not found.") ?></span>
                <p class="mt-4 text-sm text-red-600">Please check the Customer ID and ensure the required database tables exist and contain the necessary columns.</p>
            </div>
        <?php else: 
            // Variables for easier access
            $pp = htmlspecialchars($user_data['profile_picture'] ?? '');
            $full_name = htmlspecialchars($user_data['firstname'] ?? '') . ' ' . htmlspecialchars($user_data['lastname'] ?? '');
            $email = htmlspecialchars($user_data['email'] ?? 'N/A');
            $phone = htmlspecialchars($user_data['phonenumber'] ?? 'N/A');
            $area = htmlspecialchars($user_data['area'] ?? 'N/A');
            $landmark = htmlspecialchars($user_data['landmark'] ?? 'N/A');
            $zipcode = htmlspecialchars($user_data['zipcode'] ?? 'N/A');
        ?>

        <div class="bg-indigo-700 text-white p-6 rounded-t-xl shadow-lg flex items-center mb-6">
            <div class="flex-shrink-0 mr-6">
                <img src="<?= !empty($pp) ? $pp : 'https://placehold.co/128x128/ffffff/000?text=' . urlencode(strtoupper(substr($user_data['firstname'], 0, 1) . substr($user_data['lastname'], 0, 1))) ?>" 
                     alt="<?= $full_name ?>" 
                     class="h-24 w-24 rounded-full border-4 border-white object-cover"
                     onerror="this.onerror=null;this.src='https://placehold.co/128x128/ffffff/000?text=U';" 
                >
            </div>
            <div>
                <h2 class="text-4xl font-extrabold"><?= $full_name ?></h2>
                <p class="text-indigo-200 mt-1">Customer ID: <?= htmlspecialchars($user_data['user_id']) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-xl shadow-md detail-card">
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-6 h-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        Contact & Account Information
                    </h3>
                    <div class="space-y-4 text-gray-700">
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-medium">Email Address:</span>
                            <span class="truncate"><?= $email ?></span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-medium">Phone Number:</span>
                            <span><?= $phone ?></span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-medium">Account Status:</span>
                            <span class="font-bold text-green-600">Active</span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-medium">Password Security:</span>
                            <span class="text-red-500 font-medium">Secured (Not viewable)</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md detail-card mt-6">
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-6 h-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Primary Shipping Address
                    </h3>
                    <div class="space-y-4 text-gray-700">
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-medium">Area/Street:</span>
                            <span><?= $area ?></span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-medium">Landmark:</span>
                            <span><?= $landmark ?></span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-medium">ZIP Code:</span>
                            <span><?= $zipcode ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-xl shadow-md detail-card">
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-6 h-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6m0 0h18m-9 0V7a3 3 0 00-3-3H9a3 3 0 00-3 3v12m9 0a2 2 0 002-2v-6a2 2 0 00-2-2m-9 0V7m0 12v-6m0-2h2m0 0h2m0 0h2m0 0h2m-6 2h2m0 0h2m0 0h2" /></svg>
                        Customer Metrics
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="stat-box p-4 rounded-lg text-center">
                            <p class="text-3xl font-extrabold text-indigo-600"><?= $total_orders ?></p>
                            <p class="text-sm font-medium text-gray-600">Total Orders</p>
                        </div>
                        <div class="stat-box p-4 rounded-lg text-center">
                            <p class="text-3xl font-extrabold text-indigo-600">N/A</p>
                            <p class="text-sm font-medium text-gray-600">Lifetime Spend</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-red-50 p-6 rounded-xl shadow-md border border-red-200 mt-6">
                    <h3 class="text-xl font-semibold text-red-700 mb-4">Danger Zone</h3>
                    <p class="text-sm text-red-600 mb-4">
                        Permanently delete this customer account. This action cannot be undone. 
                        Requires **Superadmin** access.
                    </p>
                    
                    <?php if ($is_superadmin): ?>
                        <a class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-150 w-full text-center block" 
                           href="delete_user.php?id=<?= urlencode($user_id) ?>" 
                           onclick="confirmDelete(event, this.href)">
                           Permanently Delete Customer
                        </a>
                    <?php else: ?>
                        <button class="bg-gray-400 text-gray-700 font-semibold py-2 px-4 rounded-lg shadow cursor-not-allowed w-full text-center block" disabled>
                            Delete (Superadmin Only)
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="orders" class="bg-white p-6 rounded-xl shadow-lg border border-gray-200 mt-8">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                Order History <?= $order_heading_suffix ?>
                <span class="text-sm font-normal text-gray-500">(Total: <?= $total_orders ?>)</span>
            </h3>
            
            <?php if (empty($orders_data)): ?>
                <p class="text-lg text-gray-500 py-4 text-center">No order data available for this customer.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orders_data as $order): 
                                // Prepare the item data for safe JavaScript transfer
                                $encoded_items = base64_encode(json_encode($order['items']));
                            ?>
                                <tr class="hover:bg-gray-50 transition duration-100">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600"><?= htmlspecialchars($order['order_id'] ?? 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= formatDate($order['order_date'] ?? '') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right">$<?= number_format($order['total_amount'] ?? 0, 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <span class="capitalize text-gray-800 font-medium">
                                            <?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= getStatusBadgeClass($order['order_status'] ?? 'unknown') ?> capitalize">
                                            <?= htmlspecialchars($order['order_status'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <button 
                                            class="text-indigo-600 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-150"
                                            onclick="showOrderItemsModal('<?= htmlspecialchars($order['order_id']) ?>', '<?= $encoded_items ?>')"
                                        >
                                            View Items
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_orders > 5): ?>
                    <div class="mt-6 text-center border-t pt-4">
                        <?php if ($is_view_all): ?>
                            <a href="view_user.php?id=<?= urlencode($user_id) ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                View Recent 5 Orders
                            </a>
                        <?php else: ?>
                            <a href="view_user.php?id=<?= urlencode($user_id) ?>&view=all" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                View All <?= $total_orders ?> Orders
                                <svg class="w-5 h-5 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<div id="order-items-modal" class="fixed inset-0 z-50 hidden modal-overlay overflow-y-auto" onclick="if (event.target.id === 'order-items-modal') closeModal()">
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl modal-content transform transition-all duration-300">
            <div class="flex justify-between items-center p-5 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900" id="modal-title">
                    Order Items
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition duration-150" onclick="closeModal()">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto" id="modal-items-content">
                <p class="text-center text-gray-500">Loading order items...</p>
            </div>
            <div class="p-4 border-t border-gray-200 text-right">
                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-150" onclick="closeModal()">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>