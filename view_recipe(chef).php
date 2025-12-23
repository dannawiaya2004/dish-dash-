<?php
require_once 'db.php';
session_start();

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$recipeID = isset($_GET['id']) ? intval($_GET['id']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite_action'])) {
    $action = $_POST['favorite_action'];
    $user_id = $_SESSION['user_id'];
    
    if ($action === 'add') {
        $conn->query("INSERT INTO fav (user_id, recipe_id) VALUES ($user_id, $recipeID)");
    } elseif ($action === 'remove') {
        $conn->query("DELETE FROM fav WHERE user_id = $user_id AND recipe_id = $recipeID");
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
}

$recipeQuery = "SELECT r.*, 
                ct.name AS cuisine_name,
                mt.name AS meal_type_name,
                u.name AS chef_name,
                u.UserID AS chef_user_id,
                (SELECT AVG(rating) FROM rate WHERE recipe_id = r.RecipeID) AS avg_rating,
                (SELECT COUNT(*) FROM rate WHERE recipe_id = r.RecipeID) AS review_count
                FROM recipe r
                LEFT JOIN cuisine_type ct ON r.cuisine_typeID = ct.cuisineID
                LEFT JOIN meal_types mt ON r.meal_typesID = mt.mealID
                LEFT JOIN chef ch ON r.chefs_id = ch.ChefID
                LEFT JOIN user u ON ch.user_id = u.UserID
                WHERE r.RecipeID = $recipeID";

$recipeResult = $conn->query($recipeQuery);

if (!$recipeResult || $recipeResult->num_rows === 0) {
    die("Recipe not found");
}

$recipe = $recipeResult->fetch_assoc();

$avgRating = round($recipe['avg_rating'], 1);
$fullStars = floor($avgRating);
$hasHalfStar = ($avgRating - $fullStars) >= 0.5;

$ingredientsQuery = "SELECT i.IngredientsID, i.name, u.quantity, i.units, i.image 
                     FROM used u
                     JOIN ingredients i ON u.ingredient_id = i.IngredientsID
                     WHERE u.recipe_id = $recipeID";
$ingredientsResult = $conn->query($ingredientsQuery);

$instructionsQuery = "SELECT * FROM instructions 
                     WHERE recipe_id = $recipeID 
                     ORDER BY step_nbr";
$instructionsResult = $conn->query($instructionsQuery);

$isFavorited = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $favoriteQuery = "SELECT * FROM fav WHERE user_id = $user_id AND recipe_id = $recipeID";
    $favoriteResult = $conn->query($favoriteQuery);
    $isFavorited = $favoriteResult->num_rows > 0;
}

$reviewsQuery = "SELECT r.*, u.name AS user_name 
                 FROM rate r
                 JOIN user u ON r.user_id = u.UserID
                 WHERE r.recipe_id = $recipeID
                 ORDER BY r.date DESC";
$reviewsResult = $conn->query($reviewsQuery);

