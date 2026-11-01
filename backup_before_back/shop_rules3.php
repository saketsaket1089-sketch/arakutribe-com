<?php
/* =====================================================================
   ARAKU TRIBE - shop_rules3.php   (NEW - one place for shop rules)
   Offers, combo discount and combo coupons live HERE, so every page,
   the cart and the checkout use the same rules.
   Used by: index3, offers3, hotdeals3, combos3, coffee_bag3/cup3/mug3/tshirt3,
            combo_cart3.php and cart3.php.
   ===================================================================== */

// OFFERS - real discounts only (shown on every page).
//   'ATMUG001' => ['type' => 'percent', 'value' => 20],   // 20% off
//   'ATBAG003' => ['type' => 'bogo'],                     // buy 1 get 1 free
$SHOP_OFFERS = [];
// While $SHOP_OFFERS is empty, localhost shows labelled SAMPLE offers so you can see the design.
$SHOP_PREVIEW_OFFERS_ON_LOCALHOST = true;

// COMBO SETS
$SHOP_COMBO_DISCOUNT_PERCENT = 0;   // % off every combo set (0 = normal combined price)
$SHOP_COMBO_STEP             = 100; // sets are bought in steps of 100 (= 100 units of each item)

// COMBO COUPONS - only for combo sets, typed in the cart ("Have a coupon?").
// A coupon is NOT applied to a combo that contains a product already on offer.
//   'COMBO10' => ['percent' => 10, 'text' => '10% off your combo'],
$SHOP_COMBO_COUPONS = [];
// While $SHOP_COMBO_COUPONS is empty, localhost accepts the sample code SAMPLE10 (10% off) for testing.

/* ---------------- no need to edit below ---------------- */
if (!function_exists('shop_is_local')) {
    function shop_is_local() { return in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'], true); }
}
if (!isset($SHOP_COUPONS_PREVIEW)) {
    $SHOP_COUPONS_PREVIEW = false;
    if (!$SHOP_COMBO_COUPONS && shop_is_local()) {
        $SHOP_COMBO_COUPONS = ['SAMPLE10' => ['percent' => 10, 'text' => '10% off your combo']];
        $SHOP_COUPONS_PREVIEW = true;
    }
}
if (!function_exists('shop_offer_map')) {
    /* Offers for each product id: the real list, or (localhost only) the same sample offers the pages show.
       $rows = products in "ORDER BY category ASC, product_id DESC" order, each with product_id, image, quantity. */
    function shop_offer_map($rows) {
        global $SHOP_OFFERS, $SHOP_PREVIEW_OFFERS_ON_LOCALHOST;
        if ($SHOP_OFFERS) return $SHOP_OFFERS;
        if (!$SHOP_PREVIEW_OFFERS_ON_LOCALHOST || !shop_is_local()) return [];
        $map = []; $tiers = [10, 15, 20, 25];
        foreach ($rows as $r) {
            $id = (string)$r['product_id'];
            if (!preg_match('/^' . preg_quote($id, '/') . '\.(jpg|jpeg|png|gif|webp)$/i', (string)$r['image'])) continue;
            if ((int)$r['quantity'] <= 0) continue;
            $seed = crc32($id);
            if ($seed % 3) continue;
            $map[$id] = ($seed >> 4) % 4 === 0 ? ['type' => 'bogo'] : ['type' => 'percent', 'value' => $tiers[($seed >> 6) % 4]];
            if (count($map) >= 8) break;
        }
        return $map;
    }
    function shop_offer_map_db($conn) {
        static $cache = null;
        if ($cache !== null) return $cache;
        $rows = [];
        $res = $conn->query("SELECT product_id, image, quantity FROM products ORDER BY category ASC, product_id DESC");
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $cache = shop_offer_map($rows);
    }
}