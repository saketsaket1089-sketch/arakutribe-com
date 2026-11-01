<?php
/**
 * api/config.php
 * Bootstrap required by every endpoint under api/. Reuses the site's
 * existing DB connection (db1.php -> $conn) and SMTP mailer
 * (agent_mailer_config.php -> get_agent_mailer()) rather than reinventing
 * either. Never modifies those files.
 *
 * Guarantees:
 *  - Every response is JSON (Content-Type set below).
 *  - PHP warnings/notices/fatals are logged, never leaked into the JSON body.
 *  - Permissive CORS so the Flutter app (any origin) can call this API.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// This XAMPP install's php.ini has serialize_precision=100 (vs. the sane
// default of -1), which makes json_encode() print floats like
// 4.1500000000000003552713678800500929355621337890625 instead of 4.15.
// Fix it for this API's responses without touching the shared php.ini.
ini_set('serialize_precision', -1);

if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}

date_default_timezone_set('UTC');

// ---- CORS (permissive - mobile app callers from any origin) ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/lib/response.php';

// ---- Never let a PHP warning/notice/deprecation leak raw HTML into the JSON body ----
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("PHP error [$severity]: $message in $file:$line");
    return true; // suppress PHP's own output, we've logged it
});

set_exception_handler(function ($e) {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'An unexpected error occurred.']]);
    exit;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('Fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'An unexpected error occurred.']]);
        }
    }
});

// ---- Reuse existing DB connection + mailer, never new credentials ----
require_once dirname(__DIR__) . '/db1.php';               // gives us $conn (mysqli)
require_once dirname(__DIR__) . '/agent_mailer_config.php'; // gives us get_agent_mailer()

require_once __DIR__ . '/lib/currency.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/mailer.php';

// Fixed secret from the frozen API contract - do not change without
// invalidating every outstanding login OTP ticket.
define('OTP_TICKET_SECRET', 'f3a9c1e7b2d84f0a9c6e21b5d7f48c3a1e9b6d0f2c5a8e3b7d1f4a6c9e2b5d8f');
