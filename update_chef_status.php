<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $chef_id = intval($_GET['id']);
    $status = $conn->real_escape_string($_GET['status']);
    
    $valid_statuses = ['approved', 'declined', 'pending'];
    if (!in_array($status, $valid_statuses)) {
        header("Location: admin.php?error=invalid_status");
        exit();
    }
    
    $query = "UPDATE chef SET status = '$status' WHERE ChefID = $chef_id";
    
    if ($conn->query($query)) {
        header("Location: admin.php?success=chef_updated");
    } else {
        header("Location: admin.php?error=db_error");
    }
} else {
    header("Location: admin.php?error=missing_params");
}

$conn->close();
?>