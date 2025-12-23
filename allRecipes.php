<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get all filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$cuisine = isset($_GET['cuisine']) ? $_GET['cuisine'] : '';
$meal_type = isset($_GET['meal_type']) ? $_GET['meal_type'] : '';
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$dietary_preference = isset($_GET['dietary_preference']) ? $_GET['dietary_preference'] : [];
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';


// Get all filter options
$meal_types_query = "SELECT * FROM meal_types";
$cuisine_types_query = "SELECT * FROM cuisine_type";
$dietary_preferences_query = "SELECT * FROM dietary_preferences";

$meal_types_result = mysqli_query($conn, $meal_types_query);
$cuisine_types_result = mysqli_query($conn, $cuisine_types_query);
$dietary_preferences_result = mysqli_query($conn, $dietary_preferences_query);

// Base query
$query = "SELECT 
            r.*, 
            u.name AS chef_name, 
            ct.name AS cuisine_name, 
            mt.name AS meal_type_name,
            (SELECT AVG(rating) FROM rate WHERE recipe_id = r.RecipeID) AS avg_rating,
            (SELECT COUNT(*) FROM fav WHERE recipe_id = r.RecipeID) AS favorite_count
          FROM recipe r
          JOIN chef c ON r.chefs_id = c.ChefID
          JOIN user u ON c.user_id = u.UserID
          JOIN cuisine_type ct ON r.cuisine_typeID = ct.CuisineID
          JOIN meal_types mt ON r.meal_typesID = mt.MealID
          WHERE r.status = 'published'";


// Add conditions
if (!empty($search)) {
    $query .= " AND (r.title LIKE '%".mysqli_real_escape_string($conn,$search)."%' 
               OR r.details LIKE '%".mysqli_real_escape_string($conn,$search)."%')";
}

if (!empty($cuisine)) {
    $query .= " AND ct.name = '".mysqli_real_escape_string($conn,$cuisine)."'";
}

if (!empty($meal_type)) {
    $query .= " AND mt.name = '".mysqli_real_escape_string($conn,$meal_type)."'";
}

if (!empty($difficulty) && in_array($difficulty, ['Easy', 'Medium', 'Hard'])) {
    $query .= " AND r.difficulty_lvl = '".mysqli_real_escape_string($conn,$difficulty)."'";
}

if (!empty($dietary_preference)) {
    if (is_array($dietary_preference)) {
        $dietary_preference = array_map(function($item) use ($conn) {
            return mysqli_real_escape_string($conn, $item);
        }, $dietary_preference);
        $query .= " AND EXISTS (SELECT 1 FROM dietaryRecipe dr 
                    JOIN dietary_preferences dp ON dr.dietary_preferences_id = dp.DietaryID
                    WHERE dr.recipe_id = r.RecipeID 
                    AND dp.name IN ('".implode("','", $dietary_preference)."'))";
    } else {
        $query .= " AND EXISTS (SELECT 1 FROM dietaryRecipe dr 
                    JOIN dietary_preferences dp ON dr.dietary_preferences_id = dp.DietaryID
                    WHERE dr.recipe_id = r.RecipeID 
                    AND dp.name = '".mysqli_real_escape_string($conn,$dietary_preference)."')";
    }
}

// Sorting
switch ($sort) {
    case 'oldest': $query .= " ORDER BY r.created_at ASC"; break;
    case 'rating': $query .= " ORDER BY avg_rating DESC"; break;
    case 'prep_time': $query .= " ORDER BY r.cooking_time ASC"; break;
    case 'popular': $query .= " ORDER BY favorite_count DESC"; break;
    default: $query .= " ORDER BY r.created_at DESC"; // newest first
}
$query .= " LIMIT 12";
$result = mysqli_query($conn, $query);
$recipe_count = mysqli_num_rows($result);

// Only show Breakfast, Lunch, Dinner circles
$popular_tags = [
    'Breakfast' => 'breakfast.jpeg',
    'Lunch' => 'lunch.jpeg',
    'Dinner' => 'dinner.jpeg'
];

