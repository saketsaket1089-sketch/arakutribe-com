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

$current_admin_id   = $_SESSION['id'];
$current_admin_role = strtolower($_SESSION['role']);
$user_role          = $current_admin_role;

$is_superadmin          = $current_admin_role === 'superadmin';
$is_admin               = $current_admin_role === 'admin';
$is_admin_or_superadmin = $is_admin || $is_superadmin;

if (!isset($_GET['id'])) {
    header("Location: admins.php");
    exit;
}

$admin_id = $_GET['id'];

// Access control: superadmin can view any, others only themselves
if (!$is_superadmin && $admin_id !== $current_admin_id) {
    header("Location: admins.php");
    exit;
}

// Fetch admin data
$stmt = $conn->prepare("SELECT id, firstname, lastname, email, role FROM admins WHERE id = ?");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: admins.php");
    exit;
}
$admin = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Admin – <?= htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']) ?></title>
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
            <li class="sidebar-link px-4 py-2 bg-gray-700/50 transition duration-150">
                <a href="admins.php" class="block font-medium">👥 Admins</a>
            </li>
            <li class="sidebar-link px-4 py-2 transition duration-150">
                <a href="products.php" class="block font-medium">🛒 Products</a>
            </li>
        <?php else: ?>
            <li class="px-4 py-2"><span class="block text-gray-500 text-sm cursor-not-allowed">👥 Admins (Login required)</span></li>
            <li class="px-4 py-2"><span class="block text-gray-500 text-sm cursor-not-allowed">🛒 Products (Login required)</span></li>
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
            <a href="admins.php" class="hover:text-indigo-600 transition">👥 Admins</a>
            <span>/</span>
            <span class="text-gray-600 font-medium">View Admin</span>
        </div>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Admin Profile</h1>
                <p class="text-gray-500 mt-1">Viewing details for <span class="font-semibold text-indigo-600"><?= htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']) ?></span></p>
            </div>
            <?php if ($is_superadmin || $admin_id === $current_admin_id): ?>
                <a href="edit_admin.php?id=<?= urlencode($admin['id']) ?>"
                   class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-5 rounded-lg shadow transition duration-150 flex items-center gap-2">
                    ✏️ Edit Admin
                </a>
            <?php endif; ?>
        </div>

        <!-- Profile Card -->
        <div class="max-w-2xl">
            <!-- Avatar / Role Banner -->
            <div class="flex items-center gap-5 p-6 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 rounded-xl mb-6">
                <div class="w-16 h-16 rounded-full bg-indigo-600 flex items-center justify-center text-white text-2xl font-bold shadow-md">
                    <?= strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)) ?>
                </div>
                <div>
                    <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']) ?></p>
                    <?php if (strtolower($admin['role']) === 'superadmin'): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 mt-1">
                            ⭐ Superadmin
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 mt-1">
                            <?= htmlspecialchars(ucfirst($admin['role'])) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($admin['id'] === $current_admin_id): ?>
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">You</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Admin ID</p>
                    <p class="text-gray-800 font-medium"><?= htmlspecialchars($admin['id']) ?></p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Role</p>
                    <p class="text-gray-800 font-medium capitalize"><?= htmlspecialchars($admin['role']) ?></p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">First Name</p>
                    <p class="text-gray-800 font-medium"><?= htmlspecialchars($admin['firstname']) ?></p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Last Name</p>
                    <p class="text-gray-800 font-medium"><?= htmlspecialchars($admin['lastname']) ?></p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Email Address</p>
                    <p class="text-gray-800 font-medium"><?= htmlspecialchars($admin['email']) ?></p>
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-8">
                <a href="admins.php"
                   class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-semibold transition duration-150">
                    ← Back to Admin List
                </a>
            </div>
        </div>

    </div>
</div>

</body>
</html>