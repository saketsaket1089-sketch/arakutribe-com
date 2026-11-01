<?php
/**
 * api/chatbot/init.php
 * Stateless mirror of chatbot_api.php's `init` case: category counts +
 * is_logged_in + currency info, for the mobile chat screen's greeting.
 * Auth optional (bearer token). Body: {session_id?, currency?}.
 *
 * This is also where the addendum's opportunistic chatbot_sessions
 * cleanup (rows >24h old) runs, since init is the natural "chat screen
 * just opened" entry point - no cron job needed.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/chatbot.php';

chatbot_cleanup_expired($conn);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$currency = currency_code($input['currency'] ?? ($_GET['currency'] ?? 'USD'));

$user = current_user($conn); // auth optional

$result = $conn->query(
    "SELECT category, COUNT(*) AS cnt
     FROM products
     WHERE category IS NOT NULL AND category <> ''
     GROUP BY category
     ORDER BY category ASC"
);

$categories = [];
$total = 0;
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'value' => $row['category'],
        'label' => chatbot_category_label($row['category']),
        'count' => (int) $row['cnt'],
    ];
    $total += (int) $row['cnt'];
}

global $CURRENCY_INFO;

json_ok([
    'categories' => $categories,
    'total' => $total,
    'is_logged_in' => (bool) $user,
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
    'currency_rate' => $CURRENCY_INFO[$currency]['rate'],
]);
