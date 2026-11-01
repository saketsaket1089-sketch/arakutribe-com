<?php
/**
 * api/lib/mailer.php
 * Thin wrapper over agent_mailer_config.php's get_agent_mailer(). Best
 * effort by design (matches existing site behavior): a mail failure never
 * blocks the underlying action - it's logged to api/logs/mail_errors.log
 * and the caller gets a normal success response regardless.
 */

function send_mail($to, $subject, $htmlBody) {
    if (empty($to)) return false;
    try {
        $mail = get_agent_mailer();
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir . '/mail_errors.log',
            '[' . date('Y-m-d H:i:s') . "] to={$to} subject=\"{$subject}\" error=" . $e->getMessage() . "\n",
            FILE_APPEND
        );
        return false;
    }
}
