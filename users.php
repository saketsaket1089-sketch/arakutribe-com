<?php
// --- START: PHP Setup and Role Check ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$user_role = $_SESSION['role'] ?? 'guest'; 
$is_superadmin          = isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
$is_admin               = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_admin_or_superadmin = $is_admin || $is_superadmin;

if (!$is_admin_or_superadmin) {
    $access_denied_message = "Access Denied: You must be an Admin or Superadmin to view this page.";
}

// --- DATABASE CONNECTION & DATA FETCHING ---
$total_users            = 0;
$active_users           = 0;
$pending_deletion_users = 0;
$deleted_users          = 0;
$total_pages            = 1;
$total_matching         = 0;
$users_data             = [];
$db_error               = '';

// Inputs — sanitized early, before DB connection
$search      = trim($_GET['search'] ?? '');
$limit_input = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit       = in_array($limit_input, [10, 25, 50, 100]) ? $limit_input : 10;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $limit;

// Helper function to fetch a single count safely (same helper style as orders.php)
function get_db_count($conn, $sql) {
    $result = @$conn->query($sql);
    if ($result && $row = $result->fetch_row()) {
        return (int) $row[0];
    }
    return 0;
}

// Helper to return a Tailwind class based on account_status, mirroring orders.php's status coloring
function get_account_status_class($status) {
    switch (strtolower((string)$status)) {
        case 'active':
            return 'bg-green-100 text-green-800 border border-green-300';
        case 'pending_deletion':
            return 'bg-yellow-100 text-yellow-800 border border-yellow-300';
        case 'deleted':
            return 'bg-gray-200 text-gray-600 border border-gray-300';
        default:
            return 'bg-gray-100 text-gray-700 border border-gray-300';
    }
}

