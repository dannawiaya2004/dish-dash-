<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chef_id = intval($_POST['chef_id']);
    $action = $_POST['action'] === 'add' ? 'add' : 'remove';
    $user_id = $_SESSION['user_id'];
    
    if ($action === 'add') {
        $query = "INSERT INTO fav_chef (user_id, chef_id, date) VALUES ('$user_id', '$chef_id', NOW())";
    } else {
        $query = "DELETE FROM fav_chef WHERE user_id = '$user_id' AND chef_id = '$chef_id'";
    }
    
    if ($conn->query($query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit();
}

header("HTTP/1.1 400 Bad Request");
exit();
?>