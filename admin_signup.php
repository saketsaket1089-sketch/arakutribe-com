<?php
/* =====================================================================
   ARAKU TRIBE - admin_signup.php  (add an admin)
   Who can use it: a signed-in SUPER ADMIN (role 'superadmin'), or anyone
   only while the admins table is still empty (to create the very first
   admin - make that one 'superadmin' in phpMyAdmin afterwards).
   ===================================================================== */
require_once __DIR__ . '/admin_layout3.php';
include 'db1.php';

$adminCount = 0;
$r = $conn->query("SELECT COUNT(*) FROM admins");
if ($r) $adminCount = (int)$r->fetch_row()[0];
$isSuper  = admin3_is_in() && ($_SESSION['role'] ?? '') === 'superadmin';
$allowed  = $isSuper || $adminCount === 0;
if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));

function generateAdminId($conn) {
    $prefix = "ATOADM";
    $stmt = $conn->prepare("SELECT id FROM admins WHERE id LIKE CONCAT(?, '%') ORDER BY id DESC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $res = $stmt->get_result();
    $number = ($res && $res->num_rows > 0) ? (int)substr($res->fetch_assoc()['id'], strlen($prefix)) + 1 : 6001;
    $stmt->close();
    return $prefix . $number;
}

$error = ''; $done = '';
if ($allowed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $repeat    = $_POST['repeat_password'] ?? '';
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $role      = ($isSuper && ($_POST['role'] ?? '') === 'superadmin') ? 'superadmin' : 'admin';

    if (!hash_equals($_SESSION['csrf_admin'], (string)($_POST['csrf'] ?? ''))) $error = 'Your session expired. Please try again.';
    elseif ($firstname === '' || $lastname === '' || $email === '') $error = 'Please fill in all fields.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))               $error = 'That email address does not look right.';
    elseif ($password !== $repeat)                                    $error = 'The two passwords do not match.';
    elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) $error = 'Password: at least 8 characters, with a capital letter, a number and a symbol.';
    else {
        $st = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $st->bind_param("s", $email); $st->execute(); $st->store_result();
        $exists = $st->num_rows > 0; $st->close();
        if ($exists) $error = 'An admin with this email already exists.';
        elseif (!($id = generateAdminId($conn))) $error = 'Could not create an admin ID. Please try again.';
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $conn->prepare("INSERT INTO admins (id, email, password, firstname, lastname, role) VALUES (?, ?, ?, ?, ?, ?)");
            $st->bind_param("ssssss", $id, $email, $hash, $firstname, $lastname, $role);
            if ($st->execute()) { $done = "Admin $firstname $lastname ($email) was added as $role."; $_POST = []; }
            else $error = 'Could not save the admin. Please try again.';
            $st->close();
        }
    }
}
$conn->close();

admin3_auth_open('Add an admin - Araku Tribe');
?>
<h1>Add an admin</h1>
<?php if (!$allowed): ?>
  <p class="sub">Only a super admin can add new admins.</p>
  <p class="a3-msg err">Please sign in as a super admin first.</p>
  <a class="btn btn-primary btn-lg" href="adminlogin.php">Admin sign in</a>
<?php else: ?>
  <p class="sub"><?= $adminCount === 0 ? 'No admins yet: this creates the first one.' : 'New admins sign in with this email and password.' ?></p>
  <?php if ($done): ?><p class="a3-msg ok" role="status"><?= admin3_e($done) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="a3-msg err" role="alert"><?= admin3_e($error) ?></p><?php endif; ?>
  <form method="POST" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= admin3_e($_SESSION['csrf_admin']) ?>">
    <div class="a3-field"><label for="fn">First name</label><input id="fn" name="firstname" required value="<?= admin3_e($_POST['firstname'] ?? '') ?>"></div>
    <div class="a3-field"><label for="ln">Last name</label><input id="ln" name="lastname" required value="<?= admin3_e($_POST['lastname'] ?? '') ?>"></div>
    <div class="a3-field"><label for="em">Email</label><input id="em" type="email" name="email" required value="<?= admin3_e($_POST['email'] ?? '') ?>"></div>
    <div class="a3-field"><label for="pw">Password</label><input id="pw" type="password" name="password" required autocomplete="new-password"></div>
    <div class="a3-field"><label for="pw2">Repeat password</label><input id="pw2" type="password" name="repeat_password" required autocomplete="new-password"></div>
    <?php if ($isSuper): ?>
    <div class="a3-field"><label for="rl">Role</label>
      <select id="rl" name="role" style="width:100%;height:48px;padding:0 12px;border:2px solid var(--line);border-radius:12px;font:600 15px var(--sans)"><option value="admin">Admin</option><option value="superadmin">Super admin</option></select></div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary btn-lg">Add admin</button>
  </form>
  <p class="a3-links"><?= $isSuper ? '<a href="dashboard.php">Back to the dashboard</a>' : '<a href="adminlogin.php">Admin sign in</a>' ?></p>
<?php endif; ?>
<?php admin3_auth_close(); ?>