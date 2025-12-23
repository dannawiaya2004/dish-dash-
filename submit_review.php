<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review']);
    exit();
}

if (!isset($_POST['recipe_id']) || !isset($_POST['rating']) || !isset($_POST['comment'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$recipe_id = (int)$_POST['recipe_id'];
$rating = (int)$_POST['rating'];
$comment = $conn->real_escape_string($_POST['comment']);
$user_id = (int)$_SESSION['user_id'];

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit();
}

$checkQuery = "SELECT * FROM rate WHERE user_id = $user_id AND recipe_id = $recipe_id";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows > 0) {
    $sql = "UPDATE rate SET rating = $rating, comment = '$comment', date = NOW() 
            WHERE user_id = $user_id AND recipe_id = $recipe_id";
} else {
    $sql = "INSERT INTO rate (user_id, recipe_id, rating, comment, date, status) 
            VALUES ($user_id, $recipe_id, $rating, '$comment', NOW(), 'published')";
}

if ($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>