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

$meal_types_query = "SELECT * FROM meal_types";
$cuisine_types_query = "SELECT * FROM cuisine_type";
$dietary_preferences_query = "SELECT * FROM dietary_preferences";
$chefs_query = "SELECT c.ChefID, u.name FROM chef c JOIN user u ON c.user_id = u.userID WHERE c.ChefID != $chef_id AND c.status ='approved'";

$meal_types_result = mysqli_query($conn, $meal_types_query);
$cuisine_types_result = mysqli_query($conn, $cuisine_types_query);
$dietary_preferences_result = mysqli_query($conn, $dietary_preferences_query);
$chefs_result = mysqli_query($conn, $chefs_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Dashboard - Dish Dash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: rgb(255, 195, 31);
            --secondary-color: #4ECDC4;
            --accent-color: #FFE66D;
            --dark-color: #292F36;
            --light-color: #F7FFF7;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --dark: #2E8B57;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fafafa;
            color: var(--dark-color);
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
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 5rem 0;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80') center/cover;
            opacity: 0.15;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-weight: 700;
            font-size: 2.8rem;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .hero-subtitle {
            font-weight: 400;
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 2rem;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 3rem;
        }
        
        .section-title {
            font-weight: 600;
            color: var(--dark-color);
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
            background: var(--primary-color);
            border-radius: 3px;
        }
        
        .filter-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }
        
        .form-select, .form-control {
            border-radius: 10px;
            padding: 0.6rem 1rem;
            border: 1px solid #e0e0e0;
            transition: var(--transition);
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 107, 0.25);
        }
        
        .filter-section .form-select[multiple] {
            height: auto;
            min-height: 44px;
            padding: 0.5rem;
        }
        
        .filter-section .form-select[multiple] option {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            margin: 2px 0;
        }
        
        .filter-section .form-select[multiple] option:checked {
            background-color: var(--primary-color);
            color: white;
        }
        
        .recipe-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
            height: 100%;
        }
        
        .recipe-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        .card-img-top {
            height: 200px;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .recipe-card:hover .card-img-top {
            transform: scale(1.05);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.75rem;
        }
        
        .card-text {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
        }
        
        .recipe-meta {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        
        .recipe-meta span {
            display: inline-flex;
            align-items: center;
            margin-right: 1rem;
            color: #666;
        }
        
        .recipe-meta i {
            margin-right: 5px;
            color: var(--primary-color);
        }
        
        .badge-cuisine {
            background-color: var(--secondary-color);
            color: white;
            font-weight: 500;
            border-radius: 50px;
            padding: 0.35em 0.8em;
            font-size: 0.75rem;
            margin-right: 0.5rem;
        }
        
        .badge-difficulty {
            font-weight: 500;
            border-radius: 50px;
            padding: 0.35em 0.8em;
            font-size: 0.75rem;
        }
        
        .badge-easy {
            background-color: #C1FBA4;
            color: #1B5E20;
        }
        
        .badge-medium {
            background-color: #FFE28A;
            color: rgb(230, 150, 0);
        }
        
        .badge-hard {
            background-color: rgb(249, 255, 161);
            color: rgb(255, 123, 15);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            background-color: rgb(255, 190, 12);
            border-color: rgb(255, 201, 22);
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-outline-secondary:hover {
            transform: translateY(-2px);
        }
        
        .action-buttons .btn {
            flex: 1;
            font-size: 0.85rem;
            padding: 0.5rem;
        }
        
        .loading {
            position: relative;
            min-height: 200px;
        }
        
        .loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .recipe-count {
            font-size: 0.9rem;
            color: #666;
            font-weight: 400;
            margin-left: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .filter-section {
                padding: 1.5rem;
            }
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
                        <a class="nav-link active" href="#"><i class="fas fa-home"></i> Dashboard</a>
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

    <section class="hero-section text-center">
        <div class="hero-content container">
            <h1 class="hero-title">Welcome Back, Chef!</h1>
            <p class="hero-subtitle">Create, share, and discover amazing recipes with our community of culinary artists</p>
            <a href="chefAddRecipe.php" class="btn btn-light btn-lg px-4">
                <i class="fas fa-plus me-2"></i> Add New Recipe
            </a>
        </div>
    </section>

    <div class="container mb-2">
        <div class="filter-section">
            <h3 class="section-title">Filter Recipes</h3>
            <div class="row mt-2">
                <div class="col-md-8">
                    <label class="filter-label">Search Recipes</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchInput" name="search" placeholder="Search by recipe name...">
                        <button class="btn btn-primary" type="button" id="searchButton">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>

            <form id="filterForm">
                <div class="row g-6 mt-2">
                    <div class="col-md-4">
                        <label class="filter-label">Meal Type</label>
                        <select class="form-select" id="mealTypeSelect" name="meal_type">
                            <option value="">All Meal Types</option>
                            <?php while ($meal_type = mysqli_fetch_assoc($meal_types_result)): ?>
                                <option value="<?= $meal_type['name'] ?>">
                                    <?= htmlspecialchars($meal_type['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="filter-label">Cuisine</label>
                        <select class="form-select" id="cuisineSelect" name="cuisine">
                            <option value="">All Cuisines</option>
                            <?php while ($cuisine = mysqli_fetch_assoc($cuisine_types_result)): ?>
                                <option value="<?= $cuisine['name'] ?>">
                                    <?= htmlspecialchars($cuisine['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="filter-label">Difficulty</label>
                        <select class="form-select" id="difficultySelect" name="difficulty">
                            <option value="">All Levels</option>
                            <option value="Easy">Easy</option>
                            <option value="Medium">Medium</option>
                            <option value="Hard">Hard</option>
                        </select>
                    </div>
                </div>
                
                <div class="row g-6 mt-3">
                    <div class="col-md-4">
                        <label class="filter-label">Dietary Preferences</label>
                        <select class="form-select" id="dietarySelect" name="dietary_preference" multiple>
                            <?php while ($diet = mysqli_fetch_assoc($dietary_preferences_result)): ?>
                                <option value="<?= $diet['name'] ?>">
                                    <?= htmlspecialchars($diet['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                    <div class="col-md-4">
                        <label class="filter-label">Chefs</label>
                        <select class="form-select" id="chefSelect" name="chef">
                            <option value="">All Chefs</option>
                            <?php while ($chef = mysqli_fetch_assoc($chefs_result)): ?>
                                <option value="<?= $chef['ChefID'] ?>">
                                    <?= htmlspecialchars($chef['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
             
                </div>
                
                <div class="mt-4 d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <button type="button" id="clearFilters" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-broom me-2"></i> Clear Filters
                    </button>
                </div>
            </form>
        </div>

        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="section-title m-0">My Recipes</h3>
                <span id="myRecipesCount" class="recipe-count"></span>
            </div>
            <div id="myRecipesContainer" class="row">
                <div class="col-12 text-center py-5 loading"></div>
            </div>
        </div>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="section-title m-0">Community Recipes</h3>
            </div>
            <div id="otherRecipesContainer" class="row">
                <div class="col-12 text-center py-5 loading"></div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        loadRecipes();

        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            loadRecipes();
        });

        $('#clearFilters').on('click', function() {
            $('#mealTypeSelect').val('');
            $('#cuisineSelect').val('');
            $('#difficultySelect').val('');
            $('#dietarySelect').val('');
            $('#chefSelect').val('');
            $('#searchInput').val('');
            loadRecipes();
        });

        $('#searchButton').on('click', function() {
            loadRecipes();
        });

        $('#searchInput').on('keypress', function(e) {
            if (e.which === 13) {
                loadRecipes();
                return false;
            }
        });

        function loadRecipes() {
            $('#myRecipesContainer').html('<div class="col-12 text-center py-5 loading"></div>');
            $('#otherRecipesContainer').html('<div class="col-12 text-center py-5 loading"></div>');

            const mealType = $('#mealTypeSelect').val();
            const cuisine = $('#cuisineSelect').val();
            const difficulty = $('#difficultySelect').val();
            const searchTerm = $('#searchInput').val();
            const dietaryPreferences = $('#dietarySelect').val() || [];
            const chefId = $('#chefSelect').val();

            $.ajax({
                url: 'get_my_recipes.php',
                type: 'GET',
                data: {
                    meal_type: mealType,
                    cuisine: cuisine,
                    difficulty: difficulty,
                    search: searchTerm,
                    dietary_preferences: dietaryPreferences,
                    chef_id: chefId,
                    current_chef_id: <?= $chef_id ?>
                },
                success: function(response) {
                    $('#myRecipesContainer').html(response);
                    const count = $('#myRecipesContainer .col-md-4').length;
                    $('#myRecipesCount').text(count + ' recipes');
                },
                error: function() {
                    $('#myRecipesContainer').html('<div class="col-12 text-center py-4 text-danger">Error loading recipes. Please try again.</div>');
                }
            });

            $.ajax({
                url: 'get_other_recipes.php',
                type: 'GET',
                data: {
                    meal_type: mealType,
                    cuisine: cuisine,
                    difficulty: difficulty,
                    search: searchTerm,
                    dietary_preferences: dietaryPreferences,
                    chef_id: chefId,
                    current_chef_id: <?= $chef_id ?>
                },
                success: function(response) {
                    $('#otherRecipesContainer').html(response);
                },
                error: function() {
                    $('#otherRecipesContainer').html('<div class="col-12 text-center py-4 text-danger">Error loading community recipes.</div>');
                }
            });
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>