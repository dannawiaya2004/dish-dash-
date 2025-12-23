<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if recipe ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No recipe specified";
    header("Location: admin.php");
    exit();
}

$recipe_id = intval($_GET['id']);

// Get the recipe details
$recipe_query = $conn->query("SELECT * FROM recipe WHERE RecipeID = $recipe_id");
if ($recipe_query->num_rows == 0) {
    $_SESSION['error'] = "Recipe not found";
    header("Location: admin.php");
    exit();
}
$recipe = $recipe_query->fetch_assoc();

// Get chef info for display
$chef_id = $recipe['chefs_id'];
$chef_query = $conn->query("SELECT u.name FROM chef c JOIN user u ON c.user_id = u.UserID WHERE c.ChefID = $chef_id");
$chef_name = $chef_query->fetch_assoc()['name'];

// Get meal types and cuisine types for dropdowns
$meal_types = [];
$meal_result = $conn->query("SELECT * FROM meal_types");
while ($row = $meal_result->fetch_assoc()) {
    $meal_types[] = $row;
}

$cuisine_types = [];
$cuisine_result = $conn->query("SELECT * FROM cuisine_type");
while ($row = $cuisine_result->fetch_assoc()) {
    $cuisine_types[] = $row;
}

// Get dietary preferences
$dietary_prefs = [];
$dietary_result = $conn->query("SELECT * FROM dietary_preferences");
while ($row = $dietary_result->fetch_assoc()) {
    $dietary_prefs[] = $row;
}

// Get current recipe dietary preferences
$current_dietary = [];
$dietary_query = $conn->query("SELECT dietary_preferences_id FROM dietaryRecipe WHERE recipe_id = $recipe_id");
while ($row = $dietary_query->fetch_assoc()) {
    $current_dietary[] = $row['dietary_preferences_id'];
}

