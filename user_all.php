<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$users = [];
$result = $conn->query("
SELECT 
    u.UserID, 
    u.name, 
    u.email, 
    u.status, 
    CASE 
        WHEN c.ChefID IS NOT NULL THEN 'Chef' 
        ELSE 'Customer' 
    END AS role
FROM 
    user u
LEFT JOIN 
    chef c ON u.UserID = c.user_id
WHERE 
    u.UserID NOT IN (67)
ORDER BY 
    u.created_at DESC;

");

while ($row = $result->fetch_assoc()) {
    echo '<tr>
            <td>'.htmlspecialchars($row['UserID']).'</td>
            <td>'.htmlspecialchars($row['name']).'</td>
            <td>'.htmlspecialchars($row['email']).'</td>
            <td>
                <span class="badge '.($row['role'] === 'Chef' ? 'bg-info' : 'bg-success').'">
                    '.htmlspecialchars($row['role']).'
                </span>
            </td>
            <td>
                <span class="badge '.($row['status'] === 'active' ? 'bg-success' : 'bg-danger').'">
                    '.htmlspecialchars(ucfirst($row['status'])).'
                </span>
            </td>
            <td>
                <a href="update_user_status.php?id='.$row['UserID'].'&status='.($row['status'] === 'active' ? 'inactive' : 'active').'" 
                   class="btn btn-sm btn-danger action-btn" data-action="update-status">
                    <i class="fas fa-ban"></i> '.($row['status'] === 'active' ? 'Ban' : 'Unban').'
                    <div class="action-loader"></div>
                </a>
            </td>
          </tr>';
}
?>