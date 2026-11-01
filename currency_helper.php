<?php
/**
 * currency_helper.php
 * ─────────────────────────────────────────────────────────────────
 * Resolves ONE currency for the whole page, based on the visitor:
 *
 *   - Logged-in users: detected once via IP geolocation, then saved
 *     PERMANENTLY on their account (users.preferred_currency), so it's
 *     the same every time they log in, on any device.
 *   - Guests: detected once per session (not saved anywhere permanent).
 *   - Either can override manually at any time via the dropdown this
 *     file renders - that override always wins and persists the same
 *     way (account for logged-in, session for guests).
 *
 * Include this AFTER db1.php/$conn and session_start() - header.php
 * already does both, so include this right after header.php.
 *
 * ⚠️ REQUIRED SCHEMA CHANGE:
 *   ALTER TABLE users ADD COLUMN preferred_currency VARCHAR(3) NULL;
 *
 * ⚠️ IP geolocation depends on outbound HTTP access to ip-api.com
 * (free, no key required, but rate-limited and not guaranteed uptime).
 * If your host blocks outbound requests, or the API is unreachable,
 * this fails silently and falls back to USD - it will never break the
 * page or throw an error, just be less accurate until the visitor
 * manually picks their currency.
 *
 * ⚠️ IP-based detection is inherently approximate (VPNs, proxies,
 * corporate networks, mobile carriers routing through another country
 * all throw it off). That's exactly why the manual override exists -
 * treat auto-detection as a convenient default, not a guarantee.
 */

// Bulletproof include guard: if this file somehow gets physically parsed
// more than once in the same request (e.g. require_once not catching a
// duplicate load due to how different callers reference the path), this
// stops the functions/constants below from ever being declared twice -
// which is what "Cannot redeclare function" fatals were caused by. This
// makes the file safe no matter how many times something tries to load it.
if (!defined('CURRENCY_HELPER_LOADED')) {
define('CURRENCY_HELPER_LOADED', true);

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

// Country code -> currency. Extend as needed.
$COUNTRY_TO_CURRENCY = [
    'IN' => 'INR',
    'US' => 'USD',
    'GB' => 'GBP',
    'AU' => 'AUD',
    'CA' => 'CAD',
    'AE' => 'AED',
    'SG' => 'SGD',
    'JP' => 'JPY',
    'CN' => 'CNY',
    'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'NL' => 'EUR', 'IE' => 'EUR',
];

/** Looks up the visitor's country from their IP. Returns null on any
    failure (blocked outbound access, API down, local/dev IP, etc.) -
    callers must handle null by falling back to a default currency. */
function detect_country_from_ip() {
    // ── TESTING ONLY on localhost/XAMPP ──────────────────────────────
    // REMOTE_ADDR is always 127.0.0.1 locally, so real geolocation is
    // impossible until this is on a live domain. This lets you simulate
    // a visitor's country via the URL, e.g.:
    //   coffee_cup.php?debug_country=IN   -> pretends to be from India
    //   coffee_cup.php?debug_country=US   -> pretends to be from the US
    // ⚠️ REMOVE THIS BLOCK (or at least the ?debug_country check) before
    // going live - as-is, anyone could force any country via the URL.
    if (isset($_GET['debug_country'])) {
        return strtoupper($_GET['debug_country']);
    }
    // ──────────────────────────────────────────────────────────────

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
        return null; // local/dev environment - nothing meaningful to detect
    }

    $context  = stream_context_create(['http' => ['timeout' => 2]]); // fail fast, never hang the page
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode", false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return null;
    }
    return $data['countryCode'] ?? null;
}

