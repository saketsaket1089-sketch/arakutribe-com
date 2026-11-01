<?php
/**
 * api/lib/response.php
 * Small helpers enforcing the frozen response envelope from the API
 * contract: {"ok":true,"data":...} or {"ok":false,"error":{"code","message"}}.
 * Both helpers exit immediately after emitting - no endpoint should ever
 * print anything else after calling one of these.
 */

function json_ok($data = null) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error($code, $message, $httpStatus = 400) {
    http_response_code($httpStatus);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => $code, 'message' => $message],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
