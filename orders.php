<?php
// --- START: PHP Error Reporting for Debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END: PHP Error Reporting for Debugging ---

session_start();

// Check if user is logged in (has role)
// IMPORTANT: This variable is used to control access to sensitive actions.
$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'superadmin']);
$user_role = $_SESSION['role'] ?? 'guest'; 

// --- DATABASE CONNECTION & DATA FETCHING/UPDATING LOGIC ---

$total_orders = 0;
$pending_orders = 0; 
$processed_orders = 0; 
$out_for_delivery_orders = 0;
$delivered_orders = 0;
$cancelled_orders = 0;
$all_orders_data = []; // Array to hold detailed order list
$all_order_items = []; // Array to hold all order items for quick lookup in JS
$db_error = '';
$status_update_message = ''; // Message for successful/failed status updates

// Defined list of all possible statuses for the dropdown
$possible_statuses = ['Pending', 'Processed', 'Out for Delivery', 'Delivered', 'Cancelled'];

// Status filter (from clicking a summary card) - empty means "show all"
$status_filter = $_GET['status'] ?? '';
$status_filter = in_array($status_filter, $possible_statuses) ? $status_filter : '';

// Pagination settings
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalOrdersForPagination = 0;
$totalPages = 1;

// Map of currency codes to symbols, used only for DISPLAY - no rate conversion here.
// Orders already store the exact amount + currency the customer paid in, so we
// never recalculate the amount; we just look up a nicer symbol if we have one.
$currencySymbols = [
    'INR' => '₹',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'AUD' => 'A$',
    'CAD' => 'C$',
];

function get_currency_symbol($code, $currencySymbols) {
    $code = strtoupper(trim((string)$code));
    return $currencySymbols[$code] ?? $code; // fallback: just show the code itself, e.g. "AED"
}

// Helper function to fetch a single count safely
function get_db_count($conn, $sql) {
    $result = @$conn->query($sql);
    if ($result && $row = $result->fetch_row()) {
        return (int) $row[0];
    }
    return 0;
}

// Helper function to return a Tailwind class based on order status for coloring
function get_order_status_class($status) {
    switch (strtolower($status)) {
        case 'pending':
            return 'bg-red-500 text-white';
        case 'processed':
            return 'bg-blue-500 text-white';
        case 'out for delivery':
            return 'bg-yellow-500 text-gray-800';
        case 'delivered':
            return 'bg-green-500 text-white';
        case 'cancelled':
            return 'bg-gray-500 text-white';
        default:
            return 'bg-gray-300 text-gray-700';
    }
}


