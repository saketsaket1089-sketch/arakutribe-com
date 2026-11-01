<?php
/* =====================================================================
   ARAKU TRIBE - reset_password3.php
   ---------------------------------------------------------------------
   The page the "Reset your password" email opens (?token=...).
   Created by process_forgot_password3.php. It
     - checks the link is real, not expired and not used yet,
     - lets the person type a new password (twice),
     - saves it (hashed with PHP's password_hash, so your sign-in must check it with
       password_verify) in your existing `users` table, found by its real column names,
       and marks the link as used, plus any other unused links for that account.
   Pairs with process_forgot_password3.php (same table: araku_password_resets).
   Uses header3.php / footer3.php like the other "3" pages; its own CSS
   and JavaScript are in this file.
   ===================================================================== */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');         // the address contains the secret token: never pass it on to other sites (fonts etc.)
session_start();

/* SETTINGS - edit these */
$LOGIN_PAGE  = 'coffee_login3.php';      // where "Sign in" goes after a successful reset
$FORGOT_PAGE = 'forgot_password3.php';   // where "request a new link" goes
$MIN_LENGTH  = 8;

include 'db1.php';
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, $user, $pass, $db);
$dbOk = !$conn->connect_error;
if ($dbOk) $conn->set_charset('utf8mb4'); else error_log('[reset-password] database connection failed: ' . $conn->connect_error);

/* The real column names of your users table (nothing is assumed) */
function pickColumn(array $columns, array $candidates): ?string {
    foreach ($candidates as $c) if (isset($columns[$c])) return $columns[$c];
    return null;
}
$userCols = [];
if ($dbOk) { $r = $conn->query("SHOW COLUMNS FROM users"); if ($r) { while ($row = $r->fetch_assoc()) $userCols[strtolower($row['Field'])] = $row['Field']; $r->free(); } }
$idCol = pickColumn($userCols, ['user_id', 'id', 'customer_id', 'uid']);
$pwCol = pickColumn($userCols, ['password', 'user_password', 'password_hash', 'passwd', 'pass']);

/* ---------------------------------------------------------------------
   Look up the token (we only ever store its SHA-256 hash)
   --------------------------------------------------------------------- */
$token   = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$isToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;

function findValidReset(mysqli $conn, string $token, bool $forUpdate = false): ?array {
    $hash = hash('sha256', $token);
    $sql  = "SELECT id, user_id, email FROM araku_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $st = $conn->prepare($sql);
    if (!$st) return null;
    $st->bind_param('s', $hash); $st->execute();
    $res = $st->get_result(); $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return ($row && (string)$row['user_id'] !== '') ? $row : null;
}
function maskEmail(string $e): string {
    [$local, $domain] = array_pad(explode('@', $e, 2), 2, '');
    return substr($local, 0, 1) . str_repeat('*', max(2, min(6, strlen($local) - 1))) . '@' . $domain;
}

$state = 'invalid';        // invalid | form | done
$error = '';
$reset = ($dbOk && $isToken) ? findValidReset($conn, $token) : null;
if ($reset) $state = 'form';

/* ---------------------------------------------------------------------
   Form submitted
   --------------------------------------------------------------------- */
