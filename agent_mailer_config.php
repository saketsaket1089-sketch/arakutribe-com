<?php
/**
 * agent_mailer_config.php
 * ─────────────────────────────────────────────────────────────────
 * SMTP details used ONLY by the chat agent (chatbot_api.php) - kept
 * completely separate from your other mail-sending files
 * (coffee_login.php, resend_otp.php, verify_user_otp.php, etc.),
 * since your project has several different PHPMailer installations
 * and it's easy for them to drift out of sync with each other.
 *
 * ─────────────────────────────────────────────────────────────────
 * HOW TO FILL IN YOUR OWN SERVER EMAIL (instead of a personal Gmail):
 * ─────────────────────────────────────────────────────────────────
 * 1. Create the mailbox first, if you haven't: in cPanel, go to
 *    "Email Accounts" -> Create -> e.g. noreply@arakutribe.com
 *    (or support@arakutribe.com) with a password you choose.
 *
 * 2. Get the exact SMTP settings for THAT mailbox: still in cPanel's
 *    Email Accounts page, click "Connect Devices" (or "Set Up Mail
 *    Client") next to the account you just created. cPanel will show
 *    you the exact recommended values - these are usually:
 *      Host:       mail.yourdomain.com  (sometimes just your domain)
 *      Port:       587 (TLS) or 465 (SSL) - cPanel tells you which
 *      Encryption: tls or ssl - matches whichever port you use
 *    Do NOT guess these - copy them exactly from what cPanel shows,
 *    since every host's mail server hostname differs.
 *
 * 3. Fill in the five constants below with those real values.
 *
 * If your host isn't cPanel, look for "Email Accounts" or "Mail" in
 * your hosting control panel, or check their support docs for
 * "SMTP settings" / "how to send mail via PHP".
 */

define('AGENT_SMTP_HOST',       'aiinfoways.com');   // TODO: from cPanel "Connect Devices"
define('AGENT_SMTP_USERNAME',   'support@hayward.university'); // TODO: the mailbox you created
define('AGENT_SMTP_PASSWORD',   'zh9nCzzB1ds_');  // TODO: that mailbox's password (NOT your cPanel password)
define('AGENT_SMTP_PORT',       587);                      // TODO: confirm from cPanel - 587 (TLS) or 465 (SSL)
define('AGENT_SMTP_SECURE',     'tls');                    // TODO: 'tls' for port 587, 'ssl' for port 465
define('AGENT_SMTP_FROM_EMAIL', 'info@aiinfoways.com', 'Araku Tribe'); // usually same as AGENT_SMTP_USERNAME
define('AGENT_SMTP_FROM_NAME',  'Araku Tribe');

// Always load PHPMailer from this exact folder - confirmed present in$mail->isSMTP();
               
               
// your project root. If this path is ever wrong, PHP will throw a clear
// "failed to open stream" error naming this exact line, rather than a
// vague mail-sending failure.
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

/**
 * Returns a ready-to-use PHPMailer instance, pre-configured with the
 * agent's SMTP settings. Caller just sets addAddress(), Subject, Body,
 * then calls send().
 *
 * @param bool $debug If true, logs the full SMTP conversation to
 *                     agent_mail_debug.log next to this file - turn
 *                     on temporarily while diagnosing, off once fixed.
 */
function get_agent_mailer($debug = false) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = AGENT_SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = AGENT_SMTP_USERNAME;
    $mail->Password   = AGENT_SMTP_PASSWORD;
    $mail->SMTPSecure = AGENT_SMTP_SECURE;
    $mail->Port       = AGENT_SMTP_PORT;
    $mail->setFrom(AGENT_SMTP_FROM_EMAIL, AGENT_SMTP_FROM_NAME);
    $mail->isHTML(true);

    if ($debug) {
        $mail->SMTPDebug   = 2;
        $mail->Debugoutput = function ($str, $level) {
            file_put_contents(
                __DIR__ . '/agent_mail_debug.log',
                '[' . date('Y-m-d H:i:s') . "] $str\n",
                FILE_APPEND
            );
        };
    }

    return $mail;
}