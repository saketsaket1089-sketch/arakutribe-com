<?php
session_start();
include 'db1.php';

// Session expiration
$timeout_duration = 1800;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: admin_login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Check login
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit;
}

$current_admin_role = strtolower($_SESSION['role']);
$current_admin_id   = $_SESSION['id'];
$user_role          = $current_admin_role;

$is_superadmin          = $current_admin_role === 'superadmin';
$is_admin               = $current_admin_role === 'admin';
$is_admin_or_superadmin = $is_admin || $is_superadmin;

// Check if 'id' is present in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$id = $_GET['id'];

// Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: products.php");
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();

// Check for low stock
$is_low_stock = ($product['quantity'] < $product['minimum_quantity']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Product – <?= htmlspecialchars($product['product_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7f7f7; }
        .sidebar { background-color: #1f2937; }
        .sidebar-link:hover { background-color: #374151; }
    </style>
</head>
<body class="flex min-h-screen">

<!-- Sidebar -->
<div class="w-64 sidebar text-white h-screen fixed top-0 left-0 overflow-y-auto shadow-2xl z-10">
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
        <?php if ($is_admin_or_superadmin): ?>
            <li class="sidebar-link px-4 py-2 transition duration-150">
                <a href="admins.php" class="block font-medium">👥 Admins</a>
            </li>
            <li class="sidebar-link px-4 py-2 bg-gray-700/50 transition duration-150">
                <a href="products.php" class="block font-medium">🛒 Products</a>
            </li>
        <?php endif; ?>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="users.php" class="block font-medium">🧑‍💻 Customers</a>
        </li>
        <li class="sidebar-link px-4 py-2 transition duration-150">
            <a href="settings.php" class="block font-medium">⚙ Settings</a>
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
            <a href="products.php" class="hover:text-indigo-600 transition">🛒 Products</a>
            <span>/</span>
            <span class="text-gray-600 font-medium">View Product</span>
        </div>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Product Details</h1>
                <p class="text-gray-500 mt-1">Viewing <span class="font-semibold text-indigo-600"><?= htmlspecialchars($product['product_name']) ?></span></p>
            </div>
            <?php if ($is_admin_or_superadmin): ?>
                <a href="edit_product.php?id=<?= urlencode($product['product_id']) ?>"
                   class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-5 rounded-lg shadow transition duration-150 flex items-center gap-2">
                    ✏️ Edit Product
                </a>
            <?php endif; ?>
        </div>

        <!-- Product Card -->
        <div class="max-w-3xl">
            <!-- Header Banner with Category -->
            <div class="flex items-center gap-5 p-6 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 rounded-xl mb-6">
                <div class="w-16 h-16 rounded-full bg-indigo-600 flex items-center justify-center text-white text-3xl font-bold shadow-md">
                    <?php
                    $category_emoji = [
                        'cup' => '☕',
                        'mug' => '🍵',
                        't_shirt' => '👕',
                        'bag' => '🎒'
                    ];
                    echo $category_emoji[$product['category']] ?? '📦';
                    ?>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($product['product_name']) ?></p>
                    <p class="text-sm text-gray-500 mt-1">
                        Category: <span class="capitalize font-semibold"><?= htmlspecialchars(str_replace('_', ' ', $product['category'])) ?></span>
                    </p>
                </div>
                <?php if ($is_low_stock): ?>
                    <span class="ml-auto inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        ⚠️ Low Stock
                    </span>
                <?php else: ?>
                    <span class="ml-auto inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                        ✅ In Stock
                    </span>
                <?php endif; ?>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Product ID</p>
                    <p class="text-gray-800 font-bold text-lg"><?= htmlspecialchars($product['product_id']) ?></p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Category</p>
                    <p class="text-gray-800 font-medium capitalize"><?= htmlspecialchars(str_replace('_', ' ', $product['category'])) ?></p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Price</p>
                    <p class="text-gray-800 font-bold text-2xl">₹<?= number_format($product['price'], 2) ?></p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Current Quantity</p>
                    <p class="text-gray-800 font-bold text-2xl <?= $is_low_stock ? 'text-red-600' : '' ?>">
                        <?= number_format($product['quantity']) ?>
                        <?= $is_low_stock ? '<span class="text-xs ml-1">(LOW)</span>' : '' ?>
                    </p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Minimum Quantity</p>
                    <p class="text-gray-800 font-medium"><?= number_format($product['minimum_quantity']) ?></p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Stock Status</p>
                    <?php if ($is_low_stock): ?>
                        <p class="text-red-600 font-semibold">⚠️ Low Stock Alert</p>
                    <?php else: ?>
                        <p class="text-green-600 font-semibold">✅ Sufficient Stock</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Warning -->
            <?php if ($is_low_stock): ?>
                <div class="flex items-center gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-xl mb-6">
                    <span class="text-yellow-500 text-xl">⚠️</span>
                    <div class="text-sm text-yellow-800">
                        <p class="font-semibold">Low Stock Alert</p>
                        <p>Current quantity (<?= $product['quantity'] ?>) is below minimum threshold (<?= $product['minimum_quantity'] ?>). Consider restocking soon.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Additional Info -->
            <?php if (isset($product['image']) && !empty($product['image'])): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 mb-6">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Product Image</p>
                    <p class="text-gray-800 font-mono text-sm"><?= htmlspecialchars($product['image']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Back Button -->
            <div class="mt-8 flex items-center gap-4">
                <a href="products.php"
                   class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-semibold transition duration-150">
                    ← Back to Products
                </a>
                
                <?php if ($is_admin_or_superadmin): ?>
                    <a href="edit_product.php?id=<?= urlencode($product['product_id']) ?>"
                       class="inline-flex items-center gap-2 text-yellow-600 hover:text-yellow-800 font-semibold transition duration-150">
                        ✏️ Edit Product
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>