// Get meal type IDs for the circles
$meal_ids = [];
$meal_result = mysqli_query($conn, "SELECT MealID, name FROM meal_types WHERE name IN ('Breakfast', 'Lunch', 'Dinner')");
while ($row = mysqli_fetch_assoc($meal_result)) {
    $meal_ids[$row['name']] = $row['MealID'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipes - DishDash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sunshine: #FFD700;
            --citrus: #FFA500;
            --coal: #333333;
            --smoke: #f5f5f5;
            --pebble: #6c757d;
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
            color:rgb(64, 134, 64) !important;
        } 
        /* Yellow Heart Icon */
        .favorites-btn {
            color: var(--sunshine);
            font-size: 1.3rem;
            margin-left: 1rem;
            transition: all 0.2s;
        }
        
        .favorites-btn:hover {
            color: var(--citrus);
            transform: scale(1.1);
        }
        
        /* Hero Section */
        .search-hero {
            background: white;
            padding: 2rem 0 1rem;
            text-align: center;
        }
        
        .page-title {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--coal);
        }
        
        .search-box {
            max-width: 600px;
            margin: 1.5rem auto;
        }
        
        /* Circular Tag Buttons */
        .popular-tags {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin: 2rem auto;
            max-width: 800px;
        }
        
        .tag-circle {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--coal);
            transition: all 0.3s ease;
        }
        
        .tag-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--sunshine);
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .tag-label {
            font-weight: 500;
            text-align: center;
        }
        
        .tag-circle:hover {
            transform: translateY(-5px);
        }
        
        .tag-circle:hover .tag-image {
            border-color: var(--citrus);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Recipe Grid */
        .recipe-section {
            padding: 2rem 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--coal);
        }
        
        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .recipe-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: none;
            text-align: center;
        }
        
        .recipe-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .recipe-img-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .recipe-img {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .recipe-content {
            padding: 1.5rem;
        }
        
        .recipe-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--coal);
        }
        
        .recipe-meta {
            display: flex;
            justify-content: center;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--pebble);
            margin-bottom: 0.5rem;
        }
        
        .rating {
            display: flex;
            justify-content: center;
            color: #FFC107;
            margin-top: 0.5rem;
        }
        
        /* Footer */
        .main-footer {
            background: var(--coal);
            color: white;
            padding: 3rem 0;
            text-align: center;
        }
        
        .footer-logo {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: inline-block;
        }
        
        .copyright {
            margin-top: 2rem;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.7);
        }
        
        /* Active Filters */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-chip {
            background: var(--sunshine);
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;

        }
        
        .remove-filter {
            background: none;
            border: none;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .recipe-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .popular-tags {
                gap: 1rem;
            }
            
            .tag-image {
                width: 80px;
                height: 80px;
            }
        }
        /* Filter Section */
.filter-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    margin-bottom: 2rem;
}

.section-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 1.5rem;
    position: relative;
    padding-bottom: 0.5rem;
}

.section-title::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: #FFA500;
    border-radius: 3px;
}

.filter-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #333;
}

.form-select, .form-control {
    border-radius: 10px;
    padding: 0.6rem 1rem;
    border: 1px solid #e0e0e0;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.form-select:focus, .form-control:focus {
    border-color: #FFA500;
    box-shadow: 0 0 0 0.25rem rgba(255, 165, 0, 0.25);
}

.filter-section .form-select[multiple] {
    height: auto;
    min-height: 44px;
    padding: 0.5rem;
}

/* Active Filters */
.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
            margin-top: 0.5rem;

}

.filter-chip {
    background: #FFD700;
    border-radius: 20px;
    padding: 0.3rem 0.8rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.remove-filter {
    background: none;
    border: none;
    cursor: pointer;
    color: #333;
}

.recipe-count {
    font-size: 0.9rem;
    color: #666;
    font-weight: 400;
    margin-left: 0.5rem;
}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light sticky-top">
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
                        <a class="nav-link active" href="allRecipes.php"><i class="fas fa-book me-1"></i> Recipes</a>
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
                <a href="favorites.php" class="favorites-btn">
                        <i class="fas fa-heart"></i>
                    </a>
            </div>
            </div>
        </div>
    
            
            </div>
        </div>
    </nav>
  <!-- Modern Recipe Hero Section -->
<section class="recipe-hero py-5" style="background: linear-gradient(rgba(0,0,0,0.1), rgba(0,0,0,0.1)), url('chef-img.jpeg') center center / 70% auto no-repeat;">
    <div class="container">
        <div class="hero-content mx-auto text-center" style="max-width: 800px;">
            <!-- Main Heading -->
            <h1 class="display-4 fw-bold mb-3 text-white text-shadow">Cook Something Amazing Today</h1>
            
            <!-- Search Box (More Prominent) -->
            <form class="hero-search mb-4" action="allRecipes.php" method="GET">
                <div class="input-group input-group-lg shadow">
                    <input type="text" class="form-control border-0 py-3" 
                           name="search" 
                           placeholder="Search recipes..."
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-warning px-4">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                </div>
            </form>

            <!-- Quick Filters (Improved Layout) -->
            <div class="quick-filters mb-4">
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="?meal_type=Breakfast" class="btn btn-sm btn-light rounded-pill px-3">
                        <i class="fas fa-egg me-1"></i> Breakfast
                    </a>
                    <a href="?meal_type=Lunch" class="btn btn-sm btn-light rounded-pill px-3">
                        <i class="fas fa-bread-slice me-1"></i> Lunch
                    </a>
                    <a href="?meal_type=Dinner" class="btn btn-sm btn-light rounded-pill px-3">
                        <i class="fas fa-utensils me-1"></i> Dinner
                    </a>
                    <a href="?difficulty=Easy" class="btn btn-sm btn-light rounded-pill px-3">
                        <i class="fas fa-bolt me-1"></i> Quick Meals
                    </a>

                </div>
            </div>

            <!-- Error Message (Styled Better) -->
            <?php if (isset($_SESSION['search_error'])): ?>
            <div class="alert alert-warning alert-dismissible fade show d-inline-flex align-items-center shadow-sm">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div><?= $_SESSION['search_error'] ?></div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['search_error']); ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
