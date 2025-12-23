<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$result = $conn->query("
    SELECT c.ChefID, u.name, c.specialties, c.status 
    FROM chef c
    JOIN user u ON c.user_id = u.UserID
    ORDER BY c.status, u.name
");

while ($row = $result->fetch_assoc()) {
    echo '<tr>
            <td>'.htmlspecialchars($row['ChefID']).'</td>
            <td>'.htmlspecialchars($row['name']).'</td>
            <td>'.htmlspecialchars($row['specialties']).'</td>
            <td>
                <span class="badge '.($row['status'] === 'approved' ? 'bg-success' : ($row['status'] === 'pending' ? 'bg-warning' : 'bg-danger')).'">
                    '.htmlspecialchars(ucfirst($row['status'])).'
                </span>
            </td>
            <td>';
    
    if ($row['status'] === 'pending') {
        echo '<a href="update_chef_status.php?id='.$row['ChefID'].'&status=approved" class="btn btn-sm btn-success action-btn" data-action="update-status">
                <i class="fas fa-check"></i> Approve
                <div class="action-loader"></div>
              </a>
              <a href="update_chef_status.php?id='.$row['ChefID'].'&status=declined" class="btn btn-sm btn-danger action-btn" data-action="update-status">
                <i class="fas fa-times"></i> Reject
                <div class="action-loader"></div>
              </a>';
    } elseif ($row['status'] === 'approved') {
        echo '<a href="update_chef_status.php?id='.$row['ChefID'].'&status=declined" class="btn btn-sm btn-danger action-btn" data-action="update-status">
                <i class="fas fa-times"></i> Revoke Approval
                <div class="action-loader"></div>
              </a>';
    } else {
        echo '<a href="update_chef_status.php?id='.$row['ChefID'].'&status=approved" class="btn btn-sm btn-success action-btn" data-action="update-status">
                <i class="fas fa-check"></i> Approve
                <div class="action-loader"></div>
              </a>';
    }
    
    echo '</td>
          </tr>';
}
?>