// After your existing queries, add this to fetch images for each instruction step
$imagesQuery = "SELECT * FROM images WHERE recipe_id = $recipeID ORDER BY step";
$imagesResult = $conn->query($imagesQuery);
$instructionImages = [];
if ($imagesResult && $imagesResult->num_rows > 0) {
    while($image = $imagesResult->fetch_assoc()) {
        $instructionImages[$image['step']] = $image['image'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe['title']); ?> - Dish Dash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
 :root {
            --sunshine: #FFD700;
            --citrus: #FFA500;
            --coal: #333333;
            --smoke: #f5f5f5;
            --pebble: #6c757d;
            --primary-color:rgb(255, 195, 31);
            --dark: #2E8B57;

        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background-color: white;
            color: var(--coal);
            line-height: 1.6;
        }
        
        /* Header */
        .main-header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .logo {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--coal);
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
        
        

/* Dropdown menu styles */
.dropdown-menu {
    border: none;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 0.5rem;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    color: #218838;
}

.dropdown-divider {
    margin: 0.25rem 0;
}

/* Responsive adjustments */
@media (max-width: 991.98px) {
    .navbar-collapse {
        background-color: #28a745;
        padding: 1rem;
        border-radius: 8px;
        margin-top: 0.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .nav-link {
        margin: 0.2rem 0;
    }
}

/* Add a subtle animation to the navbar on scroll */
.navbar.scrolled {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    background: white;
}

.navbar.scrolled .navbar-brand {
    color: #28a745 !important;
}

.navbar.scrolled .nav-link {
    color: #495057 !important;
}

.navbar.scrolled .nav-link:hover,
.navbar.scrolled .nav-link.active {
    color: #28a745 !important;
}

.navbar.scrolled .nav-link::before {
    background-color: #28a745;
}

.navbar.scrolled .navbar-toggler-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2840, 167, 69, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}

        /* Recipe Page Styles */
        .recipe-header {
            margin-bottom: 2rem;
        }
        
        .recipe-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .rating-stars {
            color: var(--sunshine);
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        
        .rating-value {
            font-weight: 600;
            color: var(--coal);
        }
        
        .review-count {
            color: var(--pebble);
            text-decoration: underline;
            cursor: pointer;
        }
        
        .recipe-meta {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            color: var(--pebble);
        }
        
        .recipe-meta span {
            margin-right: 1rem;
        }
        
        .recipe-meta a {
            color: var(--citrus);
            text-decoration: none;
        }
        
        .recipe-meta a:hover {
            text-decoration: underline;
        }
        .recipe-image-container {
    display: flex;
    justify-content: center;
    margin: 2rem 0 3rem;
    }
        
    .recipe-image {
    width: 400px; /* Increased size */
    height: 400px; /* Increased size */
    object-fit: cover;
    border-radius: 50%;
    border: 8px solid var(--citrus); /* Thicker border */
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}
.recipe-image:hover {
    transform: scale(1.03);
}
        
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
    
        

        
        .btn-print {
            background-color: white;
            color: var(--coal);
            border: 2px solid var(--pebble);
            font-weight: 600;
        }
        
        .btn-print:hover {
            background-color: var(--smoke);
        }
        
        .recipe-details {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--smoke);
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-icon {
            color: var(--citrus);
            font-size: 1.2rem;
        }
        
        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--coal);
            border-bottom: 2px solid var(--sunshine);
            padding-bottom: 0.5rem;
        }
        
        .ingredients-list {
            list-style-type: none;
            padding-left: 0;
        }
        
        .ingredients-list li {
    padding: 0.8rem 0;
    border-bottom: 1px solid var(--smoke);
    display: flex;
    align-items: center;
    list-style-type: none;
}
        
        .ingredient-checkbox {
            margin-right: 1rem;
            width: 1.2rem;
            height: 1.2rem;
            accent-color: var(--citrus);
        }
        
        .ingredient-thumbnail {
    border-radius: 50%;
    width: 60px;
    height: 60px;
    object-fit: cover;
    margin-right: 1.5rem;
    border: 2px solid var(--sunshine);
}
        
        .instructions-list {
            counter-reset: step-counter;
            list-style-type: none;
            padding-left: 0;
        }
        .instruction-step {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px dashed var(--pebble);
}
        
        .instructions-list li {
            position: relative;
            padding-left: 3rem;
            margin-bottom: 1.5rem;
            min-height: 2.5rem;
        }
        
        .instructions-list li::before {
            counter-increment: step-counter;
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            background: var(--citrus);
            color: white;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .instruction-image {
    margin-top: 15px;
    border-radius: 10px;
    max-width: 100%;
    height: auto;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}
.logo {
    text-decoration: none !important;
}
        
        .recipe-video {
            margin-top: 2rem;
            margin-bottom: 3rem;
        }
        
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 8px;
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .made-it-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 3rem;
            margin-bottom: 3rem;
            justify-content: center;
        }
        
        .btn-made-it {
            background-color: var(--citrus);
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border: none;
        }
        
        .btn-made-it:hover {
            background-color: #e69500;
        }
        
        /* Reviews Section */
        .reviews-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--smoke);
        }
        
        .review-form {
            background-color: var(--smoke);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .review-form-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .star-rating {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .star-rating .star {
            font-size: 1.5rem;
            color: var(--pebble);
            cursor: pointer;
        }
        
        .star-rating .star.active {
            color: var(--sunshine);
        }
        
        .review-list {
            margin-top: 2rem;
        }
        
        .review-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--smoke);
        }
        
        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .review-user {
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .review-date {
            color: var(--pebble);
            font-size: 0.9rem;
        }
        
        .review-stars {
            color: var(--sunshine);
            margin-left: auto;
        }
        
        @media (max-width: 768px) {
            .recipe-details {
                flex-direction: column;
                gap: 1rem;
            }
            
            .recipe-title {
                font-size: 2rem;
            }
        }
        
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .print-section, .print-section * {
                visibility: visible;
            }
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .print-logo {
                text-align: center;
                margin-bottom: 20px;
            }
            .print-logo img {
                max-height: 80px;
            }
            .print-details {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
            }
            .print-title {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 15px;
                text-align: center;
            }
        }
        .main-header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .logo {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--coal);
        }
        
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        /* Notification Styles */
.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 350px;
    max-width: 90%;
    z-index: 9999;
}

