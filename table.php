<?php
include('db.php');

// Query to fetch data from recipe, cuisine_type, and user through the chef table
$sql = "SELECT 
    r.title AS RecipeName, 
    r.cooking_time, 
    ct.name AS CuisineType, 
    u.name AS ChefName
FROM recipe r
LEFT JOIN cuisine_type ct ON r.cuisine_typeID = ct.cuisineID
LEFT JOIN chef ch ON r.chefs_id = ch.ChefID
LEFT JOIN user u ON ch.user_id = u.UserID";

$result = $conn->query($sql); // Changed $con to $conn

// Check for query errors
if (!$result) {
    die('Query failed: ' . $conn->error); // Changed $con to $conn
}
?>

<div class="table-responsive mt-4">
    <table class="table table-hover" id="recipe-table">
        <thead class="thead-dark">
            <tr>
                <th>Recipe Name</th>
                <th>Duration (min)</th>
                <th>Cuisine Type</th>
                <th>Chef Name</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['RecipeName']); ?></td>
                    <td><?php echo htmlspecialchars($row['cooking_time']); ?></td>
                    <td><?php echo htmlspecialchars($row['CuisineType']); ?></td>
                    <td><?php echo htmlspecialchars($row['ChefName']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>