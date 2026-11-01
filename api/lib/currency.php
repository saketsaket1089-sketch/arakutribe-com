<?php
/**
 * api/lib/currency.php
 * Copied verbatim from ../../currency_helper.php's $CURRENCY_INFO table so
 * on-screen/converted prices match the live website exactly. Do not edit
 * one without the other.
 */

$CURRENCY_INFO = [
    'USD' => ['symbol' => '$',    'rate' => 1,      'name' => 'US Dollar'],
    'INR' => ['symbol' => '₹',    'rate' => 83.00,  'name' => 'Indian Rupee'],
    'EUR' => ['symbol' => '€',    'rate' => 0.92,   'name' => 'Euro'],
    'GBP' => ['symbol' => '£',    'rate' => 0.79,   'name' => 'British Pound'],
    'AUD' => ['symbol' => 'A$',   'rate' => 1.52,   'name' => 'Australian Dollar'],
    'CAD' => ['symbol' => 'C$',   'rate' => 1.36,   'name' => 'Canadian Dollar'],
    'AED' => ['symbol' => 'AED ', 'rate' => 3.67,   'name' => 'UAE Dirham'],
    'SGD' => ['symbol' => 'S$',   'rate' => 1.34,   'name' => 'Singapore Dollar'],
    'JPY' => ['symbol' => '¥',    'rate' => 149.50, 'name' => 'Japanese Yen'],
    'CNY' => ['symbol' => '¥',    'rate' => 7.24,   'name' => 'Chinese Yuan'],
];

/** Normalizes any caller-supplied currency string, falling back to USD for anything unsupported. */
function currency_code($input) {
    global $CURRENCY_INFO;
    $code = strtoupper(trim((string)$input));
    return array_key_exists($code, $CURRENCY_INFO) ? $code : 'USD';
}

/** Converts a USD price into the given currency, rounded to 2dp - mirrors the site's display logic. */
function currency_convert($usdPrice, $currencyCode) {
    global $CURRENCY_INFO;
    $code = currency_code($currencyCode);
    $rate = $CURRENCY_INFO[$code]['rate'];
    return round(((float) $usdPrice) * $rate, 2);
}

function currency_symbol($currencyCode) {
    global $CURRENCY_INFO;
    $code = currency_code($currencyCode);
    return $CURRENCY_INFO[$code]['symbol'];
}