.notification {
    position: relative;
    padding: 15px 20px;
    margin-bottom: 15px;
    border-radius: 8px;
    color: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
}

.notification.show {
    opacity: 1;
    transform: translateX(0);
}

.notification.hide {
    opacity: 0;
    transform: translateY(-20px);
}

.notification-success {
    background-color: #28a745;
}

.notification-warning {
    background-color: #ffc107;
    color: #212529;
}

.notification-error {
    background-color: #dc3545;
}

.notification-info {
    background-color: #17a2b8;
}

.notification-icon {
    margin-right: 15px;
    font-size: 1.5rem;
}

.notification-close {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    font-size: 1rem;
    opacity: 0.7;
}

.notification-close:hover {
    opacity: 1;
}

.notification-content {
    flex: 1;
    padding-right: 20px;
}

.notification-title {
    font-weight: 600;
    margin-bottom: 5px;
}

.notification-message {
    font-size: 0.9rem;
    line-height: 1.4;
}
/* Ingredients Header Styles */
.section-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: var(--coal);
    border-bottom: 2px solid var(--sunshine);
    padding-bottom: 0.5rem;
}

/* Serving Selector Styles */
.serving-selector {
    display: flex;
    align-items: center;
}

.serving-selector .btn-group {
    margin-left: 10px;
}

.serving-btn {
    min-width: 40px;
    border-radius: 4px !important;
    margin: 0 2px;
    font-weight: 600;
}

.serving-btn.active {
    background-color: var(--citrus);
    color: white;
    border-color: var(--citrus);
}

.serving-info {
    font-size: 0.9rem;
    color: var(--pebble);
    font-style: italic;
}

