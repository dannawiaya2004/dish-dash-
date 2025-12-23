<?php


// Start session and include database connection
require 'db.php';
session_start();

// Verify database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get and sanitize search query
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_query = $conn->real_escape_string($search_query);

// Initialize result arrays
$recipe_results = [];
$chef_results = [];
$ingredient_results = [];
$debug_info = [];

if (!empty($search_query)) {
    // 1. RECIPE SEARCH ============================================
    $recipe_search = "SELECT r.*, 
                     AVG(rt.rating) AS avg_rating, 
                     COUNT(rt.rating) AS rating_count,
                     ct.name AS cuisine_type,
                     mt.name AS meal_type,
                     u.name AS chef_name
              FROM recipe r
              LEFT JOIN rate rt ON r.RecipeID = rt.recipe_id
              LEFT JOIN cuisine_type ct ON r.cuisine_typeID = ct.CuisineID
              LEFT JOIN meal_types mt ON r.meal_typesID = mt.MealID
              LEFT JOIN chef c ON r.chefs_id = c.ChefID
              LEFT JOIN user u ON c.user_id = u.UserID
              WHERE r.status = 'published' AND 
                    (r.title LIKE '%$search_query%' OR 
                     r.details LIKE '%$search_query%' )
              GROUP BY r.RecipeID
              ORDER BY CASE 
                  WHEN r.title LIKE '%$search_query%' THEN 1 
                  ELSE 3 
              END, avg_rating DESC";
    
    $debug_info['recipe_query'] = $recipe_search;
    
    $result = $conn->query($recipe_search);
    if ($result === false) {
        $debug_info['recipe_error'] = $conn->error;
    } else {
        $recipe_results = $result->fetch_all(MYSQLI_ASSOC);
        $debug_info['recipe_count'] = count($recipe_results);
        if (!empty($recipe_results)) {
            $debug_info['recipe_sample'] = $recipe_results[0];
        }
    }
    
    // 2. CHEF SEARCH ==============================================
    $chef_search = "SELECT c.*, u.name, u.email,
                   (SELECT COUNT(*) FROM recipe WHERE chefs_id = c.ChefID) as recipe_count,
                   (SELECT AVG(rating) FROM rate_chef WHERE chef_id = c.ChefID) as avg_rating
            FROM chef c
            JOIN user u ON c.user_id = u.UserID
            WHERE c.status = 'approved' AND 
                  (u.name LIKE '%$search_query%' OR 
                   c.specialties LIKE '%$search_query%')";
    
    $debug_info['chef_query'] = $chef_search;
    
    $result = $conn->query($chef_search);
    if ($result === false) {
        $debug_info['chef_error'] = $conn->error;
    } else {
        $chef_results = $result->fetch_all(MYSQLI_ASSOC);
        $debug_info['chef_count'] = count($chef_results);
    }
    
    // 4. TABLE VERIFICATION =======================================
    $tables_to_check = ['recipe', 'ingredient', 'chef', 'user', 'rate', 'cuisine_type', 'meal_types'];
    foreach ($tables_to_check as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $debug_info['tables'][$table] = ($result->num_rows > 0) ? 'Exists' : 'MISSING';
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DishDash - Search Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FF8C00; /* Darker orange for better contrast */
            --primary-light: #FFA500;
            --secondary: #2E8B57; /* Sea green */
            --secondary-light: #3CB371;
            --dark: #228B22; /* Forest green */
            --light: #F5FFF5; /* Very light green */
            --accent: #32CD32; /* Lime green */
            --text-dark: #333;
            --text-light: #FFF;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light);
            color: var(--text-dark);
            padding-bottom: 50px;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            color: var(--secondary) !important;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .navbar-brand span {
            color: var(--primary);
        }
        
        .nav-link {
            color: var(--text-dark) !important;
            font-weight: 500;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--dark) !important;
        }
        
        .search-header {
            background: linear-gradient(135deg, var(--secondary) 100%, var(--dark) 100%);
            color: var(--text-light);
            padding: 80px 0;
            margin-bottom: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .search-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="rgba(255,255,255,0.05)" d="M0,0 L100,0 L100,100 L0,100 Z" /></svg>');
            opacity: 0.1;
        }
        
        .search-header h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 15px;
            position: relative;
        }
        
        .search-header .lead {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
        }
        
        .result-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            background: white;
            height: 100%;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .result-card .card-img-top {
            height: 200px;
            object-fit: cover;
            border-bottom: 3px solid var(--primary);
        }
        
        .result-card .card-title {
            color: var(--secondary);
            font-weight: 600;
            margin-top: 15px;
        }
        
        .result-card .card-footer {
            background: rgba(46, 139, 87, 0.05);
            border-top: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #e67e00;
            border-color: #e67e00;
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .nav-tabs {
            border-bottom: 2px solid rgba(46, 139, 87, 0.1);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--text-dark) !important;
            font-weight: 500;
            padding: 12px 20px;
            position: relative;
        }
        
        .nav-tabs .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--primary);
            transition: width 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover::after {
            width: 100%;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary) !important;
            background: transparent;
        }
        
        .nav-tabs .nav-link.active::after {
            width: 100%;
        }
        
        .badge.bg-primary {
            background-color: var(--primary) !important;
        }
        
        .badge.bg-success {
            background-color: var(--secondary) !important;
        }
        
        .alert-info {
            background-color: rgba(46, 139, 87, 0.1);
            border-color: rgba(46, 139, 87, 0.3);
            color: var(--secondary);
        }
        
        .alert-warning {
            background-color: rgba(255, 140, 0, 0.1);
            border-color: rgba(255, 140, 0, 0.3);
            color: #e67e00;
        }
        
        .search-box {
            position: relative;
            width: 25%;
        }
        
        .search-box .form-control {
            border-radius: 50px;
            padding-left: 20px;
            border: 2px solid rgba(46, 139, 87, 0.2);
        }
        
        .search-box .btn {
            border-radius: 40px;
            padding: 4px 15px;
            position: absolute;
            right: 5px;
            top: 3px;
        }
        
        /* Rating stars */
        .rating-stars {
            color: var(--primary);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .search-header {
                padding: 60px 0;
            }
            
            .search-header h1 {
                font-size: 2rem;
            }
            
            .nav-tabs .nav-link {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="Recipe Book Logo.jpeg" alt="Logo" class="rounded-circle me-2" width="40" height="40">
                <span >DishDash</span>
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
                        <a class="nav-link" href="fridge.php"><i class="fas fa-ice-cream me-1"></i> My Fridge</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="weekly_plan.php"><i class="fas fa-calendar-alt me-1"></i> Meal Planner</a>
                    </li>
                    <li class="nav-item">
                        <a href="chatbot.php" class="nav-link"><i class="fas fa-robot me-1"></i> Chef Assistant</a>
                    </li>
                </ul>
                <form action="search.php" method="GET" class="d-flex search-box">
                    <input type="text" name="q" class="form-control" 
                           placeholder="Search recipes, chefs..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <section class="search-header">
        <div class="container">
            <h1 class="mb-3">Search Results</h1>
            <p class="lead"><?php echo !empty($search_query) ? 'Showing results for: ' . htmlspecialchars($search_query) : 'Enter a search term above'; ?></p>
        </div>
    </section>

    <div class="container mb-5">
        <?php if (empty($search_query)): ?>
            <div class="alert alert-info text-center py-4">
                <h4><i class="fas fa-search me-2"></i> Ready to explore?</h4>
                <p class="mb-0">Search for recipes, chefs, or ingredients to get started</p>
            </div>
        <?php else: ?>
            <ul class="nav nav-tabs mb-4" id="searchTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#recipes">
                        <i class="fas fa-utensils me-1"></i> Recipes (<?php echo count($recipe_results); ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#chefs">
                        <i class="fas fa-user-chef me-1"></i> Chefs (<?php echo count($chef_results); ?>)
                    </button>
                </li>
          
            </ul>

            <div class="tab-content">
                <!-- Recipes Tab -->
                <div class="tab-pane fade show active" id="recipes">
                    <?php if (!empty($recipe_results)): ?>
                        <div class="row">
                            <?php foreach ($recipe_results as $recipe): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="result-card">
                                    <img src="./image/<?php echo $recipe['image']; ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($recipe['title']); ?></h5>
                                        <p class="card-text text-muted">
                                            <?php echo htmlspecialchars(substr($recipe['details'], 0, 100)); ?>...
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i> <?php echo $recipe['cooking_time'] ?? 'N/A'; ?> mins
                                            </small>
                                            <div class="rating-stars">
                                                <?php if (isset($recipe['avg_rating'])): ?>
                                                    <?php 
                                                    $fullStars = floor($recipe['avg_rating']);
                                                    $hasHalfStar = ($recipe['avg_rating'] - $fullStars) >= 0.5;
                                                    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                                                    
                                                    for ($i = 0; $i < $fullStars; $i++): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php endfor; ?>
                                                    
                                                    <?php if ($hasHalfStar): ?>
                                                        <i class="fas fa-star-half-alt"></i>
                                                    <?php endif; ?>
                                                    
                                                    <?php for ($i = 0; $i < $emptyStars; $i++): ?>
                                                        <i class="far fa-star"></i>
                                                    <?php endfor; ?>
                                                    
                                                    <small class="ms-1">(<?php echo number_format($recipe['avg_rating'], 1); ?>)</small>
                                                <?php else: ?>
                                                    <small>No ratings yet</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="recipe.php?id=<?php echo $recipe['RecipeID']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-book-open me-1"></i> View Recipe
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center py-4">
                            <h4><i class="fas fa-exclamation-circle me-2"></i> No recipes found</h4>
                            <p class="mb-0">We couldn't find any recipes matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Chefs Tab -->
                <div class="tab-pane fade" id="chefs">
                    <?php if (!empty($chef_results)): ?>
                        <div class="row">
                            <?php foreach ($chef_results as $chef): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="result-card text-center">
                                    <div class="card-body">
                                        <div class="position-relative mb-3">
                                         
                                        </div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($chef['name']); ?></h5>
                                        <p class="text-muted mb-3"><?php echo $chef['specialties'] ?? 'Professional Chef'; ?></p>
                                        <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                                            <span class="badge bg-primary">
                                                <i class="fas fa-utensils me-1"></i> <?php echo $chef['recipe_count']; ?> Recipes
                                            </span>
                                            <span class="badge bg-success">
                                                <i class="fas fa-star me-1"></i> 
                                                <?php echo isset($chef['avg_rating']) ? number_format($chef['avg_rating'], 1) : 'N/A'; ?>
                                            </span>
                                        </div>
                                        <a href="chef_profile.php?id=<?php echo $chef['ChefID']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-user me-1"></i> View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center py-4">
                            <h4><i class="fas fa-exclamation-circle me-2"></i> No chefs found</h4>
                            <p class="mb-0">We couldn't find any chefs matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Ingredients Tab -->
                <div class="tab-pane fade" id="ingredients">
                    <?php if (!empty($ingredient_results)): ?>
                        <!-- [Ingredient results code would go here] -->
                    <?php else: ?>
                        <div class="alert alert-warning text-center py-4">
                            <h4><i class="fas fa-exclamation-circle me-2"></i> No ingredients found</h4>
                            <p class="mb-0">We couldn't find any ingredients matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const recipeCount = <?php echo count($recipe_results); ?>;
            const chefCount = <?php echo count($chef_results); ?>;
            
            if (recipeCount === 0 && chefCount > 0) {
                const tab = new bootstrap.Tab(document.querySelector('#searchTabs .nav-link:nth-child(2)'));
                tab.show();
            } else if (recipeCount === 0 && chefCount === 0 && <?php echo count($ingredient_results); ?> > 0) {
                const tab = new bootstrap.Tab(document.querySelector('#searchTabs .nav-link:nth-child(3)'));
                tab.show();
            }
            
            // Add smooth scrolling to all links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            });
        });
    </script>
</body>
</html>