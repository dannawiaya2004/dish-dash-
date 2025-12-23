<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$chef_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$chef_id = $conn->real_escape_string($chef_id);

if ($chef_id <= 0) {
    header("Location: chefs.php");
    exit();
}

$chef_query = "SELECT c.*, u.name, u.email, u.phone, u.country, u.experience, u.details AS user_details
               FROM chef c
               JOIN user u ON c.user_id = u.UserID
               WHERE c.ChefID = '$chef_id' AND c.status = 'approved'";
$chef_result = $conn->query($chef_query);
$chef = $chef_result->fetch_assoc();

if (!$chef) {
    header("Location: chefs.php");
    exit();
}

$recipes_query = "SELECT r.*, ct.name AS cuisine_type, mt.name AS meal_type,
                 (SELECT AVG(rating) FROM rate WHERE recipe_id = r.RecipeID) AS avg_rating,
                 (SELECT COUNT(*) FROM rate WHERE recipe_id = r.RecipeID) AS rating_count
                 FROM recipe r
                 LEFT JOIN cuisine_type ct ON r.cuisine_typeID = ct.CuisineID
                 LEFT JOIN meal_types mt ON r.meal_typesID = mt.MealID
                 WHERE r.chefs_id = '$chef_id' AND r.status = 'published'
                 ORDER BY r.created_at DESC";
$recipes_result = $conn->query($recipes_query);
$recipes = $recipes_result->fetch_all(MYSQLI_ASSOC);

$ratings_query = "SELECT r.*, u.name AS user_name
                 FROM rate_chef r
                 JOIN user u ON r.user_id = u.UserID
                 WHERE r.chef_id = '$chef_id'
                 ORDER BY r.date DESC";
$ratings_result = $conn->query($ratings_query);
$ratings = $ratings_result->fetch_all(MYSQLI_ASSOC);

$avg_rating_query = "SELECT AVG(rating) AS avg_rating, COUNT(*) AS rating_count
                    FROM rate_chef
                    WHERE chef_id = '$chef_id'";
$avg_rating_result = $conn->query($avg_rating_query);
$rating_stats = $avg_rating_result->fetch_assoc();

$education_query = "SELECT * FROM graduate WHERE chef_id = '$chef_id' ORDER BY year DESC";
$education_result = $conn->query($education_query);
$education = $education_result->fetch_all(MYSQLI_ASSOC);

$experience_query = "SELECT * FROM Experience WHERE chef_id = '$chef_id' ORDER BY year_start DESC";
$experience_result = $conn->query($experience_query);
$experiences = $experience_result->fetch_all(MYSQLI_ASSOC);