.badge-medium {
            background-color: #FFE28A;
            color:rgb(230, 150, 0);
        }
        
        .badge-hard {
            background-color:rgb(249, 255, 161);
            color:rgb(255, 123, 15);
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

    <div class="print-section" style="display: none;">
        <div class="print-logo">
            <img src="Recipe Book Logo.jpeg" alt="DishDash Logo">
        </div>
        <h1 class="print-title"><?php echo htmlspecialchars($recipe['title']); ?></h1>
        
        <div class="print-details">
            <div><strong>Cook Time:</strong> <?php echo htmlspecialchars($recipe['cooking_time'] ?? 'N/A'); ?> mins</div>
            <div><strong>Servings:</strong> <?php echo htmlspecialchars($recipe['serving'] ?? '4'); ?></div>
        </div>
    <!-- Ingredients Section with Scaling -->
    <div class="ingredients-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="section-title mb-0">Ingredients</h2>
                <div class="serving-selector">
                    <span class="me-2">Scale:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-dark serving-btn active" data-multiplier="1">1X</button>
                        <button type="button" class="btn btn-outline-dark serving-btn" data-multiplier="2">2X</button>
                        <button type="button" class="btn btn-outline-dark serving-btn" data-multiplier="4">4X</button>
                    </div>
                </div>
            </div>
            <div class="serving-info mb-4">
                Original recipe (1X) yields <strong><?php echo htmlspecialchars($recipe['serving'] ?? '2'); ?></strong> servings
            </div>
            
            <ul class="ingredients-list">
                <?php if ($ingredientsResult && $ingredientsResult->num_rows > 0): ?>
                    <?php while($ingredient = $ingredientsResult->fetch_assoc()): ?>
                        <li class="d-flex align-items-center">
                            <?php if (!empty($ingredient['image'])): ?>
                                <img src="ingredients/<?php echo htmlspecialchars($ingredient['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($ingredient['name']); ?>" 
                                     class="ingredient-thumbnail">
                            <?php else: ?>
                                <div class="ingredient-thumbnail bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-carrot text-warning"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <?php 
                                if (!empty($ingredient['quantity']) && $ingredient['quantity'] != 0) {
                                    echo '<span class="ingredient-quantity">' . htmlspecialchars($ingredient['quantity']) . '</span> ';
                                }
                                
                                if (!empty($ingredient['units']) && (!isset($ingredient['quantity']) || $ingredient['quantity'] != 0)) {
                                    echo htmlspecialchars($ingredient['units']) . ' ';
                                }
                                
                                echo htmlspecialchars($ingredient['name']);
                                ?>
                            </div>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li>No ingredients listed for this recipe.</li>
                <?php endif; ?>
            </ul>
        </div>
        
        <h2>Instructions</h2>
        <ol class="instructions-list">
            <?php 
            if ($instructionsResult && $instructionsResult->num_rows > 0) {
                while($instruction = $instructionsResult->fetch_assoc()): ?>
                    <li><?php echo htmlspecialchars($instruction['cooking_step']); ?></li>
                <?php endwhile; 
            } else { ?>
                <li>No instructions available for this recipe.</li>
            <?php } ?>
        </ol>
    </div>

    <div class="container my-5">
        <div class="recipe-header">
            <h1 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h1>
            
            <div class="d-flex align-items-center mb-2">
                <div class="rating-stars">
                    <?php
                    for ($i = 0; $i < $fullStars; $i++) {
                        echo '<i class="fas fa-star"></i>';
                    }
                    
                    if ($hasHalfStar) {
                        echo '<i class="fas fa-star-half-alt"></i>';
                        $fullStars++;
                    }
                    
                    for ($i = $fullStars; $i < 5; $i++) {
                        echo '<i class="far fa-star"></i>';
                    }
                    ?>
                </div>
                <span class="rating-value"><?php echo $avgRating; ?></span>
                <a href="#reviews" class="review-count ms-2"><?php echo $recipe['review_count']; ?> reviews</a>
            </div>
            
            <div class="recipe-meta">
                <span>Submitted by <a href="chef_profile.php?id=<?php echo $recipe['chef_user_id']; ?>"><?php echo htmlspecialchars($recipe['chef_name']); ?></a></span>
                <span>|</span>
                <span>Updated on <?php echo date('F j, Y', strtotime($recipe['created_at'])); ?></span>
            </div>
            <div class="recipe-image-container">
    <img src="<?php echo htmlspecialchars($recipe['image'] ? 'image/'.$recipe['image'] : 'images/default-recipe.jpg'); ?>" 
         alt="<?php echo htmlspecialchars($recipe['title']); ?>" 
         class="recipe-image">
</div>
            
         
            
            <div class="recipe-details">
                <div class="detail-item">
                    <i class="fas fa-fire detail-icon"></i>
                    <div>
                        <div class="detail-label">Cook Time</div>
                        <div class="detail-value"><?php echo htmlspecialchars($recipe['cooking_time'] ?? 'N/A'); ?> mins</div>
                    </div>
                </div>
                <div class="detail-item">
                    <i class="fas fa-utensils detail-icon"></i>
                    <div>
                        <div class="detail-label">Servings</div>
                        <div class="detail-value"><?php echo htmlspecialchars($recipe['serving'] ?? '4'); ?></div>
                    </div>
                </div>
                <div class="detail-item">
                    <i class="fas fa-chart-line detail-icon"></i>
                    <div>
                        <div class="detail-label">Difficulty</div>
                        <div class="detail-value"><?php echo htmlspecialchars($recipe['difficulty_lvl'] ?? 'Medium'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
    <h2 class="section-title">Ingredients</h2>
    <ul class="ingredients-list">
        <?php if ($ingredientsResult && $ingredientsResult->num_rows > 0): ?>
            <?php $ingredientsResult->data_seek(0); while($ingredient = $ingredientsResult->fetch_assoc()): ?>
                <li class="d-flex align-items-center">
                    <?php if (!empty($ingredient['image'])): ?>
                        <img src="ingredients/<?php echo htmlspecialchars($ingredient['image']); ?>" 
                             alt="<?php echo htmlspecialchars($ingredient['name']); ?>" 
                             class="ingredient-thumbnail">
                    <?php else: ?>
                        <div class="ingredient-thumbnail bg-light d-flex align-items-center justify-content-center">
                            <i class="fas fa-carrot text-warning"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong>
                            <?php 
                            if (!empty($ingredient['quantity']) && $ingredient['quantity'] != 0) {
                                echo htmlspecialchars($ingredient['quantity']) . ' ';
                            }
                            
                            if (!empty($ingredient['units']) && (!isset($ingredient['quantity']) || $ingredient['quantity'] != 0)) {
                                echo htmlspecialchars($ingredient['units']) . ' ';
                            }
                            
                            echo htmlspecialchars($ingredient['name']);
                            ?>
                        </strong>
                    </div>
                </li>
            <?php endwhile; ?>
        <?php else: ?>
            <li>No ingredients listed for this recipe.</li>
        <?php endif; ?>
    </ul>
</div>

<!-- Updated instructions section -->
<div class="col-md-6">
    <h2 class="section-title">Instructions</h2>
    <div class="instructions-list">
        <?php if ($instructionsResult && $instructionsResult->num_rows > 0): ?>
            <?php $instructionsResult->data_seek(0); while($instruction = $instructionsResult->fetch_assoc()): ?>
                <div class="instruction-step">
                    <div class="d-flex align-items-start mb-2">
                        <div class="step-number bg-citrus text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                             style="width: 30px; height: 30px; min-width: 30px;">
                            <?php echo $instruction['step_nbr']; ?>
                        </div>
                        <div>
                            <?php echo htmlspecialchars($instruction['cooking_step']); ?>
                            <?php if (isset($instructionImages[$instruction['step_nbr']])): ?>
                                <div class="mt-3">
                                    <img src="instructions/<?php echo htmlspecialchars($instructionImages[$instruction['step_nbr']]); ?>" 
                                         alt="Step <?php echo $instruction['step_nbr']; ?>" 
                                         class="instruction-image img-fluid">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No instructions available for this recipe.</p>
        <?php endif; ?>
    </div>
</div>
        <?php if (!empty($recipe['video'])): ?>
        <div class="recipe-video">
            <h2 class="section-title">Recipe Video</h2>
            <div class="video-container">
                <?php
                $videoUrl = $recipe['video'];
                if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false) {
                    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $videoUrl, $matches);
                    $videoId = $matches[1] ?? '';
                } else {
                    $videoId = $videoUrl;
                }
                
                if (!empty($videoId)) {
                    echo '<iframe src="https://www.youtube.com/embed/'.htmlspecialchars($videoId).'" 
                          frameborder="0" 
                          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                          allowfullscreen></iframe>';
                } else {
                    echo '<p>Invalid video URL provided</p>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
  
        
        <div class="reviews-section" id="reviews">
            <h2 class="section-title">Reviews</h2>
          
            
            <div class="review-list">
                <?php if ($reviewsResult && $reviewsResult->num_rows > 0): ?>
                    <?php while($review = $reviewsResult->fetch_assoc()): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="review-user"><?php echo htmlspecialchars($review['user_name']); ?></span>
                                <span class="review-date"><?php echo date('F j, Y', strtotime($review['date'])); ?></span>
                                <span class="review-stars">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </span>
                            </div>
                            <p><?php echo htmlspecialchars($review['comment']); ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No reviews yet. Be the first to review!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printRecipe() {
            document.querySelector('.print-section').style.display = 'block';
            window.print();
            document.querySelector('.print-section').style.display = 'none';
        }
        
        document.querySelectorAll('.ingredient-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const label = this.nextElementSibling;
                if (this.checked) {
                    label.style.textDecoration = 'line-through';
                    label.style.color = '#999';
                } else {
                    label.style.textDecoration = 'none';
                    label.style.color = '#333';
                }
            });
        });
        

        
     
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = parseInt(ratingValue.value);
                
                stars.forEach((s, index) => {
                    if (index < currentRating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
        });
        
        document.getElementById('submitReview').addEventListener('click', function() {
            const rating = parseInt(ratingValue.value);
            const comment = document.getElementById('reviewComment').value.trim();
            const recipeId = <?php echo $recipeID; ?>;
            
            if (rating === 0) {
                alert('Please select a rating');
                return;
            }
            
            if (comment === '') {
                alert('Please enter your review comment');
                return;
            }
            
            fetch('submit_review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `recipe_id=${recipeId}&rating=${rating}&comment=${encodeURIComponent(comment)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Thank you for your review!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting your review');
            });
        });
   // Notification system functions
