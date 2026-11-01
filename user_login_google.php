<?php
/* =====================================================================
   ARAKU TRIBE - user_login_google.php  (fixed for the live server)
   "Sign in with Google": sends the visitor to Google, and handles the
   return trip. Keeps the same file name because Google is set up to
   return to this exact address.

   Fixes compared with the old version:
     - the return address (REDIRECT_URI) used to be written as
       http://localhost/arakutribe.com/... . On the live site that sent
       people back to THEIR OWN computer after Google: a 404, or your old
       local pages. It is now worked out from where the file really runs:
       live address on the server, localhost on your computer.
     - on the live site everyone is moved to ONE address first (www or not,
       as set in $LIVE_BASE), so the sign-in session is not lost on the way
       back from Google
     - errors are only shown on your own computer, never to customers
     - does NOT sign the customer in. It only marks the account as
       waiting for the emailed code; verify_user_otp3.php signs them in
       after the code is right
     - "Cancel" on Google's screen no longer loops back to Google
     - `state` check, so a sign-in can only finish if it started here
     - a Google email that Google hasn't confirmed can't be used to
       take over an existing account with that email
     - database errors go to the PHP error log, not onto the screen
     - errors go back to coffee_login3.php, success to verify_user_otp3.php
   ===================================================================== */

/* Are we on your own computer (localhost) or on the live server? */
$__host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$IS_DEV = in_array($__host, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || (bool)preg_match('/\.(local|test|localhost)$/', $__host);

// Errors are shown on localhost only. On the live site they go to the server's error log.
ini_set('display_errors', $IS_DEV ? 1 : 0);
ini_set('display_startup_errors', $IS_DEV ? 1 : 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

session_start();
include 'db1.php';

if (!isset($conn) || !($conn instanceof mysqli)) { $conn = new mysqli($host, $user, $pass, $db); }
if ($conn->connect_error) {
    error_log('Google sign-in: DB connection failed: ' . $conn->connect_error);
    die('Sorry, sign-in is unavailable right now. Please try again in a moment.');
}
if (!extension_loaded('curl')) {
    die('Error: the PHP cURL extension is required for Google sign-in.');
}

/* ---------------------------------------------------------------------
   SETTINGS
   CLIENT_SECRET has been shared in a chat: reset it in Google Cloud
   Console (APIs & Services > Credentials > your OAuth client) and paste
   the NEW one below.

   In Google Cloud Console > your OAuth client > "Authorized redirect URIs",
   list BOTH of these, exactly (https, www or not as in $LIVE_BASE):
       https://www.arakutribe.com/user_login_google.php      (live site)
       http://localhost/arakutribe.com/user_login_google.php  (your computer)
   --------------------------------------------------------------------- */
define('CLIENT_ID',     '22079492185-rmucf16lvs426f41lucibers373m4eeo.apps.googleusercontent.com');
define('CLIENT_SECRET', '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');
define('SCOPE',         'openid email profile');

// YOUR LIVE ADDRESS, always ending in a slash (same value as $SITE_URL in process_forgot_password3.php).
$LIVE_BASE = 'https://arakutribe.com/';

/* The address Google sends the visitor back to = this very file, on whichever server it runs. */
if ($IS_DEV) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    define('REDIRECT_URI', $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/' . basename(__FILE__));
} else {
    define('REDIRECT_URI', rtrim($LIVE_BASE, '/') . '/' . basename(__FILE__));
    if ($IS_DEV && isset($_GET['debug'])) { echo REDIRECT_URI; exit; }
}

$LOGIN_PAGE  = 'coffee_login3.php';
$VERIFY_PAGE = 'verify_user_otp3.php';

function g3_fail($message) {
    global $LOGIN_PAGE;
    unset($_SESSION['google_oauth_state']);
    $_SESSION['message'] = $message;
    header('Location: ' . $LOGIN_PAGE);
    exit;
}
function g3_curl($url, $postFields = null, $headers = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }
    $body = curl_exec($ch);
    $err  = $body === false ? curl_error($ch) : '';
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$body, $code, $err];
}

/* =====================================================================
   1. GOOGLE SENT THE VISITOR BACK WITH AN ERROR (e.g. they pressed Cancel)
   Checked first: Google returns ?error=... WITHOUT a code, which the old
   version treated as "start again" and looped back to Google.
   ===================================================================== */
if (isset($_GET['error'])) {
    g3_fail($_GET['error'] === 'access_denied'
        ? 'Google sign-in was cancelled. You can sign in with your email instead.'
        : 'Google sign-in failed: ' . htmlspecialchars((string)$_GET['error']));
}

/* =====================================================================
   2. START: send the visitor to Google
   ===================================================================== */
