<?php
$host = "localhost";
$user = "a17578a4_mywebsite_user";       // your cPanel DB user
$pass = "Sagar@123";       // your cPanel DB password
$db   = "a17578a4_mywebsite_db";         // your cPanel DB name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
