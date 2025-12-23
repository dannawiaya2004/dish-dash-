<?php
require 'db.php';
session_start();

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM user WHERE UserID = $user_id";
$user_result = $conn->query($user_query);

if (!$user_result || $user_result->num_rows == 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $user_result->fetch_assoc();

$popular_recipes = [];
$top_chefs = [];
$dietary_restrictions = [];
$fridge_recipes = [];



$popular_recipes_query = "SELECT r.*, 
       AVG(rt.rating) AS avg_rating, 
       COUNT(rt.rating) AS rating_count, 
       COUNT(f.recipe_id) AS favorite_count, 
       ct.name AS cuisine_type, 
       mt.name AS meal_type, 
       u.name AS chef_name
FROM recipe r
LEFT JOIN rate rt ON r.RecipeID = rt.recipe_id
LEFT JOIN fav f ON r.RecipeID = f.recipe_id
LEFT JOIN cuisine_type ct ON r.cuisine_typeID = ct.CuisineID
LEFT JOIN meal_types mt ON r.meal_typesID = mt.MealID
LEFT JOIN chef c ON r.chefs_id = c.ChefID
LEFT JOIN user u ON c.user_id = u.UserID
WHERE r.status = 'published'
GROUP BY r.RecipeID
ORDER BY avg_rating DESC, favorite_count DESC
LIMIT 6
";

if ($result = $conn->query($popular_recipes_query)) {
    $popular_recipes = $result->fetch_all(MYSQLI_ASSOC);
}

$top_chefs_query = "SELECT c.*, u.name, u.email, (SELECT COUNT(*) FROM recipe r WHERE chefs_id = c.ChefID AND r.status = 'published') as recipe_count,
 (SELECT AVG(rating) FROM rate_chef WHERE chef_id = c.ChefID) as avg_rating,
  (SELECT COUNT(*) FROM rate_chef WHERE chef_id = c.ChefID) as rating_count
   FROM chef c 
   JOIN user u ON c.user_id = u.UserID 
   WHERE c.status = 'approved' ORDER BY rating_count DESC, avg_rating DESC LIMIT 3;";

if ($result = $conn->query($top_chefs_query)) {
    $top_chefs = $result->fetch_all(MYSQLI_ASSOC);
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DishDash - Welcome <?php echo htmlspecialchars($user['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #FFA500; /* Orange */
            --secondary: #FFD700; /* Yellow */
            --dark: #2E8B57; /* Sea Green */
            --light: #F5FFFA; /* Mint Cream */
            --accent: #32CD32; /* Lime Green */
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light);
            color: #333;
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
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            text-align: center;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .search-bar {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .section-title {
            font-weight: 700;
            margin-bottom: 40px;
            position: relative;
            display: inline-block;
            color: var(--dark);
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary);
        }
        

        
        .rating-stars {
            color: var(--secondary);
            margin-bottom: 10px;
        }
        

        .chef-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        
        

        
        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .footer {
            background-color: var(--dark);
            color: white;
            padding: 50px 0;
        }
        
        .footer-links a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: var(--secondary);
        }
        
        .social-icons a {
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
            transition: color 0.3s;
        }
        
        .social-icons a:hover {
            color: var(--secondary);
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .progress-bar {
            background-color: var(--primary);
        }
        
        .matching-ingredients {
            font-size: 0.9rem;
            color: #666;
        }
        
        .alert-info {
            background-color: #e8f4ea;
            border-color: #d1e7d8;
            color:rgb(88, 194, 134);
        }
        
        .text-muted {
            color: #6c757d !important;
        }




/* Updated Card Styles */
.recipe-card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    margin-bottom: 30px;
    background: white;
    position: relative;
}

.recipe-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
}

.card-img-top {
    height: 220px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.recipe-card:hover .card-img-top {
    transform: scale(1.03);
}

.badge-popular {
    position: absolute;
    top: 15px;
    right: 15px;
    background-color: var(--primary);
    color: white;
    padding: 5px 10px;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.badge-cuisine {
    position: absolute;
    top: 15px;
    left: 15px;
    background-color: var(--dark);
    color: white;
    padding: 5px 10px;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.card-body {
    padding: 20px;
}

.card-title {
    font-weight: 700;
    font-size: 1.2rem;
    margin-bottom: 10px;
    color: var(--dark);
    line-height: 1.3;
}

.card-text {
    color: #666;
    font-size: 0.95rem;
    margin-bottom: 15px;
    line-height: 1.5;
}

.recipe-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    font-size: 0.85rem;
}

.recipe-meta span {
    display: flex;
    align-items: center;
    color: #777;
}

.recipe-meta i {
    margin-right: 5px;
    color: var(--primary);
}

.card-footer {
    background: linear-gradient(to right, #f9f9f9, white);
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 15px 20px;
}

/* Chef Card Improvements */
.chef-card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    text-align: center;
    padding: 30px;
    background: white;
    position: relative;
    border-top: 4px solid var(--accent);
}

.chef-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
}

.chef-img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    margin: 0 auto 20px;
    transition: all 0.3s ease;
}

.chef-card:hover .chef-img {
    transform: scale(1.05);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.chef-name {
    font-weight: 700;
    margin-bottom: 5px;
    color: var(--dark);
    font-size: 1.3rem;
}

.chef-specialty {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 15px;
    font-size: 0.95rem;
    position: relative;
    display: inline-block;
    padding-bottom: 8px;
}

.chef-specialty:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 2px;
    background-color: var(--accent);
}

.chef-stats {
    display: flex;
    justify-content: space-around;
    margin-bottom: 20px;
    background: rgba(245, 245, 245, 0.7);
    padding: 12px;
    border-radius: 10px;
}

.stat-item {
    text-align: center;
    padding: 0 10px;
}

.stat-number {
    font-weight: 700;
    font-size: 1.3rem;
    color: var(--dark);
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: #777;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 5px;
}

/* Hover effects for buttons */
.btn-primary {
    background-color: var(--primary);
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 8px rgba(255, 165, 0, 0.2);
}

.btn-primary:hover {
    background-color: #e69500;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(255, 165, 0, 0.3);
}

.btn-outline-primary {
    transition: all 0.3s ease;
}

.btn-outline-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                        <a class="nav-link active" href="main.php"><i class="fas fa-home me-1"></i> Home</a>
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
              
            </div>
        </div>
    
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="favorites.php"><i class="fas fa-heart me-2"></i> Favorites</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title animate__animated animate__fadeInDown">Welcome back, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>!</h1>
            <p class="hero-subtitle animate__animated animate__fadeInDown animate__delay-1s">Discover delicious recipes tailored just for you</p>
            <div class="search-bar animate__animated animate__fadeInUp animate__delay-1s">
                <form action="search.php" method="GET" class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search recipes and chefs">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <h2 class="text-center section-title">Popular Recipes</h2>
            <?php if (!empty($popular_recipes)): ?>
                <div class="row">
                    <?php foreach ($popular_recipes as $recipe): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="recipe-card h-100">
                            <div class="position-relative">
                                <img src="./image/<?php echo $recipe['image']; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                <span class="badge badge-pill badge-popular">
                                    <i class="fas fa-fire me-1"></i> Popular
                                </span>
                                <span class="badge badge-pill badge-cuisine">
                                    <?php echo htmlspecialchars($recipe['cuisine_type']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="rating-stars">
                                    <?php 
                                    $avg_rating = round($recipe['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $avg_rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                    <span class="ms-2">(<?php echo $recipe['rating_count']; ?>)</span>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars(substr($recipe['title'], 0, 25)); ?>...</h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($recipe['details'], 0, 100)); ?>...</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted"><i class="fas fa-clock me-1"></i> <?php echo $recipe['cooking_time']; ?> mins</span>
                                    <span class="text-muted"><i class="fas fa-utensils me-1"></i> <?php echo htmlspecialchars($recipe['meal_type']); ?></span>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <a href="recipe.php?id=<?php echo $recipe['RecipeID']; ?>" class="btn btn-primary btn-block">
                                    View Recipe <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="allRecipes.php" class="btn btn-outline-primary btn-lg">
                        Browse All Recipes <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    No popular recipes found. Check back later!
                </div>
            <?php endif; ?>
        </div>
    </section>

    

    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center section-title">Popular Chefs</h2>
            <?php if (!empty($top_chefs)): ?>
                <div class="row">
                    <?php foreach ($top_chefs as $chef): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="chef-card h-100">
                      
                            <h4 class="chef-name"><?php echo htmlspecialchars($chef['name']); ?></h4>
                            <p class="chef-specialty"><?php echo !empty($chef['specialties']) ? htmlspecialchars($chef['specialties']) : 'Professional Chef'; ?></p>
                            <div class="chef-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $chef['recipe_count']; ?></div>
                                    <div class="stat-label">Recipes</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo number_format($chef['avg_rating'], 1); ?></div>
                                    <div class="stat-label">Rating</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $chef['rating_count']; ?></div>
                                    <div class="stat-label">Reviews</div>
                                </div>
                            </div>
                            <a href="chef_profile.php?id=<?php echo $chef['ChefID']; ?>" class="btn btn-outline-primary">
                                View Profile <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="chefs.php" class="btn btn-primary btn-lg">
                        Meet All Chefs <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    No featured chefs available at the moment.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5><i class="fas fa-utensils me-2"></i> DishDash</h5>
                    <p>Discover, cook, and share delicious recipes from around the world.</p>
                  
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Explore</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="main.php">Home</a></li>
                        <li><a href="recipes.php">Recipes</a></li>
                        <li><a href="chefs.php">Chefs</a></li>
                        <li><a href="fridge.php">My Fridge</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Company</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="about_us.php">About Us</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                    </ul>
                </div>
              
            </div>
            <hr class="my-4 bg-light">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> DishDash. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">Made with <i class="fas fa-heart text-danger"></i> for food lovers</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const animateCards = function() {
            const cards = document.querySelectorAll('.recipe-card, .chef-card');
            
            cards.forEach(card => {
                const cardPosition = card.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.2;
                
                if (cardPosition < screenPosition) {
                    card.classList.add('animate__animated', 'animate__fadeInUp');
                }
            });
        };
        
        window.addEventListener('scroll', animateCards);
        window.addEventListener('load', animateCards);
    </script>
</body>
</html>