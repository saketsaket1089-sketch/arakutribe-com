<?php
/**
 * api/lib/profile.php
 * Shapes a `users` row into the API's profile JSON, never including the
 * password hash.
 */

function profile_picture_url($filename) {
    if (empty($filename)) return null;
    return 'http://localhost/arakutribe.com/uploads/profile_pictures/' . $filename;
}

function format_profile(array $user) {
    return [
        'user_id' => (int) $user['user_id'],
        'firstname' => $user['firstname'],
        'lastname' => $user['lastname'],
        'email' => $user['email'],
        'phonenumber' => $user['phonenumber'],
        'area' => $user['area'],
        'landmark' => $user['landmark'],
        'zipcode' => $user['zipcode'],
        'profile_picture' => $user['profile_picture'],
        'profile_picture_url' => profile_picture_url($user['profile_picture']),
        'preferred_currency' => $user['preferred_currency'],
        'account_status' => $user['account_status'],
    ];
}