if (!isset($_GET['code'])) {
    // Live site: always start on the one official address, so the session cookie made here is the one
    // that is still there when Google sends the visitor back (www and non-www do not share cookies).
    if (!$IS_DEV) {
        $wantHost = (string)parse_url($LIVE_BASE, PHP_URL_HOST);
        if ($wantHost !== '' && strcasecmp($__host, $wantHost) !== 0) { header('Location: ' . REDIRECT_URI); exit; }
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => CLIENT_ID,
        'redirect_uri'  => REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => SCOPE,
        'access_type'   => 'online',
        'state'         => $state,
        'prompt'        => 'select_account',   // lets people pick which Google account
    ]);
    header('Location: ' . $authUrl);
    exit;
}

/* =====================================================================
   3. RETURN TRIP: check it started here, then swap the code for a token
   ===================================================================== */
$expected = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);
if ($expected === '' || !hash_equals($expected, (string)($_GET['state'] ?? ''))) {
    g3_fail('Your Google sign-in timed out. Please try again.');
}

[$tokenBody, $tokenCode, $tokenErr] = g3_curl('https://oauth2.googleapis.com/token', http_build_query([
    'code'          => (string)$_GET['code'],
    'client_id'     => CLIENT_ID,
    'client_secret' => CLIENT_SECRET,
    'redirect_uri'  => REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]), ['Content-Type: application/x-www-form-urlencoded']);

if ($tokenBody === false) {
    error_log('Google sign-in: token request failed: ' . $tokenErr);
    g3_fail("We couldn't reach Google. Please check your connection and try again.");
}
$tokenData = json_decode($tokenBody, true);
if ($tokenCode != 200 || empty($tokenData['access_token'])) {
    error_log('Google sign-in: token exchange failed, HTTP ' . $tokenCode . ': ' . ($tokenData['error_description'] ?? $tokenBody));
    g3_fail('Google sign-in failed. Please try again.');
}

/* =====================================================================
   4. WHO IS IT?
   ===================================================================== */
[$infoBody, $infoCode, $infoErr] = g3_curl('https://www.googleapis.com/oauth2/v3/userinfo', null,
    ['Authorization: Bearer ' . $tokenData['access_token']]);
if ($infoBody === false || $infoCode != 200) {
    error_log('Google sign-in: userinfo failed, HTTP ' . $infoCode . ' ' . $infoErr);
    g3_fail("We couldn't read your Google account details. Please try again.");
}
$userInfo = json_decode($infoBody, true);
if (empty($userInfo['email']) || empty($userInfo['sub'])) {
    g3_fail("Google didn't share your email address. Please allow email access and try again.");
}

$google_id = (string)$userInfo['sub'];
$email     = (string)$userInfo['email'];
$verified  = !empty($userInfo['email_verified']) && $userInfo['email_verified'] !== 'false';
$firstname = $userInfo['given_name'] ?? ($userInfo['name'] ?? 'there');

/* =====================================================================
   5. FIND OR CREATE THE ACCOUNT (same rules as before: by google_id OR email)
   ===================================================================== */
$stmt = $conn->prepare("SELECT user_id, firstname, google_id FROM users WHERE google_id = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $google_id, $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    // Matched only by email (account was made with a password): only allow it if Google confirmed that email.
    $matchedByEmailOnly = ((string)($existing['google_id'] ?? '') !== $google_id);
    if ($matchedByEmailOnly && !$verified) {
        g3_fail('This Google email isn\'t confirmed by Google yet, so we can\'t link it to your account. Please sign in with your password.');
    }
    $uid  = (int)$existing['user_id'];
    $name = $existing['firstname'] ?: $firstname;
    $_SESSION['message'] = 'Signed in with Google. Enter the code we emailed you to finish.';
} else {
    if (!$verified) {
        g3_fail('Please confirm your email with Google first, or create an account with your email and a password.');
    }
    $ins = $conn->prepare("INSERT INTO users (firstname, email, google_id) VALUES (?, ?, ?)");
    $ins->bind_param("sss", $firstname, $email, $google_id);
    if (!$ins->execute()) {
        error_log('Google sign-in: could not create user: ' . $conn->error);
        g3_fail("We couldn't create your account right now. Please try again.");
    }
    $uid  = (int)$conn->insert_id;
    $name = $firstname;
    $ins->close();
    $_SESSION['message'] = 'Welcome, ' . $firstname . '! Your account is ready. Enter the code we emailed you to finish.';
}

/* =====================================================================
   6. NOT SIGNED IN YET: hand over to the code page
   ===================================================================== */
unset($_SESSION['user_id'], $_SESSION['user_logged_in']);
$_SESSION['mfa_pending']          = ['user_id' => $uid, 'email' => $email, 'name' => $name];
$_SESSION['google_login_pending'] = true;      // tells verify_user_otp3.php to send a fresh code
$_SESSION['user_email']           = $email;
$_SESSION['user_name']            = $name;
$_SESSION['google_id']            = $google_id;

header('Location: ' . $VERIFY_PAGE);
exit;