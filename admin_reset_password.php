<?php
include 'db1.php';

$message = ""; // to hold success or error messages
$showForm = true; // control form visibility

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['email']) && !empty($_POST['new_password'])) {
        $email = $_POST['email'];
        $new_password = $_POST['new_password'];

        // Password validation pattern
        $pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/";

        if (!preg_match($pattern, $new_password)) {
            $message = "❌ Password must be at least 8 characters long and include at least one lowercase letter, one uppercase letter, one digit, and one special character.";
        } else {
            $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                $message = "❌ No admin found with that email.";
            } else {
                $stmt->close();

                $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $new_password_hashed, $email);

                if ($update_stmt->execute()) {
                    $message = "Password reset successful. <a href='adminlogin.php'>Login Now</a>";
                    $showForm = false;
                } else {
                    $message = "❌ Failed to reset password: " . $update_stmt->error;
                }

                $update_stmt->close();
            }
        }
    } else {
        $message = "❌ Please provide both email and new password.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>

<?php
if ($message !== "") {
    echo "<p>$message</p>";
}

if ($showForm):
?>

<form method="POST" action="">
    <input type="email" name="email" required placeholder="Enter email"><br><br>
    <input type="password" name="new_password" required placeholder="Enter new password"><br><br>
    <!-- <small>Password must be at least 8 characters long and include at least one uppercase, one lowercase, one number, and one special character.</small><br><br> -->
    <input type="submit" value="Reset Password">
</form>

<?php endif; ?>

</body>
</html>
