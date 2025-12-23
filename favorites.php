<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle remove recipe action
if (isset($_GET['remove_recipe'])) {
    $recipe_id = (int)$_GET['remove_recipe'];
    $remove_query = "DELETE FROM fav WHERE user_id = $user_id AND recipe_id = $recipe_id";
    if ($conn->query($remove_query)) {
        $_SESSION['success'] = "Recipe removed from favorites!";
    } else {
        $_SESSION['error'] = "Failed to remove recipe from favorites.";
    }
    header("Location: favorites.php");
    exit();
}

// Handle unfollow chef action
if (isset($_GET['unfollow_chef'])) {
    $chef_id = (int)$_GET['unfollow_chef'];
    $unfollow_query = "DELETE FROM fav_chef WHERE user_id = $user_id AND chef_id = $chef_id";
    if ($conn->query($unfollow_query)) {
        $_SESSION['success'] = "Chef unfollowed successfully!";
    } else {
        $_SESSION['error'] = "Failed to unfollow chef.";
    }
    header("Location: favorites.php");
    exit();
}

// Get favorite recipes
$favoritesQuery = "SELECT r.*, 
                  ct.name AS cuisine_name,
                  mt.name AS meal_type_name,
                  u.name AS chef_name,
                  u.UserID AS chef_user_id,
                  (SELECT AVG(rating) FROM rate WHERE recipe_id = r.RecipeID) AS avg_rating,
                  (SELECT COUNT(*) FROM rate WHERE recipe_id = r.RecipeID) AS review_count
                  FROM recipe r
                  LEFT JOIN cuisine_type ct ON r.cuisine_typeID = ct.CuisineID
                  LEFT JOIN meal_types mt ON r.meal_typesID = mt.MealID
                  LEFT JOIN chef ch ON r.chefs_id = ch.ChefID
                  LEFT JOIN user u ON ch.user_id = u.UserID
                  JOIN fav f ON r.RecipeID = f.recipe_id
                  WHERE f.user_id = $user_id AND r.status = 'published'
                  ORDER BY f.date DESC";

$favoritesResult = $conn->query($favoritesQuery);

// Get favorite chefs
$chefsQuery = "SELECT u.UserID, u.name, c.ChefID,
              COUNT(r.RecipeID) AS recipe_count,
              AVG(rt.rating) AS avg_rating
              FROM user u
              JOIN chef c ON u.UserID = c.user_id
              LEFT JOIN recipe r ON c.ChefID = r.chefs_id
              LEFT JOIN rate rt ON r.RecipeID = rt.recipe_id
              JOIN fav_chef fc ON c.ChefID = fc.chef_id
              WHERE fc.user_id = $user_id
              GROUP BY u.UserID
              ORDER BY fc.date DESC";

