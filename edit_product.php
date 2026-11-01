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

// Only admins and superadmins can edit products
if (!$is_admin_or_superadmin) {
    header("Location: products.php");
    exit;
}

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

$success_msg = '';
$error_msg   = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $min_quantity = intval($_POST['minimum_quantity'] ?? 0);

    if (empty($product_name) || $price <= 0 || $quantity < 0 || $min_quantity < 0) {
        $error_msg = "Please fill in all fields with valid values.";
    } else {
        $update_stmt = $conn->prepare("UPDATE products SET product_name=?, price=?, quantity=?, minimum_quantity=? WHERE product_id=?");
        $update_stmt->bind_param("sdiis", $product_name, $price, $quantity, $min_quantity, $id);

        if ($update_stmt->execute()) {
            $success_msg = "Product <strong>" . htmlspecialchars($product_name) . "</strong> updated successfully!";
            // Refresh product data
            $product['product_name'] = $product_name;
            $product['price'] = $price;
            $product['quantity'] = $quantity;
            $product['minimum_quantity'] = $min_quantity;
        } else {
            $error_msg = "Database error: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product – <?= htmlspecialchars($product['product_name']) ?></title>
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
            <a href="view_product.php?id=<?= urlencode($product['product_id']) ?>" class="hover:text-indigo-600 transition">View Product</a>
            <span>/</span>
            <span class="text-gray-600 font-medium">Edit</span>
        </div>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Edit Product</h1>
                <p class="text-gray-500 mt-1">Updating <span class="font-semibold text-indigo-600"><?= htmlspecialchars($product['product_name']) ?></span></p>
            </div>
            <a href="view_product.php?id=<?= urlencode($product['product_id']) ?>"
               class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg shadow transition duration-150 flex items-center gap-2">
                 View Products
            </a>
        </div>

        <!-- Success Message -->
        <?php if ($success_msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 bg-green-50 border border-green-300 text-green-800 rounded-xl shadow-sm">
                <span class="text-xl">✅</span>
                <span class="font-medium"><?= $success_msg ?></span>
                <a href="products.php" class="ml-auto text-sm underline hover:text-green-600 whitespace-nowrap">Back to Products →</a>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error_msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 bg-red-50 border border-red-300 text-red-800 rounded-xl shadow-sm">
                <span class="text-xl">❌</span>
                <span class="font-medium"><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Product Info Banner -->
        <div class="flex items-center gap-4 p-5 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 rounded-xl mb-8">
            <div class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center text-white text-lg font-bold shadow">
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
                <p class="font-bold text-gray-800"><?= htmlspecialchars($product['product_name']) ?></p>
                <p class="text-sm text-gray-500">
                    ID: <?= htmlspecialchars($product['product_id']) ?> &middot; 
                    Category: <span class="capitalize"><?= htmlspecialchars($product['category']) ?></span>
                </p>
            </div>
        </div>

        <!-- Form -->
        <div class="max-w-2xl">
            <form method="POST" action="" class="space-y-6">

                <!-- Product ID (Read-only) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Product ID</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($product['product_id']) ?>"
                        disabled
                        class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-400 bg-gray-50 cursor-not-allowed"
                    >
                    <p class="text-xs text-gray-400 mt-1">Product ID cannot be changed.</p>
                </div>

                <!-- Product Name -->
                <div>
                    <label for="product_name" class="block text-sm font-semibold text-gray-700 mb-1">
                        Product Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="product_name"
                        name="product_name"
                        value="<?= htmlspecialchars($product['product_name']) ?>"
                        required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    >
                </div>

                <!-- Category (Read-only) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Category</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $product['category']))) ?>"
                        disabled
                        class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-400 bg-gray-50 cursor-not-allowed"
                    >
                    <p class="text-xs text-gray-400 mt-1">Category cannot be changed after creation.</p>
                </div>

                <!-- Price and Quantity Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="price" class="block text-sm font-semibold text-gray-700 mb-1">
                            Price (₹) <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="price"
                            name="price"
                            value="<?= htmlspecialchars($product['price']) ?>"
                            step="0.01"
                            min="0"
                            required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                        >
                    </div>

                    <div>
                        <label for="quantity" class="block text-sm font-semibold text-gray-700 mb-1">
                            Current Quantity <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="<?= htmlspecialchars($product['quantity']) ?>"
                            min="0"
                            required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                        >
                    </div>
                </div>

                <!-- Minimum Quantity -->
                <div>
                    <label for="minimum_quantity" class="block text-sm font-semibold text-gray-700 mb-1">
                        Minimum Quantity (Low Stock Alert) <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        id="minimum_quantity"
                        name="minimum_quantity"
                        value="<?= htmlspecialchars($product['minimum_quantity']) ?>"
                        min="0"
                        required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    >
                    <p class="text-xs text-gray-400 mt-1">Products below this quantity will be flagged as low stock.</p>
                </div>

                <!-- Low Stock Warning -->
                <?php if ($product['quantity'] < $product['minimum_quantity']): ?>
                    <div class="flex items-center gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                        <span class="text-yellow-500 text-xl">⚠️</span>
                        <div class="text-sm text-yellow-800">
                            <p class="font-semibold">Low Stock Alert</p>
                            <p>Current quantity (<?= $product['quantity'] ?>) is below minimum (<?= $product['minimum_quantity'] ?>).</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Divider -->
                <div class="border-t border-gray-100 pt-2"></div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-md transition duration-150 flex items-center gap-2"
                    >
                        💾 Save Changes
                    </button>
                    <a href="products.php"
                       class="text-gray-500 hover:text-gray-800 font-medium transition duration-150">
                        ← Cancel
                    </a>
                </div>

            </form>
        </div>

    </div>
</div>

</body>
</html>