// !!! IMPORTANT: This file must establish a mysqli connection as $conn
if (file_exists('db1.php')) {
    require_once 'db1.php'; 
    // NOTE: db1.php already opens $conn (with host/user/pass/db/port).
    // We do NOT reconnect here - reusing that single connection.

    if (isset($conn) && is_object($conn) && property_exists($conn, 'connect_error') && !$conn->connect_error) {

        $db_error = ''; 

        // --- A. HANDLE STATUS UPDATE REQUEST (POST request from JavaScript) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
            if ($is_admin) {
                $orderId = $_POST['order_id'] ?? null;
                $newStatus = $_POST['new_status'] ?? null;
                
                // Sanitize and validate inputs
                if ($orderId && in_array($newStatus, $possible_statuses)) {
                    $orderId = (int) $conn->real_escape_string($orderId);
                    $newStatus = $conn->real_escape_string($newStatus);

                    $update_query = "UPDATE orders SET order_status = '{$newStatus}' WHERE order_id = {$orderId}";
                    
                    if ($conn->query($update_query)) {
                        // Success message for the AJAX response (not displayed on page reload)
                        echo json_encode(['success' => true, 'message' => "Order #{$orderId} status updated to '{$newStatus}'."]);
                    } else {
                        echo json_encode(['success' => false, 'message' => "Database error: " . $conn->error]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid order ID or status provided.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Access denied. You must be an Admin or Superadmin to update status.']);
            }
            // End script execution after sending JSON response to AJAX call
            exit; 
        }

        // --- B. FETCH SUMMARY COUNTS (these are totals across ALL orders, not just this page) ---
        $total_orders = get_db_count($conn, "SELECT COUNT(order_id) FROM orders");
        $pending_orders = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Pending'");
        $processed_orders = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Processed'");
        $out_for_delivery_orders = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Out for Delivery'");
        $delivered_orders = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Delivered'");
        $cancelled_orders = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Cancelled'");

        // --- B2. PAGINATION MATH (based on the filtered count, not the grand total) ---
        if ($status_filter !== '') {
            $totalOrdersForPagination = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = '" . $conn->real_escape_string($status_filter) . "'");
        } else {
            $totalOrdersForPagination = $total_orders;
        }
        $totalPages = max(1, (int)ceil($totalOrdersForPagination / $perPage));
        $page = min($page, $totalPages); // clamp to last page if out of range
        $offset = ($page - 1) * $perPage;


        // --- C. FETCH DETAILED ORDER LIST WITH USER NAMES (only this page's 10 rows) ---
        $statusWhereClause = '';
        if ($status_filter !== '') {
            $statusWhereClause = "WHERE o.order_status = '" . $conn->real_escape_string($status_filter) . "'";
        }

        $orders_query = "
            SELECT 
                o.order_id, 
                o.total_amount, 
                o.order_status, 
                o.order_date, 
                o.payment_status,
                o.currency,
                o.guest_email,
                o.shipping_address,
                o.billing_address,
                o.user_id,
                u.firstname, 
                u.lastname,
                u.email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            {$statusWhereClause}
            ORDER BY o.order_date DESC
            LIMIT {$perPage} OFFSET {$offset}
        "; 
        
        $orders_result = @$conn->query($orders_query);
        if ($orders_result) {
            while ($row = $orders_result->fetch_assoc()) {
                $all_orders_data[] = $row;
            }
            $orders_result->free();
        } else {
             $db_error = "Error fetching orders list: " . $conn->error;
        }


        // --- D. FETCH ORDER ITEMS ONLY FOR THE ORDERS SHOWN ON THIS PAGE ---
        if (!empty($all_orders_data)) {
            $orderIdsOnPage = array_map(fn($o) => (int)$o['order_id'], $all_orders_data);
            $idsList = implode(',', $orderIdsOnPage);

            $items_query = "
                SELECT 
                    order_id, 
                    product_name, 
                    quantity, 
                    price
                FROM order_items 
                WHERE order_id IN ({$idsList})
                ORDER BY order_id, id
            ";

            // Build a quick lookup of order_id -> currency symbol from what we already fetched,
            // so each item can be shown with the same currency its parent order was placed in.
            $orderCurrencyById = [];
            foreach ($all_orders_data as $o) {
                $orderCurrencyById[$o['order_id']] = get_currency_symbol($o['currency'], $currencySymbols);
            }

            $items_result = @$conn->query($items_query);
            if ($items_result) {
                while ($item = $items_result->fetch_assoc()) {
                    $order_id = $item['order_id'];
                    // No conversion - price is already stored in the order's original currency
                    $item['price'] = round((float)$item['price'], 2);
                    $item['currency_symbol'] = $orderCurrencyById[$order_id] ?? '';
                    if (!isset($all_order_items[$order_id])) {
                        $all_order_items[$order_id] = [];
                    }
                    $all_order_items[$order_id][] = $item;
                }
                $items_result->free();
            } else {
                 $db_error = "Error fetching order items: " . $conn->error;
            }
        }


        // Close connection after fetching all data
        $conn->close();

    } else if (isset($conn) && property_exists($conn, 'connect_error') && $conn->connect_error) {
         $db_error = "Database connection failed via db1.php. Error: " . $conn->connect_error;
    } else {
        $db_error = "Database connection object (\$conn) was not available after including db1.php.";
    }

} else {
    $db_error = "Missing 'db1.php' file. Cannot attempt database connection.";
}

// Build shipping/billing address lookup, keyed by order_id, for the JS drawer to use
$all_order_addresses = [];
foreach ($all_orders_data as $o) {
    $all_order_addresses[$o['order_id']] = [
        'shipping' => $o['shipping_address'] ?? '',
        'billing'  => $o['billing_address'] ?? '',
    ];
}

// Pass order items data, statuses, and currency symbol to JavaScript securely
$order_items_json = json_encode($all_order_items);
$order_addresses_json = json_encode($all_order_addresses);
$possible_statuses_json = json_encode($possible_statuses);

// --- END DATABASE CONNECTION & DATA FETCHING/UPDATING LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management</title>
    <!-- Load Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f6f1ea; /* warm cream */
        }
        .sidebar {
            background: linear-gradient(180deg, #2b1810 0%, #1f120c 100%); /* espresso gradient */
        }
        .sidebar-link:hover {
            background-color: rgba(255,255,255,0.08);
        }
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -6px rgba(59, 36, 24, 0.25);
        }
        /* Style for the order items modal/drawer */
        .order-details-drawer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out, padding 0.3s ease-in-out;
            background-color: #f9fafb;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        .order-details-drawer.active {
            max-height: 500px; /* Adjust based on expected content height */
            padding: 1rem;
        }
        /* Style for the editable status dropdown */
        .status-select {
            border: 1px solid #d1d5db; /* gray-300 */
            border-radius: 0.375rem; /* rounded-md */
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem; /* text-sm */
            line-height: 1.25rem;
            background-color: white;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .status-select:focus {
            border-color: #4f46e5; /* indigo-600 */
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.5);
            outline: none;
        }
        /* Pagination */
        .pagination-link {
            padding: 0.5rem 0.9rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            background-color: #fff;
            border: 1px solid #d1d5db;
            transition: background-color 0.15s ease-in-out;
        }
        .pagination-link:hover {
            background-color: #f3f4f6;
        }
        .pagination-link.active {
            background-color: #6b4226;
            color: #fff;
            border-color: #6b4226;
        }
        .pagination-link.disabled {
            opacity: 0.4;
            pointer-events: none;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        coffee: {
                            50:  '#f6f1ea',
                            100: '#eaddc9',
                            200: '#d9c3a1',
                            300: '#c8a165',
                            400: '#a97c50',
                            500: '#8b5e3c',
                            600: '#6b4226',
                            700: '#573620',
                            800: '#3b2418',
                            900: '#2b1810',
                        }
                    }
                }
            }
        }

        // Global variable to store data for JS lookup
        const ORDER_ITEMS_DATA = <?= $order_items_json ?>;
        const ORDER_ADDRESSES_DATA = <?= $order_addresses_json ?>;
        const POSSIBLE_STATUSES = <?= $possible_statuses_json ?>;

        /**
         * Utility function to display non-intrusive messages (replaces alert()).
         * @param {string} message The message to show.
         * @param {boolean} isSuccess Whether the message is success or error.
         */
        function displayMessage(message, isSuccess = true) {
            const container = document.getElementById('message-container');
            if (!container) return;

            const colorClass = isSuccess ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `p-4 mb-4 rounded-lg shadow-md border ${colorClass} transition-opacity duration-300 ease-in-out opacity-100`;
            messageDiv.innerHTML = `<strong class="font-bold">${isSuccess ? 'Success:' : 'Error:'}</strong> <span class="block sm:inline">${message}</span>`;
            
            container.appendChild(messageDiv);

            // Automatically remove the message after 5 seconds
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.addEventListener('transitionend', () => messageDiv.remove());
            }, 5000);
        }

        /**
         * Toggles the visibility of the order items drawer and populates it.
         * @param {number} orderId The ID of the order to display.
         */
        function toggleOrderDetails(orderId) {
            const drawerId = `drawer-${orderId}`;
            const drawer = document.getElementById(drawerId);
            const content = document.getElementById(`content-${orderId}`);
            
            // Find any currently open drawer and close it if it's not the current one
            document.querySelectorAll('.order-details-drawer.active').forEach(openDrawer => {
                if (openDrawer.id !== drawerId) {
                    openDrawer.classList.remove('active');
                }
            });

            // Toggle the current drawer
            if (drawer.classList.contains('active')) {
                drawer.classList.remove('active');
            } else {
                // Populate content before opening
                const items = ORDER_ITEMS_DATA[orderId] || [];
                const addresses = ORDER_ADDRESSES_DATA[orderId] || { shipping: '', billing: '' };
                content.innerHTML = ''; // Clear previous content

                let html = '';

                // Address block
                html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';
                html += `
                    <div>
                        <p class="font-semibold text-gray-700 mb-1 text-sm">📦 Shipping Address</p>
                        <p class="text-sm text-gray-600 whitespace-pre-line">${addresses.shipping ? addresses.shipping : 'Not provided'}</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-700 mb-1 text-sm">🧾 Billing Address</p>
                        <p class="text-sm text-gray-600 whitespace-pre-line">${addresses.billing ? addresses.billing : 'Not provided'}</p>
                    </div>
                `;
                html += '</div>';

                if (items.length === 0) {
                    html += '<p class="text-gray-500 p-2">No items found for this order.</p>';
                } else {
                    html += '<p class="font-semibold text-gray-700 mb-2">Order Items:</p>';
                    html += '<div class="space-y-1 text-sm">';
                    items.forEach(item => {
                        html += `
                            <div class="flex justify-between border-b border-gray-200/50 py-1">
                                <span class="text-gray-600">${item.product_name}</span>
                                <span class="font-medium text-gray-800">
                                    ${item.quantity} x ${item.currency_symbol || ''}${parseFloat(item.price).toFixed(2)}
                                </span>
                            </div>
                        `;
                    });
                    html += '</div>';
                }

                content.innerHTML = html;

                drawer.classList.add('active');
            }
        }

        /**
         * Sends an AJAX request to update the order status in the database.
         * @param {number} orderId The ID of the order to update.
         * @param {string} newStatus The new status value.
         * @param {HTMLElement} selectElement The dropdown element that triggered the change.
         */
        async function updateOrderStatus(orderId, newStatus, selectElement) {
            const originalStatus = selectElement.getAttribute('data-original-status');
            
            // Optimistically update UI
            selectElement.className = `status-select capitalize text-sm ${getStatusClass(newStatus)}`;
            selectElement.disabled = true;

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('order_id', orderId);
            formData.append('new_status', newStatus);

            try {
                const response = await fetch('orders.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    displayMessage(result.message, true);
                    // Update the original status attribute in case the user clicks again
                    selectElement.setAttribute('data-original-status', newStatus);
                } else {
                    displayMessage(result.message, false);
                    // Revert UI change on failure
                    selectElement.value = originalStatus;
                    selectElement.className = `status-select capitalize text-sm ${getStatusClass(originalStatus)}`;
                }
            } catch (error) {
                displayMessage('Network or server error during update.', false);
                // Revert UI change on network failure
                selectElement.value = originalStatus;
                selectElement.className = `status-select capitalize text-sm ${getStatusClass(originalStatus)}`;
            } finally {
                selectElement.disabled = false;
            }
        }

        /**
         * Matches PHP's logic for status coloring in JS.
         */
        function getStatusClass(status) {
            switch (status.toLowerCase()) {
                case 'pending':
                    return 'bg-red-100 text-red-800 border-red-300';
                case 'processed':
                    return 'bg-blue-100 text-blue-800 border-blue-300';
                case 'out for delivery':
                    return 'bg-yellow-100 text-yellow-800 border-yellow-300';
                case 'delivered':
                    return 'bg-green-100 text-green-800 border-green-300';
                case 'cancelled':
                    return 'bg-gray-100 text-gray-800 border-gray-300';
                default:
                    return 'bg-gray-100 text-gray-800 border-gray-300';
            }
        }
    </script>
