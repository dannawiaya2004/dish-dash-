<?php
// Database connection settings
$servername = "127.0.0.1"; // localhost
$username = "root";
$password = ""; // replace with your database password
$dbname = "senior";

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>