<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$result = $conn->query("
    SELECT r.RecipeID, r.title, u.name AS chef_name, r.status, r.created_at
    FROM recipe r
    JOIN chef c ON r.chefs_id = c.ChefID
    JOIN user u ON c.user_id = u.UserID
    ORDER BY r.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    echo '<tr>
            <td>'.htmlspecialchars($row['RecipeID']).'</td>
            <td>'.htmlspecialchars($row['title']).'</td>
            <td>'.htmlspecialchars($row['chef_name']).'</td>
            <td>
                <span class="badge '.($row['status'] === 'published' ? 'bg-success' : ($row['status'] === 'archived' ? 'bg-danger' : 'bg-warning')).'">
                    '.htmlspecialchars(ucfirst($row['status'])).'
                </span>
            </td>
            <td>';
    
    if ($row['status'] === 'pending') {
        echo '<a href="update_recipe_status.php?id='.$row['RecipeID'].'&status=published" class="btn btn-sm btn-success action-btn" data-action="update-status">
                <i class="fas fa-check"></i> Publish
                <div class="action-loader"></div>
              </a>
              <a href="update_recipe_status.php?id='.$row['RecipeID'].'&status=rejected" class="btn btn-sm btn-danger action-btn" data-action="update-status">
                <i class="fas fa-times"></i> Reject
                <div class="action-loader"></div>
              </a>';
    } elseif ($row['status'] === 'archived') {
        echo '<a href="update_recipe_status.php?id='.$row['RecipeID'].'&status=published" class="btn btn-sm btn-success action-btn" data-action="update-status">
                <i class="fas fa-undo"></i> Re-publish
                <div class="action-loader"></div>
              </a>
                     <a href="admin_edit_recipe.php?id='. $row['RecipeID'] .'" class="btn btn-sm btn-primary">
      <i class="fas fa-edit"></i> Edit
    </a>';
    } else {
        echo '<a href="update_recipe_status.php?id='.$row['RecipeID'].'&status=archived" class="btn btn-sm btn-danger action-btn" data-action="update-status">
                <i class="fas fa-ban"></i> Archive
                <div class="action-loader"></div>
              </a>
                     <a href="admin_edit_recipe.php?id= '.$row['RecipeID'] .'" class="btn btn-sm btn-primary">
      <i class="fas fa-edit"></i> Edit
    </a>';
    }
    
    echo '</td>
          </tr>';
}
?>