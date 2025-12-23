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
    $_SESSION['error'] = "You need to create a chef profile first";
    header("Location: signup.php");
    exit();
}
$chef_row = mysqli_fetch_assoc($chef_check);
$chef_id = $chef_row['ChefID'];

// Check if recipe ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No recipe specified";
    header("Location: chef.php");
    exit();
}

$recipe_id = intval($_GET['id']);

// Verify the recipe belongs to the current chef
$recipe_check = mysqli_query($conn, "SELECT * FROM recipe WHERE RecipeID = $recipe_id AND chefs_id = $chef_id");
if (mysqli_num_rows($recipe_check) == 0) {
    $_SESSION['error'] = "Recipe not found or you don't have permission to edit it";
    header("Location: chef.php");
    exit();
}
$recipe = mysqli_fetch_assoc($recipe_check);

// same as in add recipe
function findSimilarIngredient($conn, $ingredient_name) {
    $query = "SELECT IngredientsID, name FROM ingredients WHERE LOWER(name) = LOWER('".mysqli_real_escape_string($conn, $ingredient_name)."')";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    $query = "SELECT IngredientsID, name FROM ingredients";
    $result = mysqli_query($conn, $query);
    
    $bestMatch = null;
    $bestScore = 0;
    
    $pluralizations = [
        's' => '', 
        'es' => '', 
        'ies' => 'y',
        'ves' => 'f'
    ];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $dbName = strtolower($row['name']);
        $inputName = strtolower($ingredient_name);
        
        foreach ($pluralizations as $suffix => $replacement) {
            if (strlen($inputName) > strlen($suffix) && 
                substr($inputName, -strlen($suffix)) === $suffix) {
                $singular = $replacement ? 
                    substr($inputName, 0, -strlen($suffix)) . $replacement : 
                    substr($inputName, 0, -strlen($suffix));
                
                if ($singular === $dbName) {
                    return $row;
                }
            }
            
            if (strlen($dbName) > strlen($suffix) && 
                substr($dbName, -strlen($suffix)) === $suffix) {
                $singular = $replacement ? 
                    substr($dbName, 0, -strlen($suffix)) . $replacement : 
                    substr($dbName, 0, -strlen($suffix));
                
                if ($singular === $inputName) {
                    return $row;
                }
            }
        }
        
        similar_text($dbName, $inputName, $percent);
        
        if ($percent > 85 && $percent > $bestScore) {
            $bestScore = $percent;
            $bestMatch = $row;
        }
    }
    
    return $bestScore > 85 ? $bestMatch : null;
}

$goals = [];
$dietary_prefs = [];
$cuisine_types = [];
$meal_types = [];
$units = ['g', 'kg', 'ml', 'l', 'tsp', 'tbsp', 'cup', 'pinch', 'piece'];

$goal_query = "SELECT * FROM goal";
$dietary_query = "SELECT * FROM dietary_preferences";
$cuisine_query = "SELECT * FROM cuisine_type";
$meal_query = "SELECT * FROM meal_types";

$goal_result = mysqli_query($conn, $goal_query) or die(mysqli_error($conn));
$dietary_result = mysqli_query($conn, $dietary_query) or die(mysqli_error($conn));
$cuisine_result = mysqli_query($conn, $cuisine_query) or die(mysqli_error($conn));
$meal_result = mysqli_query($conn, $meal_query) or die(mysqli_error($conn));

if ($goal_result && mysqli_num_rows($goal_result) > 0) {
    while ($row = mysqli_fetch_assoc($goal_result)) {
        $goals[] = $row;
    }
}

if ($dietary_result && mysqli_num_rows($dietary_result) > 0) {
    while ($row = mysqli_fetch_assoc($dietary_result)) {
        $dietary_prefs[] = $row;
    }
}

if ($cuisine_result && mysqli_num_rows($cuisine_result) > 0) {
    while ($row = mysqli_fetch_assoc($cuisine_result)) {
        $cuisine_types[] = $row;
    }
}

if ($meal_result && mysqli_num_rows($meal_result) > 0) {
    while ($row = mysqli_fetch_assoc($meal_result)) {
        $meal_types[] = $row;
    }
}