if (file_exists('db1.php')) {
    require_once 'db1.php';
    // NOTE: db1.php already opens $conn - we reuse it directly, no reconnect.

    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {

        $like_search = '%' . $search . '%';

        // ── A. Summary counts (mirrors orders.php's status breakdown cards) ──
        $total_users            = get_db_count($conn, "SELECT COUNT(*) FROM users");
        $active_users           = get_db_count($conn, "SELECT COUNT(*) FROM users WHERE account_status = 'active'");
        $pending_deletion_users = get_db_count($conn, "SELECT COUNT(*) FROM users WHERE account_status = 'pending_deletion'");
        $deleted_users          = get_db_count($conn, "SELECT COUNT(*) FROM users WHERE account_status = 'deleted'");

        // ── B. Count total matching users (for search + pagination) ──────
        $count_sql = "SELECT COUNT(*) AS total
                      FROM users u
                      WHERE u.firstname    LIKE ?
                         OR u.lastname     LIKE ?
                         OR u.email        LIKE ?
                         OR u.phonenumber  LIKE ?
                         OR CAST(u.user_id AS CHAR) LIKE ?";

        $count_stmt = $conn->prepare($count_sql);
        $total_matching = $total_users;
        if ($count_stmt) {
            $count_stmt->bind_param('sssss',
                $like_search, $like_search, $like_search,
                $like_search, $like_search
            );
            $count_stmt->execute();
            $count_result   = $count_stmt->get_result();
            $total_matching = (int)$count_result->fetch_assoc()['total'];
            $count_stmt->close();
        } else {
            $db_error = "Count query prepare failed: " . $conn->error;
        }

        $total_pages = $total_matching > 0 ? (int)ceil($total_matching / $limit) : 1;
        $page        = min($page, $total_pages);
        $offset      = ($page - 1) * $limit;

        // ── C. Fetch paginated users with order count ──────────────────
        $data_sql = "SELECT
                        u.user_id,
                        u.firstname,
                        u.lastname,
                        u.email,
                        u.phonenumber,
                        u.area,
                        u.landmark,
                        u.zipcode,
                        u.account_status,
                        u.preferred_currency,
                        COUNT(o.order_id) AS total_orders
                     FROM users u
                     LEFT JOIN orders o ON u.user_id = o.user_id
                     WHERE u.firstname    LIKE ?
                        OR u.lastname     LIKE ?
                        OR u.email        LIKE ?
                        OR u.phonenumber  LIKE ?
                        OR CAST(u.user_id AS CHAR) LIKE ?
                     GROUP BY u.user_id, u.firstname, u.lastname, u.email, u.phonenumber,
                              u.area, u.landmark, u.zipcode, u.account_status, u.preferred_currency
                     ORDER BY u.user_id DESC
                     LIMIT ? OFFSET ?";

        $data_stmt = $conn->prepare($data_sql);
        if ($data_stmt) {
            $data_stmt->bind_param('sssssii',
                $like_search, $like_search, $like_search,
                $like_search, $like_search,
                $limit, $offset
            );
            $data_stmt->execute();
            $result = $data_stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $users_data[] = $row;
            }
            $data_stmt->close();
        } else {
            // Fallback: skip order join if orders table missing
            $db_error = "Main query failed, trying fallback: " . $conn->error;

            $fallback_sql = "SELECT user_id, firstname, lastname, email, phonenumber, area, landmark, zipcode, account_status, preferred_currency
                             FROM users
                             WHERE firstname   LIKE ?
                                OR lastname    LIKE ?
                                OR email       LIKE ?
                                OR phonenumber LIKE ?
                                OR CAST(user_id AS CHAR) LIKE ?
                             ORDER BY user_id DESC
                             LIMIT ? OFFSET ?";

            $fb_stmt = $conn->prepare($fallback_sql);
            if ($fb_stmt) {
                $fb_stmt->bind_param('sssssii',
                    $like_search, $like_search, $like_search,
                    $like_search, $like_search,
                    $limit, $offset
                );
                $fb_stmt->execute();
                $fb_result = $fb_stmt->get_result();
                while ($row = $fb_result->fetch_assoc()) {
                    $row['total_orders'] = 0;
                    $users_data[] = $row;
                }
                $fb_stmt->close();
                $db_error = "Warning: Could not join with 'orders' table. Showing users only.";
            } else {
                $db_error = "Fatal Error: Could not fetch user data. " . $conn->error;
            }
        }

        $conn->close();

    } elseif (isset($conn) && $conn instanceof mysqli && $conn->connect_error) {
        $db_error = "Database connection failed: " . $conn->connect_error;
    } else {
        $db_error = "Database connection object (\$conn) was not available after including db1.php.";
    }
} else {
    $db_error = "Missing 'db1.php' file.";
}
// --- END DATABASE LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f6f1ea; }
        .sidebar { background: linear-gradient(180deg, #2b1810 0%, #1f120c 100%); }
        .sidebar-link:hover { background-color: rgba(255,255,255,0.08); }
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -6px rgba(59, 36, 24, 0.25);
        }
        .no-access-btn {
            background-color: #e5e7eb !important;
            color: #6b7280 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            box-shadow: none !important;
        }
        /* Pagination - matches orders.php exactly */
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
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['Inter','sans-serif'] },
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
        } } }

        function displayMessage(message, isSuccess = true) {
            const container = document.getElementById('message-container');
            if (!container) return;
            const color = isSuccess
                ? 'bg-green-100 border-green-400 text-green-700'
                : 'bg-red-100 border-red-400 text-red-700';
            const div = document.createElement('div');
            div.className = `p-4 mb-4 rounded-lg shadow-md border ${color} transition-opacity duration-300 ease-in-out opacity-100`;
            div.innerHTML = `<strong class="font-bold">${isSuccess ? 'Success:' : 'Access Denied:'}</strong>
                             <span class="block sm:inline">${message}</span>`;
            container.innerHTML = '';
            container.appendChild(div);
            setTimeout(() => {
                div.style.opacity = '0';
                div.addEventListener('transitionend', () => div.remove());
            }, 7000);
        }

        function confirmDelete(event, deleteUrl) {
            event.preventDefault();
            const userRole = "<?= htmlspecialchars($user_role) ?>";
            if (userRole !== 'superadmin') {
                displayMessage("🚫 You do not have permission to delete users. Superadmins only.", false);
                return;
            }
            if (confirm("Are you absolutely sure you want to delete this user? This cannot be undone.")) {
                window.location.href = deleteUrl;
            }
        }

        function handleAdminDeleteAttempt(event) {
            event.preventDefault();
            displayMessage("🚫 Superadmin permission required to delete users.", false);
        }

        function submitSearchForm() {
            const form = document.getElementById('search-form');
            form.querySelector('input[name="page"]').value = 1;
            form.submit();
        }

        function clearSearch() {
            const form = document.getElementById('search-form');
            form.querySelector('input[name="search"]').value = '';
            form.querySelector('input[name="page"]').value = 1;
            form.submit();
        }
    </script>
