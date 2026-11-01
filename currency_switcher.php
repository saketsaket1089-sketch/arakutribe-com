<?php
/**
 * currency_switcher.php
 * ─────────────────────────────────────────────────────────────────
 * Include this once near the top of any page that displays prices.
 * It defines a helper function - render_currency_picker() - to call
 * right after each individual product's price line. Each item gets
 * its own small dropdown + converted amount, independent of every
 * other item on the page (no shared/global box at the top).
 *
 * Usage per product:
 *   <p>Price: $<?= $price ?></p>
 *   <?= render_currency_picker($productId, $product['price']) ?>
 *
 * ⚠️ Rates below are fixed constants, not a live feed - update
 * CURRENCY_RATES (in the script below) periodically, or wire in a
 * real forex API if you need this to stay accurate automatically.
 */

function render_currency_picker($uniqueId, $usdPrice) {
    $uid = htmlspecialchars((string)$uniqueId);
    ob_start();
    ?>
    <div class="currency-picker" data-price-usd="<?= htmlspecialchars($usdPrice) ?>">
        <select class="currency-picker-select" data-target="currency-result-<?= $uid ?>">
            <option value="USD">USD ($)</option>
            <option value="INR">INR (₹)</option>
            <option value="EUR">EUR (€)</option>
            <option value="GBP">GBP (£)</option>
            <option value="AUD">AUD (A$)</option>
            <option value="CAD">CAD (C$)</option>
            <option value="AED">AED</option>
            <option value="SGD">SGD (S$)</option>
            <option value="JPY">JPY (¥)</option>
            <option value="CNY">CNY (¥)</option>
        </select>
        <div class="currency-picker-result" id="currency-result-<?= $uid ?>" style="display:none;"></div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<style>
.currency-picker {
    margin-top: 4px;
    margin-bottom: 6px;
}
.currency-picker-select {
    padding: 3px 8px;
    font-size: 12px;
    border-radius: 6px;
    border: 1px solid #c9a75c;
    background: #fffdf7;
    color: #5a3a24;
    cursor: pointer;
}
.currency-picker-result {
    font-size: 12.5px;
    color: #b4811d;
    font-weight: 600;
    margin-top: 3px;
}
.currency-picker-formula {
    display: block;
    font-size: 10.5px;
    color: #8a7444;
    font-style: italic;
    font-weight: 400;
    margin-top: 1px;
}
</style>

<script>
(function () {
    const CURRENCY_RATES = {
        USD: { rate: 1,      symbol: '$',    name: 'US Dollar' },
        INR: { rate: 83.00,  symbol: '₹',    name: 'Indian Rupee' },
        EUR: { rate: 0.92,   symbol: '€',    name: 'Euro' },
        GBP: { rate: 0.79,   symbol: '£',    name: 'British Pound' },
        AUD: { rate: 1.52,   symbol: 'A$',   name: 'Australian Dollar' },
        CAD: { rate: 1.36,   symbol: 'C$',   name: 'Canadian Dollar' },
        AED: { rate: 3.67,   symbol: 'AED ', name: 'UAE Dirham' },
        SGD: { rate: 1.34,   symbol: 'S$',   name: 'Singapore Dollar' },
        JPY: { rate: 149.50, symbol: '¥',    name: 'Japanese Yen' },
        CNY: { rate: 7.24,   symbol: '¥',    name: 'Chinese Yuan' },
    };

    function applyConversion(selectEl) {
        const resultEl = document.getElementById(selectEl.getAttribute('data-target'));
        const usd = parseFloat(selectEl.closest('.currency-picker').getAttribute('data-price-usd')) || 0;
        const currency = selectEl.value;
        const info = CURRENCY_RATES[currency];

        if (!info || currency === 'USD') {
            resultEl.style.display = 'none';
            return;
        }

        const converted = usd * info.rate;
        resultEl.innerHTML =
            `≈ ${info.symbol}${converted.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}` +
            `<span class="currency-picker-formula">1 USD = ${info.symbol}${info.rate.toLocaleString()} (${info.name})</span>`;
        resultEl.style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.currency-picker-select').forEach(function (select) {
            select.addEventListener('change', function () { applyConversion(this); });
        });
    });
})();
</script>