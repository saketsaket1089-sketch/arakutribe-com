<?php
/* =====================================================================
   ARAKU TRIBE - session_ping3.php   (NEW)
   Tiny "I'm still here" call used by header3.php's session timeout.
   While a signed-in customer is active, the page calls this at most once
   a minute so the server knows they're still using the site.
   Returns JSON: { ok: true, left: seconds }  or  { ok: false } when the
   session has already timed out (the page then signs them out).
   ===================================================================== */
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) { echo json_encode(['ok' => false, 'guest' => true]); exit; }

$limit = (int)($_SESSION['h3_idle_limit'] ?? 900);          // set by header3.php ($H3_IDLE_MINUTES)
$last  = (int)($_SESSION['last_activity'] ?? time());
if (time() - $last > $limit) { echo json_encode(['ok' => false]); exit; }

$_SESSION['last_activity'] = time();
echo json_encode(['ok' => true, 'left' => $limit]);