</head>
<body class="flex min-h-screen">

<!-- Sidebar -->
<div class="w-64 sidebar text-coffee-100 h-screen fixed top-0 left-0 overflow-y-auto shadow-2xl z-10">
    <div class="p-6 border-b border-white/10">
        <h2 class="text-2xl font-extrabold tracking-tight text-white flex items-center gap-2">☕ ArakuTribe</h2>
        <p class="text-xs text-coffee-200 mt-1">Role: <span class="capitalize font-semibold text-white"><?= htmlspecialchars($user_role) ?></span></p>
    </div>
    <ul class="py-4 space-y-1">
        <li class="sidebar-link px-4 py-2 transition duration-150 rounded-r-full">
            <a href="dashboard.php" class="block font-medium">🏠 Dashboard</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150 rounded-r-full">
            <a href="orders.php" class="block font-medium">📦 Orders</a>
        </li>
        <?php if ($is_admin_or_superadmin): ?>
            <li class="sidebar-link px-4 py-2 transition duration-150 rounded-r-full">
                <a href="admins.php" class="block font-medium">👥 Admins</a>
            </li>
            <li class="sidebar-link px-4 py-2 transition duration-150 rounded-r-full">
                <a href="products.php" class="block font-medium">🛒 Products</a>
            </li>
        <?php else: ?>
            <li class="px-4 py-2"><span class="block text-coffee-300 text-sm cursor-not-allowed">👥 Admins (Login required)</span></li>
            <li class="px-4 py-2"><span class="block text-coffee-300 text-sm cursor-not-allowed">🛒 Products (Login required)</span></li>
        <?php endif; ?>
        <li class="sidebar-link px-4 py-2 bg-white/10 transition duration-150 rounded-r-full">
            <a href="users.php" class="block font-medium text-white">🧑‍💻 Customers</a>
        </li>
        <li class="px-4 py-2 mt-6 border-t border-white/10 hover:bg-red-900/40 transition duration-150 rounded-r-full">
            <a href="logout_admin.php" class="block font-semibold text-red-300 hover:text-white">🚪 Logout</a>
        </li>
    </ul>
</div>

