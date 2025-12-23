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

//approved chefs
$chefs_query = "SELECT c.*, u.name, u.email, 
                (SELECT COUNT(*) FROM recipe WHERE chefs_id = c.ChefID) as recipe_count,
                (SELECT AVG(rating) FROM rate_chef WHERE chef_id = c.ChefID) as avg_rating,
                (SELECT COUNT(*) FROM rate_chef WHERE chef_id = c.ChefID) as rating_count
                FROM chef c 
                JOIN user u ON c.user_id = u.UserID 
                WHERE c.status = 'approved'
                ORDER BY rating_count DESC, avg_rating DESC";

$chefs_result = $conn->query($chefs_query);
$all_chefs = $chefs_result ? $chefs_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DishDash - Our Chefs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #e69500; /* Orange */
            --secondary: #58c286; /* RGB(88, 194, 134) */
            --dark: #2E8B57; /* Sea Green */
            --light: #F5FFFA; /* Mint Cream */
            --accent: #58c286; /* RGB(88, 194, 134) */
        }
        
        .chefs-hero {
            background-color: var(--dark);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .chef-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            background: white;
            height: 100%;
            display: flex;
            flex-direction: column;
            border-top: 4px solid var(--secondary);
        }
        
        .chef-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .chef-avatar {
            width: 80px;
            height: 80px;
            background-color: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 2rem;
            color: var(--dark);
            font-weight: bold;
            border: 3px solid var(--primary);
        }
        
        .chef-card-header {
            background: linear-gradient(135deg, var(--light), white);
            padding: 30px 20px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .chef-card-body {
            padding: 20px;
            flex-grow: 1;
        }
        
        .chef-name {
            font-weight: 700;
            color: var(--dark);
            margin: 15px 0 5px;
        }
        
        .chef-specialty {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .chef-bio {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .chef-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            background: rgba(245, 255, 250, 0.7);
            padding: 12px;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--dark);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .rating-stars {
            color: var(--primary);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: #d18700;
            border-color: #d18700;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            background-color: #f9f9f9;
            border-radius: 10px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
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
                        <a class="nav-link" href="allRecipes.php"><i class="fas fa-book me-1"></i> Recipes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="fridge.php"><i class="fas fa-ice-cream me-1"></i> My Fridge</a>
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
                    <a href="logout.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <section class="chefs-hero">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Our Culinary Experts</h1>
            <p class="lead mb-0">Discover the talented chefs behind your favorite recipes</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row">
                <?php if (!empty($all_chefs)): ?>
                    <?php foreach ($all_chefs as $chef): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="chef-card">
                            <div class="chef-card-header">
                               
                                <h3 class="chef-name"><?php echo htmlspecialchars($chef['name']); ?></h3>
                                <p class="chef-specialty">
                                    <i class="fas fa-utensils me-1"></i>
                                    <?php echo !empty($chef['specialties']) ? htmlspecialchars($chef['specialties']) : 'Professional Chef'; ?>
                                </p>
                                <div class="rating-stars">
                                    <?php 
                                    $avg_rating = round($chef['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $avg_rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                    <span class="ms-2">(<?php echo $chef['rating_count']; ?>)</span>
                                </div>
                            </div>
                            <div class="chef-card-body">
                                <p class="chef-bio">
                                    <?php echo !empty($chef['details']) ? htmlspecialchars(substr($chef['details'], 0, 50)) . (strlen($chef['details']) > 50 ? '...' : '') : 'Passionate about creating delicious meals .'; ?>
                                </p>
                                <div class="chef-stats">
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $chef['recipe_count']; ?></div>
                                        <div class="stat-label">Recipes</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo number_format($chef['avg_rating'], 1); ?></div>
                                        <div class="stat-label">Rating</div>
                                    </div>
                                </div>
                                <a href="chef_profile.php?id=<?php echo $chef['ChefID']; ?>" class="btn btn-primary w-100">
                                    View Profile <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-user-chef"></i>
                            <h3 class="mb-3">No Chefs Available</h3>
                            <p class="text-muted mb-4">We currently don't have any chefs listed. Please check back later.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-4">
        <div class="container">
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
        // Animate cards on scroll
        const animateChefCards = function() {
            const cards = document.querySelectorAll('.chef-card');
            
            cards.forEach((card, index) => {
                const cardPosition = card.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.2;
                
                if (cardPosition < screenPosition) {
                    card.style.transitionDelay = `${index * 0.1}s`;
                    card.classList.add('animate__animated', 'animate__fadeInUp');
                }
            });
        };
        
        window.addEventListener('scroll', animateChefCards);
        window.addEventListener('load', animateChefCards);
    </script>
</body>
</html>