</head>
<body class="flex min-h-screen">

<!-- Sidebar (Fixed for desktop, responsive layout) -->
<div class="w-64 sidebar text-coffee-100 h-screen fixed top-0 left-0 overflow-y-auto shadow-2xl z-10 transition-all duration-300">
    <div class="p-6 border-b border-white/10">
        <h2 class="text-2xl font-extrabold tracking-tight text-white flex items-center gap-2">☕ ArakuTribe</h2>
        <p class="text-xs text-coffee-200 mt-1">Role: <span class="capitalize font-semibold text-white"><?= htmlspecialchars($user_role) ?></span></p>
    </div>
    
    <ul class="py-4 space-y-1">
        
        <!-- Navigation Links -->
        <li class="sidebar-link px-4 py-2 transition duration-150 rounded-r-full">
            <a href="dashboard.php" class="block font-medium">🏠 Dashboard</a>
        </li>
        <li class="sidebar-link px-4 py-2 bg-white/10 transition duration-150 rounded-r-full">
            <a href="orders.php" class="block font-medium text-white">📦 Orders</a>
        </li>
        
        <!-- Role-based Access Links -->
        <?php if ($is_admin): ?>
            <li class="sidebar-link px-4 py-2 transition duration-150 rounded-r-full">
                <a href="admins.php" class="block font-medium">👥 Admins</a>
            </li>
            <li class="sidebar-link px-4 py-2 transition duration-150 rounded-r-full">
                <a href="products.php" class="block font-medium">🛒 Products</a>
            </li>
        <?php else: ?>
            <li class="px-4 py-2">
                <span class="block text-coffee-300 text-sm cursor-not-allowed">👥 Admins (Login required)</span>
            </li>
            <li class="px-4 py-2">
                <span class="block text-coffee-300 text-sm cursor-not-allowed">🛒 Products (Login required)</span>
            </li>
        <?php endif; ?>
