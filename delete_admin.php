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

// Only superadmins may delete
if (!$is_superadmin) {
    header("Location: admins.php");
    exit;
}

// Validate ID from URL
$admin_id = $_GET['id'] ?? '';
if (empty($admin_id)) {
    header("Location: admins.php");
    exit;
}

// Prevent self-deletion
if ($admin_id === $current_admin_id) {
    $error = "self";
} else {
    // Fetch the admin to be deleted
    $stmt = $conn->prepare("SELECT id, firstname, lastname, email, role FROM admins WHERE id = ?");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        header("Location: admins.php");
        exit;
    }

    $target_admin = $result->fetch_assoc();
    $stmt->close();

    // Prevent deleting another superadmin
    if (strtolower($target_admin['role']) === 'superadmin') {
        $error = "superadmin";
    }
}

// Handle confirmed deletion (POST)
$delete_success = false;
if (!isset($error) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("s", $admin_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $delete_success = true;
    } else {
        $error = "db";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Admin</title>
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
            <a href="admins.php" class="hover:text-indigo-600 transition">👥 Admins</a>
            <span>/</span>
            <span class="text-red-500 font-medium">Delete Admin</span>
        </div>

        <?php if ($delete_success): ?>
            <!-- ✅ Success State -->
            <div class="max-w-lg mx-auto text-center py-16">
                <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Admin Deleted</h2>
                <p class="text-gray-500 mb-8">
                    <span class="font-semibold text-gray-700"><?= htmlspecialchars($target_admin['firstname'] . ' ' . $target_admin['lastname']) ?></span>
                    has been permanently removed from the system.
                </p>
                <a href="admins.php"
                   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow transition duration-150">
                    ← Back to Admin List
                </a>
            </div>

        <?php elseif (isset($error)): ?>
            <!-- ⛔ Blocked State -->
            <div class="max-w-lg mx-auto text-center py-16">
                <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-12.728 12.728M5.636 5.636l12.728 12.728" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-3">Action Not Allowed</h2>

                <?php if ($error === 'self'): ?>
                    <p class="text-gray-500 mb-8">You cannot delete your own account while you are logged in.</p>
                <?php elseif ($error === 'superadmin'): ?>
                    <p class="text-gray-500 mb-8">Superadmin accounts cannot be deleted through this panel.</p>
                <?php else: ?>
                    <p class="text-gray-500 mb-8">A database error occurred. Please try again.</p>
                <?php endif; ?>

                <a href="admins.php"
                   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow transition duration-150">
                    ← Back to Admin List
                </a>
            </div>

        <?php else: ?>
            <!-- ⚠️ Confirmation State -->
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Delete Admin</h1>
            <p class="text-gray-500 mb-8">This action is <span class="font-semibold text-red-600">permanent and cannot be undone.</span></p>

            <div class="max-w-xl">
                <!-- Admin info card -->
                <div class="flex items-center gap-4 p-5 bg-red-50 border border-red-200 rounded-xl mb-8">
                    <div class="w-14 h-14 rounded-full bg-red-500 flex items-center justify-center text-white text-xl font-bold shadow flex-shrink-0">
                        <?= strtoupper(substr($target_admin['firstname'], 0, 1) . substr($target_admin['lastname'], 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($target_admin['firstname'] . ' ' . $target_admin['lastname']) ?></p>
                        <p class="text-sm text-gray-500 truncate"><?= htmlspecialchars($target_admin['email']) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">ID: <?= htmlspecialchars($target_admin['id']) ?></p>
                    </div>
                    <?php if (strtolower($target_admin['role']) === 'superadmin'): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 flex-shrink-0">⭐ Superadmin</span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 flex-shrink-0"><?= htmlspecialchars(ucfirst($target_admin['role'])) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Warning notice -->
                <div class="flex gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-xl mb-8">
                    <span class="text-yellow-500 text-xl flex-shrink-0">⚠️</span>
                    <div class="text-sm text-yellow-800">
                        <p class="font-semibold mb-1">You are about to permanently delete this admin account.</p>
                        <p>All associated data and access permissions will be removed. This cannot be reversed.</p>
                    </div>
                </div>

                <!-- Confirm / Cancel buttons -->
                <form method="POST" action="delete_admin.php?id=<?= urlencode($admin_id) ?>" class="flex items-center gap-4">
                    <button
                        type="submit"
                        name="confirm_delete"
                        value="1"
                        class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-md transition duration-150 flex items-center gap-2"
                    >
                        🗑️ Yes, Delete Admin
                    </button>
                    <a href="admins.php"
                       class="text-gray-500 hover:text-gray-800 font-medium transition duration-150">
                        ← Cancel
                    </a>
                </form>
            </div>

        <?php endif; ?>

    </div>
</div>

</body>
</html>