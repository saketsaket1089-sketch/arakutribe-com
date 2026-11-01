<?php
session_start();
include 'db1.php';

// Session expiration (optional)
$timeout_duration = 1800; // 30 mins
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: admin_login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Check login session variables
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit;
}

$current_admin_role = strtolower($_SESSION['role']);
$current_admin_id   = $_SESSION['id'];

$is_superadmin           = $current_admin_role === 'superadmin';
$is_admin                = $current_admin_role === 'admin';
$is_admin_or_superadmin  = $is_admin || $is_superadmin;
$user_role               = $current_admin_role;

// Superadmin fetches all admins; others fetch only themselves
if ($is_superadmin) {
    $sql    = "SELECT * FROM admins ORDER BY CASE WHEN LOWER(role) = 'superadmin' THEN 0 ELSE 1 END, id ASC";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Database error: " . mysqli_error($conn));
    }
} else {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $current_admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        die("Database error: " . $conn->error);
    }
}

// Collect rows into array for counting
$admins_data  = [];
while ($row = $result->fetch_assoc()) {
    $admins_data[] = $row;
}
$total_admins = count($admins_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f6f1ea;
        }
        .sidebar {
            background: linear-gradient(180deg, #2b1810 0%, #1f120c 100%);
        }
        .sidebar-link:hover {
            background-color: rgba(255,255,255,0.08);
        }
        .superadmin-badge {
            color: #15803d;
            font-weight: 700;
        }
        .no-access-btn {
            background-color: #e5e7eb !important;
            color: #6b7280 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            box-shadow: none !important;
        }
        #adminTable_wrapper .dataTables_length,
        #adminTable_wrapper .dataTables_filter,
        #adminTable_wrapper .dataTables_info,
        #adminTable_wrapper .dataTables_paginate {
            padding: 8px 4px;
            font-size: 0.875rem;
            color: #4b5563;
        }
        #adminTable_wrapper .dataTables_filter input {
            border: 1px solid #d9c3a1;
            border-radius: 0.5rem;
            padding: 0.35rem 0.75rem;
            margin-left: 0.5rem;
            outline: none;
        }
        #adminTable_wrapper .dataTables_filter input:focus {
            border-color: #6b4226;
            box-shadow: 0 0 0 1px #6b4226;
        }
        #adminTable_wrapper .dataTables_paginate .paginate_button {
            border-radius: 0.5rem;
            padding: 4px 12px !important;
            margin: 0 2px;
            border: 1px solid #eaddc9 !important;
            background: white !important;
            color: #374151 !important;
        }
        #adminTable_wrapper .dataTables_paginate .paginate_button.current,
        #adminTable_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #6b4226 !important;
            color: white !important;
            border-color: #6b4226 !important;
        }
        #adminTable_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
            background: #f6f1ea !important;
            color: #111827 !important;
        }
        table.dataTable {
            border-collapse: collapse !important;
        }
        table.dataTable thead th {
            background-color: #f9fafb;
            color: #6b7280;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 500;
            padding: 12px 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.dataTable tbody td {
            padding: 14px 24px;
            font-size: 0.875rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        table.dataTable tbody tr:hover {
            background-color: #f6f1ea;
        }
        table.dataTable.no-footer {
            border-bottom: 1px solid #e5e7eb;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
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

        function displayMessage(message, isSuccess = true) {
            const container = document.getElementById('message-container');
            if (!container) return;
            const colorClass = isSuccess
                ? 'bg-green-100 border-green-400 text-green-700'
                : 'bg-red-100 border-red-400 text-red-700';
            const messageDiv = document.createElement('div');
            messageDiv.className = `p-4 mb-4 rounded-lg shadow-md border ${colorClass} transition-opacity duration-300 ease-in-out opacity-100`;
            messageDiv.innerHTML = `<strong class="font-bold">${isSuccess ? 'Success:' : 'Access Denied:'}</strong> <span class="block sm:inline">${message}</span>`;
            container.innerHTML = '';
            container.appendChild(messageDiv);
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.addEventListener('transitionend', () => messageDiv.remove());
            }, 7000);
        }

        function confirmDelete(event, deleteUrl) {
            event.preventDefault();
            const userRole = "<?= htmlspecialchars($user_role) ?>";
            if (userRole !== 'superadmin') {
                displayMessage("🚫 You do not have permission to delete admins. This action is restricted to Superadmins only.", false);
                return;
            }
            if (confirm("Are you absolutely sure you want to delete this admin? This action cannot be undone.")) {
                window.location.href = deleteUrl;
            }
        }
    </script>
</head>
<body class="flex min-h-screen">

<!-- Sidebar -->
<div class="w-64 sidebar text-coffee-100 h-screen fixed top-0 left-0 overflow-y-auto shadow-2xl z-10 transition-all duration-300">
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
            <li class="sidebar-link px-4 py-2 bg-white/10 transition duration-150 rounded-r-full">
                <a href="admins.php" class="block font-medium text-white">👥 Admins</a>
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
        
        <li class="px-4 py-2 mt-6 border-t border-white/10 hover:bg-red-900/40 transition duration-150 rounded-r-full">
            <a href="logout_admin.php" class="block font-semibold text-red-300 hover:text-white">🚪 Logout</a>
        </li>
    </ul>
</div>

<!-- Content Area -->
<div class="flex-1 ml-64 p-8">
    <div class="bg-white p-8 rounded-xl shadow-lg min-h-[90vh]">
        <h1 class="text-3xl font-bold text-coffee-900 mb-2">👥 Admin Management</h1>
        <p class="text-gray-500 mb-4">
            View and manage admin accounts.
            <span class="font-bold text-red-600">Deletion</span> is restricted to <strong>Superadmins</strong>.
            Your role: <span class="capitalize font-semibold text-coffee-600"><?= htmlspecialchars($user_role) ?></span>
        </p>

        <!-- Floating Message Container -->
        <div id="message-container" class="fixed top-2 right-2 z-50 w-full max-w-sm"></div>

        <!-- Actions Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 border-b pb-4">
            <h3 class="text-xl font-semibold text-coffee-800">
                Total Admins: <span class="text-coffee-600"><?= number_format($total_admins) ?></span>
            </h3>

            <!-- Add Admin Button (Superadmin only) -->
            <?php if ($is_superadmin): ?>
                <a class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-150 flex items-center gap-1 w-full md:w-auto justify-center"
                   href="add_admin.php">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Add New Admin
                </a>
            <?php else: ?>
                <button class="bg-gray-400 text-gray-700 font-semibold py-2 px-4 rounded-lg shadow cursor-not-allowed flex items-center gap-1 w-full md:w-auto justify-center"
                        onclick="displayMessage('🚫 Only Superadmins can add new admins.', false)" disabled>
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Add New Admin
                </button>
            <?php endif; ?>
        </div>

        <!-- Admins Table -->
        <div class="overflow-x-auto rounded-xl shadow-lg border border-gray-200">
            <table id="adminTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Password (Hashed)</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($admins_data)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500 text-lg">
                                No admin accounts found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($admins_data as $admin): ?>
                        <tr class="hover:bg-gray-50 transition duration-150">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($admin['id']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-medium">
                                <?= htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']) ?>
                                <?php if ($current_admin_id === $admin['id']): ?>
                                    <span class="ml-1 text-xs text-coffee-500 font-semibold">(You)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($admin['email']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400 font-mono max-w-[180px] truncate" title="<?= htmlspecialchars($admin['password']) ?>">
                                <?= htmlspecialchars(substr($admin['password'], 0, 30)) ?>...
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <?php if (strtolower($admin['role']) === 'superadmin'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        ⭐ Superadmin
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-coffee-100 text-coffee-800">
                                        <?= htmlspecialchars(ucfirst($admin['role'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex justify-center items-center space-x-2">
                                    <a class="bg-yellow-500 hover:bg-yellow-600 text-white p-2 rounded-lg text-xs font-semibold shadow-md transition duration-150"
                                       href="edit_admin.php?id=<?= urlencode($admin['id']) ?>">Edit</a>

                                    <a class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg text-xs font-semibold shadow-md transition duration-150"
                                       href="view_admin.php?id=<?= urlencode($admin['id']) ?>">View</a>

                                    <?php if ($is_superadmin): ?>
                                        <a class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg text-xs font-semibold shadow-md transition duration-150"
                                           href="delete_admin.php?id=<?= urlencode($admin['id']) ?>"
                                           onclick="confirmDelete(event, this.href)">Delete</a>
                                    <?php else: ?>
                                        <button class="p-2 rounded-lg text-xs font-semibold shadow no-access-btn"
                                                onclick="displayMessage('🚫 Superadmin permission required to delete admins.', false); return false;"
                                                disabled>
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function () {
        $('#adminTable').DataTable({
            pageLength: 25,
            language: {
                search: "🔍 Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ admins",
            }
        });
    });
</script>

</body>
</html>