// Get current recipe goals
$current_goal = null;
$goal_query = "SELECT goal_id FROM recipe_goal WHERE recipe_id = $recipe_id";
$goal_result = mysqli_query($conn, $goal_query);
if (mysqli_num_rows($goal_result) > 0) {
    $current_goal = mysqli_fetch_assoc($goal_result)['goal_id'];
}

// Get current dietary preferences
$current_dietary = [];
$dietary_query = "SELECT dietary_preferences_id FROM dietaryRecipe WHERE recipe_id = $recipe_id";
$dietary_result = mysqli_query($conn, $dietary_query);
while ($row = mysqli_fetch_assoc($dietary_result)) {
    $current_dietary[] = $row['dietary_preferences_id'];
}

// Get current ingredients
$ingredients = [];
$ingredient_query = "SELECT i.IngredientsID, i.name, i.units, i.image, u.quantity 
                    FROM used u 
                    JOIN ingredients i ON u.ingredient_id = i.IngredientsID 
                    WHERE u.recipe_id = $recipe_id";
$ingredient_result = mysqli_query($conn, $ingredient_query);
while ($row = mysqli_fetch_assoc($ingredient_result)) {
    $ingredients[] = $row;
}

// Get current instructions
$instructions = [];
$instruction_query = "SELECT i.step_nbr, i.cooking_step, img.image 
                     FROM instructions i 
                     LEFT JOIN images img ON i.recipe_id = img.recipe_id AND i.step_nbr = img.step 
                     WHERE i.recipe_id = $recipe_id 
                     ORDER BY i.step_nbr";
