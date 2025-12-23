<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chef_id = intval($_POST['chef_id']);
    $rating = intval($_POST['rating']);
    $comment = $conn->real_escape_string(trim($_POST['comment']));
    $user_id = $_SESSION['user_id'];
    $date = date('Y-m-d H:i:s');
    
    if ($rating < 1 || $rating > 5 || empty($comment)) {
        $_SESSION['error'] = 'Please provide both a rating and comment';
        header("Location: chef_profile.php?id=$chef_id");
        exit();
    }
    
    // Insert review
    $query = "INSERT INTO rate_chef (user_id, chef_id, rating, comment, date) 
              VALUES ('$user_id', '$chef_id', '$rating', '$comment', '$date')";
    
    if ($conn->query($query)) {
        $_SESSION['success'] = 'Review submitted successfully!';
    } else {
        $_SESSION['error'] = 'Error submitting review: ' . $conn->error;
    }
    
    header("Location: chef_profile.php?id=$chef_id");
    exit();
}

header("Location: chefs.php");
exit();
?>