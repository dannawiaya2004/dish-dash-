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

//user information
$user_query = "SELECT * FROM user WHERE UserID = $user_id";
$user_result = $conn->query($user_query);
$user = $user_result->fetch_assoc();

//favorite recipes
$favorites_query = "SELECT r.* FROM fav f 
                   JOIN recipe r ON f.recipe_id = r.RecipeID 
                   WHERE f.user_id = $user_id";
$favorites_result = $conn->query($favorites_query);
$favorite_recipes = [];
while ($row = $favorites_result->fetch_assoc()) {
    $favorite_recipes[] = $row;
}

//all restrictions
$all_restrictions_query = "SELECT * FROM restriction";
$all_restrictions_result = $conn->query($all_restrictions_query);
$all_restrictions = [];
while ($row = $all_restrictions_result->fetch_assoc()) {
    $all_restrictions[$row['RestrictionID']] = $row;
}

//current restrictions
$user_restrictions_query = "SELECT restriction_id FROM user_restriction WHERE user_id = $user_id";
$user_restrictions_result = $conn->query($user_restrictions_query);
$current_restrictions = [];
while ($row = $user_restrictions_result->fetch_assoc()) {
    $current_restrictions[] = $row['restriction_id'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $bio = $conn->real_escape_string($_POST['bio']);

    // Update user info
    $update_query = "UPDATE user SET 
                    name = '$username',
                    email = '$email',
                    details = '$bio'
                    WHERE UserID = $user_id";
    
    if ($conn->query($update_query)) {
        $_SESSION['success'] = "Profile updated successfully!";

        $user_result = $conn->query($user_query);
        $user = $user_result->fetch_assoc();
    } else {
        $_SESSION['error'] = "Error updating profile: " . $conn->error;
    }
}

//password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            // Validate 
            if (strlen($new_password) < 8) {
                $_SESSION['error'] = "Password must be at least 8 characters long!";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_password_query = "UPDATE user SET password = '$hashed_password' WHERE UserID = $user_id";
                
                if ($conn->query($update_password_query)) {
                    $_SESSION['success'] = "Password changed successfully!";
                } else {
                    $_SESSION['error'] = "Error changing password: " . $conn->error;
                }
            }
        } else {
            $_SESSION['error'] = "New passwords do not match!";
        }
    } else {
        $_SESSION['error'] = "Current password is incorrect!";
    }
}

//restrictions update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_restrictions'])) {
    $new_restrictions = isset($_POST['restrictions']) ? $_POST['restrictions'] : [];
    
   
    // Add new restrictions
    if (!empty($new_restrictions)) {
        foreach ($new_restrictions as $restriction_id) {
            $restriction_id = (int)$restriction_id;
            $insert_query = "INSERT INTO user_restriction (user_id, restriction_id) VALUES ($user_id, $restriction_id)";
            $conn->query($insert_query);
        }
    }
    
    $_SESSION['success'] = "Dietary restrictions updated successfully!";

    $user_restrictions_result = $conn->query($user_restrictions_query);
    $current_restrictions = [];
    while ($row = $user_restrictions_result->fetch_assoc()) {
        $current_restrictions[] = $row['restriction_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - DishDash</title>
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
        
        .profile-header {
            background: linear-gradient(135deg, #e69500 0%, #2E8B57 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #e69500;
            color: white;
            font-weight: 600;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .recipe-img {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        
        .restriction-badge {
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
            padding: 5px 10px;
            background-color: #f8d7da;
            color: #721c24;
            border-radius: 10px;
        }
        
        .restriction-badge.active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .btn-primary {
            background-color: #e69500;
            border-color: #e69500;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #d18a00;
            border-color: #d18a00;
        }
        
        .btn-outline-primary {
            color: #e69500;
            border-color: #e69500;
        }
        
        .btn-outline-primary:hover {
            background-color: #e69500;
            color: white;
        }
        
        .back-button {
            position: absolute;
            left: 20px;
            top: 20px;
            color: white;
            font-size: 1.5rem;
            z-index: 10;
        }
        
        .tab-content {
            padding: 20px 0;
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
    
            
            </div>
        </div>
    </nav>
    <section class="profile-header">
        <div class="container text-center">
            <h1 class="fw-bold mb-2"><?= htmlspecialchars($user['name']) ?></h1>
            <p class="lead mb-0">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
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
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile">
                    <i class="fas fa-user me-2"></i>Profile
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#favorites">
                    <i class="fas fa-heart me-2"></i>Favorites
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#restrictions">
                    <i class="fas fa-allergies me-2"></i>Dietary Restrictions
                </button>
            </li>
            
            
        </ul>
        
        <div class="tab-content mt-4">
            <!-- Profile Tab -->
            <div class="tab-pane fade show active" id="profile">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-user-edit me-2"></i>Edit Profile
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" 
                                               value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Bio</label>
                                        <textarea class="form-control" name="bio" rows="3"><?= htmlspecialchars($user['details']) ?></textarea>
                                    </div>
                                 
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-lock me-2"></i>Change Password
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Favorites Tab -->
            <div class="tab-pane fade" id="favorites">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-heart me-2"></i>Favorite Recipes
                    </div>
                    <div class="card-body">
                        <?php if (!empty($favorite_recipes)): ?>
                            <div class="row">
                                <?php foreach ($favorite_recipes as $recipe): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center border rounded p-3">
                                        <img src="./image/<?= $recipe['image'] ?? 'default-recipe.jpg' ?>" 
                                             class="recipe-img me-3">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($recipe['title']) ?></h6>
                                            <small class="text-muted d-block mb-2">
                                                <i class="fas fa-clock me-1"></i><?= $recipe['cooking_time'] ?> mins
                                            </small>
                                            <a href="recipe.php?id=<?= $recipe['RecipeID'] ?>" class="btn btn-sm btn-outline-primary">
                                                View Recipe
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-heart-broken text-muted mb-3" style="font-size: 2rem;"></i>
                                <h5 class="fw-bold mb-2">No Favorite Recipes</h5>
                                <p class="text-muted">Save your favorite recipes to see them here</p>
                                <a href="allRecipes.php" class="btn btn-primary">
                                    <i class="fas fa-book me-2"></i>Browse Recipes
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Restrictions Tab -->
            <div class="tab-pane fade" id="restrictions">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-allergies me-2"></i>Dietary Restrictions
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <p class="mb-3">Select any dietary restrictions or allergies you have. This will help us recommend suitable recipes.</p>
                            
                            <div class="row">
                                <?php foreach ($all_restrictions as $restriction): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="restrictions[]" 
                                               value="<?= $restriction['RestrictionID'] ?>"
                                               id="restriction<?= $restriction['RestrictionID'] ?>"
                                               <?= in_array($restriction['RestrictionID'], $current_restrictions) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="restriction<?= $restriction['RestrictionID'] ?>">
                                            <?= htmlspecialchars($restriction['name']) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" name="update_restrictions" class="btn btn-primary mt-3">
                                <i class="fas fa-save me-2"></i>Save Restrictions
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light py-4 mt-5">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; <?= date('Y') ?> DishDash. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>