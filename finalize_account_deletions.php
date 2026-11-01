<?php
/**
 * finalize_account_deletions.php
 * ─────────────────────────────────────────────────────────────
 * Run this on a schedule (e.g. once a day via cron) to permanently
 * anonymize any account whose 30-day grace period has expired.
 *
 * This does NOT delete the users row itself (that would break the
 * foreign key relationship to `orders`, wiping out order history
 * integrity). Instead it scrubs personal fields and flips
 * account_status to 'deleted', while leaving the row (and their
 * past orders) intact for accounting/legal records, per
 * terms_deletion.php section 6.
 *
 * Example crontab entry (daily at 3am):
 *   0 3 * * * php /full/path/to/finalize_account_deletions.php >> /full/path/to/deletion_log.txt 2>&1
 *
 * Can also just be opened once manually in a browser to test it,
 * but it's meant to run unattended via cron/task scheduler.
 */

include 'db1.php';

$GRACE_PERIOD_DAYS = 30;

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error . "\n");
}

$stmt = $conn->prepare(
    "SELECT user_id, email
     FROM users
     WHERE account_status = 'pending_deletion'
       AND deletion_requested_at IS NOT NULL
       AND deletion_requested_at <= (NOW() - INTERVAL ? DAY)"
);
$stmt->bind_param("i", $GRACE_PERIOD_DAYS);
$stmt->execute();
$result = $stmt->get_result();

$count = 0;
while ($row = $result->fetch_assoc()) {
    $uid = $row['user_id'];

    $update = $conn->prepare(
        "UPDATE users SET
            firstname = 'Deleted',
            lastname  = 'User',
            email     = CONCAT('deleted_user_', ?, '@arakutribe.invalid'),
            phonenumber = NULL,
            area = NULL,
            landmark = NULL,
            zipcode = NULL,
            profile_picture = NULL,
            password = ?,
            account_status = 'deleted'
         WHERE user_id = ?"
    );
    // Overwrite the password hash with a random, unusable value so the
    // old credentials can never be used to log back in.
    $unusable_password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $update->bind_param("isi", $uid, $unusable_password, $uid);
    $update->execute();
    $update->close();

    // Clear their cart, if any - nothing left to check out with.
    $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $clear_cart->bind_param("i", $uid);
    $clear_cart->execute();
    $clear_cart->close();

    echo "Anonymized user_id {$uid} (was {$row['email']})\n";
    $count++;
}
$stmt->close();
$conn->close();

echo "Done. {$count} account(s) permanently anonymized.\n";