<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$chef_check = mysqli_query($conn, "SELECT ChefID FROM chef WHERE user_id = $user_id");
if (mysqli_num_rows($chef_check) == 0) {
    $_SESSION['error'] = "You need to be a chef to perform this action";
    header("Location: login.php");
    exit();
}

// Check if recipe ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No recipe specified";
    header("Location: chef.php");
    exit();
}

$recipe_id = intval($_GET['id']);
$chef_row = mysqli_fetch_assoc($chef_check);
$chef_id = $chef_row['ChefID'];

//the recipe belongs to this chef
$recipe_check = mysqli_query($conn, "SELECT * FROM recipe WHERE RecipeID = $recipe_id AND chefs_id = $chef_id");
if (mysqli_num_rows($recipe_check) == 0) {
    $_SESSION['error'] = "Recipe not found or you don't have permission to modify it";
    header("Location: chef.php");
    exit();
}

 
$archive_query = "UPDATE recipe SET status = 'archived' WHERE RecipeID = $recipe_id AND chefs_id = $chef_id";
if (mysqli_query($conn, $archive_query)) {
    $_SESSION['success_message'] = "Recipe has been archived successfully";
} else {
    $_SESSION['error'] = "Error archiving recipe: " . mysqli_error($conn);
}

header("Location: chef.php");
exit();
?>