$chefsResult = $conn->query($chefsQuery);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - Dish Dash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        /* Main Layout */
        .app-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 1.5rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            text-align: center;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .logo {
            width: 140px;
            margin: 0 auto 1rem;
            display: block;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary);
        }
        
        .user-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .user-email {
            color: var(--gray);
            font-size: 0.85rem;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--dark);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(88, 194, 134, 0.1);
            color: var(--secondary);
        }
        
        .nav-link i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            padding: 3rem;
            background-color: white;
        }
        
        .page-header {
            margin-bottom: 3rem;
            text-align: center;
        }
        
        .page-title {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
            position: relative;
            display: inline-block;
        }
        
        .page-title:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        .page-subtitle {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Recipe Cards */
        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .recipe-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            border-top: 4px solid var(--secondary);
        }
        
        .recipe-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .card-img-container {
            position: relative;
            padding-top: 75%;
            overflow: hidden;
        }
        
        .card-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .recipe-card:hover .card-img {
            transform: scale(1.05);
        }
        
        .card-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--dark);
        }
        
        .card-meta {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .card-author {
            color: var(--secondary);
            font-weight: 500;
            text-decoration: none;
        }
        
        .card-author:hover {
            text-decoration: underline;
        }
        
        .card-rating {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .rating-stars {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .rating-value {
            font-weight: 600;
            margin-right: 5px;
        }
        
        .review-count {
            color: var(--gray);
            font-size: 0.85rem;
        }
        
        .card-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .btn-view {
            background: var(--secondary);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-view:hover {
            background: #4ab68a;
            color: white;
        }
        
        .btn-remove {
            color: var(--primary);
            background: none;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-remove:hover {
            color: #d18700;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 0;
            grid-column: 1 / -1;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            opacity: 0.7;
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .empty-text {
            color: var(--gray);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-explore {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-explore:hover {
            background: #d18700;
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .app-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                height: auto;
                position: static;
                padding: 1.5rem;
            }
            
            .main-content {
                padding: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .recipe-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                margin-bottom: 2rem;
            }
            
            .page-title {
                font-size: 1.75rem;
            }
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--primary);
        }
        
        .chef-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }
        
        .chef-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1.5rem;
            text-align: center;
            border-top: 4px solid var(--secondary);
        }
        
        .chef-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .chef-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            margin-bottom: 1.5rem;
        }
        
        .chef-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .chef-meta {
            color: var(--gray);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .chef-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        
        .chef-stars {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .chef-actions {
    display: flex;
    gap: 12px;
    width: 100%;
    margin-top: 1rem;
}

.chef-actions .btn {
    flex: 1;
    padding: 8px 12px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chef-actions .btn-primary {
    background-color: var(--secondary);
    border-color: var(--secondary);
}

.chef-actions .btn-primary:hover {
    background-color: #4ab68a;
    border-color: #4ab68a;
}

.chef-actions .btn-outline-danger {
    color: #dc3545;
    border-color: #dc3545;
}

.chef-actions .btn-outline-danger:hover {
    background-color: rgba(220, 53, 69, 0.1);
}

.chef-actions i {
    font-size: 0.9rem;
}
        
        .btn-chef {
            flex: 1;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .btn-profile {
            background: var(--secondary);
            color: white;
        }
        
        .btn-profile:hover {
            background: #4ab68a;
            color: white;
        }
        
        .btn-unfollow {
            color: var(--primary);
            background: none;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-unfollow:hover {
            background: rgba(230, 149, 0, 0.1);
        }
        
        /* Tabs */
        .favorites-tabs {
            display: flex;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .tab-btn {
            padding: 12px 24px;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            color: var(--dark);
        }
        
        .tab-btn.active:after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>



<body>
    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="Recipe Book Logo.jpeg" alt="DishDash" class="logo">
                <?php if(isset($_SESSION['user_id']) && isset($_SESSION['name'])): ?>
                    <img src="uploads/avatars/<?php echo $_SESSION['profile_pic'] ?? 'default.jpg'; ?>" alt="User" class="user-avatar">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
                <?php endif; ?>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item"><a href="main.php" class="nav-link"><i class="fas fa-home"></i> Home</a></li>
                <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> Profile</a></li>
                <li class="nav-item"><a href="favorites.php" class="nav-link active"><i class="fas fa-heart"></i> Favorites</a></li>
                <li class="nav-item"><a href="fridge.php" class="nav-link"><i class="fas fa-archive"></i> My Fridge</a></li>
                <li class="nav-item">
                   <a class="nav-link " href="weekly_plan.php"><i class="fas fa-calendar-alt"></i> Meal Planner</a></li>
                    </li>
                                 <li class="nav-item">
    <a href="chatbot.php" class="nav-link ">
      <i class="fas fa-robot"></i>
      <span>Chef Assistant</span>
    </a>
    </li>
                <li class="nav-item"><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
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
            
            <div class="page-header">
                <h1 class="page-title">My Favorites</h1>
                <p class="page-subtitle">All your saved recipes and followed chefs</p>
            </div>
            
            <div class="favorites-tabs">
                <a href="?tab=recipes" class="tab-btn <?= (!isset($_GET['tab']) || $_GET['tab'] == 'recipes') ? 'active' : '' ?>">Recipes</a>
                <a href="?tab=chefs" class="tab-btn <?= (isset($_GET['tab']) && $_GET['tab'] == 'chefs') ? 'active' : '' ?>">Chefs</a>
            </div>
            
            <!-- Recipes Tab -->
            <div id="recipes-tab" class="tab-content <?= (!isset($_GET['tab']) || $_GET['tab'] == 'recipes') ? 'active' : '' ?>">
                <h2 class="section-title">Favorite Recipes</h2>
                <div class="recipe-grid">
                    <?php if ($favoritesResult && $favoritesResult->num_rows > 0): ?>
                        <?php while($recipe = $favoritesResult->fetch_assoc()): 
                            $avgRating = round($recipe['avg_rating'], 1);
                            $fullStars = floor($avgRating);
                            $hasHalfStar = ($avgRating - $fullStars) >= 0.5;
                        ?>
                        <div class="recipe-card">
                            <div class="card-img-container">
                                <img src="./image/<?php echo $recipe['image']; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>" class="card-img">
                                <span class="card-badge"><?php echo htmlspecialchars($recipe['cuisine_name']); ?></span>
                            </div>
                            <div class="card-body">
                                <h3 class="card-title"><?php echo htmlspecialchars(substr($recipe['title'], 0, 20)); ?>...</h3>
                                <div class="card-meta">
                                    By   <a href="chef_profile.php?id=<?php echo   $recipe['chef_user_id']; ?>" class="card-author">
                                        <p> 
                                            
                                        </p>
                                        <?php echo   htmlspecialchars( $recipe['chef_name']); ?>
                                    </a>
                                </div>
                                <div class="card-rating">
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
                                    <a href="recipe.php?id=<?php echo $recipe['RecipeID']; ?>#reviews" class="review-count">
                                        (<?php echo $recipe['review_count']; ?> reviews)
                                    </a>
                                </div>
                                <div class="card-actions">
                                    <a href="recipe.php?id=<?php echo $recipe['RecipeID']; ?>" class="btn-view">
                                        <i class="fas fa-utensils me-1"></i> View Recipe
                                    </a>
                                    <a href="?remove_recipe=<?php echo $recipe['RecipeID']; ?>" class="btn-remove" onclick="return confirm('Remove this recipe from your favorites?')">
                                        <i class="fas fa-trash-alt me-1"></i> Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="far fa-heart"></i>
                            </div>
                            <h3 class="empty-title">Your Favorites List is Empty</h3>
                            <p class="empty-text">
                                You haven't saved any recipes yet. Browse our collection and click the heart icon to save your favorites here.
                            </p>
                            <a href="allRecipes.php" class="btn-explore">
                                <i class="fas fa-compass me-2"></i> Explore Recipes
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chefs Tab -->
            <div id="chefs-tab" class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'chefs') ? 'active' : '' ?>">
                <h2 class="section-title">Favorite Chefs</h2>
                <div class="chef-grid">
                    <?php if ($chefsResult && $chefsResult->num_rows > 0): ?>
                        <?php while($chef = $chefsResult->fetch_assoc()): 
                          
                        ?>
                            <div class="chef-card">
                                <h3 class="chef-name"><?php echo htmlspecialchars($chef['name']); ?></h3>
                                <div class="chef-meta">
                                    <?php echo $chef['recipe_count'] ?? 0; ?> recipes
                                </div>
                           
                                <div class="chef-actions">
    <a href="chef_profile.php?id=<?php echo $chef['ChefID']; ?>" 
       class="btn btn-primary btn-sm d-flex align-items-center justify-content-center">
        <i class="fas fa-user me-2"></i> View Profile
    </a>
    <a href="?unfollow_chef=<?php echo $chef['ChefID']; ?>" 
       class="btn btn-outline-danger btn-sm d-flex align-items-center justify-content-center"
       onclick="return confirm('Unfollow this chef?')">
        <i class="fas fa-user-minus me-2"></i> Unfollow
    </a>
</div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <div class="empty-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <h3 class="empty-title">No Favorite Chefs Yet</h3>
                            <p class="empty-text">
                                You haven't followed any chefs yet. Browse chef profiles and click the follow button to see their updates here.
                            </p>
                            <a href="chefs.php" class="btn-explore">
                                <i class="fas fa-compass me-2"></i> Discover Chefs
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>