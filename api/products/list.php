<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';

$currency = currency_code($_GET['currency'] ?? 'USD');
$category = trim((string) ($_GET['category'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 9)));

$user = current_user($conn); // auth optional - only affects is_wishlisted

$where = '';
$params = [];
$types = '';
if ($category !== '') {
    $where = 'WHERE category = ?';
    $params[] = $category;
    $types .= 's';
}

switch ($sort) {
    case 'lowtohigh':
        $orderBy = 'ORDER BY price ASC';
        break;
    case 'hightolow':
        $orderBy = 'ORDER BY price DESC';
        break;
    case 'quantity_asc':
        $orderBy = 'ORDER BY quantity ASC';
        break;
    case 'quantity_desc':
        $orderBy = 'ORDER BY quantity DESC';
        break;
    default:
        $orderBy = ''; // natural order
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products $where");
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$totalPages = max(1, (int) ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare("SELECT * FROM products $where $orderBy LIMIT ? OFFSET ?");
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$res = $stmt->get_result();

$wishlisted = [];
if ($user) {
    $wstmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wstmt->bind_param("i", $user['user_id']);
    $wstmt->execute();
    $wres = $wstmt->get_result();
    while ($wrow = $wres->fetch_assoc()) {
        $wishlisted[$wrow['product_id']] = true;
    }
    $wstmt->close();
}

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = format_product($row, $currency, isset($wishlisted[$row['product_id']]));
}
$stmt->close();

json_ok([
    'items' => $items,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => $totalPages,
]);
