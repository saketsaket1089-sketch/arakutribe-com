<?php
/* =====================================================================
   ARAKU TRIBE - process_forgot_password3.php
   ---------------------------------------------------------------------
   Receives the email from forgot_password3.php (POST) and emails that
   person a one-time link to reset_password3.php, where they choose a
   new password.

   - Uses YOUR database through db1.php, and YOUR existing `users` table.
     It looks at the table's real column names when it runs (id, email,
     name), so nothing about your table is assumed or changed.
   - The only thing it adds to your database is ONE new table,
     `araku_password_resets` (created automatically the first time; the
     SQL is in the comment further down if you prefer to run it yourself).
   - Talks back to forgot_password3.php the way that page expects:
     $_SESSION['success_message'] / $_SESSION['error_message'], then a
     redirect back to forgot_password3.php.
   - The email goes out through PHPMailer + SMTP: fill in the SMTP block
     below ONCE, with the same mail account your sign-in OTP emails use.

   Safety
   - Only a SHA-256 hash of the link's token is stored, the link works
     for $EXPIRY_MINUTES minutes and only once, and a newer link cancels
     the older ones.
   - The visitor always sees the same "if that email is registered..."
     message, so nobody can use this page to find out who has an account.
   - At most $MAX_PER_EMAIL_PER_HOUR emails per address and
     $MAX_PER_IP_PER_HOUR requests per visitor per hour.
   ===================================================================== */
ini_set('display_errors', '0');          // PHP errors go to the error log, never into the page
error_reporting(E_ALL);
ini_set('log_errors', '1');
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------------------------------------------------------------------
   SETTINGS - edit these
   --------------------------------------------------------------------- */
$FORGOT_PAGE            = 'forgot_password3.php';   // where people come from, and where they go back to
$RESET_PAGE             = 'reset_password3.php';    // the page the emailed link opens
$EXPIRY_MINUTES         = 60;
$MAX_PER_EMAIL_PER_HOUR = 3;
$MAX_PER_IP_PER_HOUR    = 12;

// YOUR LIVE ADDRESS, with the folder if the site is not in the root, always ending in a slash,
// e.g. 'https://www.arakutribe.com/'. The emailed link on the live site is built from THIS, so it can never say
// "localhost" and can never be tricked into pointing at another site. When you test on your own computer
// (localhost) the link automatically points at localhost instead, so you do not need to change this to test.
$SITE_URL = 'https://www.arakutribe.com/';

// Mail account: the SAME values your sign-in OTP emails already use (PHPMailer + SMTP).
$SMTP = [
    'host'       => 'aiinfoways.com',
    'port'       => 587,
    'secure'     => 'tls',         // 'tls' for port 587, 'ssl' for port 465
    'user'       => 'support@hayward.university',
    'pass'       => 'zh9nCzzB1ds_',
    'from_email' => 'info@aiinfoways.com',
    'from_name'  => 'Araku Tribe',
];

/*  The one table this file adds (created automatically; you only need this if your database login is not
    allowed to create tables):

    CREATE TABLE IF NOT EXISTS araku_password_resets (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id     VARCHAR(64)  NOT NULL DEFAULT '',
        email       VARCHAR(254) NOT NULL,
        token_hash  CHAR(64)     NOT NULL,
        expires_at  DATETIME     NOT NULL,
        used_at     DATETIME     NULL,
        request_ip  VARCHAR(45)  NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_token (token_hash),
        KEY idx_email_time (email, created_at),
        KEY idx_ip_time (request_ip, created_at),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

/* ---------------------------------------------------------------------
   Helpers
   --------------------------------------------------------------------- */
/** Leaves a message for forgot_password3.php and sends the visitor back there. */
function backToForgot(string $kind, string $message, string $email = '', bool $early = false): void {
    global $FORGOT_PAGE;
    $_SESSION[$kind === 'ok' ? 'success_message' : 'error_message'] = $message;
    $url = $FORGOT_PAGE . ($kind !== 'ok' && $email !== '' ? '?email=' . rawurlencode($email) : '');
    header('Cache-Control: no-store');
    header('Location: ' . $url);
    session_write_close();
    if ($early) {                      // hand the answer over now, so the visitor does not wait for the email to be sent
        ignore_user_abort(true);
        @set_time_limit(90);                                           // the email may take a while after the visitor has gone
        @ini_set('zlib.output_compression', '0');                      // compression would make the server hold the answer back
        if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
        header('Content-Length: 0');
        header('Connection: close');
        while (ob_get_level() > 0) @ob_end_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    }
}

