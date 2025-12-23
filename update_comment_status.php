<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_POST['user_id']) || !isset($_POST['recipe_id']) || !isset($_POST['date']) || !isset($_POST['status'])) {
    header("Location: main.php?error=missing_params");
    exit();
}

$user_id = (int)$_POST['user_id'];
$recipe_id = (int)$_POST['recipe_id'];
$date = $conn->real_escape_string($_POST['date']);
$new_status = $conn->real_escape_string($_POST['status']);

$sql = "UPDATE rate SET status = '$new_status' 
        WHERE user_id = $user_id AND recipe_id = $recipe_id AND date = '$date'";

if ($conn->query($sql)) {
    header("Location: admin.php?success=comment_updated");
} else {
    header("Location: admin.php?error=db_error");
}

$conn->close();
?>