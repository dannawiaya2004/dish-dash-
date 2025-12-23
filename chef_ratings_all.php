<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$result = $conn->query("
    SELECT rc.user_id, rc.chef_id, u.name AS user_name, ch.name AS chef_name, 
           rc.comment, rc.rating
    FROM rate_chef rc
    JOIN user u ON rc.user_id = u.UserID
    JOIN (
        SELECT c.ChefID, u.name 
        FROM chef c
        JOIN user u ON c.user_id = u.UserID
    ) ch ON rc.chef_id = ch.ChefID
    ORDER BY rc.date DESC
");

while ($row = $result->fetch_assoc()) {
    echo '<tr>
            <td>'.htmlspecialchars($row['user_name']).'</td>
            <td>'.htmlspecialchars($row['chef_name']).'</td>
            <td>'.htmlspecialchars($row['comment']).'</td>
            <td>';
    
    for ($i = 0; $i < 5; $i++) {
        echo '<i class="fas fa-star '.($i < $row['rating'] ? 'text-warning' : 'text-secondary').'"></i>';
    }
    
   
}
?>