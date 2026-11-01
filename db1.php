<?php
$servername = "localhost";
$host = $servername; 
$user = 'root';       // your MySQL username
$pass = '';           // your MySQL password
$db   = 'arakucoffee';

$conn = new mysqli($servername, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
