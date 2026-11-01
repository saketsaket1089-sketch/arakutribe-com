<?php
session_start();
require_once 'vendor/autoload.php';
include("db1.php");  // ✅ Make sure your DB connection is here

use Google\Client;
use Google\Service\Oauth2;

$client = new Client();
$client->setClientId('22079492185-rmucf16lvs426f41lucibers373m4eeo.apps.googleusercontent.com'');
$client->setClientSecret('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');
$client->setRedirectUri('https://www.arakutribe.com/user-callback.php');


$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (!isset($token['error'])) {
        // ✅ Clear any old user session
session_unset();
session_destroy();
session_start(); // Start fresh session again

        $client->setAccessToken($token);

        $oauth = new Oauth2($client);
        $userInfo = $oauth->userinfo->get();

        $google_email = $userInfo->email;
        $google_name = $userInfo->name;
        $google_first_name = $userInfo->givenName ?? '';
        $google_last_name = $userInfo->familyName ?? '';
        $google_picture = $userInfo->picture ?? '';

        // ✅ Check if user exists in DB
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $google_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // ✅ Insert user into DB
            $insert = $conn->prepare("INSERT INTO users (firstname, lastname, email, phonenumber) VALUES (?, ?, ?, '')");
            $insert->bind_param("sss", $google_first_name, $google_last_name, $google_email);
            if (!$insert->execute()) {
                die("DB insert failed: " . $insert->error);
            }
            $user_id = $insert->insert_id;
        } else {
            $row = $result->fetch_assoc();
            $user_id = $row['user_id'];
        }

        // ✅ Store user info in session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user'] = [
            'name' => $google_name,
            'email' => $google_email,
            'picture' => $google_picture
        ];

        // ✅ Redirect to homepage
        // header('Location: index.php');
        header('Location: index.php');
        exit;
    } else {
        echo "⚠️ Token error: " . htmlspecialchars($token['error']);
    }
} else {
    echo "⚠️ No code received.";
}