<li class="sidebar-link px-4 py-2 transition duration-150 rounded-r-full">
            <a href="users.php" class="block font-medium">🧑‍💻 Customers</a>
        </li>
        <!--<li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="categories.php" class="block font-medium">🏷 Categories</a>
        </li>
        
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="transactions.php" class="block font-medium">💰 Transactions</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="coupons.php" class="block font-medium">🎁 Coupons / Discounts</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="reviews.php" class="block font-medium">⭐ Reviews</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="messages.php" class="block font-medium">📧 Messages / Contact</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="content.php" class="block font-medium">📝 Content Management</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="settings.php" class="block font-medium">⚙ Settings</a>
        </li>

        <!-- Logout Link -->
        <li class="px-4 py-2 mt-6 border-t border-white/10 hover:bg-red-900/40 transition duration-150 rounded-r-full">
            <a href="logout_admin.php" class="block font-semibold text-red-300 hover:text-white">🚪 Logout</a>
        </li>
    </ul>
</div>

<!-- Content Area -->
<div class="flex-1 ml-64 p-8">
    <div class="bg-white p-8 rounded-xl shadow-lg">
        <h1 class="text-3xl font-bold text-coffee-900 mb-2">☕ Orders Management</h1>
        <p class="text-gray-500 mb-1">View and manage all customer orders, statuses, and items. <span class="font-semibold text-coffee-600"><?= $is_admin ? 'Status is editable.' : 'Status is read-only.' ?></span></p>
        <p class="text-gray-400 text-sm mb-4">Each order is shown in the currency the customer paid in.</p>
        
        <!-- Floating Message Container for status updates -->
        <div id="message-container" class="fixed top-2 right-2 z-50 w-full max-w-sm"></div>

        <?php if ($db_error): ?>
            <!-- Error message if DB connection failed -->
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Database Error:</strong>
                <span class="block sm:inline"><?= htmlspecialchars($db_error) ?></span>
            </div>
        <?php endif; ?>
        
        
        <!-- ORDER STATUS SUMMARY CARDS -->
        <div class="flex items-center justify-between mb-4 border-b pb-2">
            <h2 class="text-xl font-semibold text-coffee-800">Orders Status Breakdown</h2>
            <?php if ($status_filter !== ''): ?>
                <a href="orders.php" class="text-sm text-coffee-600 hover:underline font-medium">Clear filter &times;</a>
            <?php endif; ?>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-6 mb-10">

            <!-- Total Orders Card (clears the filter) -->
            <a href="orders.php" class="stat-card block bg-coffee-50 p-6 rounded-xl shadow-md border-l-4 <?= $status_filter === '' ? 'border-coffee-800 ring-2 ring-coffee-400' : 'border-coffee-600' ?>">
                <p class="text-sm font-medium text-coffee-700">Total Orders</p>
                <p class="text-3xl font-bold text-coffee-900 mt-1"><?= number_format($total_orders) ?></p>
            </a>
            
            <!-- Pending Card -->
            <a href="?status=Pending" class="stat-card block bg-red-50 p-6 rounded-xl shadow-md border-l-4 <?= $status_filter === 'Pending' ? 'border-red-800 ring-2 ring-red-400' : 'border-red-600' ?>">
                <p class="text-sm font-medium text-red-600">Pending</p>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($pending_orders) ?></p>
            </a>
            
            <!-- Processed Card (Processing) -->
            <a href="?status=Processed" class="stat-card block bg-blue-50 p-6 rounded-xl shadow-md border-l-4 <?= $status_filter === 'Processed' ? 'border-blue-800 ring-2 ring-blue-400' : 'border-blue-600' ?>">
                <p class="text-sm font-medium text-blue-600">Processing</p>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($processed_orders) ?></p>
            </a>

            <!-- Out for Delivery Card -->
            <a href="?status=Out+for+Delivery" class="stat-card block bg-yellow-50 p-6 rounded-xl shadow-md border-l-4 <?= $status_filter === 'Out for Delivery' ? 'border-yellow-800 ring-2 ring-yellow-400' : 'border-yellow-600' ?>">
                <p class="text-sm font-medium text-yellow-600">Out for Delivery</p>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($out_for_delivery_orders) ?></p>
            </a>
            
            <!-- Delivered Card -->
            <a href="?status=Delivered" class="stat-card block bg-green-50 p-6 rounded-xl shadow-md border-l-4 <?= $status_filter === 'Delivered' ? 'border-green-800 ring-2 ring-green-400' : 'border-green-600' ?>">
                <p class="text-sm font-medium text-green-600">Delivered</p>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($delivered_orders) ?></p>
            </a>

            <!-- Cancelled Card -->
            <a href="?status=Cancelled" class="stat-card block bg-gray-100 p-6 rounded-xl shadow-md border-l-4 <?= $status_filter === 'Cancelled' ? 'border-gray-700 ring-2 ring-gray-400' : 'border-gray-500' ?>">
                <p class="text-sm font-medium text-gray-600">Cancelled</p>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($cancelled_orders) ?></p>
            </a>
        </div>

        
        <!-- DETAILED ORDERS TABLE -->
        <div class="flex items-center justify-between mb-4 border-b pb-2">
            <h2 class="text-xl font-semibold text-coffee-800">
                <?= $status_filter !== '' ? htmlspecialchars($status_filter) . ' Orders' : 'Recent Orders List' ?>
            </h2>
            <p class="text-sm text-gray-500">
                <?php if ($totalOrdersForPagination > 0): ?>
                    Showing <?= ($offset ?? 0) + 1 ?>–<?= min(($offset ?? 0) + $perPage, $totalOrdersForPagination) ?> of <?= $totalOrdersForPagination ?> orders
                <?php else: ?>
                    No orders to show
                <?php endif; ?>
            </p>
        </div>
        
        <?php if (empty($all_orders_data)): ?>
            <div class="bg-gray-50 p-6 rounded-lg text-center text-gray-500">
                No orders found in the database.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-lg shadow-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($all_orders_data as $order): ?>
                            <?php $orderSymbol = get_currency_symbol($order['currency'], $currencySymbols); ?>
                            <!-- Main Order Row -->
                            <tr class="hover:bg-gray-50 cursor-pointer transition duration-150" 
                                onclick="toggleOrderDetails(<?= htmlspecialchars($order['order_id']) ?>)">
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= htmlspecialchars($order['order_id']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php if (!empty($order['user_id']) && !empty($order['email'])): ?>
                                        <p class="font-semibold"><?= htmlspecialchars($order['firstname']) . ' ' . htmlspecialchars($order['lastname']) ?></p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($order['email']) ?></p>
                                    <?php else: ?>
                                        <p class="font-semibold text-gray-700">
                                            Guest
                                            <span class="ml-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] align-middle">no account</span>
                                        </p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($order['guest_email'] ?? 'No email on file') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($order['order_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-800">
                                    <?= htmlspecialchars($orderSymbol) ?><?= number_format((float)$order['total_amount'], 2) ?>
                                    <span class="text-xs text-gray-400 font-normal block"><?= htmlspecialchars($order['currency']) ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($is_admin): ?>
                                        <!-- Editable Status Dropdown for Admins/Superadmins -->
                                        <select 
                                            class="status-select capitalize text-sm <?= get_order_status_class($order['order_status']) ?>"
                                            data-original-status="<?= htmlspecialchars($order['order_status']) ?>"
                                            onchange="updateOrderStatus(<?= htmlspecialchars($order['order_id']) ?>, this.value, this)"
                                            >
                                            <?php foreach ($possible_statuses as $status): ?>
                                                <option 
                                                    value="<?= htmlspecialchars($status) ?>"
                                                    <?= (strtolower($status) == strtolower($order['order_status'])) ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <!-- Read-only Status Badge for others -->
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full capitalize <?= get_order_status_class($order['order_status']) ?>">
                                            <?= htmlspecialchars($order['order_status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="capitalize <?= (strtolower($order['payment_status']) === 'paid' ? 'text-green-600 font-medium' : 'text-red-600') ?>">
                                        <?= htmlspecialchars($order['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button class="text-indigo-600 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        View Items
                                    </button>
                                </td>
                            </tr>
                            <!-- Order Items Drawer Row -->
                            <tr class="w-full">
                                <td colspan="7" class="p-0 border-none">
                                    <div id="drawer-<?= htmlspecialchars($order['order_id']) ?>" class="order-details-drawer w-full">
                                        <div id="content-<?= htmlspecialchars($order['order_id']) ?>" class="p-2 border border-dashed border-gray-300 rounded-lg bg-white/70">
                                            <!-- Items will be injected here by JavaScript -->
                                            Loading items...
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION CONTROLS -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-center gap-2 mt-6 flex-wrap">
                <a href="?page=<?= max(1, $page - 1) ?><?= $status_filter !== '' ? '&status=' . urlencode($status_filter) : '' ?>" class="pagination-link <?= $page <= 1 ? 'disabled' : '' ?>">&laquo; Prev</a>

                <?php
                $maxVisiblePages = 5;
                $startPage = max(1, $page - floor($maxVisiblePages / 2));
                $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);
                if ($endPage - $startPage + 1 < $maxVisiblePages) {
                    $startPage = max(1, $endPage - $maxVisiblePages + 1);
                }
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <a href="?page=<?= $p ?><?= $status_filter !== '' ? '&status=' . urlencode($status_filter) : '' ?>" class="pagination-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>

                <a href="?page=<?= min($totalPages, $page + 1) ?><?= $status_filter !== '' ? '&status=' . urlencode($status_filter) : '' ?>" class="pagination-link <?= $page >= $totalPages ? 'disabled' : '' ?>">Next &raquo;</a>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

</body>
</html>