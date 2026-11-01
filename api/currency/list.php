<?php
require_once __DIR__ . '/../config.php';

$list = [];
foreach ($CURRENCY_INFO as $code => $info) {
    $list[] = ['code' => $code, 'symbol' => $info['symbol'], 'rate' => $info['rate'], 'name' => $info['name']];
}

json_ok($list);
