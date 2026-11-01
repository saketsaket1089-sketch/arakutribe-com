<?php
session_start();
include 'db1.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

if (!$is_admin_or_superadmin) {
    header("Location: products.php");
    exit;
}

$prefixes = [
    'cup'     => ['prefix' => 'ATCUP', 'base' => 50000],
    'mug'     => ['prefix' => 'ATMUG', 'base' => 60000],
    't_shirt' => ['prefix' => 'ATTSH', 'base' => 30000],
    'bag'     => ['prefix' => 'ATBAG', 'base' => 40000],
];

$success_msg          = '';
$error_msg            = '';
$generated_product_id = '';

// Preserve form values on error
$product_name    = '';
$price           = '';
$quantity        = '';
$minimum_quantity = 10;
$category        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_name     = trim($_POST['product_name']    ?? '');
    $price            = floatval($_POST['price']        ?? 0);
    $quantity         = intval($_POST['quantity']       ?? 0);
    $minimum_quantity = intval($_POST['minimum_quantity'] ?? 10);
    $category         = trim($_POST['category']        ?? '');

    // --- Validation ---
    if (empty($product_name)) {
        $error_msg = "Product name is required.";
    } elseif (empty($category) || !isset($prefixes[$category])) {
        $error_msg = "Please select a valid category.";
    } elseif ($price <= 0) {
        $error_msg = "Price must be greater than zero.";
    } elseif ($quantity < 0) {
        $error_msg = "Quantity cannot be negative.";
    } elseif ($minimum_quantity < 0) {
        $error_msg = "Minimum quantity cannot be negative.";
    } else {

        $prefix = $prefixes[$category]['prefix'];
        $base   = $prefixes[$category]['base'];

        // Get last product_id in this category
        $sql  = "SELECT product_id FROM products WHERE category = ? AND product_id LIKE CONCAT(?, '%') ORDER BY product_id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error_msg = "Prepare failed (ID lookup): " . $conn->error;
        } else {
            $stmt->bind_param("ss", $category, $prefix);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $last_id_num = (int)substr($row['product_id'], strlen($prefix));
                $new_number  = $last_id_num + 1;
            } else {
                $new_number = $base + 1;
            }
            $stmt->close();

            $product_id           = $prefix . str_pad($new_number, 5, "0", STR_PAD_LEFT);
            $generated_product_id = $product_id;

            // FIX: type string must have exactly 6 chars for 6 bind values
            // s = product_id (string)
            // s = product_name (string)
            // d = price (double/float)
            // i = quantity (integer)
            // i = minimum_quantity (integer)
            // s = category (string)
            $insert_sql  = "INSERT INTO products (product_id, product_name, price, quantity, minimum_quantity, category) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);

            if (!$insert_stmt) {
                $error_msg = "Prepare failed (insert): " . $conn->error;
            } else {
                $insert_stmt->bind_param("ssdiss",
                    $product_id,
                    $product_name,
                    $price,
                    $quantity,
                    $minimum_quantity,
                    $category
                );

                if ($insert_stmt->execute()) {
                    $success_msg = "Product <strong>" . htmlspecialchars($product_name) . "</strong> added successfully with ID: <strong>" . htmlspecialchars($product_id) . "</strong>";
                    // Clear form values after success
                    $product_name     = '';
                    $price            = '';
                    $quantity         = '';
                    $minimum_quantity = 10;
                    $category         = '';
                } else {
                    $error_msg = "Insert failed: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7f7f7; }
        .sidebar { background-color: #1f2937; }
        .sidebar-link:hover { background-color: #374151; }

        /* Inline validation */
        .field-error-msg { font-size: 12px; color: #dc2626; margin-top: 4px; display: block; font-weight: 500; }
        .input-error     { border-color: #dc2626 !important; background-color: #fff5f5 !important; }
        .input-valid     { border-color: #16a34a !important; }
        .field-hint      { font-size: 11px; color: #9ca3af; margin-top: 3px; display: block; }
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
            <span class="text-gray-600 font-medium">Add New Product</span>
        </div>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Add New Product</h1>
                <p class="text-gray-500 mt-1">Create a new product in the inventory system.</p>
            </div>
            <a href="products.php"
               class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg shadow transition duration-150 flex items-center gap-2">
                🛒 View All Products
            </a>
        </div>

        <!-- Success Message -->
        <?php if ($success_msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 bg-green-50 border border-green-300 text-green-800 rounded-xl shadow-sm">
                <span class="text-xl">✅</span>
                <span class="font-medium"><?= $success_msg ?></span>
                <a href="products.php" class="ml-auto text-sm underline hover:text-green-600 whitespace-nowrap">View Products →</a>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error_msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 bg-red-50 border border-red-300 text-red-800 rounded-xl shadow-sm">
                <span class="text-xl">❌</span>
                <span class="font-medium"><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
                <span class="text-blue-500 text-xl">ℹ️</span>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">Product ID Auto-Generation</p>
                    <p>The system will automatically generate a unique Product ID based on the category:</p>
                    <ul class="list-disc list-inside mt-2 space-y-1">
                        <li><strong>Cup:</strong> ATCUP50001, ATCUP50002…</li>
                        <li><strong>Mug:</strong> ATMUG60001, ATMUG60002…</li>
                        <li><strong>T-Shirt:</strong> ATTSH30001, ATTSH30002…</li>
                        <li><strong>Bag:</strong> ATBAG40001, ATBAG40002…</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="max-w-2xl">
            <form method="POST" action="" class="space-y-6" novalidate id="addProductForm">

                <!-- Product Name -->
                <div>
                    <label for="product_name" class="block text-sm font-semibold text-gray-700 mb-1">
                        Product Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="product_name"
                        name="product_name"
                        value="<?= htmlspecialchars($product_name) ?>"
                        required
                        placeholder="e.g. Araku Coffee Ceramic Mug"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    >
                </div>

                <!-- Category -->
                <div>
                    <label for="category" class="block text-sm font-semibold text-gray-700 mb-1">
                        Category <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="category"
                        name="category"
                        required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    >
                        <option value="" disabled <?= empty($category) ? 'selected' : '' ?>>Select a category…</option>
                        <option value="cup"     <?= $category === 'cup'     ? 'selected' : '' ?>>☕ Cup</option>
                        <option value="mug"     <?= $category === 'mug'     ? 'selected' : '' ?>>🍵 Mug</option>
                        <option value="t_shirt" <?= $category === 't_shirt' ? 'selected' : '' ?>>👕 T-Shirt</option>
                        <option value="bag"     <?= $category === 'bag'     ? 'selected' : '' ?>>🎒 Bag</option>
                    </select>
                </div>

                <!-- Price & Quantity -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="price" class="block text-sm font-semibold text-gray-700 mb-1">
                            Price ($) <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="price"
                            name="price"
                            value="<?= htmlspecialchars($price !== '' ? $price : '') ?>"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="e.g. 299.00"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                        >
                        <span class="field-hint">Must be greater than 0</span>
                    </div>

                    <div>
                        <label for="quantity" class="block text-sm font-semibold text-gray-700 mb-1">
                            Initial Quantity <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="<?= htmlspecialchars($quantity !== '' ? $quantity : '') ?>"
                            min="0"
                            required
                            placeholder="e.g. 100"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                        >
                        <span class="field-hint">0 or more units</span>
                    </div>
                </div>

                <!-- Minimum Quantity -->
                <div>
                    <label for="minimum_quantity" class="block text-sm font-semibold text-gray-700 mb-1">
                        Minimum Quantity (Low Stock Alert)
                    </label>
                    <input
                        type="number"
                        id="minimum_quantity"
                        name="minimum_quantity"
                        value="<?= htmlspecialchars($minimum_quantity) ?>"
                        min="0"
                        placeholder="e.g. 10"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    >
                    <p class="text-xs text-gray-400 mt-1">Products below this quantity will be flagged as low stock. Default: 10</p>
                </div>

                <div class="border-t border-gray-100 pt-2"></div>

                <!-- Submit -->
                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-md transition duration-150 flex items-center gap-2"
                    >
                        ➕ Add Product
                    </button>
                    <a href="products.php" class="text-gray-500 hover:text-gray-800 font-medium transition duration-150">
                        ← Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
// ── Client-side validation ─────────────────────────────────

function showFieldError(input, message) {
    clearFieldError(input);
    input.classList.add('input-error');
    input.classList.remove('input-valid');
    const span = document.createElement('span');
    span.className = 'field-error-msg';
    span.textContent = message;
    const hint = input.parentElement.querySelector('.field-hint');
    if (hint) hint.insertAdjacentElement('beforebegin', span);
    else input.insertAdjacentElement('afterend', span);
}

function clearFieldError(input) {
    input.classList.remove('input-error');
    const existing = input.parentElement.querySelector('.field-error-msg');
    if (existing) existing.remove();
}

function markValid(input) {
    clearFieldError(input);
    input.classList.add('input-valid');
}

// Product Name
const nameInput = document.getElementById('product_name');
nameInput.addEventListener('blur', function () {
    if (this.value.trim() === '') showFieldError(this, 'Product name is required.');
    else markValid(this);
});

// Category
const categorySelect = document.getElementById('category');
categorySelect.addEventListener('change', function () {
    if (this.value === '') showFieldError(this, 'Please select a category.');
    else markValid(this);
});

// Price
const priceInput = document.getElementById('price');
priceInput.addEventListener('blur', function () {
    const val = parseFloat(this.value);
    if (isNaN(val) || val <= 0) showFieldError(this, 'Price must be greater than 0.');
    else markValid(this);
});

// Quantity
const qtyInput = document.getElementById('quantity');
qtyInput.addEventListener('blur', function () {
    const val = parseInt(this.value);
    if (this.value === '' || isNaN(val) || val < 0) showFieldError(this, 'Quantity must be 0 or more.');
    else markValid(this);
});

// Minimum Quantity
const minQtyInput = document.getElementById('minimum_quantity');
minQtyInput.addEventListener('blur', function () {
    const val = parseInt(this.value);
    if (this.value !== '' && (isNaN(val) || val < 0)) showFieldError(this, 'Minimum quantity cannot be negative.');
    else clearFieldError(this);
});

// Submit guard
document.getElementById('addProductForm').addEventListener('submit', function (e) {
    [nameInput, categorySelect, priceInput, qtyInput, minQtyInput]
        .forEach(el => el.dispatchEvent(new Event('blur')));

    // Also trigger change on select
    categorySelect.dispatchEvent(new Event('change'));

    if (this.querySelectorAll('.input-error').length > 0) {
        e.preventDefault();
        const first = this.querySelector('.input-error');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

</body>
</html>