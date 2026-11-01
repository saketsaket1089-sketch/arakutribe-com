<?php
/**
 * api/chatbot/categories.php
 * Mirrors chatbot_api.php's `get_categories` case. Public, GET.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/chatbot.php';

$result = $conn->query(
    "SELECT category, COUNT(*) AS cnt
     FROM products
     WHERE category IS NOT NULL AND category <> ''
     GROUP BY category
     ORDER BY category ASC"
);

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'value' => $row['category'],
        'label' => chatbot_category_label($row['category']),
        'count' => (int) $row['cnt'],
    ];
}

json_ok(['categories' => $categories]);
