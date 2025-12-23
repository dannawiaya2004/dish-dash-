<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$user_ingredients = [];
$result = $conn->query("SELECT ingredient_id FROM user_ingredients WHERE user_id = $user_id");
while ($row = $result->fetch_assoc()) {
    $user_ingredients[] = $row['ingredient_id'];
}

$user_restriction_names = [];
$result = $conn->query("
    SELECT r.name 
    FROM restriction r
    JOIN user_restriction ur ON r.RestrictionID = ur.restriction_id
    WHERE ur.user_id = $user_id
");
while ($row = $result->fetch_assoc()) {
    $user_restriction_names[] = $conn->real_escape_string(strtolower($row['name']));
}

$recipes = [];
$query = "
SELECT r.*, 
       (SELECT COUNT(*) FROM used WHERE recipe_id = r.RecipeID) AS ingredient_count,
       (SELECT GROUP_CONCAT(i.name SEPARATOR ', ') 
        FROM used ri 
        JOIN ingredients i ON ri.ingredient_id = i.IngredientsID 
        WHERE ri.recipe_id = r.RecipeID) AS ingredient_names,
       (SELECT GROUP_CONCAT(i.IngredientsID SEPARATOR ',')
        FROM used ri
        JOIN ingredients i ON ri.ingredient_id = i.IngredientsID
        WHERE ri.recipe_id = r.RecipeID) AS ingredient_ids
FROM recipe r
WHERE r.status = 'published'
";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $has_all_ingredients = true;
        if (!empty($user_ingredients)) {
            $recipe_ingredient_ids = explode(',', $row['ingredient_ids']);
            foreach ($recipe_ingredient_ids as $ing_id) {
                if (!in_array($ing_id, $user_ingredients)) {
                    $has_all_ingredients = false;
                    break;
                }
            }
        }
        
        if ($has_all_ingredients) {
            $has_restriction = false;
            $restricted_items = [];
            if (!empty($user_restriction_names)) {
                $ingredient_names = explode(', ', $row['ingredient_names']);
                foreach ($ingredient_names as $name) {
                    $ingredient_name_lower = strtolower($name);
                    foreach ($user_restriction_names as $restriction) {
                        if (strpos($ingredient_name_lower, $restriction) !== false) {
                            $has_restriction = true;
                            $restricted_items[] = $name;
                            break;
                        }
                    }
                }
            }
            
            $row['can_make'] = true;
            $row['has_restriction'] = $has_restriction;
            $row['restricted_items'] = array_unique($restricted_items);
            $recipes[] = $row;
        }
    }
} else {
    echo "Error: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dish Dash - Recipes You Can Make</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #ffcc00;
            --primary-dark: #ff9900;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background: url('fridge.jpeg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .recipe-container {
            background: rgba(255, 255, 255, 0.97);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin-top: 40px;
            margin-bottom: 40px;
        }
        
        .recipe-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .recipe-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .recipe-img {
            height: 220px;
            object-fit: cover;
            width: 100%;
            border-bottom: 4px solid var(--primary);
        }
        
        .recipe-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .recipe-content {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .recipe-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .recipe-description {
            color: #6c757d;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .recipe-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            color: #495057;
            font-size: 0.9rem;
            background: rgba(255, 204, 0, 0.1);
            padding: 5px 10px;
            border-radius: 8px;
        }
        
        .meta-item i {
            color: var(--primary);
            margin-right: 5px;
            font-size: 1rem;
        }
        
        .ingredients-preview {
            background: rgba(255, 204, 0, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            flex-grow: 1;
            overflow: hidden;
        }
        
        .ingredients-preview h6 {
            color: var(--primary-dark);
            margin-bottom: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .ingredients-list {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.85rem;
            color: #495057;
            line-height: 1.5;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-view i {
            margin-left: 5px;
            transition: transform 0.2s;
        }
        
        .btn-view:hover i {
            transform: translateX(3px);
        }
        
        .difficulty {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .difficulty-easy {
            background: #d4edda;
            color: #155724;
        }
        
        .difficulty-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .difficulty-hard {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            padding: 50px;
            text-align: center;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            margin: 30px 0;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 25px;
            opacity: 0.8;
        }
        
        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 25px;
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
            color:rgb(64, 134, 64) !important;
        }

        
        .allergy-warning {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #721c24;
        }
        
        .allergy-warning i {
            color: #dc3545;
            margin-right: 8px;
            font-size: 1.1rem;
        }
        
        .allergy-warning strong {
            font-weight: 600;
        }
        
        .can-make-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            z-index: 2;
        }
        .text-warning {
            color: rgb(64, 134, 64) !important;
        }
        
        /* Ensure all cards in a row are the same height */
        .row-cols-md-2 > *, .row-cols-lg-3 > * {
            display: flex;
        }
        
        .btn-view-container {
            margin-top: auto;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="Recipe Book Logo.jpeg" alt="Logo" class="rounded-circle me-2" width="45" height="45">
                <span class="fw-bold fs-4">DishDash</span>
            </a>
            <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="main.php"><i class="fas fa-home me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="allRecipes.php"><i class="fas fa-book me-1"></i> Recipes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="fridge.php"><i class="fas fa-ice-cream me-1"></i> My Fridge</a>
                    </li>
                    <li class="nav-item">
                    <li class="nav-item"><a class="nav-link " href="weekly_plan.php"><i class="fas fa-calendar-alt"></i> Meal Planner</a></li>
                    </li>
                                 <li class="nav-item">
    <a href="chatbot.php" class="nav-link ">
      <i class="fas fa-robot"></i>
      <span>Chef Assistant</span>
    </a>
    </li>
                </ul>
                <a href="logout.php" class="btn btn-outline-light px-4">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container py-4">
        <div class="recipe-container">
        <h1 class="text-center text-warning mb-4"> <i class="fas fa-utensils me-2"></i> Recipes You Can Make</h1>

            
            <?php if (empty($recipes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No Recipes Found</h3>
                    <p>You don't have all the ingredients needed for any recipe in our database.</p>
                    <a href="ingredients.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Add More Ingredients
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-4">
                    <?php if (!empty($user_restriction_names)): ?>
                        <span class="d-block mt-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Recipes containing ingredients that match your dietary restrictions are marked with a warning.
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($recipes as $recipe): 
                        $difficulty_class = '';
                        if (isset($recipe['difficulty'])) {
                            switch(strtolower($recipe['difficulty'])) {
                                case 'easy': $difficulty_class = 'difficulty-easy'; break;
                                case 'medium': $difficulty_class = 'difficulty-medium'; break;
                                case 'hard': $difficulty_class = 'difficulty-hard'; break;
                            }
                        }
                    ?>
                        <div class="col">
                            <div class="recipe-card">
                                <span class="can-make-badge">
                                    <i class="fas fa-check-circle me-1"></i> Can Make
                                </span>
                                
                                <span class="recipe-badge">
                                    <i class="fas fa-carrot me-1"></i>
                                    <?= htmlspecialchars($recipe['ingredient_count'] ?? '0') ?> Ingredients
                                </span>
                                
                                <img src="./image/<?= htmlspecialchars($recipe['image']) ?>" 
                                     alt="<?= htmlspecialchars($recipe['title']) ?>" 
                                     class="recipe-img">
                                <div class="recipe-content">
                                    <h3 class="recipe-title"><?= htmlspecialchars($recipe['title']) ?></h3>
                                    
                                    <?php if (isset($recipe['difficulty'])): ?>
                                        <span class="difficulty <?= $difficulty_class ?> mb-2">
                                            <?= htmlspecialchars(ucfirst($recipe['difficulty'])) ?> 
                                        </span>
                                    <?php endif; ?>
                                    
                                    <p class="recipe-description">
                                        <?= htmlspecialchars(substr($recipe['details'] ?? 'No description available', 0, 100)) ?>...
                                    </p>
                                    
                                    <?php if ($recipe['has_restriction']): ?>
                                        <div class="allergy-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <strong>Allergy Alert:</strong> Contains 
                                            <?= htmlspecialchars(implode(', ', $recipe['restricted_items'])) ?> 
                                            which may not suit your dietary needs.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="ingredients-preview">
                                        <h6><i class="fas fa-carrot me-1"></i> Main Ingredients</h6>
                                        <p class="ingredients-list" title="<?= htmlspecialchars($recipe['ingredient_names']) ?>">
                                            <?= htmlspecialchars($recipe['ingredient_names'] ?? 'Ingredients not specified') ?>
                                        </p>
                                    </div>
                                    
                                    <div class="recipe-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <?= htmlspecialchars($recipe['cooking_time'] ?? 'N/A') ?> mins
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-utensils"></i>
                                            <?= htmlspecialchars($recipe['serving'] ?? 'N/A') ?> servings
                                        </span>
                                        <?php if (isset($recipe['calories'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-fire"></i>
                                            <?= htmlspecialchars($recipe['calories']) ?> kcal
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="btn-view-container">
                                        <a href="recipe.php?id=<?= $recipe['RecipeID'] ?>" 
                                           class="btn btn-view">
                                            View Full Recipe <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>