function showNotification(type, title, message, duration = 5000) {
    const container = document.getElementById('notificationContainer');
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: 'check-circle',
        warning: 'exclamation-triangle',
        error: 'times-circle',
        info: 'info-circle'
    };
    
    notification.innerHTML = `
        <i class="fas fa-${icons[type]} notification-icon"></i>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close">&times;</button>
    `;
    
    container.appendChild(notification);
    
    // Trigger the show animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Close button handler
    notification.querySelector('.notification-close').addEventListener('click', () => {
        closeNotification(notification);
    });
    
    // Auto-close if duration is set
    if (duration > 0) {
        setTimeout(() => {
            closeNotification(notification);
        }, duration);
    }
    
    return notification;
}

function closeNotification(notification) {
    notification.classList.remove('show');
    notification.classList.add('hide');
    
    // Remove from DOM after animation completes
    setTimeout(() => {
        notification.remove();
    }, 300);
}


document.addEventListener('DOMContentLoaded', function() {
    const servingBtns = document.querySelectorAll('.serving-btn');
    const originalServings = parseInt('<?php echo htmlspecialchars($recipe['serving'] ?? '2'); ?>');
    let currentMultiplier = 1;
    
    // Highlight the default 1X button
    document.querySelector('.serving-btn[data-multiplier="1"]').classList.add('active');
    
    servingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const multiplier = parseInt(this.getAttribute('data-multiplier'));
            currentMultiplier = multiplier;
            
            // Update active button
            servingBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update ingredient quantities
            updateIngredientQuantities(multiplier);
        });
    });
    
    function updateIngredientQuantities(multiplier) {
        const ingredientItems = document.querySelectorAll('.ingredients-list li');
        
        ingredientItems.forEach(item => {
            const quantityElement = item.querySelector('.ingredient-quantity');
            if (quantityElement) {
                const originalQuantity = parseFloat(quantityElement.getAttribute('data-original'));
                if (!isNaN(originalQuantity)) {
                    const newQuantity = originalQuantity * multiplier;
                    // Display as integer if no decimal, otherwise show 1 decimal place
                    quantityElement.textContent = newQuantity % 1 === 0 ? newQuantity : newQuantity.toFixed(1);
                }
            }
        });
    }
    
    // Initialize original quantities
    document.querySelectorAll('.ingredient-quantity').forEach(el => {
        const quantityText = el.textContent.trim();
        if (quantityText) {
            const quantityValue = parseFloat(quantityText);
            if (!isNaN(quantityValue)) {
                el.setAttribute('data-original', quantityValue);
                el.classList.add('ingredient-quantity');
            }
        }
    });
});

// Add scroll effect to navbar
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 10) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

    </script>
    <div class="notification-container" id="notificationContainer"></div>
</body>
</html>