/* Hero Section Styles */
.recipe-hero {
    border-radius: 0 0 12px 12px;
    margin-bottom: 2rem;
    position: relative;
}

.hero-content {
    position: relative;
    z-index: 2;
    padding: 2rem 0;
}

.text-shadow {
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.hero-search .form-control {
    border-radius: 50px 0 0 50px !important;
}

.hero-search .btn {
    border-radius: 0 50px 50px 0 !important;
}

.tag-image {
    transition: transform 0.3s ease;
}

.tag-circle:hover .tag-image {
    transform: scale(1.05);
}

@media (max-width: 768px) {
    .recipe-hero {
        padding: 3rem 0;
    }
    
    h1.display-4 {
        font-size: 2.2rem;
    }
    
    .hero-search .input-group {
        flex-direction: column;
    }
    
    .hero-search .form-control {
        border-radius: 50px !important;
        margin-bottom: 0.5rem;
    }
    
    .hero-search .btn {
        border-radius: 50px !important;
        width: 100%;
    }
    .fa-filter{
                margin-bottom: 1.5rem;

    }
}
</style>
<!-- Filter Section -->
<div class="filter-section mb-5">
    <h3 class="section-title">Filter Recipes</h3>
    <form id="filterForm" method="GET" action="allRecipes.php">
     

        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <label class="filter-label">Meal Type</label>
                <select class="form-select" name="meal_type">
                    <option value="">All Meal Types</option>
                    <?php 
                    $meal_types_result->data_seek(0); 
                    while ($meal_type_row = mysqli_fetch_assoc($meal_types_result)): ?>
                        <option value="<?= htmlspecialchars($meal_type_row['name']) ?>" <?= $meal_type == $meal_type_row['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($meal_type_row['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="filter-label">Cuisine</label>
                <select class="form-select" name="cuisine">
                    <option value="">All Cuisines</option>
                    <?php 
                    $cuisine_types_result->data_seek(0); 
                    while ($cuisine_row = mysqli_fetch_assoc($cuisine_types_result)): ?>
                        <option value="<?= htmlspecialchars($cuisine_row['name']) ?>" <?= $cuisine == $cuisine_row['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cuisine_row['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="filter-label">Difficulty</label>
                <select class="form-select" name="difficulty">
                    <option value="">All Levels</option>
                    <option value="Easy" <?= $difficulty == 'Easy' ? 'selected' : '' ?>>Easy</option>
                    <option value="Medium" <?= $difficulty == 'Medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="Hard" <?= $difficulty == 'Hard' ? 'selected' : '' ?>>Hard</option>
                </select>
            </div>
        </div>
        
        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <label class="filter-label">Dietary Preferences</label>
                <select class="form-select" name="dietary_preference[]" multiple>
                    <?php 
                    $dietary_preferences_result->data_seek(0); 
                    while ($diet_row = mysqli_fetch_assoc($dietary_preferences_result)): 
                        $selected = is_array($dietary_preference) && in_array($diet_row['name'], $dietary_preference) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($diet_row['name']) ?>" <?= $selected ?>>
                            <?= htmlspecialchars($diet_row['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
            </div>

        
        <div class="mt-4 d-flex justify-content-between">
            <button type="submit" class="btn btn-warning px-4">
                <i class="fas fa-filter me-2"></i> Apply Filters
            </button>
            <a href="allRecipes.php" class="btn btn-outline-secondary px-4">
                <i class="fas fa-broom me-2"></i> Clear Filters
            </a>
        </div>
    </form>
</div>

<!-- Active Filters -->
<?php if (!empty($search) || !empty($cuisine) || !empty($meal_type) || !empty($difficulty) || !empty($dietary_preference)): ?>
<div class="active-filters mb-4">
    <h5 class="mb-2">Active Filters:</h5>
    <?php if (!empty($search)): ?>
        <span class="filter-chip">
            Search: "<?= htmlspecialchars($search) ?>"
            <a href="allRecipes.php?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="remove-filter">
                <i class="fas fa-times"></i>
            </a>
        </span>
    <?php endif; ?>
    
    <?php if (!empty($cuisine)): ?>
        <span class="filter-chip">
            Cuisine: <?= htmlspecialchars($cuisine) ?>
            <a href="allRecipes.php?<?= http_build_query(array_merge($_GET, ['cuisine' => ''])) ?>" class="remove-filter">
                <i class="fas fa-times"></i>
            </a>
        </span>
    <?php endif; ?>
    
    <?php if (!empty($meal_type)): ?>
        <span class="filter-chip">
            Meal Type: <?= htmlspecialchars($meal_type) ?>
            <a href="allRecipes.php?<?= http_build_query(array_merge($_GET, ['meal_type' => ''])) ?>" class="remove-filter">
                <i class="fas fa-times"></i>
            </a>
        </span>
    <?php endif; ?>
    
    <?php if (!empty($difficulty)): ?>
        <span class="filter-chip">
            Difficulty: <?= htmlspecialchars($difficulty) ?>
            <a href="allRecipes.php?<?= http_build_query(array_merge($_GET, ['difficulty' => ''])) ?>" class="remove-filter">
                <i class="fas fa-times"></i>
            </a>
        </span>
    <?php endif; ?>
    
    <?php if (!empty($dietary_preference)): ?>
        <?php foreach((array)$dietary_preference as $diet): ?>
            <span class="filter-chip">
                <?= htmlspecialchars($diet) ?>
                <?php
                $new_dietary = array_filter((array)$dietary_preference, function($item) use ($diet) {
                    return $item !== $diet;
                });
                $query = array_merge($_GET, ['dietary_preference' => $new_dietary]);
                ?>
                <a href="allRecipes.php?<?= http_build_query($query) ?>" class="remove-filter">
                    <i class="fas fa-times"></i>
                </a>
            </span>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Recipe Count -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="section-title">All Recipes</h3>
    <span class="recipe-count"><?= $recipe_count ?> recipes found</span>
</div>
    <!-- Recipe Grid Section -->
    <section class="recipe-section">
        <div class="container">
           
            
 <div class="recipe-grid">
    <?php if($recipe_count > 0): ?>
        <?php while($recipe = mysqli_fetch_assoc($result)): ?>
            <div class="recipe-card">
                <div class="recipe-img-container position-relative">
                    <img src="<?= !empty($recipe['image']) ? 'image/'.$recipe['image'] : 'images/default-recipe.jpg' ?>" 
                         class="recipe-img" alt="<?= htmlspecialchars($recipe['title']) ?>">
                    
                    <!-- Meal Type Badge (Top Left) -->
                    <span class="position-absolute top-0 start-0 m-2 badge bg-warning text-dark">
                        <i class="fas fa-utensils me-1"></i>
                        <?= htmlspecialchars($recipe['meal_type_name']) ?>
                    </span>
                    
                    <!-- Cuisine Badge (Top Right) -->
                    <span class="position-absolute top-0 end-0 m-2 badge bg-white text-dark border border-light">
                        <i class="fas fa-globe me-1"></i>
                        <?= htmlspecialchars($recipe['cuisine_name']) ?>
                    </span>
                </div>
                <div class="recipe-content">
                    <h3 class="recipe-title">
                        <a href="recipe.php?id=<?= $recipe['RecipeID'] ?>" class="text-decoration-none text-dark">
                            <?= htmlspecialchars($recipe['title']) ?>
                        </a>
                    </h3>
                    <div class="recipe-meta">
                        <span><i class="far fa-clock"></i> <?= $recipe['cooking_time'] ?> min</span>
                        <span><i class="fas fa-utensils"></i> <?= $recipe['serving'] ?> serving<?= $recipe['serving'] > 1 ? 's' : '' ?></span>
                    </div>
                    <div class="rating">
                        <?php
                        $rating = round($recipe['avg_rating'] ?? 0);
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12 text-center py-5">
            <img src="no-recipe-found.jpeg" alt="No recipes found" style="max-width: 300px; margin-bottom: 2rem;">
            <h4>No recipes found matching your filters</h4>
            <p class="text-muted">Try adjusting your search or filters</p>
            <a href="allRecipes.php" class="btn btn-warning mt-3">Clear all filters</a>
        </div>
    <?php endif; ?>
</div>

<style>
/* Additional Styles for Badges */
.recipe-img-container .badge {
    padding: 0.35em 0.8em;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 50px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    backdrop-filter: blur(2px);
}

.bg-warning {
    background-color: var(--sunshine) !important;
}

.border-light {
    border-color: rgba(255,255,255,0.5) !important;
}
</style>
            
        
        </div>
    </section>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <a href="#" class="footer-logo">DishDash</a>
            <p>Discover, cook, and share amazing recipes from around the world.</p>
            <p class="copyright">&copy; <?= date('Y') ?> DishDash. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to remove a query parameter from URL
        function removeQueryParam(param) {
            const url = new URL(window.location.href);
            url.searchParams.delete(param);
            window.location.href = url.toString();
        }
    </script>
</body>
</html>