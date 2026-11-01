<?php
session_start();

// CAPTCHA settings
$length = 6;
$width = 150;
$height = 40;
$font_size = 20;

// Generate a random string
$characters = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
$captcha_string = '';
for ($i = 0; $i < $length; $i++) {
    $captcha_string .= $characters[rand(0, strlen($characters) - 1)];
}

// Store the CAPTCHA string in the session for validation
$_SESSION['captcha'] = $captcha_string;

// Create the image
$image = imagecreate($width, $height);

// Set colors
$background_color = imagecolorallocate($image, 255, 248, 240); // Light cream/white background
$text_color = imagecolorallocate($image, 107, 62, 38); // Coffee-like brown
$line_color = imagecolorallocate($image, 180, 180, 180); // Light gray for noise

// Fill background
imagefill($image, 0, 0, $background_color);

// Add some random lines and dots for noise
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand() % $height, $width, rand() % $height, $line_color);
}
for ($i = 0; $i < 500; $i++) {
    imagesetpixel($image, rand() % $width, rand() % $height, $line_color);
}

// Add the CAPTCHA text to the image (Use a system font path if 'arial.ttf' fails)
$font = dirname(__FILE__) . '/arial.ttf'; // Replace with a real font path or just use imagestring if no font is available
if (!file_exists($font)) {
    // Fallback if the font file is not found (less secure/stylish, but functional)
    imagestring($image, 5, 45, 10, $captcha_string, $text_color);
} else {
    // Add text using the font
    imagettftext($image, $font_size, 0, 10, 30, $text_color, $font, $captcha_string);
}

// Output the image
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
?>