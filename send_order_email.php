<?php
// NOTE: Make sure PHPMailer files are correctly installed via Composer or manual download
// and the paths below are correct.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ADJUST THESE PATHS to match your installation
require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

function sendOrderConfirmationEmail($recipient_email, $order_id, $total, $address, $items) {
    $mail = new PHPMailer(true);

    try {
        // Server settings - *** UPDATE THESE CREDENTIALS ***
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';            // Specify main SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'vegiprasanthi697@gmail.com'; // SMTP username
        $mail->Password = 'bhfq begl ekux oppn';              // SMTP password/App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use ENCRYPTION_SMTPS for port 465
        $mail->Port = 587;                                   // TCP port to connect to (587 for TLS, 465 for SSL)
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom('no-reply@yourcoffeeshop.com', 'Your Coffee Shop');
        $mail->addAddress($recipient_email);
        $mail->addReplyTo('support@yourcoffeeshop.com', 'Support Team');

        // Content preparation
        $item_list = "<table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                        <tr>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Item</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Qty</th>
                            <th style='border: 1px solid #ddd; padding: 8px;'>Price</th>
                        </tr>";
        
        foreach ($items as $item) {
            $subtotal = $item['qty'] * $item['price'];
            $item_list .= "<tr>
                            <td style='border: 1px solid #ddd; padding: 8px;'>{$item['name']}</td>
                            <td style='border: 1px solid #ddd; padding: 8px;'>{$item['qty']}</td>
                            <td style='border: 1px solid #ddd; padding: 8px;'>$" . number_format($subtotal, 2) . "</td>
                        </tr>";
        }
        $item_list .= "</table>";

        $mail->isHTML(true);
        $mail->Subject = "✅ Coffee Order #{$order_id} Confirmed!";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f9f9f9;'>
                <h2 style='color: #6b3e26;'>Thank You for Your Coffee Order!</h2>
                <p>Your order (ID: <strong>#{$order_id}</strong>) has been successfully placed and is now being prepared.</p>
                
                <h3 style='color: #6b3e26;'>Order Details</h3>
                <p><strong>Shipping Address:</strong> {$address}</p>
                <p><strong>Total Charged:</strong> <span style='color: #4CAF50; font-weight: bold;'>$" . number_format($total, 2) . "</span></p>

                <h3 style='color: #6b3e26;'>Items Ordered:</h3>
                {$item_list}
                
                <p>We'll notify you again when your order ships.</p>
                <p>Best,<br>The Coffee Shop Team</p>
            </div>
        ";
        $mail->AltBody = "Order #{$order_id} Confirmed! Total: $$total. Shipping to: {$address}. Thank you!";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error to a file for debugging, do not show sensitive details to the user
        error_log("PHPMailer Error for Order #$order_id: {$mail->ErrorInfo}");
        return false;
    }
}
?>