function siteBase(string $configured): string {
    $host  = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $isDev = in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || (bool)preg_match('/\.(local|test|localhost)$/', $host);
    if (!$isDev && $configured !== '') return rtrim($configured, '/') . '/';             // live site: always your own address
    if (!$isDev) {                                                                          // live site, nothing set: best guess, and say so
        error_log('[forgot-password] SITE_URL is empty on a live server. Set it at the top of process_forgot_password3.php.');
        $h = preg_replace('/[^a-z0-9.\-:]/', '', strtolower((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '')));
        if ($h !== '' && !in_array(preg_replace('/:\d+$/', '', $h), ['localhost', '127.0.0.1'], true)) {
            $proto = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
            return $proto . '://' . $h . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
        }
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $port  = (int)($_SERVER['SERVER_PORT'] ?? 80);
    $portPart = (($https && $port === 443) || (!$https && $port === 80)) ? '' : ':' . $port;
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . $portPart . $dir . '/';
}

/** The first of $candidates that is really a column of the users table (case-insensitive), or null. */
function pickColumn(array $columns, array $candidates): ?string {
    foreach ($candidates as $c) if (isset($columns[$c])) return $columns[$c];
    return null;
}

function resetEmailHtml(string $name, string $link, int $minutes): string {
    $hi   = $name !== '' ? 'Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',' : 'Hi,';
    $safe = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    return '<!doctype html><html><body style="margin:0;padding:0;background:#F6EEE4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6EEE4;padding:28px 12px;"><tr><td align="center">
  <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#FFFFFF;border-radius:14px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#2A1910;">
    <tr><td style="background:#6B3E26;padding:22px 30px;font-family:Georgia,serif;font-size:22px;letter-spacing:.5px;color:#F4B63D;">ARAKU TRIBE COFFEE</td></tr>
    <tr><td style="padding:32px 30px 10px;">
      <h1 style="margin:0 0 14px;font-family:Georgia,serif;font-weight:400;font-size:24px;color:#2A1910;">Reset your password</h1>
      <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">' . $hi . '</p>
      <p style="margin:0 0 24px;font-size:15px;line-height:1.6;">We received a request to reset the password for your Araku Tribe account. Tap the button below to choose a new one. The link works for <strong>' . (int)$minutes . ' minutes</strong> and can be used once.</p>
      <p style="margin:0 0 26px;"><a href="' . $safe . '" style="display:inline-block;background:#C9501A;color:#FFFFFF;text-decoration:none;font-weight:bold;font-size:15px;padding:14px 30px;border-radius:8px;">Choose a new password</a></p>
      <p style="margin:0 0 6px;font-size:13px;line-height:1.6;color:#6D5A4A;">If the button does not work, copy this link into your browser:</p>
      <p style="margin:0 0 24px;font-size:12px;line-height:1.5;word-break:break-all;"><a href="' . $safe . '" style="color:#C9501A;">' . $safe . '</a></p>
      <p style="margin:0 0 28px;font-size:13px;line-height:1.6;color:#6D5A4A;">Did not ask for this? You can ignore this email. Your password stays the same until someone opens the link and sets a new one.</p>
    </td></tr>
    <tr><td style="background:#FBF4EA;padding:16px 30px;font-size:12px;color:#8A7766;">Araku Tribe Coffee, Araku Valley, Andhra Pradesh</td></tr>
  </table>
</td></tr></table></body></html>';
}

/** Finds PHPMailer wherever it is on this site (the usual places). Returns true when it is loaded. */
function loadPhpMailer(): bool {
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) return true;
    foreach (['PHPMailer/src/', 'phpmailer/src/', 'vendor/phpmailer/phpmailer/src/'] as $rel) {
        $dir = __DIR__ . '/' . $rel;
        if (is_file($dir . 'PHPMailer.php') && is_file($dir . 'SMTP.php') && is_file($dir . 'Exception.php')) {
            require_once $dir . 'Exception.php'; require_once $dir . 'PHPMailer.php'; require_once $dir . 'SMTP.php';
            return true;
        }
    }
    if (is_file(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; return class_exists('PHPMailer\\PHPMailer\\PHPMailer'); }
    return false;
}

/** Sends the reset email. Returns null on success, or a short reason (written to the error log only). */
function sendResetMail(array $smtp, string $toEmail, string $toName, string $link, int $minutes): ?string {
    if (!loadPhpMailer()) return 'PHPMailer was not found (looked in PHPMailer/src, phpmailer/src and vendor/).';
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPDebug  = 0;                          // never print mail-server chatter into the page
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['user'];
        $mail->Password   = $smtp['pass'];
        $mail->SMTPSecure = $smtp['secure'];
        $mail->Port       = (int)$smtp['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 12;                         // give up on a silent mail server after 12 s (it used to be 20)
        $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = ['socket' => ['bindto' => '0.0.0.0:0']];   // use IPv4: many servers hang for a long time trying IPv6 first
        $mail->setFrom($smtp['from_email'] !== '' ? $smtp['from_email'] : $smtp['user'], $smtp['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Reset your Araku Tribe password';
        $mail->Body    = resetEmailHtml($toName, $link, $minutes);
        $mail->AltBody = "Reset your Araku Tribe password\n\nOpen this link to choose a new password (valid $minutes minutes, one use):\n$link\n\nDid not ask for this? Ignore this email - your password stays the same.";
        $mail->send();
        return null;
    } catch (\Throwable $e) {
        return 'Mail server said: ' . $e->getMessage();
    }
}

function countRecent(mysqli $conn, string $column, string $value): int {
    $n = 0;
    $st = $conn->prepare("SELECT COUNT(*) FROM araku_password_resets WHERE $column = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    if ($st) { $st->bind_param('s', $value); $st->execute(); $st->bind_result($n); $st->fetch(); $st->close(); }
    return (int)$n;
}

/* ---------------------------------------------------------------------
   1. POST only, and a sensible email
   --------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: ' . $FORGOT_PAGE); exit; }

$email = trim((string)($_POST['email'] ?? ''));
if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    backToForgot('err', 'Please enter a valid email address.', substr($email, 0, 254));
    exit;
}
$lowerEmail = strtolower($email);
$genericMessage = "If that email is registered with us, a password reset link is on its way. It works for $EXPIRY_MINUTES minutes, so please check your inbox (and spam folder).";

// Mail not set up yet: say so plainly (this does not depend on the email typed, so it reveals nothing about accounts).
if ($SMTP['host'] === '' || $SMTP['user'] === '' || $SMTP['pass'] === '') {
    error_log('[forgot-password] The SMTP settings at the top of process_forgot_password3.php are empty, so no reset email can be sent.');
    backToForgot('err', 'Password reset emails are not switched on yet. Please contact us and we will help you sign in.', $email);
    exit;
}

/* ---------------------------------------------------------------------
   2. Your database (db1.php) and the token table
   --------------------------------------------------------------------- */
include 'db1.php';
mysqli_report(MYSQLI_REPORT_OFF);                       // we check errors ourselves
$conn = new mysqli($host, $user, $pass, $db);
$serverError = 'Something went wrong on our side. Please try again in a few minutes.';
if ($conn->connect_error) {
    error_log('[forgot-password] database connection failed: ' . $conn->connect_error);
    backToForgot('err', $serverError, $email);
    exit;
}
$conn->set_charset('utf8mb4');

$conn->query("CREATE TABLE IF NOT EXISTS araku_password_resets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     VARCHAR(64)  NOT NULL DEFAULT '',
    email       VARCHAR(254) NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    request_ip  VARCHAR(45)  NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token_hash),
    KEY idx_email_time (email, created_at),
    KEY idx_ip_time (request_ip, created_at),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if ($conn->errno) {
    error_log('[forgot-password] could not create araku_password_resets (run the SQL from the top of process_forgot_password3.php yourself): ' . $conn->error);
    backToForgot('err', $serverError, $email);
    exit;
}
if (mt_rand(1, 25) === 1) $conn->query("DELETE FROM araku_password_resets WHERE created_at < (NOW() - INTERVAL 7 DAY)");   // tidy up now and then

/* ---------------------------------------------------------------------
   3. Read the real column names of your users table
   --------------------------------------------------------------------- */
$cols = [];
$r = $conn->query("SHOW COLUMNS FROM users");
if ($r) { while ($row = $r->fetch_assoc()) $cols[strtolower($row['Field'])] = $row['Field']; $r->free(); }
$idCol    = pickColumn($cols, ['user_id', 'id', 'customer_id', 'uid']);
$emailCol = pickColumn($cols, ['email', 'user_email', 'email_address', 'mail']);
$nameCol  = pickColumn($cols, ['firstname', 'first_name', 'name', 'user_name', 'full_name', 'username']);
if (!$idCol || !$emailCol) {
    error_log('[forgot-password] the users table has no recognisable id/email column. Columns found: ' . implode(', ', array_values($cols)));
    backToForgot('err', $serverError, $email);
    exit;
}

/* ---------------------------------------------------------------------
   4. Too many requests? Same friendly answer, but nothing is sent.
   --------------------------------------------------------------------- */
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
if (countRecent($conn, 'email', $lowerEmail) >= $MAX_PER_EMAIL_PER_HOUR || ($ip !== '' && countRecent($conn, 'request_ip', $ip) >= $MAX_PER_IP_PER_HOUR)) {
    $conn->close();
    backToForgot('ok', $genericMessage, '', true);
    exit;
}

/* ---------------------------------------------------------------------
   5. Find the account and store a token (a dummy, already-used row for
      unknown emails, so every request is counted the same way)
   --------------------------------------------------------------------- */
$account = null;
$select = "SELECT `$idCol` AS _id, `$emailCol` AS _email" . ($nameCol ? ", `$nameCol` AS _name" : ", '' AS _name") . " FROM users WHERE LOWER(`$emailCol`) = ? LIMIT 1";
$st = $conn->prepare($select);
if ($st) { $st->bind_param('s', $lowerEmail); $st->execute(); $res = $st->get_result(); $account = $res ? $res->fetch_assoc() : null; $st->close(); }
else error_log('[forgot-password] could not read the users table: ' . $conn->error);

$mailJob = null;
if ($account && (string)$account['_id'] !== '') {
    $uid   = (string)$account['_id'];
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    $st = $conn->prepare("UPDATE araku_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");   // older links stop working
    if ($st) { $st->bind_param('s', $uid); $st->execute(); $st->close(); }

    $st = $conn->prepare("INSERT INTO araku_password_resets (user_id, email, token_hash, expires_at, request_ip) VALUES (?, ?, ?, NOW() + INTERVAL ? MINUTE, ?)");
    $stored = false;
    if ($st) { $st->bind_param('sssis', $uid, $lowerEmail, $hash, $EXPIRY_MINUTES, $ip); $stored = $st->execute(); $st->close(); }
    if ($stored) {
        $mailJob = ['to' => (string)$account['_email'], 'name' => trim((string)$account['_name']), 'link' => siteBase($SITE_URL) . $RESET_PAGE . '?token=' . $token];
    } else {
        error_log('[forgot-password] could not store the reset token: ' . $conn->error);
    }
} else {
    $dummy = hash('sha256', random_bytes(32));
    $st = $conn->prepare("INSERT INTO araku_password_resets (user_id, email, token_hash, expires_at, used_at, request_ip) VALUES ('', ?, ?, NOW(), NOW(), ?)");
    if ($st) { $st->bind_param('sss', $lowerEmail, $dummy, $ip); $st->execute(); $st->close(); }
}
$conn->close();

/* ---------------------------------------------------------------------
   6. Answer the visitor first, then send the email
   --------------------------------------------------------------------- */
backToForgot('ok', $genericMessage, '', true);
if ($mailJob) {
    $t0  = microtime(true);
    $err = sendResetMail($SMTP, $mailJob['to'], $mailJob['name'], $mailJob['link'], $EXPIRY_MINUTES);
    $secs = round(microtime(true) - $t0, 1);
    if ($err !== null) error_log('[forgot-password] email to ' . $mailJob['to'] . ' was NOT sent (after ' . $secs . ' s). ' . $err);
    else error_log('[forgot-password] email to ' . $mailJob['to'] . ' sent in ' . $secs . ' s.');
}
exit;