<?php
session_start();
require 'db.php';

$user_id = $_SESSION['user_id'];
$chef_check = mysqli_query($conn, "SELECT ChefID FROM chef WHERE user_id = $user_id");
if (mysqli_num_rows($chef_check) == 0) {
    die("Unauthorized");
}
$chef_row = mysqli_fetch_assoc($chef_check);
$current_chef_id = $chef_row['ChefID'];

$meal_type_filter = isset($_GET['meal_type']) ? mysqli_real_escape_string($conn, $_GET['meal_type']) : '';
$cuisine_filter = isset($_GET['cuisine']) ? mysqli_real_escape_string($conn, $_GET['cuisine']) : '';
$difficulty_filter = isset($_GET['difficulty']) ? mysqli_real_escape_string($conn, $_GET['difficulty']) : '';
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$dietary_preferences = isset($_GET['dietary_preferences']) ? $_GET['dietary_preferences'] : [];
$chef_filter = isset($_GET['chef_id']) ? (int)$_GET['chef_id'] : 0;

// Build filter conditions - exclude current chef's recipes
$filter_conditions = ["r.chefs_id != $current_chef_id", "r.status = 'published'"];

if (!empty($meal_type_filter)) {
    $filter_conditions[] = "m.name = '$meal_type_filter'";
}
if (!empty($cuisine_filter)) {
    $filter_conditions[] = "c.name = '$cuisine_filter'";
}
if (!empty($difficulty_filter)) {
    $filter_conditions[] = "r.difficulty_lvl = '$difficulty_filter'";
}
if (!empty($search_term)) {
    $filter_conditions[] = "r.title LIKE '%$search_term%'";
}
if ($chef_filter > 0) {
    $filter_conditions[] = "r.chefs_id = $chef_filter";
}

// Handle dietary preferences if any are selected
if (!empty($dietary_preferences) && is_array($dietary_preferences)) {
    $escaped_dietary = array_map(function($item) use ($conn) {
        return mysqli_real_escape_string($conn, $item);
    }, $dietary_preferences);
    
    $dietary_list = "'" . implode("','", $escaped_dietary) . "'";
    $filter_conditions[] = "r.RecipeID IN (
        SELECT dr.recipe_id 
        FROM dietaryRecipe dr
        JOIN dietary_preferences dp ON dr.dietary_preferences_id = dp.DietaryID
        WHERE dp.name IN ($dietary_list)
    )";
}

$filter_sql = implode(' AND ', $filter_conditions);

$query = "SELECT 
    r.RecipeID, 
    r.title, 
    r.image, 
    r.difficulty_lvl, 
    c.name AS cuisine, 
    m.name AS meal_type,
    u.name AS chef_name
FROM 
    recipe r
JOIN 
    cuisine_type c ON r.cuisine_typeID = c.CuisineID
JOIN 
    meal_types m ON r.meal_typesID = m.MealID
JOIN 
    chef ch ON r.chefs_id = ch.ChefID
JOIN 
    user u ON ch.user_id = u.UserID
WHERE 
    $filter_sql
ORDER BY 
    r.created_at DESC;
";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    while ($recipe = mysqli_fetch_assoc($result)) {
        echo '<div class="col-md-4 mb-4">';
        echo '<div class="card recipe-card">';
        echo '<img src="image/' . htmlspecialchars($recipe['image']) . '" class="card-img-top" alt="' . htmlspecialchars($recipe['title']) . '">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title">' . htmlspecialchars(substr($recipe['title'], 0, 25)) . '...</h5>';
        echo '<p class="card-text">';
        echo 'Chef: ' . htmlspecialchars($recipe['chef_name']) . '<br>';
        echo 'Cuisine: ' . htmlspecialchars($recipe['cuisine']) . '<br>';
        echo 'Difficulty: ' . htmlspecialchars($recipe['difficulty_lvl']) . '<br>';
        echo 'Meal Type: ' . htmlspecialchars($recipe['meal_type']);
        
      
        
        echo '</p>';
        echo '<div class="d-flex action-buttons">';
        echo '<a href="view_recipe(chef).php?id=' . $recipe['RecipeID'] . '" class="btn btn-sm btn-primary me-2"><i class="fas fa-eye me-1"></i> View</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
} else {
    echo '<div class="col-12">';
    echo '<div class="alert alert-info">No community recipes found matching your criteria.</div>';
    echo '</div>';
}
?>