$instruction_result = mysqli_query($conn, $instruction_query);
while ($row = mysqli_fetch_assoc($instruction_result)) {
    $instructions[$row['step_nbr']]['cooking_step'] = $row['cooking_step'];
    if ($row['image']) {
        $instructions[$row['step_nbr']]['image'] = $row['image'];
    }
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $cooking_time = intval($_POST['cooking_time']);
    $serving = intval($_POST['serving']); 
    $difficulty = mysqli_real_escape_string($conn, $_POST['difficulty']);
    $goal_id = intval($_POST['goal']);
    $cuisine_type_id = intval($_POST['cuisine_type']);
    $meal_type_id = intval($_POST['meal_type']);
    $video = mysqli_real_escape_string($conn, $_POST['video']);
    $details = mysqli_real_escape_string($conn, $_POST['details']);
    $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date']);
    
    //new image is provided
    $image_changed = false;
    $filename = $recipe['image'];
    if (!empty($_FILES["image"]["name"])) {
        $filename = $_FILES["image"]["name"];
        $tempname = $_FILES["image"]["tmp_name"];
        $folder = "image/" . $filename;
        
        if (!move_uploaded_file($tempname, $folder)) {
            $message = "<div class='alert alert-danger'>Failed to upload image!</div>";
        } else {
            $image_changed = true;
            // Delete old image if it's not the default
            if ($recipe['image'] && $recipe['image'] != 'default.jpg') {
                @unlink("image/" . $recipe['image']);
            }
        }
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Update recipe
        $update_recipe = "UPDATE recipe SET
            title = '$title',
            cooking_time = $cooking_time,
            serving = $serving,
            difficulty_lvl = '$difficulty',
            image = '$filename',
            video = '$video',
            details = '$details',
            expiry_date = " . ($expiry_date ? "'$expiry_date'" : "NULL") . ",
            cuisine_typeID = $cuisine_type_id,
            meal_typesID = $meal_type_id
            WHERE RecipeID = $recipe_id";
        
        if (!mysqli_query($conn, $update_recipe)) {
            throw new Exception("Error updating recipe: " . mysqli_error($conn));
        }
        
        // Handle goals
        mysqli_query($conn, "DELETE FROM recipe_goal WHERE recipe_id = $recipe_id");
        if ($goal_id > 0) {
            $insert_goal = "INSERT INTO recipe_goal (recipe_id, goal_id) 
                VALUES ($recipe_id, $goal_id)";
            if (!mysqli_query($conn, $insert_goal)) {
                throw new Exception("Error inserting goal: " . mysqli_error($conn));
            }
        }
        
        // Handle dietary preferences
        mysqli_query($conn, "DELETE FROM dietaryRecipe WHERE recipe_id = $recipe_id");
        if (isset($_POST['dietary_preferences'])) {
            foreach ($_POST['dietary_preferences'] as $pref_id) {
                $pref_id = intval($pref_id);
                $insert_preference = "INSERT INTO dietaryRecipe (recipe_id, dietary_preferences_id) 
                    VALUES ($recipe_id, $pref_id)";
                if (!mysqli_query($conn, $insert_preference)) {
                    throw new Exception("Error inserting dietary preference: " . mysqli_error($conn));
                }
            }
        }
        
        // Handle ingredients - first delete all existing
        mysqli_query($conn, "DELETE FROM used WHERE recipe_id = $recipe_id");
        
        foreach ($_POST['ingredient_name'] as $key => $ingredient_name) {
            $ingredient_name = trim(mysqli_real_escape_string($conn, $ingredient_name));
            $quantity = floatval($_POST['ingredient_quantity'][$key]);
            $unit = mysqli_real_escape_string($conn, $_POST['ingredient_unit'][$key]);
            
            if (!empty($ingredient_name)) {
                $ingredient_image = "";
                if (!empty($_FILES['ingredient_image']['name'][$key])) {
                    $ingredient_filename = $_FILES["ingredient_image"]["name"][$key];
                    $ingredient_tempname = $_FILES["ingredient_image"]["tmp_name"][$key];
                    $ingredient_folder = "ingredients/" . $ingredient_filename;
                    
                    if (move_uploaded_file($ingredient_tempname, $ingredient_folder)) {
                        $ingredient_image = $ingredient_filename;
                    }
                }
                
                $existing_ingredient = findSimilarIngredient($conn, $ingredient_name);
                
                if ($existing_ingredient) {
                    $ingredient_id = $existing_ingredient['IngredientsID'];
                    
                    if (!empty($ingredient_image)) {
                        $update_image = "UPDATE ingredients SET image = '$ingredient_image' WHERE IngredientsID = $ingredient_id";
                        mysqli_query($conn, $update_image);
                    }
                } else {
                    $ingredient_query = "INSERT INTO ingredients (name, units, image) VALUES ('$ingredient_name', '$unit', " . 
                        (!empty($ingredient_image) ? "'$ingredient_image'" : "NULL") . ")";
                    if (!mysqli_query($conn, $ingredient_query)) {
                        throw new Exception("Error inserting ingredient: " . mysqli_error($conn));
                    }
                    $ingredient_id = mysqli_insert_id($conn);
                }
                
                $insert_ingredient = "INSERT INTO used (ingredient_id, recipe_id, quantity) 
                    VALUES ($ingredient_id, $recipe_id, $quantity)";
                if (!mysqli_query($conn, $insert_ingredient)) {
                    throw new Exception("Error inserting ingredient usage: " . mysqli_error($conn));
                }
            }
        }
        
        // Handle instructions - first delete all existing
        mysqli_query($conn, "DELETE FROM instructions WHERE recipe_id = $recipe_id");
        mysqli_query($conn, "DELETE FROM images WHERE recipe_id = $recipe_id");
        
        foreach ($_POST['instructions'] as $index => $step) {
            if (!empty(trim($step))) {
                $step = mysqli_real_escape_string($conn, $step);
                $step_number = $index + 1;
                
                $insert_instruction = "INSERT INTO instructions (cooking_step, step_nbr, details, recipe_id) 
                    VALUES ('$step', $step_number, '', $recipe_id)";
                if (!mysqli_query($conn, $insert_instruction)) {
                    throw new Exception("Error inserting instruction: " . mysqli_error($conn));
                }
                
                if (!empty($_FILES['instruction_image']['name'][$index])) {
                    $instruction_filename = $_FILES['instruction_image']['name'][$index];
                    $instruction_tempname = $_FILES['instruction_image']['tmp_name'][$index];
                    $instruction_folder = "instructions/" . $instruction_filename;
                    
                    if (move_uploaded_file($instruction_tempname, $instruction_folder)) {
                        $insert_instruction_image = "INSERT INTO images (image, step, recipe_id) 
                            VALUES ('$instruction_filename', $step_number, $recipe_id)";
                        if (!mysqli_query($conn, $insert_instruction_image)) {
                            throw new Exception("Error inserting instruction image: " . mysqli_error($conn));
                        }
                    }
                }
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['success_message'] = "Recipe updated successfully!";
        header("Location: chef.php");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "<div class='alert alert-danger'>" . $e->getMessage() . "</div>";
        
        if ($image_changed && file_exists($folder)) {
            unlink($folder);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dish Dash - Edit Recipe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
             :root{   --dark: #2E8B57;
}
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--dark) !important;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: #333 !important;
            font-weight: 500;
            margin: 0 10px;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--dark) !important;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .hero {
            background-color:rgb(78, 78, 78);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            text-align: center;
        }
        .ingredient-row {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        .ingredient-input {
            flex: 1;
        }
        .ingredient-image-container {
            width: 80px;
            height: 80px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background-color: #f8f9fa;
        }
        .ingredient-image-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ingredient-image-container i {
            font-size: 24px;
            color: #aaa;
        }
        .instruction-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .instruction-image-container {
            width: 100%;
            height: 150px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-top: 10px;
            background-color: white;
        }
        .btn-primary {
            background-color: #FFD700;
            border-color: #FFD700;
        }
        .btn-primary:hover {
            background-color: #FFD700;
            border-color: #FFD700;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        #imagePreview {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 10px;
        }
        .existing-image {
            max-width: 100px;
            max-height: 100px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
   <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="Recipe Book Logo.jpeg" alt="Logo" width="40" height="40" class="rounded-circle"> DishDash
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link " href="chef.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="chefAddRecipe.php"><i class="fas fa-plus-circle"></i> Add Recipe</a>
                    </li>
                  
                    <li class="nav-item">
                        <a class="nav-link" href="chef_pro.php"><i class="fas fa-user"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero">
        <div class="container">
            <h1><i class="fas fa-edit me-2"></i>Edit Recipe</h1>
            <p class="mb-0">Update your culinary masterpiece</p>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-danger"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="chef.php?id=<?php echo $recipe_id; ?>" enctype="multipart/form-data">
                            <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i>Basic Information</h4>
                            <div class="mb-3">
                                <label for="title" class="form-label">Recipe Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                    value="<?php echo htmlspecialchars($recipe['title']); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="cooking_time" class="form-label">Cooking Time (minutes)</label>
                                    <input type="number" class="form-control" id="cooking_time" name="cooking_time" 
                                        value="<?php echo $recipe['cooking_time']; ?>" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="serving" class="form-label">Serving</label>
                                    <input type="number" class="form-control" id="serving" name="serving" 
                                        value="<?php echo $recipe['serving']; ?>" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="difficulty" class="form-label">Difficulty Level</label>
                                    <select class="form-select" id="difficulty" name="difficulty" required>
                                        <option value="">Select difficulty</option>
                                        <option value="Easy" <?php echo $recipe['difficulty_lvl'] == 'Easy' ? 'selected' : ''; ?>>Easy</option>
                                        <option value="Medium" <?php echo $recipe['difficulty_lvl'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="Hard" <?php echo $recipe['difficulty_lvl'] == 'Hard' ? 'selected' : ''; ?>>Hard</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="goal" class="form-label">Goal</label>
                                    <select class="form-select" id="goal" name="goal">
                                        <option value="0">Select a goal (optional)</option>
                                        <?php foreach ($goals as $goal): ?>
                                            <option value="<?php echo $goal['GoalID']; ?>" 
                                                <?php echo $current_goal == $goal['GoalID'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($goal['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cuisine_type" class="form-label">Cuisine Type</label>
                                    <select class="form-select" id="cuisine_type" name="cuisine_type" required>
                                        <option value="">Select cuisine type</option>
                                        <?php foreach ($cuisine_types as $cuisine): ?>
                                            <option value="<?php echo $cuisine['CuisineID']; ?>" 
                                                <?php echo $recipe['cuisine_typeID'] == $cuisine['CuisineID'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cuisine['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="meal_type" class="form-label">Meal Type</label>
                                    <select class="form-select" id="meal_type" name="meal_type" required>
                                        <option value="">Select meal type</option>
                                        <?php foreach ($meal_types as $meal): ?>
                                            <option value="<?php echo $meal['MealID']; ?>" 
                                                <?php echo $recipe['meal_typesID'] == $meal['MealID'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($meal['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <h4 class="mb-3 mt-4"><i class="fas fa-leaf me-2"></i>Dietary Preferences</h4>
                            <div class="row mb-4">
                                <?php foreach ($dietary_prefs as $pref): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="dietary_preferences[]" 
                                                   id="pref_<?php echo $pref['DietaryID']; ?>" 
                                                   value="<?php echo $pref['DietaryID']; ?>"
                                                   <?php echo in_array($pref['DietaryID'], $current_dietary) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="pref_<?php echo $pref['DietaryID']; ?>">
                                                <?php echo htmlspecialchars($pref['name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <h4 class="mb-3"><i class="fas fa-carrot me-2"></i>Ingredients</h4>
                            <div id="ingredients-container">
                                <?php foreach ($ingredients as $index => $ingredient): ?>
                                    <div class="ingredient-row">
                                        <div class="ingredient-number fw-bold"><?php echo $index + 1; ?>.</div>
                                        <div class="ingredient-input">
                                            <input type="text" class="form-control" name="ingredient_name[]" 
                                                value="<?php echo htmlspecialchars($ingredient['name']); ?>" required>
                                        </div>
                                        <div class="ingredient-input" style="max-width: 100px;">
                                            <input type="number" step="0.01" class="form-control" name="ingredient_quantity[]" 
                                                value="<?php echo $ingredient['quantity']; ?>" required>
                                        </div>
                                        <div class="ingredient-input" style="max-width: 120px;">
                                            <select class="form-select" name="ingredient_unit[]" required>
                                                <?php foreach ($units as $unit): ?>
                                                    <option value="<?php echo $unit; ?>" 
                                                        <?php echo $ingredient['units'] == $unit ? 'selected' : ''; ?>>
                                                        <?php echo $unit; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ingredient-image-container" onclick="document.getElementById('ingredient-image-<?php echo $index + 1; ?>').click()">
                                            <?php if (!empty($ingredient['image'])): ?>
                                                <img src="ingredients/<?php echo $ingredient['image']; ?>" 
                                                    class="ingredient-image-preview" id="ingredient-image-preview-<?php echo $index + 1; ?>">
                                            <?php else: ?>
                                                <i class="fas fa-camera"></i>
                                                <img class="ingredient-image-preview" id="ingredient-image-preview-<?php echo $index + 1; ?>" style="display: none;">
                                            <?php endif; ?>
                                            <input type="file" id="ingredient-image-<?php echo $index + 1; ?>" 
                                                name="ingredient_image[]" accept="image/*" style="display: none;" 
                                                onchange="previewIngredientImage(event, <?php echo $index + 1; ?>)">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary mt-2" onclick="addIngredient()">
                                <i class="fas fa-plus me-1"></i> Add Another Ingredient
                            </button>
                            
                            <h4 class="mb-3 mt-4"><i class="fas fa-image me-2"></i>Recipe Image</h4>
                            <div class="mb-3">
                                <input type="file" class="form-control" id="image" name="image" accept="image/*" onchange="previewImage(event)">
                                <div class="mt-2">
                                    <p>Current Image:</p>
                                    <img src="image/<?php echo $recipe['image']; ?>" id="imagePreview" class="existing-image">
                                </div>
                            </div>
                            
                            <h4 class="mb-3"><i class="fas fa-list-ol me-2"></i>Instructions</h4>
                            <div id="instructions">
                                <?php foreach ($instructions as $step_num => $instruction): ?>
                                    <div class="instruction-container mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="fw-bold me-2"><?php echo $step_num; ?>.</div>
                                            <div class="flex-grow-1">
                                                <textarea class="form-control" name="instructions[]" rows="3" required><?php 
                                                    echo htmlspecialchars($instruction['cooking_step']); 
                                                ?></textarea>
                                            </div>
                                        </div>
                                        <label class="form-label">Step Image (Optional)</label>
                                        <div class="instruction-image-container" onclick="document.getElementById('instruction-image-<?php echo $step_num; ?>').click()">
                                            <?php if (!empty($instruction['image'])): ?>
                                                <img src="instructions/<?php echo $instruction['image']; ?>" 
                                                    id="instruction-image-preview-<?php echo $step_num; ?>" class="ingredient-image-preview">
                                            <?php else: ?>
                                                <i class="fas fa-camera"></i>
                                                <img id="instruction-image-preview-<?php echo $step_num; ?>" class="ingredient-image-preview" style="display: none;">
                                            <?php endif; ?>
                                            <input type="file" id="instruction-image-<?php echo $step_num; ?>" 
                                                name="instruction_image[]" accept="image/*" style="display: none;" 
                                                onchange="previewInstructionImage(event, <?php echo $step_num; ?>)">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary mt-2" onclick="addInstruction()">
                                <i class="fas fa-plus me-1"></i> Add Another Step
                            </button>
                            
                            <h4 class="mb-3 mt-4"><i class="fas fa-info-circle me-2"></i>Additional Information</h4>
                            <div class="mb-3">
                                <label for="video" class="form-label">Video URL (Optional)</label>
                                <input type="url" class="form-control" id="video" name="video" 
                                    value="<?php echo htmlspecialchars($recipe['video']); ?>" placeholder="https://youtube.com/watch?v=...">
                            </div>
                            
                            <div class="mb-3">
                                <label for="details" class="form-label">Recipe Details (Optional)</label>
                                <textarea class="form-control" id="details" name="details" rows="3" 
                                    placeholder="Any additional information about your recipe..."><?php 
                                    echo htmlspecialchars($recipe['details']); ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="expiry_date" class="form-label">Period of validity (in days)</label>
                                <input type="number" class="form-control" id="expiry_date" name="expiry_date" 
                                    value="<?php echo $recipe['expiry_date']; ?>">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Update Recipe
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function previewImage(event) {
        const image = document.getElementById('imagePreview');
        if (event.target.files && event.target.files[0]) {
            image.src = URL.createObjectURL(event.target.files[0]);
            image.style.display = 'block';
        }
    }

    function previewIngredientImage(event, index) {
        const preview = document.getElementById(`ingredient-image-preview-${index}`);
        const container = preview.parentElement;
        
        if (event.target.files && event.target.files[0]) {
            preview.src = URL.createObjectURL(event.target.files[0]);
            preview.style.display = 'block';
            if (container.querySelector('i')) {
                container.querySelector('i').style.display = 'none';
            }
        }
    }

    function previewInstructionImage(event, index) {
        const preview = document.getElementById(`instruction-image-preview-${index}`);
        const container = preview.parentElement;
        
        if (event.target.files && event.target.files[0]) {
            preview.src = URL.createObjectURL(event.target.files[0]);
            preview.style.display = 'block';
            if (container.querySelector('i')) {
                container.querySelector('i').style.display = 'none';
            }
        }
    }

    let ingredientCount = <?php echo count($ingredients); ?>;
    function addIngredient() {
        ingredientCount++;
        const container = document.getElementById("ingredients-container");
        
        const row = document.createElement("div");
        row.className = "ingredient-row";
        
        const numberDiv = document.createElement("div");
        numberDiv.className = "ingredient-number fw-bold";
        numberDiv.textContent = ingredientCount + ".";
        row.appendChild(numberDiv);
        
        const nameInputDiv = document.createElement("div");
        nameInputDiv.className = "ingredient-input";
        const nameInput = document.createElement("input");
        nameInput.type = "text";
        nameInput.className = "form-control";
        nameInput.name = "ingredient_name[]";
        nameInput.placeholder = "Ingredient name";
        nameInput.required = true;
        nameInputDiv.appendChild(nameInput);
        row.appendChild(nameInputDiv);
        
        const quantityInputDiv = document.createElement("div");
        quantityInputDiv.className = "ingredient-input";
        quantityInputDiv.style.maxWidth = "100px";
        const quantityInput = document.createElement("input");
        quantityInput.type = "number";
        quantityInput.step = "0.01";
        quantityInput.className = "form-control";
        quantityInput.name = "ingredient_quantity[]";
        quantityInput.placeholder = "Qty";
        quantityInput.required = true;
        quantityInputDiv.appendChild(quantityInput);
        row.appendChild(quantityInputDiv);
        
        const unitInputDiv = document.createElement("div");
        unitInputDiv.className = "ingredient-input";
        unitInputDiv.style.maxWidth = "120px";
        const unitSelect = document.createElement("select");
        unitSelect.className = "form-select";
        unitSelect.name = "ingredient_unit[]";
        unitSelect.required = true;
        
        // Add unit options
        const units = <?php echo json_encode($units); ?>;
        units.forEach(unit => {
            const option = document.createElement("option");
            option.value = unit;
            option.textContent = unit;
            unitSelect.appendChild(option);
        });
        
        unitInputDiv.appendChild(unitSelect);
        row.appendChild(unitInputDiv);
        
        // Image
        const imageContainer = document.createElement("div");
        imageContainer.className = "ingredient-image-container";
        imageContainer.onclick = function() {
            document.getElementById(`ingredient-image-${ingredientCount}`).click();
        };
        
        const cameraIcon = document.createElement("i");
        cameraIcon.className = "fas fa-camera";
        imageContainer.appendChild(cameraIcon);
        
        const imagePreview = document.createElement("img");
        imagePreview.id = `ingredient-image-preview-${ingredientCount}`;
        imagePreview.className = "ingredient-image-preview";
        imageContainer.appendChild(imagePreview);
        
        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.id = `ingredient-image-${ingredientCount}`;
        fileInput.name = "ingredient_image[]";
        fileInput.accept = "image/*";
        fileInput.style.display = "none";
        fileInput.onchange = function(event) {
            previewIngredientImage(event, ingredientCount);
        };
        imageContainer.appendChild(fileInput);
        
        row.appendChild(imageContainer);
        container.appendChild(row);
    }

    let instructionCount = <?php echo count($instructions); ?>;
    function addInstruction() {
        instructionCount++;
        const instructionsDiv = document.getElementById("instructions");
        
        const container = document.createElement("div");
        container.className = "instruction-container mb-3";
        
        const headerDiv = document.createElement("div");
        headerDiv.className = "d-flex align-items-center mb-2";
        
        const numberDiv = document.createElement("div");
        numberDiv.className = "fw-bold me-2";
        numberDiv.textContent = instructionCount + ".";
        headerDiv.appendChild(numberDiv);
        
        const textareaDiv = document.createElement("div");
        textareaDiv.className = "flex-grow-1";
        
        const textarea = document.createElement("textarea");
        textarea.className = "form-control";
        textarea.name = "instructions[]";
        textarea.rows = 3;
        textarea.placeholder = "Enter step " + instructionCount;
        textarea.required = true;
        textareaDiv.appendChild(textarea);
        
        headerDiv.appendChild(textareaDiv);
        container.appendChild(headerDiv);
        
        const imageLabel = document.createElement("label");
        imageLabel.className = "form-label";
        imageLabel.textContent = "Step Image (Optional)";
        container.appendChild(imageLabel);
        
        const imageContainer = document.createElement("div");
        imageContainer.className = "instruction-image-container";
        imageContainer.onclick = function() {
            document.getElementById(`instruction-image-${instructionCount}`).click();
        };
        
        const cameraIcon = document.createElement("i");
        cameraIcon.className = "fas fa-camera";
        imageContainer.appendChild(cameraIcon);
        
        const imagePreview = document.createElement("img");
        imagePreview.id = `instruction-image-preview-${instructionCount}`;
        imagePreview.className = "ingredient-image-preview";
        imageContainer.appendChild(imagePreview);
        
        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.id = `instruction-image-${instructionCount}`;
        fileInput.name = "instruction_image[]";
        fileInput.accept = "image/*";
        fileInput.style.display = "none";
        fileInput.onchange = function(event) {
            previewInstructionImage(event, instructionCount);
        };
        imageContainer.appendChild(fileInput);
        
        container.appendChild(imageContainer);
        instructionsDiv.appendChild(container);
        
        container.scrollIntoView({ behavior: 'smooth' });
    }

    document.querySelector('form').addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
    });
</script>
</body>
</html>