if ($reset && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $new     = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (!hash_equals((string)($_SESSION['reset_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired. Please try again.';
    } elseif (strlen($new) < $MIN_LENGTH) {
        $error = "Your password needs at least $MIN_LENGTH characters.";
    } elseif (strlen($new) > 72) {
        $error = 'Please use 72 characters or fewer.';                       // bcrypt only reads the first 72 bytes
    } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $error = 'Please include at least one letter and one number.';
    } elseif ($new !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $conn->begin_transaction();
        $locked = findValidReset($conn, $token, true);                       // re-check inside the transaction: one use only
        $saved  = false;
        if ($locked) {
            $uid = (string)$locked['user_id'];
            if (!$idCol || !$pwCol) {
                error_log('[reset-password] users table has no recognisable id/password column. Columns found: ' . implode(', ', array_values($userCols)));
                $error = 'We could not update the password automatically. Please contact us.';
            } else {
                $st = $conn->prepare("SELECT `$pwCol` FROM users WHERE `$idCol` = ? LIMIT 1");
                $current = '';
                if ($st) { $st->bind_param('s', $uid); $st->execute(); $st->bind_result($current); $st->fetch(); $st->close(); }
                $current = (string)$current;
                if ($current !== '' && !preg_match('/^\$(2[abxy]|argon2id?)\$/', $current)) {
                    // the stored password is not a password_hash() value (older/plain/md5): saving a new-style one could lock this person out
                    error_log('[reset-password] the password in users.' . $pwCol . ' is not a password_hash() value, so it was left unchanged.');
                    $error = 'We could not update the password automatically. Please contact us.';
                } elseif ($current !== '' && password_verify($new, $current)) {
                    $error = 'That is your current password. Please choose a new one.';
                } else {
                    $newHash = password_hash($new, PASSWORD_DEFAULT);
                    $st = $conn->prepare("UPDATE users SET `$pwCol` = ? WHERE `$idCol` = ? LIMIT 1");
                    if ($st) {
                        $st->bind_param('ss', $newHash, $uid);
                        $saved = $st->execute();
                        $st->close();
                    }
                    if ($saved) {
                        $st = $conn->prepare("UPDATE araku_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
                        if ($st) { $st->bind_param('s', $uid); $saved = $st->execute(); $st->close(); }
                    }
                }
            }
        } else {
            $error = 'This link has just expired or was already used.';
        }
        if ($saved) {
            $conn->commit();
            session_regenerate_id(true);
            unset($_SESSION['reset_csrf']);
            $state = 'done';
        } else {
            $conn->rollback();
            if ($error === '') $error = 'We could not save your new password. Please try again.';
            if (!findValidReset($conn, $token)) $state = 'invalid';
        }
    }
}
if ($state === 'form') {
    if (empty($_SESSION['reset_csrf'])) $_SESSION['reset_csrf'] = bin2hex(random_bytes(32));
}
$maskedEmail = ($state === 'form' && $reset) ? maskEmail((string)$reset['email']) : '';
if ($dbOk) $conn->close();

$PAGE_TITLE       = 'Choose a new password - Araku Tribe';
$PAGE_DESCRIPTION = 'Choose a new password for your Araku Tribe account.';
$ACTIVE_NAV       = '';
include __DIR__ . '/header3.php';
?>
<main class="rp">
  <div class="rp-card">

<?php if ($state === 'done'): ?>
    <div class="rp-icon ok" aria-hidden="true">&#10003;</div>
    <h1>Password changed</h1>
    <p class="rp-lead">Your password has been updated. You can sign in with it now.</p>
    <a class="rp-btn" href="<?= htmlspecialchars($LOGIN_PAGE) ?>">Sign in</a>

<?php elseif ($state === 'form'): ?>
    <div class="rp-icon" aria-hidden="true">&#128273;</div>
    <h1>Choose a new password</h1>
    <p class="rp-lead">For <strong><?= htmlspecialchars($maskedEmail) ?></strong></p>

    <?php if ($error !== ''): ?><div class="rp-error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="" id="rpForm" novalidate>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="csrf"  value="<?= htmlspecialchars($_SESSION['reset_csrf']) ?>">

      <label for="rpPass">New password</label>
      <div class="rp-field">
        <input type="password" id="rpPass" name="password" autocomplete="new-password" minlength="<?= (int)$MIN_LENGTH ?>" maxlength="72" required>
        <button type="button" class="rp-eye" data-target="rpPass" aria-label="Show password">Show</button>
      </div>

      <label for="rpConfirm">Confirm new password</label>
      <div class="rp-field">
        <input type="password" id="rpConfirm" name="confirm" autocomplete="new-password" maxlength="72" required>
        <button type="button" class="rp-eye" data-target="rpConfirm" aria-label="Show password">Show</button>
      </div>

      <ul class="rp-rules" id="rpRules" aria-live="polite">
        <li data-rule="len"><?= (int)$MIN_LENGTH ?> or more characters</li>
        <li data-rule="mix">A letter and a number</li>
        <li data-rule="same">Both passwords match</li>
      </ul>

      <button type="submit" class="rp-btn" id="rpSubmit">Save new password</button>
    </form>

<?php else: ?>
    <div class="rp-icon warn" aria-hidden="true">!</div>
    <h1>This link can't be used</h1>
    <p class="rp-lead">It may have expired, or it was already used. For your security, reset links work for one hour and only once.</p>
    <a class="rp-btn" href="<?= htmlspecialchars($FORGOT_PAGE) ?>">Request a new link</a>
<?php endif; ?>

  </div>
</main>

<style>
.rp{min-height:62vh;display:flex;align-items:center;justify-content:center;padding:clamp(36px,7vw,84px) 16px;background:var(--cream,#FFF7ED)}
.rp-card{width:100%;max-width:440px;background:#fff;border:1px solid var(--line,#EADBC8);border-radius:var(--r-lg,18px);padding:clamp(26px,4vw,40px);text-align:center;box-shadow:0 18px 44px -22px rgba(42,25,16,.35)}
.rp-icon{width:58px;height:58px;margin:0 auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;background:var(--cream-2,#F6EBDD);color:var(--brown,#6B3E26)}
.rp-icon.ok{background:var(--green,#2F6B4F);color:#fff;font-size:28px;font-weight:700}
.rp-icon.warn{background:var(--gold,#F4B63D);color:var(--brown-deep,#2A1910);font-weight:800}
.rp h1{margin:0 0 8px;font-family:var(--serif,Georgia,serif);font-weight:400;font-size:clamp(1.5rem,3.4vw,1.9rem);color:var(--brown-deep,#2A1910);line-height:1.15}
.rp-lead{margin:0 0 22px;color:var(--muted,#6D5A4A);font-size:.98rem;line-height:1.55}
.rp-lead strong{color:var(--brown-deep,#2A1910)}
.rp form{text-align:left}
.rp label{display:block;margin:14px 0 6px;font-weight:700;font-size:.9rem;color:var(--brown-deep,#2A1910)}
.rp-field{position:relative}
.rp-field input{width:100%;box-sizing:border-box;padding:13px 64px 13px 14px;border:1.5px solid var(--line,#E2D2BE);border-radius:10px;font:inherit;font-size:1rem;background:#fff;color:var(--ink,#2A1910)}
.rp-field input:focus{outline:0;border-color:var(--orange-cta,#C9501A);box-shadow:0 0 0 3px rgba(201,80,26,.18)}
.rp-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);border:0;background:none;color:var(--brown,#6B3E26);font-weight:700;font-size:.82rem;padding:8px 10px;cursor:pointer;border-radius:8px}
.rp-eye:hover{background:var(--cream-2,#F6EBDD)}
.rp-rules{list-style:none;margin:16px 0 4px;padding:0;font-size:.86rem;color:var(--muted,#6D5A4A)}
.rp-rules li{padding:3px 0 3px 24px;position:relative}
.rp-rules li::before{content:"";position:absolute;left:3px;top:50%;width:10px;height:10px;margin-top:-5px;border-radius:50%;border:2px solid var(--line,#D8C7B1);box-sizing:border-box}
.rp-rules li.ok{color:var(--green,#2F6B4F)}
.rp-rules li.ok::before{background:var(--green,#2F6B4F);border-color:var(--green,#2F6B4F)}
.rp-btn{display:block;width:100%;box-sizing:border-box;margin-top:22px;padding:14px 20px;border:0;border-radius:999px;background:var(--orange-cta,#C9501A);color:#fff;font:inherit;font-weight:800;font-size:1rem;text-align:center;text-decoration:none;cursor:pointer;transition:background-color .2s}
.rp-btn:hover{background:var(--orange-cta-hover,#A93F10);color:#fff}
.rp-btn:disabled{opacity:.6;cursor:progress}
.rp-error{margin:0 0 6px;padding:11px 14px;border-radius:10px;background:#FCE9E6;color:#9B2C1F;font-size:.92rem;text-align:left}
</style>

<script>
(function () {
  var form = document.getElementById('rpForm'); if (!form) return;
  var pass = document.getElementById('rpPass'), conf = document.getElementById('rpConfirm'), submit = document.getElementById('rpSubmit');
  var min = <?= (int)$MIN_LENGTH ?>;
  var rules = {}; document.querySelectorAll('#rpRules li').forEach(function (li) { rules[li.dataset.rule] = li; });
  function paint() {
    var v = pass.value;
    rules.len.classList.toggle('ok', v.length >= min);
    rules.mix.classList.toggle('ok', /[A-Za-z]/.test(v) && /\d/.test(v));
    rules.same.classList.toggle('ok', v !== '' && v === conf.value);
  }
  pass.addEventListener('input', paint); conf.addEventListener('input', paint); paint();
  document.querySelectorAll('.rp-eye').forEach(function (b) {
    b.addEventListener('click', function () {
      var input = document.getElementById(b.dataset.target), show = input.type === 'password';
      input.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show'; b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });
  form.addEventListener('submit', function (e) {
    if (!Object.keys(rules).every(function (k) { return rules[k].classList.contains('ok'); })) { e.preventDefault(); pass.focus(); return; }
    submit.disabled = true; submit.textContent = 'Saving...';
  });
})();
</script>
<?php include __DIR__ . '/footer3.php'; ?>