/** Resolves which currency code to display prices in for this request. */
function resolve_display_currency($conn) {
    global $COUNTRY_TO_CURRENCY, $CURRENCY_INFO;

    // 1. Manual override always wins, and is saved so it sticks going forward.
    if (isset($_GET['set_currency']) && array_key_exists($_GET['set_currency'], $CURRENCY_INFO)) {
        $chosen = $_GET['set_currency'];
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $conn->prepare("UPDATE users SET preferred_currency = ? WHERE user_id = ?");
                $stmt->bind_param("si", $chosen, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->close();
            } catch (\Throwable $e) {
                // preferred_currency column probably doesn't exist yet - see
                // the ALTER TABLE note at the top of this file. Falls back
                // to session-only (won't persist across logins) rather than
                // crashing the page.
                error_log("resolve_display_currency: could not save preferred_currency - " . $e->getMessage());
            }
        }
        $_SESSION['guest_currency'] = $chosen;
        return $chosen;
    }

    // 2. Logged-in users: permanent preference stored on their account.
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $conn->prepare("SELECT preferred_currency FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($row['preferred_currency']) && array_key_exists($row['preferred_currency'], $CURRENCY_INFO)) {
                return $row['preferred_currency'];
            }
        } catch (\Throwable $e) {
            // preferred_currency column probably doesn't exist yet - see the
            // ALTER TABLE note at the top of this file. Falls through to
            // session-only detection below instead of crashing the page.
            error_log("resolve_display_currency: could not read preferred_currency - " . $e->getMessage());
            if (isset($_SESSION['guest_currency']) && array_key_exists($_SESSION['guest_currency'], $CURRENCY_INFO)) {
                return $_SESSION['guest_currency'];
            }
            $country  = detect_country_from_ip();
            $currency = $COUNTRY_TO_CURRENCY[$country] ?? 'USD';
            $_SESSION['guest_currency'] = $currency;
            return $currency;
        }

        // First time for this account - detect once, then save permanently.
        $country  = detect_country_from_ip();
        $currency = $COUNTRY_TO_CURRENCY[$country] ?? 'USD';

        try {
            $stmt = $conn->prepare("UPDATE users SET preferred_currency = ? WHERE user_id = ?");
            $stmt->bind_param("si", $currency, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {
            error_log("resolve_display_currency: could not save preferred_currency - " . $e->getMessage());
        }

        return $currency;
    }

    // 3. Guests: detect once per session, cache in session only (not permanent).
    if (isset($_SESSION['guest_currency']) && array_key_exists($_SESSION['guest_currency'], $CURRENCY_INFO)) {
        return $_SESSION['guest_currency'];
    }

    $country  = detect_country_from_ip();
    $currency = $COUNTRY_TO_CURRENCY[$country] ?? 'USD';
    $_SESSION['guest_currency'] = $currency;
    return $currency;
}

/** Renders the "showing prices in X - change" notice + override dropdown.
    Preserves existing query params (sort, page) when switching currency. */
function render_currency_notice($currentCurrency) {
    global $CURRENCY_INFO;
    ob_start();
    ?>
    <div class="currency-notice">
        <span>
            Showing prices in <strong><?= htmlspecialchars($currentCurrency) ?></strong>
            (<?= htmlspecialchars($CURRENCY_INFO[$currentCurrency]['name'] ?? '') ?>)
        </span>
        <form method="GET" class="currency-notice-form">
            <?php foreach ($_GET as $k => $v): if ($k !== 'set_currency'): ?>
                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endif; endforeach; ?>
            <select name="set_currency" onchange="this.form.submit()">
                <?php foreach ($CURRENCY_INFO as $code => $info): ?>
                    <option value="<?= $code ?>" <?= $code === $currentCurrency ? 'selected' : '' ?>>
                        <?= $code ?> (<?= htmlspecialchars($info['symbol']) ?>) — <?= htmlspecialchars($info['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <style>
        .currency-notice {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            margin-bottom: 16px; padding: 8px 14px;
            background: #fdf6ed; border: 1px solid #e2d3bd; border-radius: 8px;
            font-size: 13px; color: #5a3a24;
        }
        .currency-notice select {
            padding: 4px 8px; border-radius: 6px; border: 1px solid #c9a75c; font-size: 12px;
        }
    </style>
    <?php
    return ob_get_clean();
}

} // end CURRENCY_HELPER_LOADED guardxa