<?php
/* =====================================================================
   ARAKU TRIBE - dashboard.php  (admin home)
   Only signed-in admins who verified their emailed code can open it
   (admin3_require). The sidebar comes from admin_layout3.php, so it is
   the same on every admin page, including Site settings.
   ===================================================================== */
require_once __DIR__ . '/admin_layout3.php';
admin3_require();

$is_logged_in = true;                         // admin3_require() already checked the role
$user_role = $_SESSION['role'] ?? 'admin';

// --- DATABASE CONNECTION & DATA FETCHING LOGIC ---
$total_orders            = 0;
$total_users             = 0;
$total_admins            = 0;
$pending_orders          = 0;
$processed_orders        = 0;
$out_for_delivery_orders = 0;
$delivered_orders        = 0;
$cancelled_orders        = 0;
$revenue_by_currency     = [];   // [ ['currency' => 'INR', 'total' => 12345.00, 'count' => 40], ... ]
$recent_orders           = [];
$recent_users            = [];
$db_error                = '';
$admins_table_available  = true;

function get_db_count($conn, $sql) {
    $result = @$conn->query($sql);
    if ($result && $row = $result->fetch_row()) {
        return (int) $row[0];
    }
    return 0;
}

function get_status_badge_class($status) {
    switch (strtolower($status)) {
        case 'pending':           return 'bg-red-100 text-red-700 border border-red-200';
        case 'processed':         return 'bg-blue-100 text-blue-700 border border-blue-200';
        case 'out for delivery':  return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
        case 'delivered':         return 'bg-green-100 text-green-700 border border-green-200';
        case 'cancelled':         return 'bg-gray-200 text-gray-600 border border-gray-300';
        default:                  return 'bg-gray-100 text-gray-700 border border-gray-200';
    }
}