// Get ingredients
$ingredients = [];
$ingredient_query = $conn->query("
    SELECT i.IngredientsID, i.name, i.units, u.quantity 
    FROM used u 
    JOIN ingredients i ON u.ingredient_id = i.IngredientsID 
    WHERE u.recipe_id = $recipe_id
");
while ($row = $ingredient_query->fetch_assoc()) {
    $ingredients[] = $row;
}

// Get instructions
$instructions = [];
$instruction_query = $conn->query("
    SELECT step_nbr, cooking_step 
    FROM instructions 
    WHERE recipe_id = $recipe_id 
    ORDER BY step_nbr
");
while ($row = $instruction_query->fetch_assoc()) {
    $instructions[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $conn->real_escape_string($_POST['title']);
    $cooking_time = intval($_POST['cooking_time']);
    $serving = intval($_POST['serving']);
    $difficulty = $conn->real_escape_string($_POST['difficulty']);
    $cuisine_type_id = intval($_POST['cuisine_type']);
    $meal_type_id = intval($_POST['meal_type']);
    $video = $conn->real_escape_string($_POST['video']);
    $details = $conn->real_escape_string($_POST['details']);
    $expiry_date = $conn->real_escape_string($_POST['expiry_date']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Handle image upload
    $filename = $recipe['image'];
    if (!empty($_FILES["image"]["name"])) {
        $filename = $_FILES["image"]["name"];
        $tempname = $_FILES["image"]["tmp_name"];
        $folder = "image/" . $filename;
        
        if (move_uploaded_file($tempname, $folder)) {
            // Delete old image if it's not the default
            if ($recipe['image'] && $recipe['image'] != 'default.jpg') {
                @unlink("image/" . $recipe['image']);
            }
        } else {
            $_SESSION['error'] = "Failed to upload image";
            header("Location: edit_recipe.php?id=$recipe_id");
            exit();
        }
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
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
            meal_typesID = $meal_type_id,
            status = '$status'
            WHERE RecipeID = $recipe_id";
        
        if (!$conn->query($update_recipe)) {
            throw new Exception("Error updating recipe: " . $conn->error);
        }
        
        // Update dietary preferences
        $conn->query("DELETE FROM dietaryRecipe WHERE recipe_id = $recipe_id");
        if (isset($_POST['dietary_preferences'])) {
            foreach ($_POST['dietary_preferences'] as $pref_id) {
                $pref_id = intval($pref_id);
                $insert_pref = "INSERT INTO dietaryRecipe (recipe_id, dietary_preferences_id) 
                              VALUES ($recipe_id, $pref_id)";
                if (!$conn->query($insert_pref)) {
                    throw new Exception("Error inserting dietary preference: " . $conn->error);
                }
            }
        }
        
        // Update ingredients
        $conn->query("DELETE FROM used WHERE recipe_id = $recipe_id");
        foreach ($_POST['ingredient_name'] as $key => $ingredient_name) {
            $ingredient_name = trim($conn->real_escape_string($ingredient_name));
            $quantity = floatval($_POST['ingredient_quantity'][$key]);
            $unit = $conn->real_escape_string($_POST['ingredient_unit'][$key]);
            
            if (!empty($ingredient_name)) {
                // Check if ingredient exists
                $ingredient_query = $conn->query("SELECT IngredientsID FROM ingredients WHERE name = '$ingredient_name'");
                if ($ingredient_query->num_rows > 0) {
                    $ingredient_id = $ingredient_query->fetch_assoc()['IngredientsID'];
                } else {
                    // Create new ingredient
                    $conn->query("INSERT INTO ingredients (name, units) VALUES ('$ingredient_name', '$unit')");
                    $ingredient_id = $conn->insert_id;
                }
                
                // Link ingredient to recipe
                $conn->query("INSERT INTO used (ingredient_id, recipe_id, quantity) 
                            VALUES ($ingredient_id, $recipe_id, $quantity)");
            }
        }
        
        // Update instructions
        $conn->query("DELETE FROM instructions WHERE recipe_id = $recipe_id");
        foreach ($_POST['instructions'] as $index => $step) {
            if (!empty(trim($step))) {
                $step = $conn->real_escape_string($step);
                $step_number = $index + 1;
                
                $conn->query("INSERT INTO instructions (cooking_step, step_nbr, recipe_id) 
                            VALUES ('$step', $step_number, $recipe_id)");
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Recipe updated successfully!";
        header("Location: admin.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: edit_recipe.php?id=$recipe_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Recipe - DishDash Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
             :root{   --dark: #2E8B57;
      --primary: #ffbe32;
      --primary-dark: #e6ab2d;
    
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
            .navbar-custom {
      background-color: var(--primary);
    }
        .nav-pills .nav-link.active {
      background-color: var(--primary);
    }
            .admin-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

    </style>
</head>
<body>

  <nav class="main-header navbar navbar-expand navbar-white navbar-light navbar-custom">
    <ul class="navbar-nav">

      <li class="nav-item d-none d-sm-inline-block">
        <a href="admin.php" class="nav-link">Home</a>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto">

      <li class="nav-item">
        <a class="nav-link" href="logout.php" role="button">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
    </ul>
  </nav>
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Recipe</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <div class="admin-info">
                            <p><strong>Chef:</strong> <?= htmlspecialchars($chef_name) ?></p>
                            <p><strong>Current Status:</strong> 
                                <span class="badge bg-<?= $recipe['status'] === 'published' ? 'success' : 
                                    ($recipe['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($recipe['status']) ?>
                                </span>
                            </p>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Recipe Title</label>
                                <input type="text" class="form-control" name="title" 
                                       value="<?= htmlspecialchars($recipe['title']) ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Cooking Time (minutes)</label>
                                    <input type="number" class="form-control" name="cooking_time" 
                                           value="<?= $recipe['cooking_time'] ?>" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Servings</label>
                                    <input type="number" class="form-control" name="serving" 
                                           value="<?= $recipe['serving'] ?>" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Difficulty</label>
                                    <select class="form-select" name="difficulty" required>
                                        <option value="Easy" <?= $recipe['difficulty_lvl'] == 'Easy' ? 'selected' : '' ?>>Easy</option>
                                        <option value="Medium" <?= $recipe['difficulty_lvl'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                                        <option value="Hard" <?= $recipe['difficulty_lvl'] == 'Hard' ? 'selected' : '' ?>>Hard</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="published" <?= $recipe['status'] == 'published' ? 'selected' : '' ?>>Published</option>
                                        <option value="pending" <?= $recipe['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="archived" <?= $recipe['status'] == 'archived' ? 'selected' : '' ?>>Archived</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Cuisine Type</label>
                                    <select class="form-select" name="cuisine_type" required>
                                        <?php foreach ($cuisine_types as $cuisine): ?>
                                            <option value="<?= $cuisine['CuisineID'] ?>" 
                                                <?= $recipe['cuisine_typeID'] == $cuisine['CuisineID'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cuisine['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Meal Type</label>
                                    <select class="form-select" name="meal_type" required>
                                        <?php foreach ($meal_types as $meal): ?>
                                            <option value="<?= $meal['MealID'] ?>" 
                                                <?= $recipe['meal_typesID'] == $meal['MealID'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($meal['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Dietary Preferences</label>
                                <div class="row">
                                    <?php foreach ($dietary_prefs as $pref): ?>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="dietary_preferences[]" 
                                                       value="<?= $pref['DietaryID'] ?>"
                                                       <?= in_array($pref['DietaryID'], $current_dietary) ? 'checked' : '' ?>>
                                                <label class="form-check-label"><?= htmlspecialchars($pref['name']) ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Ingredients</label>
                                <div id="ingredients-container">
                                    <?php foreach ($ingredients as $index => $ingredient): ?>
                                        <div class="ingredient-row">
                                            <input type="text" class="form-control" name="ingredient_name[]" 
                                                   value="<?= htmlspecialchars($ingredient['name']) ?>" 
                                                   placeholder="Ingredient name" required>
                                            <input type="number" step="0.01" class="form-control" 
                                                   name="ingredient_quantity[]" 
                                                   value="<?= $ingredient['quantity'] ?>" 
                                                   placeholder="Quantity" required>
                                            <select class="form-select" name="ingredient_unit[]" style="max-width: 120px;">
                                                <option value="g" <?= $ingredient['units'] == 'g' ? 'selected' : '' ?>>g</option>
                                                <option value="kg" <?= $ingredient['units'] == 'kg' ? 'selected' : '' ?>>kg</option>
                                                <option value="ml" <?= $ingredient['units'] == 'ml' ? 'selected' : '' ?>>ml</option>
                                                <option value="l" <?= $ingredient['units'] == 'l' ? 'selected' : '' ?>>l</option>
                                                <option value="tsp" <?= $ingredient['units'] == 'tsp' ? 'selected' : '' ?>>tsp</option>
                                                <option value="tbsp" <?= $ingredient['units'] == 'tbsp' ? 'selected' : '' ?>>tbsp</option>
                                                <option value="cup" <?= $ingredient['units'] == 'cup' ? 'selected' : '' ?>>cup</option>
                                                <option value="pinch" <?= $ingredient['units'] == 'pinch' ? 'selected' : '' ?>>pinch</option>
                                                <option value="piece" <?= $ingredient['units'] == 'piece' ? 'selected' : '' ?>>piece</option>
                                            </select>
                                            <button type="button" class="btn btn-danger remove-ingredient" style="width: 40px;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="add-ingredient" class="btn btn-secondary mt-2">
                                    <i class="fas fa-plus me-1"></i> Add Ingredient
                                </button>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Recipe Image</label>
                                <input type="file" class="form-control" name="image">
                                <?php if ($recipe['image']): ?>
                                    <div class="mt-2">
                                        <p>Current Image:</p>
                                        <img src="image/<?= $recipe['image'] ?>" style="max-height: 150px;" class="img-thumbnail">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Video URL (optional)</label>
                                <input type="url" class="form-control" name="video" 
                                       value="<?= htmlspecialchars($recipe['video']) ?>" 
                                       placeholder="https://youtube.com/watch?v=...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Instructions</label>
                                <div id="instructions-container">
                                    <?php foreach ($instructions as $index => $instruction): ?>
                                        <div class="instruction-container">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="fw-bold me-2"><?= $index + 1 ?>.</div>
                                                <textarea class="form-control" name="instructions[]" rows="3" 
                                                          required><?= htmlspecialchars($instruction['cooking_step']) ?></textarea>
                                            </div>
                                            <button type="button" class="btn btn-danger btn-sm remove-instruction">
                                                <i class="fas fa-trash me-1"></i> Remove Step
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="add-instruction" class="btn btn-secondary mt-2">
                                    <i class="fas fa-plus me-1"></i> Add Instruction Step
                                </button>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Recipe Details</label>
                                <textarea class="form-control" name="details" rows="5"><?= htmlspecialchars($recipe['details']) ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Expiry Date (days)</label>
                                <input type="number" class="form-control" name="expiry_date" 
                                       value="<?= $recipe['expiry_date'] ?>" 
                                       placeholder="How many days this recipe stays fresh">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add ingredient row
        document.getElementById('add-ingredient').addEventListener('click', function() {
            const container = document.getElementById('ingredients-container');
            const newRow = document.createElement('div');
            newRow.className = 'ingredient-row';
            newRow.innerHTML = `
                <input type="text" class="form-control" name="ingredient_name[]" placeholder="Ingredient name" required>
                <input type="number" step="0.01" class="form-control" name="ingredient_quantity[]" placeholder="Quantity" required>
                <select class="form-select" name="ingredient_unit[]" style="max-width: 120px;">
                    <option value="g">g</option>
                    <option value="kg">kg</option>
                    <option value="ml">ml</option>
                    <option value="l">l</option>
                    <option value="tsp">tsp</option>
                    <option value="tbsp">tbsp</option>
                    <option value="cup">cup</option>
                    <option value="pinch">pinch</option>
                    <option value="piece">piece</option>
                </select>
                <button type="button" class="btn btn-danger remove-ingredient" style="width: 40px;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(newRow);
        });

        // Remove ingredient row
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-ingredient')) {
                e.target.closest('.ingredient-row').remove();
            }
        });

        // Add instruction step
        document.getElementById('add-instruction').addEventListener('click', function() {
            const container = document.getElementById('instructions-container');
            const stepCount = container.querySelectorAll('.instruction-container').length + 1;
            const newStep = document.createElement('div');
            newStep.className = 'instruction-container';
            newStep.innerHTML = `
                <div class="d-flex align-items-center mb-2">
                    <div class="fw-bold me-2">${stepCount}.</div>
                    <textarea class="form-control" name="instructions[]" rows="3" required></textarea>
                </div>
                <button type="button" class="btn btn-danger btn-sm remove-instruction">
                    <i class="fas fa-trash me-1"></i> Remove Step
                </button>
            `;
            container.appendChild(newStep);
        });

        // Remove instruction step
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-instruction')) {
                e.target.closest('.instruction-container').remove();
                // Renumber steps
                const steps = document.querySelectorAll('.instruction-container');
                steps.forEach((step, index) => {
                    step.querySelector('.fw-bold').textContent = `${index + 1}.`;
                });
            }
        });
        // Add ingredient row
document.getElementById('add-ingredient').addEventListener('click', function() {
    const container = document.getElementById('ingredients-container');
    const newRow = document.createElement('div');
    newRow.className = 'ingredient-row';
    newRow.innerHTML = `
        <input type="text" class="form-control" name="ingredient_name[]" placeholder="Ingredient name" required>
        <input type="number" step="0.01" class="form-control" name="ingredient_quantity[]" placeholder="Quantity" required>
        <select class="form-select" name="ingredient_unit[]" style="max-width: 120px;">
            <option value="g">g</option>
            <option value="kg">kg</option>
            <option value="ml">ml</option>
            <option value="l">l</option>
            <option value="tsp">tsp</option>
            <option value="tbsp">tbsp</option>
            <option value="cup">cup</option>
            <option value="pinch">pinch</option>
            <option value="piece">piece</option>
        </select>
        <button type="button" class="btn btn-danger remove-ingredient" style="width: 40px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(newRow);
});

// Remove ingredient row
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-ingredient')) {
        e.target.closest('.ingredient-row').remove();
    }
});

// Add instruction step
document.getElementById('add-instruction').addEventListener('click', function() {
    const container = document.getElementById('instructions-container');
    const stepCount = container.querySelectorAll('.instruction-container').length + 1;
    const newStep = document.createElement('div');
    newStep.className = 'instruction-container';
    newStep.innerHTML = `
        <div class="d-flex align-items-center mb-2">
            <div class="fw-bold me-2">${stepCount}.</div>
            <textarea class="form-control" name="instructions[]" rows="3" required></textarea>
        </div>
        <button type="button" class="btn btn-danger btn-sm remove-instruction">
            <i class="fas fa-trash me-1"></i> Remove Step
        </button>
    `;
    container.appendChild(newStep);
});

// Remove instruction step
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-instruction')) {
        e.target.closest('.instruction-container').remove();
        // Renumber steps
        const steps = document.querySelectorAll('.instruction-container');
        steps.forEach((step, index) => {
            step.querySelector('.fw-bold').textContent = `${index + 1}.`;
        });
    }
});
    </script>
</body>
</html>