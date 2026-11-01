<?php
/**
 * api/chatbot/support.php
 * Mirrors chatbot_api.php's `submit_support_request` case exactly - same
 * topic labels, same two emails (confirmation to the customer, notify the
 * support inbox) with the same copy. Works identically for guests and
 * logged-in users. Public, POST {email, topic}.
 */
require_once __DIR__ . '/../config.php';

$user = current_user($conn); // auth optional - only affects the "who" line in the support-inbox email
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$supportEmail = trim((string) ($input['email'] ?? ''));
$topic = trim((string) ($input['topic'] ?? 'other'));

if ($supportEmail === '' || !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('VALIDATION_ERROR', 'Please enter a valid email address.', 400);
}

$topicLabels = [
    'order_status' => 'Order Status / Tracking',
    'payment' => 'Payment Issue',
    'returns' => 'Returns / Refunds',
    'product' => 'Product Question',
    'other' => 'Something Else',
];
$topicLabel = $topicLabels[$topic] ?? 'Something Else';
$whoLine = $user ? "Logged-in user (user_id {$user['user_id']})" : "Guest (not logged in)";

// 1. Confirmation to the customer.
$confirmationHtml = "<p>Hi,</p>
                <p>Thanks for reaching out about <strong>" . htmlspecialchars($topicLabel) . "</strong>.
                Our support team has received your request and will contact you at this email shortly.</p>
                <p>— Araku Tribe Support</p>";
$confirmationSent = send_mail($supportEmail, 'We received your support request - Araku Tribe', $confirmationHtml);

// 2. Notify the support inbox so a human can follow up.
$notifyHtml = "<p>New support request from the chat assistant.</p>
                <p><strong>Topic:</strong> " . htmlspecialchars($topicLabel) . "</p>
                <p><strong>Customer email:</strong> " . htmlspecialchars($supportEmail) . "</p>
                <p><strong>Account:</strong> " . htmlspecialchars($whoLine) . "</p>";
send_mail(AGENT_SMTP_FROM_EMAIL, "New chat support request: {$topicLabel}", $notifyHtml);

if (!$confirmationSent) {
    json_error('MAIL_FAILED', 'Could not send the confirmation email right now.', 502);
}

json_ok(['message' => 'Support request received.']);