if (file_exists('db1.php')) {
    require_once 'db1.php';

    if (isset($conn) && is_object($conn) && property_exists($conn, 'connect_error') && !$conn->connect_error) {

        $db_error = '';

        // --- Core counts ---
        $total_orders = get_db_count($conn, "SELECT COUNT(order_id) FROM orders");
        $total_users  = get_db_count($conn, "SELECT COUNT(user_id) FROM users");

        // Admins table may or may not exist depending on setup - fail gracefully
        $admins_check = @$conn->query("SHOW TABLES LIKE 'admins'");
        if ($admins_check && $admins_check->num_rows > 0) {
            $total_admins = get_db_count($conn, "SELECT COUNT(*) FROM admins");
        } else {
            $admins_table_available = false;
        }

        // --- Order status breakdown ---
        $pending_orders          = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Pending'");
        $processed_orders        = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Processed'");
        $out_for_delivery_orders = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Out for Delivery'");
        $delivered_orders        = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Delivered'");
        $cancelled_orders        = get_db_count($conn, "SELECT COUNT(order_id) FROM orders WHERE order_status = 'Cancelled'");

        // --- Revenue grouped by currency (never sum different currencies together) ---
        $rev_result = @$conn->query("
            SELECT currency, SUM(total_amount) AS total, COUNT(*) AS cnt
            FROM orders
            GROUP BY currency
            ORDER BY total DESC
        ");
        if ($rev_result) {
            while ($row = $rev_result->fetch_assoc()) {
                $revenue_by_currency[] = $row;
            }
        }

        // --- Recent orders (latest 6), guest-safe with LEFT JOIN ---
        $recent_orders_result = @$conn->query("
            SELECT o.order_id, o.total_amount, o.currency, o.order_status, o.order_date,
                   o.guest_email, o.user_id, u.firstname, u.lastname
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            ORDER BY o.order_date DESC
            LIMIT 6
        ");
        if ($recent_orders_result) {
            while ($row = $recent_orders_result->fetch_assoc()) {
                $recent_orders[] = $row;
            }
        }

        // --- Recent customers (latest 5) ---
        $recent_users_result = @$conn->query("
            SELECT user_id, firstname, lastname, email, account_status
            FROM users
            ORDER BY user_id DESC
            LIMIT 5
        ");
        if ($recent_users_result) {
            while ($row = $recent_users_result->fetch_assoc()) {
                $recent_users[] = $row;
            }
        }

        $conn->close();

    } else if (isset($conn) && property_exists($conn, 'connect_error') && $conn->connect_error) {
        $db_error = "Database connection failed. Please check db1.php.";
    } else {
        $db_error = "Database connection object (\$conn) was not available after including db1.php.";
    }
} else {
    $db_error = "Missing 'db1.php' file. Cannot attempt database connection.";
}

// Data for the status breakdown chart (Chart.js)
$chart_labels = ['Pending', 'Processed', 'Out for Delivery', 'Delivered', 'Cancelled'];
$chart_values = [$pending_orders, $processed_orders, $out_for_delivery_orders, $delivered_orders, $cancelled_orders];
$chart_labels_json = json_encode($chart_labels);
$chart_values_json = json_encode($chart_values);
// --- END DATABASE CONNECTION & DATA FETCHING LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Admin Dashboard - ArakuTribe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800;900&family=Young+Serif&display=swap" rel="stylesheet">
    <style><?= admin3_css() ?></style>
    <style>
        body {
            font-family: 'Figtree', 'Inter', sans-serif;
            background-color: #f6f1ea; /* warm cream */
        }
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -6px rgba(59, 36, 24, 0.25);
        }
        .coffee-banner {
            background: linear-gradient(120deg, #6b4226 0%, #3b2418 100%);
        }
        .coffee-ring {
            box-shadow: 0 0 0 3px rgba(139, 94, 60, 0.15);
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background-color: #c8a165; border-radius: 10px; }
        @media (max-width: 900px) { body { display: block !important; } .ml-64 { margin-left: 0 !important; } }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Figtree', 'Inter', 'sans-serif'] },
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
    </script>
</head>
<body class="flex min-h-screen">

<!-- Sidebar (shared: admin_layout3.php) -->
<?php admin3_sidebar('dashboard'); ?>

<!-- Content Area -->
<div class="flex-1 ml-64 p-8">

    <!-- Coffee-themed welcome banner -->
    <div class="coffee-banner rounded-2xl shadow-lg p-8 mb-8 text-white flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-bold flex items-center gap-2">☕ Welcome back, Admin</h1>
            <p class="text-coffee-100 mt-1">Here's what's brewing at ArakuTribe today.</p>
        </div>
        <div class="text-right">
            <p class="text-sm text-coffee-200">Logged in as</p>
            <p class="text-lg font-semibold capitalize"><?= htmlspecialchars($user_role) ?></p>
        </div>
    </div>

    <?php if ($db_error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative mb-6" role="alert">
            <strong class="font-bold">Connection Error:</strong>
            <span class="block sm:inline"><?= htmlspecialchars($db_error) ?></span>
        </div>
    <?php endif; ?>

    <!-- ESSENTIAL METRICS -->
    <h2 class="text-xl font-semibold text-coffee-800 mb-4 border-b border-coffee-200 pb-2">Essential Metrics</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">

        <a href="orders.php" class="stat-card block bg-white p-6 rounded-2xl shadow-md border-l-4 border-coffee-600">
            <p class="text-sm font-medium text-coffee-600">☕ Total Orders</p>
            <p class="text-4xl font-extrabold text-coffee-900 mt-2"><?= number_format($total_orders) ?></p>
            <p class="text-xs text-coffee-400 mt-2">View all orders →</p>
        </a>

        <a href="users.php" class="stat-card block bg-white p-6 rounded-2xl shadow-md border-l-4 border-amber-600">
            <p class="text-sm font-medium text-amber-700">🧑‍💻 Total Customers</p>
            <p class="text-4xl font-extrabold text-coffee-900 mt-2"><?= number_format($total_users) ?></p>
            <p class="text-xs text-coffee-400 mt-2">Manage customers →</p>
        </a>

        <?php if ($admins_table_available): ?>
            <a href="admins.php" class="stat-card block bg-white p-6 rounded-2xl shadow-md border-l-4 border-purple-600">
                <p class="text-sm font-medium text-purple-700">👥 Total Admins</p>
                <p class="text-4xl font-extrabold text-coffee-900 mt-2"><?= number_format($total_admins) ?></p>
                <p class="text-xs text-coffee-400 mt-2">Manage admins →</p>
            </a>
        <?php else: ?>
            <div class="stat-card block bg-white p-6 rounded-2xl shadow-md border-l-4 border-gray-300">
                <p class="text-sm font-medium text-gray-500">👥 Total Admins</p>
                <p class="text-2xl font-semibold text-gray-400 mt-2">Not tracked</p>
                <p class="text-xs text-gray-400 mt-2">No "admins" table found</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">

        <!-- Order status breakdown chart -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-md">
            <h3 class="text-lg font-semibold text-coffee-800 mb-4">Order Status Breakdown</h3>
            <canvas id="statusChart" height="140"></canvas>
        </div>

        <!-- Revenue by currency -->
        <div class="bg-white p-6 rounded-2xl shadow-md">
            <h3 class="text-lg font-semibold text-coffee-800 mb-4">Revenue by Currency</h3>
            <?php if (empty($revenue_by_currency)): ?>
                <p class="text-sm text-gray-400">No order revenue yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($revenue_by_currency as $rev): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-coffee-50 coffee-ring">
                            <div>
                                <p class="text-sm font-semibold text-coffee-800"><?= htmlspecialchars($rev['currency'] ?: 'Unknown') ?></p>
                                <p class="text-xs text-coffee-400"><?= number_format((int)$rev['cnt']) ?> orders</p>
                            </div>
                            <p class="text-lg font-bold text-coffee-700">
                                <?= number_format((float)$rev['total'], 2) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-400 mt-3">Shown separately per currency — amounts are not converted or combined.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">

        <!-- Recent Orders -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="flex items-center justify-between p-6 pb-4 border-b border-coffee-100">
                <h3 class="text-lg font-semibold text-coffee-800">Recent Orders</h3>
                <a href="orders.php" class="text-sm text-coffee-600 hover:text-coffee-800 font-medium">View all →</a>
            </div>
            <?php if (empty($recent_orders)): ?>
                <p class="text-sm text-gray-400 p-6">No orders yet.</p>
            <?php else: ?>
                <div class="divide-y divide-coffee-50">
                    <?php foreach ($recent_orders as $order):
                        $customerLabel = (!empty($order['user_id']) && !empty($order['firstname']))
                            ? htmlspecialchars($order['firstname'] . ' ' . $order['lastname'])
                            : 'Guest (' . htmlspecialchars($order['guest_email'] ?? 'no email') . ')';
                    ?>
                        <div class="flex items-center justify-between px-6 py-3 hover:bg-coffee-50/60 transition">
                            <div>
                                <p class="text-sm font-semibold text-coffee-900">#<?= htmlspecialchars($order['order_id']) ?> · <?= $customerLabel ?></p>
                                <p class="text-xs text-gray-400"><?= date('M d, Y', strtotime($order['order_date'])) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-coffee-800">
                                    <?= number_format((float)$order['total_amount'], 2) ?> <?= htmlspecialchars($order['currency']) ?>
                                </p>
                                <span class="text-xs px-2 py-0.5 rounded-full <?= get_status_badge_class($order['order_status']) ?>">
                                    <?= htmlspecialchars($order['order_status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Customers -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="flex items-center justify-between p-6 pb-4 border-b border-coffee-100">
                <h3 class="text-lg font-semibold text-coffee-800">Recent Customers</h3>
                <a href="users.php" class="text-sm text-coffee-600 hover:text-coffee-800 font-medium">View all →</a>
            </div>
            <?php if (empty($recent_users)): ?>
                <p class="text-sm text-gray-400 p-6">No customers yet.</p>
            <?php else: ?>
                <div class="divide-y divide-coffee-50">
                    <?php foreach ($recent_users as $u):
                        $name = trim(htmlspecialchars($u['firstname'] ?? '') . ' ' . htmlspecialchars($u['lastname'] ?? ''));
                        $name = $name !== '' ? $name : 'N/A';
                        $status = $u['account_status'] ?? 'active';
                    ?>
                        <div class="flex items-center justify-between px-6 py-3 hover:bg-coffee-50/60 transition">
                            <div>
                                <p class="text-sm font-semibold text-coffee-900">#<?= htmlspecialchars($u['user_id']) ?> · <?= $name ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($u['email'] ?? 'N/A') ?></p>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full capitalize <?= get_status_badge_class($status === 'active' ? 'delivered' : ($status === 'pending_deletion' ? 'out for delivery' : 'cancelled')) ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="bg-white rounded-2xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-coffee-800 mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
            <a href="orders.php" class="px-4 py-2 rounded-lg bg-coffee-600 text-white text-sm font-semibold hover:bg-coffee-700 transition">📦 Manage Orders</a>
            <a href="users.php" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 transition">🧑‍💻 Manage Customers</a>
            <a href="admins.php" class="px-4 py-2 rounded-lg bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 transition">👥 Manage Admins</a>
            <a href="products.php" class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition">🛒 Manage Products</a>
            <a href="admin_settings3.php" class="px-4 py-2 rounded-lg bg-orange-600 text-white text-sm font-semibold hover:bg-orange-700 transition">⚙️ Site settings</a>
            <?php if ($user_role === 'superadmin'): ?>
                <a href="admin_signup.php" class="px-4 py-2 rounded-lg bg-coffee-800 text-white text-sm font-semibold hover:bg-coffee-900 transition">➕ Add an admin</a>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    const statusLabels = <?= $chart_labels_json ?>;
    const statusValues = <?= $chart_values_json ?>;

    const ctx = document.getElementById('statusChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Orders',
                    data: statusValues,
                    backgroundColor: ['#dc2626', '#2563eb', '#d97706', '#16a34a', '#6b7280'],
                    borderRadius: 8,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#3b2418',
                        padding: 10,
                        titleFont: { size: 13 },
                        bodyFont: { size: 13 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#8b5e3c' },
                        grid: { color: '#f0e6da' }
                    },
                    x: {
                        ticks: { color: '#8b5e3c' },
                        grid: { display: false }
                    }
                }
            }
        });
    }
</script>

</body>
</html>