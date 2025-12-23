<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$result = $conn->query("
    SELECT r.user_id, r.recipe_id, u.name AS user_name, re.title AS recipe_title, 
           r.comment, r.rating, r.date, r.status
    FROM rate r
    JOIN user u ON r.user_id = u.UserID
    JOIN recipe re ON r.recipe_id = re.RecipeID
    ORDER BY r.date DESC
");

while ($row = $result->fetch_assoc()) {
    echo '<tr>
            <td>'.htmlspecialchars($row['user_name']).'</td>
            <td>'.htmlspecialchars($row['recipe_title']).'</td>
            <td>'.htmlspecialchars($row['comment']).'</td>
            <td>';
    
    for ($i = 0; $i < 5; $i++) {
        echo '<i class="fas fa-star '.($i < $row['rating'] ? 'text-warning' : 'text-secondary').'"></i>';
    }
    
    echo '</td>
            <td>
                <span class="badge '.($row['status'] === 'published' ? 'bg-success' : 'bg-secondary').'">
                    '.htmlspecialchars(ucfirst($row['status'])).'
                </span>
            </td>
            <td>
                <form method="post" action="update_comment_status.php" class="ajax-form">
                    <input type="hidden" name="user_id" value="'.$row['user_id'].'">
                    <input type="hidden" name="recipe_id" value="'.$row['recipe_id'].'">
                    <input type="hidden" name="date" value="'.$row['date'].'">
                    <input type="hidden" name="status" value="'.($row['status'] === 'published' ? 'archived' : 'published').'">
                    <button type="submit" class="btn btn-sm '.($row['status'] === 'published' ? 'btn-danger' : 'btn-success').' action-btn">
                        <i class="fas '.($row['status'] === 'published' ? 'fa-trash' : 'fa-check').'"></i>
                        '.($row['status'] === 'published' ? 'Archive' : 'Publish').'
                        <div class="action-loader"></div>
                    </button>
                </form>
            </td>
          </tr>';
}
?>