// Check if current user has favorited this chef
$is_favorited = false;
$user_id = $conn->real_escape_string($_SESSION['user_id']);
$favorite_query = "SELECT 1 FROM fav_chef WHERE user_id = '$user_id' AND chef_id = '$chef_id'";
$favorite_result = $conn->query($favorite_query);
$is_favorited = $favorite_result->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($chef['name']); ?> - DishDash Chef</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FFA500;
            --secondary: #FFD700;
            --dark: #2E8B57;
            --light: #F5FFFA;
            --accent: #32CD32;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --card-radius: 12px;
            --card-border: 1px solid rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .chef-header {
            background: linear-gradient(135deg, var(--dark) 0%, #3aa76d 100%);
            color: white;
            padding: 80px 0 120px;
            margin-bottom: 60px;
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .chef-avatar {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            position: absolute;
            bottom: -80px;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
            z-index: 10;
        }
        
        .chef-avatar:hover {
            transform: translateX(-50%) scale(1.05);
        }
        
        .rating-stars {
            color: var(--secondary);
            font-size: 1.2rem;
            letter-spacing: 2px;
        }
        
        /* Enhanced Card Styling */
        .card {
            border: none;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            background-color: var(--card-bg);
            overflow: hidden;
            margin-bottom: 25px;
            border: var(--card-border);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 15px 20px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Recipe Cards */
        .recipe-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .recipe-card .card-img-top {
            height: 220px;
            object-fit: cover;
            width: 100%;
            transition: var(--transition);
        }
        
        .recipe-card:hover .card-img-top {
            transform: scale(1.03);
        }
        
        .recipe-card .card-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .recipe-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .recipe-card .card-text {
            color: #666;
            font-size: 0.9rem;
            flex-grow: 1;
            margin-bottom: 15px;
        }
        
        .recipe-card .card-footer {
            background-color: transparent;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding: 15px;
        }
        
        /* Review Cards */
        .review-card {
            border-radius: var(--card-radius);
            padding: 20px;
            margin-bottom: 20px;
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border: var(--card-border);
            transition: var(--transition);
        }
        
        .review-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .review-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .review-content {
            flex-grow: 1;
        }
        
        .review-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 3px;
        }
        
        .review-comment {
            margin-top: 10px;
            line-height: 1.6;
            color: #555;
            font-size: 0.95rem;
        }
        
        /* Timeline Cards */
        .timeline-card {
            position: relative;
            padding-left: 30px;
            margin-bottom: 30px;
        }
        
        .timeline-card::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 15px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--dark);
            border: 3px solid white;
            z-index: 1;
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
        
        /* Button Styling */
        .btn-primary {
            background-color: var(--dark);
            border-color: var(--dark);
        }
        
        .btn-primary:hover {
            background-color: #2a7a56;
            border-color: #2a7a56;
        }
        
        .btn-outline-primary {
            color: var(--dark);
            border-color: var(--dark);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--dark);
            border-color: var(--dark);
        }
        
        /* Form Styling */
        #ratingStars i {
            cursor: pointer;
            transition: var(--transition);
            margin: 0 2px;
        }
        
        #ratingStars i:hover {
            transform: scale(1.2);
            color: var(--secondary);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .chef-header {
                padding: 60px 0 100px;
            }
            
            .chef-avatar {
                width: 120px;
                height: 120px;
                bottom: -60px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="main.php">
                <span class="fw-bold text-success">DishDash</span>
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
                        <a class="nav-link" href="chefs.php"><i class="fas fa-utensils me-1"></i> Chefs</a>
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
    </nav>

    <section class="chef-header text-center">
        <div class="container">
            <h1 class="mb-3"><?php echo htmlspecialchars($chef['name']); ?></h1>
            <p class="lead mb-4"><?php echo htmlspecialchars($chef['specialties'] ?? 'Professional Chef'); ?></p>
            <div class="d-flex justify-content-center gap-4">
                <div>
                    <h4 class="mb-0"><?php echo count($recipes); ?></h4>
                    <small>Recipes</small>
                </div>
                <div>
                    <h4 class="mb-0"><?php echo $rating_stats['rating_count'] ?? 0; ?></h4>
                    <small>Ratings</small>
                </div>
                <div>
                    <h4 class="mb-0"><?php echo $chef['experience'] ?? 'N/A'; ?></h4>
                    <small>Years Experience</small>
                </div>
            </div>
        </div>
    </section>

    <div class="container" style="margin-top: 80px;">
        <div class="row">
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if ($rating_stats['avg_rating']): ?>
                                <div class="rating-stars mb-2">
                                    <?php 
                                    $full_stars = floor($rating_stats['avg_rating']);
                                    $half_star = ceil($rating_stats['avg_rating'] - $full_stars);
                                    $empty_stars = 5 - $full_stars - $half_star;
                                    
                                    for ($i = 0; $i < $full_stars; $i++) {
                                        echo '<i class="fas fa-star"></i>';
                                    }
                                    if ($half_star) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    }
                                    for ($i = 0; $i < $empty_stars; $i++) {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </div>
                                <h5><?php echo number_format($rating_stats['avg_rating'], 1); ?>/5</h5>
                                <small class="text-muted"><?php echo $rating_stats['rating_count']; ?> ratings</small>
                            <?php else: ?>
                                <p class="text-muted">No ratings yet</p>
                            <?php endif; ?>
                        </div>
                        
                        <button class="btn btn-<?php echo $is_favorited ? 'danger' : 'primary'; ?> w-100 mb-3" 
                                id="favoriteBtn">
                            <i class="fas fa-heart me-1"></i>
                            <?php echo $is_favorited ? 'Unfollow' : 'Follow'; ?>
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Details</h5>
                    </div>
                    <div class="card-body">
                        <p><i class="fas fa-envelope me-2 text-muted"></i> <?php echo htmlspecialchars($chef['email']); ?></p>
                        <?php if (!empty($chef['phone'])): ?>
                            <p><i class="fas fa-phone me-2 text-muted"></i> <?php echo htmlspecialchars($chef['phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($chef['country'])): ?>
                            <p><i class="fas fa-globe me-2 text-muted"></i> <?php echo htmlspecialchars($chef['country']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($chef['specialties'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-award me-2"></i>Specialties</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo htmlspecialchars($chef['specialties']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-9">
                <ul class="nav nav-tabs" id="chefTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#recipes">
                            <i class="fas fa-book me-1"></i> Recipes (<?php echo count($recipes); ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#about">
                            <i class="fas fa-user me-1"></i> About
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews">
                            <i class="fas fa-star me-1"></i> Reviews (<?php echo $rating_stats['rating_count'] ?? 0; ?>)
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-4">
                    <!-- Recipes Tab -->
                    <div class="tab-pane fade show active" id="recipes">
                        <?php if (!empty($recipes)): ?>
                            <div class="row">
                                <?php foreach ($recipes as $recipe): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card recipe-card">
                                        <img src="./image/<?php echo $recipe['image'] ?? 'default-recipe.jpg'; ?>" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($recipe['title']); ?></h5>
                                            <p class="card-text">
                                                <?php echo htmlspecialchars(substr($recipe['details'], 0, 100)); ?>...
                                            </p>
                                            <div class="d-flex justify-content-between text-muted small">
                                                <span><i class="fas fa-clock me-1"></i> <?php echo $recipe['cooking_time']; ?> mins</span>
                                                <span><i class="fas fa-utensils me-1"></i> <?php echo $recipe['meal_type']; ?></span>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <a href="recipe.php?id=<?php echo $recipe['RecipeID']; ?>" class="btn btn-sm btn-primary">
                                                    View Recipe
                                                </a>
                                                <?php if ($recipe['avg_rating']): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-star"></i> <?php echo number_format($recipe['avg_rating'], 1); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                                    <h4>No Recipes Published Yet</h4>
                                    <p class="text-muted">This chef hasn't shared any recipes with the community.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- About Tab -->
                    <div class="tab-pane fade" id="about">
                        <?php if (!empty($chef['user_details'])): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>About Me</h5>
                                </div>
                                <div class="card-body">
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($chef['user_details'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($education)): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Education</h5>
                                </div>
                                <div class="card-body">
                                    <div class="timeline-card">
                                        <?php foreach ($education as $edu): ?>
                                        <div class="timeline-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($edu['university']); ?></h6>
                                            <p class="text-muted mb-1 small"><?php echo htmlspecialchars($edu['major']); ?></p>
                                            <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i>Graduated <?php echo $edu['year']; ?></small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($experiences)): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Professional Experience</h5>
                                </div>
                                <div class="card-body">
                                    <div class="timeline-card">
                                        <?php foreach ($experiences as $exp): ?>
                                        <div class="timeline-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($exp['place']); ?></h6>
                                            <?php if (!empty($exp['position'])): ?>
                                                <p class="text-muted mb-1 small"><?php echo htmlspecialchars($exp['position']); ?></p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?php echo $exp['year_start']; ?> - <?php echo $exp['year_stop'] ?? 'Present'; ?>
                                            </small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Reviews Tab -->
                    <div class="tab-pane fade" id="reviews">
                        <?php if (!empty($ratings)): ?>
                            <div class="mb-4">
                                <?php foreach ($ratings as $rating): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="review-content">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($rating['user_name']); ?></h6>
                                                <div class="rating-stars">
                                                    <?php 
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $rating['rating']) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <small class="review-date">
                                                <?php echo date('M j, Y', strtotime($rating['date'])); ?>
                                            </small>
                                            <?php if (!empty($rating['comment'])): ?>
                                                <div class="review-comment mt-3">
                                                    <?php echo htmlspecialchars($rating['comment']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                    <h4>No Reviews Yet</h4>
                                    <p class="text-muted">Be the first to review this chef!</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Add Review Form -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Leave a Review</h5>
                            </div>
                            <div class="card-body">
                                <form id="reviewForm" action="submit_review_chef.php" method="POST">
                                    <input type="hidden" name="chef_id" value="<?php echo $chef_id; ?>">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Rating</label>
                                        <div class="rating-stars mb-2" id="ratingStars">
                                            <i class="far fa-star" data-rating="1"></i>
                                            <i class="far fa-star" data-rating="2"></i>
                                            <i class="far fa-star" data-rating="3"></i>
                                            <i class="far fa-star" data-rating="4"></i>
                                            <i class="far fa-star" data-rating="5"></i>
                                            <input type="hidden" name="rating" id="ratingValue" value="0" required>
                                        </div>
                                        <div class="invalid-feedback d-block">Please select a rating</div>
                                    </div>
                                    <div class="mb-4">
                                        <label for="comment" class="form-label fw-bold">Your Review</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="4" 
                                                  placeholder="Share your experience with this chef..." required></textarea>
                                        <div class="invalid-feedback">Please enter your review</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary px-4 py-2">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Review
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Favorite button functionality
        document.getElementById('favoriteBtn').addEventListener('click', function() {
            const isFavorited = this.classList.contains('btn-danger');
            const action = isFavorited ? 'remove' : 'add';
            
            fetch('favorite_chef.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `chef_id=<?php echo $chef_id; ?>&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (isFavorited) {
                        this.classList.remove('btn-danger');
                        this.classList.add('btn-primary');
                        this.innerHTML = '<i class="fas fa-heart me-1"></i> Follow';
                    } else {
                        this.classList.remove('btn-primary');
                        this.classList.add('btn-danger');
                        this.innerHTML = '<i class="fas fa-heart me-1"></i> Unfollow';
                    }
                }
            });
        });
        
        // Rating stars interaction
        const stars = document.querySelectorAll('#ratingStars i');
        const ratingValue = document.getElementById('ratingValue');
        const ratingError = document.querySelector('.rating-stars + .invalid-feedback');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                ratingValue.value = rating;
                ratingError.style.display = 'none';
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    } else {
                        s.classList.remove('fas');
                        s.classList.add('far');
                    }
                });
            });
            
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    }
                });
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = parseInt(ratingValue.value);
                
                stars.forEach((s, index) => {
                    if (index >= currentRating) {
                        s.classList.remove('fas');
                        s.classList.add('far');
                    }
                });
            });
        });
        
        // Review form validation and submission
        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate form
            let isValid = true;
            const rating = parseInt(ratingValue.value);
            const comment = document.getElementById('comment').value.trim();
            
            if (rating < 1 || rating > 5) {
                ratingError.style.display = 'block';
                isValid = false;
            } else {
                ratingError.style.display = 'none';
            }
            
            if (comment === '') {
                document.getElementById('comment').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('comment').classList.remove('is-invalid');
            }
            
            if (isValid) {
                // Submit form normally (no AJAX)
                this.submit();
            }
        });
    </script>
</body>
</html>