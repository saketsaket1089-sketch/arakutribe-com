<?php
/* =====================================================================
   ARAKU TRIBE - logout_user3.php
   Signs out completely (session data + session cookie) and goes to the
   home page, which shows "You've signed out".
   ?timeout=1 (sent by the session timeout in header3.php) shows
   "You were signed out after ... minutes without activity" instead.
   ===================================================================== */
session_start();
$timedOut = !empty($_GET['timeout']);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: index.php?' . ($timedOut ? 'timed_out=1' : 'signed_out=1'));
exit;