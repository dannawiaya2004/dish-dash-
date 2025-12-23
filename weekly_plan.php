<?php
require 'db.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$allowed_days = [1, 2, 3, 4, 5, 6, 7];

$day_map = [
    'monday' => 1,
    'tuesday' => 2,
    'wednesday' => 3,
    'thursday' => 4,
    'friday' => 5,
    'saturday' => 6,
    'sunday' => 7
];

$day_display = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $week_start = date('Y-m-d', strtotime($_POST['week_start']));
    $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
    
    $conn->autocommit(FALSE);
    
    try {
        $plan_query = "INSERT INTO weekly_plan (week_start_date, week_end_date, user_id) 
                      VALUES ('$week_start', '$week_end', $user_id)";
        
        if (!$conn->query($plan_query)) {
            throw new Exception("Error creating plan: " . $conn->error);
        }
        
        $plan_id = $conn->insert_id;
        
        if (isset($_POST['recipes'])) {
            foreach ($_POST['recipes'] as $day_name => $recipe_id) {
                if (!empty($recipe_id) && $recipe_id != '0') {

                    $day_name = strtolower(trim($day_name));
                    $day_number = $day_map[$day_name] ?? null;
                    
                    if (!in_array($day_number, $allowed_days)) {
                        throw new Exception("Invalid day of week: $day_name");
                    }
                    
                    $recipe_id = (int)$recipe_id;
                    $day_query = "INSERT INTO weekly_plan_recipe (plan_id, recipe_id, day_of_week) 
                                 VALUES ($plan_id, $recipe_id, $day_number)";
                    
                    if (!$conn->query($day_query)) {
                        throw new Exception("Error adding recipe for $day_name: " . $conn->error);
                    }
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Weekly plan created successfully!";
        header("Location: weekly_plan.php?view=$plan_id");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    $conn->autocommit(TRUE); 
}

//existing plan
$view_plan = null;
$shopping_list = [];
$existing_ingredients = [];
$plan_recipes = [];

if (isset($_GET['view'])) {
    $plan_id = (int)$_GET['view'];
    
    $plan_query = "SELECT * FROM weekly_plan WHERE PlanID = $plan_id AND user_id = $user_id";
    $result = $conn->query($plan_query);
    $view_plan = $result->fetch_assoc();
    
    if ($view_plan) {
        $recipes_query = "SELECT r.*, wpr.day_of_week 
                         FROM weekly_plan_recipe wpr
                         JOIN recipe r ON wpr.recipe_id = r.RecipeID
                         WHERE wpr.plan_id = $plan_id";
        $result = $conn->query($recipes_query);
        
        while ($row = $result->fetch_assoc()) {
            $row['day_name'] = $day_display[$row['day_of_week']] ?? 'Unknown';
            $plan_recipes[] = $row;
        }
        
        $ingredients = [];
        foreach ($plan_recipes as $recipe) {
            $ing_query = "SELECT i.*, u.quantity as needed_qty, ui.quantity as have_qty
                         FROM used u
                         JOIN ingredients i ON u.ingredient_id = i.IngredientsID
                         LEFT JOIN user_ingredients ui ON ui.ingredient_id = i.IngredientsID AND ui.user_id = $user_id
                         WHERE u.recipe_id = {$recipe['RecipeID']}";
            $result = $conn->query($ing_query);
            
            while ($ing = $result->fetch_assoc()) {
                $ing_id = $ing['IngredientsID'];
                if (!isset($ingredients[$ing_id])) {
                    $ingredients[$ing_id] = [
                        'name' => $ing['name'],
                        'units' => $ing['units'],
                        'needed_qty' => 0,
                        'have_qty' => $ing['have_qty'] ?? 0
                    ];
                }
                $ingredients[$ing_id]['needed_qty'] += $ing['needed_qty'];
            }
        }
        
        foreach ($ingredients as $id => $ing) {
            $to_buy = max(0, $ing['needed_qty'] - $ing['have_qty']);
            if ($to_buy > 0) {
                $shopping_list[$id] = [
                    'name' => $ing['name'],
                    'qty' => $to_buy,
                    'units' => $ing['units']
                ];
            }
            
            if ($ing['have_qty'] > 0) {
                $existing_ingredients[$id] = [
                    'name' => $ing['name'],
                    'qty' => $ing['have_qty'],
                    'units' => $ing['units']
                ];
            }
        }
    }
}

$favorites_query = "SELECT r.* FROM fav f 
                   JOIN recipe r ON f.recipe_id = r.RecipeID 
                   WHERE f.user_id = $user_id";
$result = $conn->query($favorites_query);
$favorite_recipes = [];
while ($row = $result->fetch_assoc()) {
    $favorite_recipes[] = $row;
}

$all_recipes_query = "SELECT * FROM recipe WHERE status = 'published' ORDER BY title";
$all_recipes = [];
$result = $conn->query($all_recipes_query);
while ($row = $result->fetch_assoc()) {
    $all_recipes[] = $row;
}

$plans_query = "SELECT * FROM weekly_plan WHERE user_id = $user_id ORDER BY week_start_date DESC";
$result = $conn->query($plans_query);
$previous_plans = [];
while ($row = $result->fetch_assoc()) {
    $previous_plans[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Meal Planner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4a6fa5;
            --secondary: #6b8cae;
            --dark: #2E8B57;
            --light: #F5FFFA;
            --accent: #FFA500;
            --danger: #dc3545;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .plan-header {
            background: linear-gradient(135deg,  #e69500 0%, #2E8B57 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .day-card {
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
        }
        
        .day-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.12);
        }
        
        .day-header {
            background-color: #e69500;
            color: white;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .day-header i {
            font-size: 1.2rem;
        }
        
        .recipe-select {
            cursor: pointer;
            border-radius: 10px;
            padding: 18px;
            border: 2px dashed #e0e0e0;
            transition: all 0.2s ease;
            background-color: #f9f9f9;
        }
        
        .recipe-select:hover {
            background-color: #f0f0f0;
            border-color: #e69500;
        }
        
        .recipe-selected {
            background-color: rgba(74, 111, 165, 0.08);
            border: 2px solid rgba(255, 165, 0, 0.2);
        }
        
        .recipe-img {
            width: 90px;
            height: 90px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
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

        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .card-header {
            font-weight: 600;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .list-group-item {
            border-left: none;
            border-right: none;
            padding: 12px 20px;
        }
        
        .list-group-item:first-child {
            border-top: none;
        }
        
        .badge {
            font-weight: 500;
            padding: 5px 10px;
        }
        
        .btn-primary {
            background-color:  #e69500;
            border-color:  #e69500;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color:  #e69500;
            border-color:  #e69500;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .recipe-details {
            flex: 1;
        }
      
        .card-footer {
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

        
        .card-footer:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
        }
        
        .card-footer i {
            margin-left: 5px;
            transition: transform 0.2s;
        }
        
        .card-footer:hover i {
            transform: translateX(3px);
        }
        
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="Recipe Book Logo.jpeg" alt="Logo" class="rounded-circle me-2" width="40" height="40">
                <span class="fw-bold">DishDash</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="main.php"><i class="fas fa-home me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="allRecipes.php"><i class="fas fa-book me-1"></i> Recipes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link " href="fridge.php"><i class="fas fa-ice-cream me-1"></i> My Fridge</a>
                    </li>
                    <li class="nav-item">
                   <a class="nav-link " href="weekly_plan.php"><i class="fas fa-calendar-alt"></i> Meal Planner</a></li>
                    </li>
                                 <li class="nav-item">
    <a href="chatbot.php" class="nav-link ">
      <i class="fas fa-robot"></i>
      <span>Chef Assistant</span>
    </a>
    </li>
                
                </ul>
                <div class="d-flex">
                    <a href="profile.php" class="btn btn-outline">
                        <i class="fas fa-user-circle me-1"></i> My Profile
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <section class="plan-header">
        <div class="container text-center">
            <h1 class="fw-bold mb-3">Weekly Meal Planner</h1>
            <p class="lead mb-0">Plan your meals, track your nutrition, and generate smart shopping lists</p>
        </div>
    </section>

    <div class="container mb-5">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <button class="nav-link <?= !$view_plan ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#new-plan">
                    <i class="fas fa-plus-circle me-2"></i>Create New Plan
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $view_plan ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#view-plan">
                    <i class="fas fa-eye me-2"></i>View Plan
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#previous-plans">
                    <i class="fas fa-history me-2"></i>Previous Plans
                </button>
            </li>
        </ul>
        
        <div class="tab-content mt-4">
            <!-- Create New Plan -->
            <div class="tab-pane fade <?= !$view_plan ? 'show active' : '' ?>" id="new-plan">
                <form method="POST" id="planForm">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Week Starting</label>
                            <input type="date" class="form-control form-control-lg" name="week_start" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Plan
                            </button>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php 
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        foreach ($days as $day): 
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="day-card h-100">
                                <div class="day-header text-capitalize">
                                    <span><?= ucfirst($day) ?></span>
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="p-3">
                                    <div class="recipe-select" data-day="<?= $day ?>">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <p class="mb-0 text-muted">Click to select recipe</p>
                                            </div>
                                            <i class="fas fa-plus text-primary"></i>
                                        </div>
                                        <input type="hidden" name="recipes[<?= $day ?>]" value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
                
                <!-- Recipe Modal -->
                <div class="modal fade" id="recipeModal">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-light">
                                <h5 class="modal-title fw-bold"><i class="fas fa-utensils me-2"></i>Select Recipe</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0">
                                <ul class="nav nav-tabs px-3 pt-2">
                                    <li class="nav-item">
                                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#favorites">
                                            <i class="fas fa-heart me-2"></i>Favorites
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#all-recipes">
                                            <i class="fas fa-book me-2"></i>All Recipes
                                        </button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content p-3">
                                    <div class="tab-pane fade show active" id="favorites">
                                        <?php if (!empty($favorite_recipes)): ?>
                                            <div class="row">
                                                <?php foreach ($favorite_recipes as $recipe): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="recipe-option p-3 border rounded d-flex align-items-center"
                                                         data-id="<?= $recipe['RecipeID'] ?>"
                                                         data-title="<?= htmlspecialchars($recipe['title']) ?>"
                                                         data-img="<?= htmlspecialchars($recipe['image']) ?>">
                                                        <img src="./image/<?= $recipe['image'] ?? 'default-recipe.jpg' ?>" 
                                                             class="recipe-img me-3">
                                                        <div class="recipe-details">
                                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($recipe['title']) ?></h6>
                                                            <small class="text-muted d-block mb-2">
                                                                <i class="fas fa-clock me-1"></i><?= $recipe['cooking_time'] ?> mins
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>No favorite recipes yet
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="all-recipes">
                                        <div class="row">
                                            <?php foreach ($all_recipes as $recipe): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="recipe-option p-3 border rounded d-flex align-items-center"
                                                     data-id="<?= $recipe['RecipeID'] ?>"
                                                     data-title="<?= htmlspecialchars($recipe['title']) ?>"
                                                     data-img="<?= htmlspecialchars($recipe['image']) ?>">
                                                    <img src="./image/<?= $recipe['image'] ?? 'default-recipe.jpg' ?>" 
                                                         class="recipe-img me-3">
                                                    <div class="recipe-details">
                                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($recipe['title']) ?></h6>
                                                        <small class="text-muted d-block mb-2">
                                                            <i class="fas fa-clock me-1"></i><?= $recipe['cooking_time'] ?> mins
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- View Plan -->
            <div class="tab-pane fade <?= $view_plan ? 'show active' : '' ?>" id="view-plan">
                <?php if ($view_plan): ?>
                    <div class="card mb-4 border-0 shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold mb-0">Week of <?= date('F j, Y', strtotime($view_plan['week_start_date'])) ?></h4>
                                <a href="weekly_plan.php" class="btn btn-outline-primary">
                                    <i class="fas fa-plus me-2"></i>Create New Plan
                                </a>
                            </div>
                            
                            <div class="row mt-4">
                                <?php 
                                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                foreach ($days as $day): 
                                    $day_number = $day_map[$day];
                                    $day_recipes = array_filter($plan_recipes, fn($r) => $r['day_of_week'] == $day_number);
                                ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="day-card h-100">
                                        <div class="day-header text-capitalize">
                                            <span><?= ucfirst($day) ?></span>
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <div class="p-3">
                                            <?php if (!empty($day_recipes)): ?>
                                                <?php foreach ($day_recipes as $recipe): ?>
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <img src="./image/<?= $recipe['image'] ?? 'default-recipe.jpg' ?>" 
                                                             class="recipe-img me-3">
                                                        <div>
                                                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($recipe['title']) ?></h6>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i><?= $recipe['cooking_time'] ?> mins
                                                            </small>
                                                        </div>
                                                        
                                                    </div>
                                                    <div class="card-footer bg-white border-top-0">
                                <a href="recipe.php?id=<?php echo $recipe['RecipeID']; ?>" class="btn btn-primary btn-block">
                                    View Recipe <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-center py-3">
                                                    <i class="fas fa-utensils text-muted mb-2" style="font-size: 2rem;"></i>
                                                    <p class="text-muted mb-0">No recipe planned</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-success text-white d-flex align-items-center">
                                            <i class="fas fa-shopping-cart me-2"></i>
                                            <span>Shopping List</span>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!empty($shopping_list)): ?>
                                                <ul class="list-group list-group-flush">
                                                    <?php foreach ($shopping_list as $item): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><?= htmlspecialchars($item['name']) ?></span>
                                                        <span class="badge bg-primary rounded-pill">
                                                            <?= $item['qty'] ?> <?= $item['units'] ?>
                                                        </span>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-check-circle text-muted mb-3" style="font-size: 2rem;"></i>
                                                    <p class="text-muted mb-0">No items needed - you have everything!</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-info text-white d-flex align-items-center">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <span>Already Have</span>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!empty($existing_ingredients)): ?>
                                                <ul class="list-group list-group-flush">
                                                    <?php foreach ($existing_ingredients as $item): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><?= htmlspecialchars($item['name']) ?></span>
                                                        <span class="badge bg-secondary rounded-pill">
                                                            <?= $item['qty'] ?> <?= $item['units'] ?>
                                                        </span>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-info-circle text-muted mb-3" style="font-size: 2rem;"></i>
                                                    <p class="text-muted mb-0">No matching ingredients in your inventory</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-alt text-muted mb-3" style="font-size: 3rem;"></i>
                            <h4 class="fw-bold mb-3">No Plan Selected</h4>
                            <p class="text-muted mb-4">Create a new plan or select one from your previous plans</p>
                            <a href="weekly_plan.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create New Plan
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Previous Plans -->
            <div class="tab-pane fade" id="previous-plans">
                <?php if (!empty($previous_plans)): ?>
                    <div class="row">
                        <?php foreach ($previous_plans as $plan): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="fw-bold mb-0">Week of <?= date('F j, Y', strtotime($plan['week_start_date'])) ?></h5>
                                        <span class="badge bg-light text-dark">
                                            <?= date('M j', strtotime($plan['week_start_date'])) ?> - <?= date('M j', strtotime($plan['week_end_date'])) ?>
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-3">
                                        Created <?= date('M j, Y', strtotime($plan['created_at'] ?? 'now')) ?>
                                    </p>
                                    <div class="d-grid">
                                        <a href="weekly_plan.php?view=<?= $plan['PlanID'] ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-2"></i>View Plan
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-history text-muted mb-3" style="font-size: 3rem;"></i>
                            <h4 class="fw-bold mb-3">No Previous Plans</h4>
                            <p class="text-muted mb-4">You haven't created any meal plans yet</p>
                            <a href="weekly_plan.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Your First Plan
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-light py-4 mt-5">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; <?= date('Y') ?> DishDash. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default date to next Monday
            const today = new Date();
            const nextMonday = new Date(today);
            nextMonday.setDate(today.getDate() + (1 + 7 - today.getDay()) % 7 || 7);
            document.querySelector('input[name="week_start"]').value = nextMonday.toISOString().split('T')[0];

            // Recipe selection
            const recipeModal = new bootstrap.Modal('#recipeModal');
            let currentDay = '';
            
            // Day click handler
            document.querySelectorAll('.recipe-select').forEach(el => {
                el.addEventListener('click', function() {
                    currentDay = this.dataset.day;
                    recipeModal.show();
                });
           
            });
            
            // Recipe selection handler
            document.querySelectorAll('.recipe-option').forEach(el => {
                el.addEventListener('click', function() {
                    const recipeId = this.dataset.id;
                    const recipeTitle = this.dataset.title;
                    const recipeImg = this.dataset.img;
                    
                    const dayCard = document.querySelector(`.recipe-select[data-day="${currentDay}"]`);
                    dayCard.innerHTML = `
                        <div class="d-flex align-items-center">
                            <img src="./image/${recipeImg || 'default-recipe.jpg'}" 
                                 class="recipe-img me-3">
                            <div>
                                <h6 class="mb-1 fw-bold">${recipeTitle}</h6>
                                <small class="text-muted">Click to change</small>
                            </div>
                        </div>
                        <input type="hidden" name="recipes[${currentDay}]" value="${recipeId}">
                    `;
                    dayCard.classList.add('recipe-selected');
                    
                    recipeModal.hide();
                });
            });
        });
    </script>
</body>
</html>