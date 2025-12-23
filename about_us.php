<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM user WHERE UserID = $user_id";
$user_result = $conn->query($user_query);
$user = $user_result->fetch_assoc();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - DishDash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #e69500; /* Orange */
            --secondary: #58c286; /* RGB(88, 194, 134) */
            --dark: #2E8B57; /* Sea Green */
            --light: #F5FFFA; /* Mint Cream */
            --accent: #58c286; /* RGB(88, 194, 134) */
            --gray: #6C757D;
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
                        url('https://images.unsplash.com/photo-1414235077428-338989a2e8c0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&q=80');
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
        
        .about-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            background: white;
            height: 100%;
            border-top: 4px solid var(--secondary);
        }
        
        .about-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .card-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .team-member {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .team-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 0 auto 20px;
        }
        
        .team-name {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .team-role {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #d18700;
            color: white;
        }
        
        .mission-section {
            background: linear-gradient(135deg, var(--light), white);
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }
        
        .mission-section:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1547592180-85f173990554?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&q=80') center/cover no-repeat;
            z-index: 0;
        }
        
        .mission-content {
            position: relative;
            z-index: 1;
        }
        
        .stats-item {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .stats-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stats-label {
            color: var(--dark);
            font-weight: 600;
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
            color: var(--primary);
        }
        
        .social-icons a {
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
            transition: color 0.3s;
        }
        
        .social-icons a:hover {
            color: var(--primary);
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
                        <a class="nav-link" href="chefs.php"><i class="fas fa-person me-1"></i> Chefs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php"><i class="fas fa-info-circle me-1"></i> About</a>
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
            <h1 class="hero-title">About DishDash</h1>
            <p class="hero-subtitle">Our story, mission, and the team behind your favorite recipe platform</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <h2 class="section-title">Our Story</h2>
                    <p>DishDash was born from a simple idea: to create a platform where food lovers can discover, share, and celebrate culinary creations from around the world. Founded in 2023, our journey began in a small kitchen with a passion for good food and great company.</p>
                    <p>What started as a personal recipe collection has grown into a vibrant community of home cooks and professional chefs alike. Today, DishDash serves thousands of users daily, helping them turn everyday meals into memorable experiences.</p>
                </div>
                <div class="col-lg-6">
                    <img src="https://images.unsplash.com/photo-1556911220-bff31c812dba?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&q=80" class="img-fluid rounded" alt="Our Story">
                </div>
            </div>
        </div>
    </section>

    <section class="mission-section">
        <div class="container mission-content">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="section-title text-white">Our Mission</h2>
                    <p class="lead text-white mb-5">To inspire and empower people to cook delicious meals with confidence, while building a community that celebrates culinary diversity and creativity.</p>
                    
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="about-card p-4 text-center">
                                <div class="card-icon">
                                    <i class="fas fa-utensils"></i>
                                </div>
                                <h4>Discover Recipes</h4>
                                <p>Explore thousands of recipes from diverse cuisines and skill levels.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="about-card p-4 text-center">
                                <div class="card-icon">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <h4>Share Passion</h4>
                                <p>Connect with a community that shares your love for cooking.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="about-card p-4 text-center">
                                <div class="card-icon">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                                <h4>Learn Skills</h4>
                                <p>Improve your culinary techniques with our expert guides.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
</body>
</html>