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

$current_admin_role     = strtolower($_SESSION['role']);
$user_role              = $current_admin_role;
$is_superadmin          = $current_admin_role === 'superadmin';
$is_admin               = $current_admin_role === 'admin';
$is_admin_or_superadmin = $is_admin || $is_superadmin;

// Only superadmins can add admins
if (!$is_superadmin) {
    header("Location: admins.php");
    exit;
}

$success_msg = '';
$error_msg   = '';

// Preserve form values on error
$firstname = '';
$lastname  = '';
$email     = '';
$role      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $role      = trim($_POST['role']      ?? '');

    $allowed_roles   = ['admin', 'superadmin'];
    $allowed_domains = ['gmail.com', 'yahoo.com', 'yahoo.co.in', 'yahoo.co.uk', 'yahoo.com.au'];

    // --- Server-side validation ---
    if (empty($firstname) || empty($lastname) || empty($email) || empty($password) || empty($role)) {
        $error_msg = "All fields are required.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $firstname)) {
        $error_msg = "First name must contain only letters and spaces.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $lastname)) {
        $error_msg = "Last name must contain only letters and spaces.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } elseif (!in_array(strtolower(substr(strrchr($email, "@"), 1)), $allowed_domains)) {
        $error_msg = "Only @gmail.com and @yahoo.com email addresses are accepted.";
    } elseif (strlen($password) < 8) {
        $error_msg = "Password must be at least 8 characters long.";
    } elseif (!in_array($role, $allowed_roles)) {
        $error_msg = "Invalid role selected.";
    } else {
        // Check duplicate email using prepared statement
        $check_stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error_msg = "This email is already registered. Please use a different email.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Generate next ID matching your format: ATOADM6001, ATOADM6002...
            $prefix    = 'ATOADM';
            $base      = 6000;
            $id_res    = $conn->query("SELECT id FROM admins WHERE id LIKE 'ATOADM%' ORDER BY id DESC LIMIT 1");
            if ($id_res && $row_id = $id_res->fetch_assoc()) {
                $last_num = (int)substr($row_id['id'], strlen($prefix));
                $next_num = $last_num + 1;
            } else {
                $next_num = $base + 1; // First admin: ATOADM6001
            }
            $next_id = $prefix . $next_num;

            $insert_stmt = $conn->prepare("INSERT INTO admins (id, email, password, firstname, lastname, role) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssss", $next_id, $email, $hashed_password, $firstname, $lastname, $role);

            if ($insert_stmt->execute()) {
                $success_msg = "Admin <strong>" . htmlspecialchars($firstname . ' ' . $lastname) . "</strong> added successfully!";
                $firstname = $lastname = $email = $role = '';
            } else {
                $error_msg = "Database error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Admin</title>
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

        /* Password strength bar */
        #strength-bar-wrap { height: 4px; border-radius: 4px; background: #e5e7eb; margin-top: 6px; overflow: hidden; }
        #strength-bar      { height: 100%; width: 0; border-radius: 4px; transition: width 0.3s, background 0.3s; }
        #strength-label    { font-size: 11px; margin-top: 3px; font-weight: 600; }
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
            <span class="text-gray-600 font-medium">Add New Admin</span>
        </div>

        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Add New Admin</h1>
                <p class="text-gray-500 mt-1">Create a new admin account for the panel.</p>
            </div>
            <a href="admins.php"
               class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg shadow transition duration-150 flex items-center gap-2">
                👥 View All Admins
            </a>
        </div>

        <!-- Success Message -->
        <?php if ($success_msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 bg-green-50 border border-green-300 text-green-800 rounded-xl shadow-sm">
                <span class="text-xl">✅</span>
                <span class="font-medium"><?= $success_msg ?></span>
                <a href="admins.php" class="ml-auto text-sm underline hover:text-green-600 whitespace-nowrap">View Admins →</a>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error_msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 bg-red-50 border border-red-300 text-red-800 rounded-xl shadow-sm">
                <span class="text-xl">❌</span>
                <span class="font-medium"><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <div class="max-w-xl">
            <form method="POST" action="" novalidate id="addAdminForm" class="space-y-6">

                <!-- Name Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="firstname" class="block text-sm font-semibold text-gray-700 mb-1">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="firstname" name="firstname"
                               value="<?= htmlspecialchars($firstname) ?>"
                               placeholder="e.g. John"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                               required>
                        <span class="field-hint">Letters and spaces only</span>
                    </div>
                    <div>
                        <label for="lastname" class="block text-sm font-semibold text-gray-700 mb-1">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="lastname" name="lastname"
                               value="<?= htmlspecialchars($lastname) ?>"
                               placeholder="e.g. Smith"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                               required>
                        <span class="field-hint">Letters and spaces only</span>
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($email) ?>"
                           placeholder="e.g. admin@gmail.com"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                           required>
                    <span class="field-hint">Only @gmail.com and @yahoo.com addresses accepted</span>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password"
                               placeholder="Minimum 8 characters"
                               autocomplete="new-password"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 pr-12 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                               required>
                        <button type="button" id="togglePassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition text-base"
                                title="Show/hide password">👁</button>
                    </div>
                    <!-- Strength bar -->
                    <div id="strength-bar-wrap"><div id="strength-bar"></div></div>
                    <span id="strength-label" class="text-gray-400"></span>
                    <span class="field-hint">Min 8 chars. Use uppercase, numbers & symbols for a stronger password.</span>
                </div>

                <!-- Role -->
                <div>
                    <label for="role" class="block text-sm font-semibold text-gray-700 mb-1">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select id="role" name="role" required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <option value="" disabled <?= empty($role) ? 'selected' : '' ?>>Select a role…</option>
                        <option value="admin"      <?= $role === 'admin'      ? 'selected' : '' ?>>Admin</option>
                        <option value="superadmin" <?= $role === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                    <span class="field-hint">Superadmins have full access including delete permissions</span>
                </div>

                <div class="border-t border-gray-100 pt-2"></div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-4">
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-md transition duration-150 flex items-center gap-2">
                        ➕ Add Admin
                    </button>
                    <a href="admins.php" class="text-gray-500 hover:text-gray-800 font-medium transition duration-150">
                        ← Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
// ============================================================
// CLIENT-SIDE FIELD VALIDATIONS — add_admin.php
// ============================================================

const ALLOWED_EMAIL_DOMAINS = ['gmail.com','yahoo.com','yahoo.co.in','yahoo.co.uk','yahoo.com.au'];

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

// Reusable: letters and spaces only
function attachLettersOnly(input, label) {
    input.addEventListener('input', function () {
        const cursor  = this.selectionStart;
        const cleaned = this.value.replace(/[^a-zA-Z\s]/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
            this.setSelectionRange(cursor - 1, cursor - 1);
        }
    });
    input.addEventListener('blur', function () {
        if (this.value.trim() === '') {
            showFieldError(this, label + ' is required.');
        } else if (!/^[a-zA-Z\s]+$/.test(this.value.trim())) {
            showFieldError(this, label + ' must contain only letters and spaces.');
        } else {
            markValid(this);
        }
    });
}

// ── First & Last Name ──────────────────────────────────────
attachLettersOnly(document.getElementById('firstname'), 'First name');
attachLettersOnly(document.getElementById('lastname'),  'Last name');

// ── Email ─────────────────────────────────────────────────
const emailInput = document.getElementById('email');
emailInput.addEventListener('blur', function () {
    const val   = this.value.trim().toLowerCase();
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (val === '') {
        showFieldError(this, 'Email is required.');
    } else if (!regex.test(val)) {
        showFieldError(this, 'Please enter a valid email address.');
    } else {
        const domain = val.split('@')[1];
        if (!ALLOWED_EMAIL_DOMAINS.includes(domain)) {
            showFieldError(this, 'Only @gmail.com and @yahoo.com emails are accepted.');
        } else {
            markValid(this);
        }
    }
});

// ── Password: strength meter + min 8 chars ─────────────────
const passwordInput  = document.getElementById('password');
const strengthBar    = document.getElementById('strength-bar');
const strengthLabel  = document.getElementById('strength-label');
const togglePassword = document.getElementById('togglePassword');

function getStrength(pwd) {
    let score = 0;
    if (pwd.length >= 8)              score++;
    if (pwd.length >= 12)             score++;
    if (/[A-Z]/.test(pwd))            score++;
    if (/[0-9]/.test(pwd))            score++;
    if (/[^a-zA-Z0-9]/.test(pwd))     score++;
    return score;
}

passwordInput.addEventListener('input', function () {
    const val = this.value;
    if (val.length === 0) {
        strengthBar.style.width = '0';
        strengthBar.style.background = '';
        strengthLabel.textContent = '';
        clearFieldError(this);
        this.classList.remove('input-valid', 'input-error');
        return;
    }
    const levels = [
        { label: 'Very Weak',   color: '#ef4444', width: '20%'  },
        { label: 'Weak',        color: '#f97316', width: '40%'  },
        { label: 'Fair',        color: '#eab308', width: '60%'  },
        { label: 'Strong',      color: '#22c55e', width: '80%'  },
        { label: 'Very Strong', color: '#16a34a', width: '100%' },
    ];
    const level = levels[Math.min(getStrength(val) - 1, 4)] || levels[0];
    strengthBar.style.width      = level.width;
    strengthBar.style.background = level.color;
    strengthLabel.textContent    = level.label;
    strengthLabel.style.color    = level.color;

    if (val.length < 8) {
        showFieldError(this, 'Password must be at least 8 characters.');
    } else {
        clearFieldError(this);
        this.classList.add('input-valid');
    }
});

passwordInput.addEventListener('blur', function () {
    if (this.value.length > 0 && this.value.length < 8) {
        showFieldError(this, 'Password must be at least 8 characters.');
    } else if (this.value.length === 0) {
        showFieldError(this, 'Password is required.');
    }
});

// Show / hide toggle
togglePassword.addEventListener('click', function () {
    const isHidden = passwordInput.type === 'password';
    passwordInput.type   = isHidden ? 'text' : 'password';
    this.textContent     = isHidden ? '🙈' : '👁';
});

// ── Role ──────────────────────────────────────────────────
const roleSelect = document.getElementById('role');
roleSelect.addEventListener('change', function () {
    if (this.value === '') showFieldError(this, 'Please select a role.');
    else markValid(this);
});

// ── Submit guard ──────────────────────────────────────────
document.getElementById('addAdminForm').addEventListener('submit', function (e) {
    [document.getElementById('firstname'),
     document.getElementById('lastname'),
     emailInput,
     passwordInput
    ].forEach(el => el.dispatchEvent(new Event('blur')));

    roleSelect.dispatchEvent(new Event('change'));

    // Email domain double-check
    const emailVal = emailInput.value.trim().toLowerCase();
    const domain   = emailVal.includes('@') ? emailVal.split('@')[1] : '';
    const domainOk = ALLOWED_EMAIL_DOMAINS.includes(domain);

    const hasErrors = this.querySelectorAll('.input-error').length > 0;
    if (hasErrors || !domainOk) {
        e.preventDefault();
        const first = this.querySelector('.input-error');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

</body>
</html>