<!-- Content -->
<div class="flex-1 ml-64 p-8">
    <div class="bg-white p-8 rounded-xl shadow-lg min-h-[90vh]">

        <h1 class="text-3xl font-bold text-coffee-900 mb-2">🧑‍💻 Customer Management</h1>
        <p class="text-gray-500 mb-4">
            View and manage all customer accounts and their order history.
            <span class="font-bold text-red-600">Deletion</span> is restricted to Superadmins.
            Your role: <span class="capitalize font-semibold text-coffee-600"><?= htmlspecialchars($user_role) ?></span>
        </p>

        <div id="message-container" class="fixed top-2 right-2 z-50 w-full max-w-sm"></div>

        <?php if (isset($access_denied_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-8 rounded-xl text-center shadow-md mb-6">
                <strong class="font-bold text-xl block mb-2">🚫 Access Denied!</strong>
                <span class="block text-lg"><?= htmlspecialchars($access_denied_message) ?></span>
                <p class="mt-4 text-sm text-red-600">Please log in with an Admin or Superadmin account.</p>
            </div>
        <?php else: ?>

            <?php if ($db_error): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6">
                    <strong class="font-bold">Database Warning:</strong>
                    <span class="block sm:inline"><?= htmlspecialchars($db_error) ?></span>
                </div>
            <?php endif; ?>

            <!-- CUSTOMER STATUS SUMMARY CARDS (mirrors orders.php's status breakdown) -->
            <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Customer Status Breakdown</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-10">

                <div class="stat-card bg-coffee-50 p-6 rounded-xl shadow-md border-l-4 border-coffee-600">
                    <p class="text-sm font-medium text-coffee-700">Total Customers</p>
                    <p class="text-3xl font-bold text-coffee-900 mt-1"><?= number_format($total_users) ?></p>
                </div>

                <div class="stat-card bg-green-50 p-6 rounded-xl shadow-md border-l-4 border-green-600">
                    <p class="text-sm font-medium text-green-600">Active</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($active_users) ?></p>
                </div>

                <div class="stat-card bg-yellow-50 p-6 rounded-xl shadow-md border-l-4 border-yellow-600">
                    <p class="text-sm font-medium text-yellow-600">Pending Deletion</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($pending_deletion_users) ?></p>
                </div>

                <div class="stat-card bg-gray-50 p-6 rounded-xl shadow-md border-l-4 border-gray-500">
                    <p class="text-sm font-medium text-gray-600">Deleted</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($deleted_users) ?></p>
                </div>
            </div>

            <!-- Search & Controls -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 border-b pb-4">

                <form method="GET" id="search-form" class="flex flex-col sm:flex-row gap-3 w-full md:w-auto items-center">

                    <div class="relative w-full sm:w-72">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            type="text"
                            name="search"
                            id="search-input"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search name, email, phone, ID…"
                            class="p-2 pl-10 pr-8 border border-gray-300 rounded-lg w-full focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            autocomplete="off"
                        >
                        <?php if ($search !== ''): ?>
                            <button type="button" onclick="clearSearch()"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none"
                                    title="Clear search">×</button>
                        <?php endif; ?>
                    </div>

                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-150 w-full sm:w-auto">
                        Search
                    </button>

                    <div class="flex items-center gap-2 text-sm">
                        <label for="limit" class="text-gray-600 whitespace-nowrap">Show:</label>
                        <select name="limit" id="limit" onchange="submitSearchForm()"
                                class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="10"  <?= $limit === 10  ? 'selected' : '' ?>>10</option>
                            <option value="25"  <?= $limit === 25  ? 'selected' : '' ?>>25</option>
                            <option value="50"  <?= $limit === 50  ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>

                    <input type="hidden" name="page" value="<?= $page ?>">
                </form>

                <?php if ($is_admin_or_superadmin): ?>
                    <a href="add_user.php"
                       class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-150 flex items-center gap-1 w-full md:w-auto justify-center">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        Add New Customer
                    </a>
                <?php else: ?>
                    <button disabled class="bg-gray-400 text-gray-700 font-semibold py-2 px-4 rounded-lg shadow cursor-not-allowed flex items-center gap-1 w-full md:w-auto justify-center">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        Add New Customer
                    </button>
                <?php endif; ?>
            </div>

            <!-- Result summary (mirrors orders.php's "Recent Orders List" header row) -->
            <div class="flex items-center justify-between mb-4 border-b pb-2">
                <h2 class="text-xl font-semibold text-gray-700">Customer List</h2>
                <p class="text-sm text-gray-500">
                    <?php if ($total_matching > 0): ?>
                        Showing <?= $offset + 1 ?>–<?= min($offset + $limit, $total_matching) ?> of <?= number_format($total_matching) ?> customers
                        <?php if ($search !== ''): ?>
                            matching "<span class="font-semibold text-indigo-600"><?= htmlspecialchars($search) ?></span>"
                            — <a href="users.php" class="text-red-500 hover:underline">Clear</a>
                        <?php endif; ?>
                    <?php else: ?>
                        No customers to show
                    <?php endif; ?>
                </p>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-lg shadow-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact / Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($users_data)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-gray-500 text-lg">
                                    <?= $search !== ''
                                        ? 'No customers found matching <strong>"' . htmlspecialchars($search) . '"</strong>.'
                                        : 'No customers found.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($users_data as $index => $row):
                            $rowNumber    = $offset + $index + 1;
                            $id           = $row['user_id'] ?? null;
                            $full_name    = trim(htmlspecialchars($row['firstname'] ?? '') . ' ' . htmlspecialchars($row['lastname'] ?? ''));
                            $full_name    = $full_name !== '' ? $full_name : 'N/A';
                            $email        = htmlspecialchars($row['email'] ?? 'N/A');
                            $phone        = htmlspecialchars($row['phonenumber'] ?? 'N/A');
                            $area         = htmlspecialchars($row['area'] ?? '');
                            $landmark     = htmlspecialchars($row['landmark'] ?? '');
                            $zipcode      = htmlspecialchars($row['zipcode'] ?? 'N/A');
                            $accountStatus   = $row['account_status'] ?? 'active';
                            $preferredCurrency = htmlspecialchars($row['preferred_currency'] ?? '—');
                            $total_orders = (int)($row['total_orders'] ?? 0);

                            $locationParts = array_filter([$area, $landmark]);
                            $locationLine  = !empty($locationParts) ? implode(', ', $locationParts) : 'No address on file';
                        ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $rowNumber ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= $id !== null ? htmlspecialchars($id) : 'N/A' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm font-semibold text-gray-900"><?= $full_name ?></p>
                                    <p class="text-xs text-gray-400"><?= $email ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm text-gray-700">📞 <?= $phone ?></p>
                                    <p class="text-xs text-gray-500"><?= $locationLine ?> · Zip: <?= $zipcode ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full capitalize <?= get_account_status_class($accountStatus) ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $accountStatus)) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?= $preferredCurrency !== '' ? $preferredCurrency : '—' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <?php if ($total_orders > 0 && $id !== null): ?>
                                        <a href="view_user.php?id=<?= urlencode($id) ?>#orders"
                                           class="text-indigo-600 hover:text-indigo-800 font-bold hover:underline transition">
                                            <?= $total_orders ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end items-center space-x-2">
                                        <?php if ($id !== null): ?>
                                            <a href="edit_user.php?id=<?= urlencode($id) ?>"
                                               class="bg-yellow-500 hover:bg-yellow-600 text-white p-2 rounded-lg text-xs font-semibold shadow-md transition">Edit</a>
                                            <a href="view_user.php?id=<?= urlencode($id) ?>"
                                               class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg text-xs font-semibold shadow-md transition">View</a>
                                            <?php if ($is_superadmin): ?>
                                                <a href="delete_user.php?id=<?= urlencode($id) ?>"
                                                   class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg text-xs font-semibold shadow-md transition"
                                                   onclick="confirmDelete(event, this.href)">Delete</a>
                                            <?php else: ?>
                                                <button class="p-2 rounded-lg text-xs font-semibold shadow no-access-btn"
                                                        onclick="handleAdminDeleteAttempt(event)">Delete</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION - identical component/classes to orders.php -->
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-center gap-2 mt-6 flex-wrap">
                <a href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>"
                   class="pagination-link <?= $page <= 1 ? 'disabled' : '' ?>">&laquo; Prev</a>

                <?php
                $maxVisiblePages = 5;
                $startPage = max(1, $page - floor($maxVisiblePages / 2));
                $endPage = min($total_pages, $startPage + $maxVisiblePages - 1);
                if ($endPage - $startPage + 1 < $maxVisiblePages) {
                    $startPage = max(1, $endPage - $maxVisiblePages + 1);
                }
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <a href="?page=<?= $p ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>"
                       class="pagination-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>

                <a href="?page=<?= min($total_pages, $page + 1) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>"
                   class="pagination-link <?= $page >= $total_pages ? 'disabled' : '' ?>">Next &raquo;</a>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

</body>
</html>