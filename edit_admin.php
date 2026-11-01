<?php
session_start();
include 'db1.php';

ini_set('display_errors', 1);
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

$current_admin_id   = $_SESSION['id'];
$current_admin_role = strtolower($_SESSION['role']);
$user_role          = $current_admin_role;

$is_superadmin          = $current_admin_role === 'superadmin';
$is_admin               = $current_admin_role === 'admin';
$is_admin_or_superadmin = $is_admin || $is_superadmin;

// Get admin ID from URL
$admin_id = $_GET['id'] ?? '';
if (empty($admin_id)) {
    header("Location: admins.php");
    exit;
}

// Access control: only superadmin or the admin themselves can edit
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

// Messages
$success_msg = '';
$error_msg   = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (empty($firstname) || empty($lastname)) {
        $error_msg = "First name and last name are required.";
    } else {
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET firstname=?, lastname=?, password=? WHERE id=?");
            $stmt->bind_param("ssss", $firstname, $lastname, $passwordHash, $admin_id);
        } else {
            $stmt = $conn->prepare("UPDATE admins SET firstname=?, lastname=? WHERE id=?");
            $stmt->bind_param("sss", $firstname, $lastname, $admin_id);
        }

        if ($stmt->execute()) {
            $success_msg = "Admin updated successfully!";
            // Refresh admin data
            $admin['firstname'] = $firstname;
            $admin['lastname']  = $lastname;
        } else {
            $error_msg = "Error updating admin: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin – <?= htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']) ?></title>
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
            <a href="view_admin.php?id=<?= urlencode($admin['id']) ?>" class="hover:text-indigo-600 transition">View Admin</a>
            <span>/</span>
            <span class="text-gray-600 font-medium">Edit</span>
        </div>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Edit Admin</h1>
                <p class="text-gray-500 mt-1">Updating profile for <span class="font-semibold text-indigo-600"><?= htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']) ?></span></p>
            </div>
            <a href="view_admin.php?id=<?= urlencode($admin['id']) ?>"
               class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg shadow transition duration-150 flex items-center gap-2">
                 View Profile
            </a>
        </div>

        <!-- Floating Message Container -->
        <div id="message-container" class="mb-4"></div>

        <?php if ($success_msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 bg-green-50 border border-green-300 text-green-800 rounded-xl shadow-sm">
                <span class="text-xl">✅</span>
                <span class="font-medium"><?= htmlspecialchars($success_msg) ?></span>
                <a href="admins.php" class="ml-auto text-sm underline hover:text-green-600">Back to Admins →</a>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 bg-red-50 border border-red-300 text-red-800 rounded-xl shadow-sm">
                <span class="text-xl">❌</span>
                <span class="font-medium"><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <div class="max-w-xl">
            <!-- Read-only info banner -->
            <div class="flex items-center gap-4 p-5 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 rounded-xl mb-8">
                <div class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center text-white text-lg font-bold shadow">
                    <?= strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)) ?>
                </div>
                <div>
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($admin['email']) ?> &middot; ID: <?= htmlspecialchars($admin['id']) ?></p>
                </div>
                <?php if (strtolower($admin['role']) === 'superadmin'): ?>
                    <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">⭐ Superadmin</span>
                <?php else: ?>
                    <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><?= htmlspecialchars(ucfirst($admin['role'])) ?></span>
                <?php endif; ?>
            </div>

            <!-- Edit Form -->
            <form method="POST" action="" class="space-y-6">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- First Name -->
                    <div>
                        <label for="firstname" class="block text-sm font-semibold text-gray-700 mb-1">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="firstname"
                            name="firstname"
                            value="<?= htmlspecialchars($admin['firstname']) ?>"
                            required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                            placeholder="Enter first name"
                        >
                    </div>

                    <!-- Last Name -->
                    <div>
                        <label for="lastname" class="block text-sm font-semibold text-gray-700 mb-1">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="lastname"
                            name="lastname"
                            value="<?= htmlspecialchars($admin['lastname']) ?>"
                            required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                            placeholder="Enter last name"
                        >
                    </div>
                </div>

                <!-- Email (read-only) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email Address</label>
                    <input
                        type="email"
                        value="<?= htmlspecialchars($admin['email']) ?>"
                        disabled
                        class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-400 bg-gray-50 cursor-not-allowed"
                    >
                    <p class="text-xs text-gray-400 mt-1">Email cannot be changed from this panel.</p>
                </div>

                <!-- New Password -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">
                        New Password
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                        placeholder="Leave blank to keep current password"
                        autocomplete="new-password"
                    >
                    <p class="text-xs text-gray-400 mt-1">Minimum 8 characters recommended. Leave blank to keep the existing password.</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-4 pt-2">
                    <button
                        type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-md transition duration-150 flex items-center gap-2"
                    >
                        💾 Save Changes
                    </button>
                    <a href="admins.php"
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