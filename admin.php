<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle form submission for adding a new chef
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_chef'])) {
    $user_id = $_POST['user_id'];
    $specialties = $_POST['specialties'];
    $details = $_POST['details'];
    
    // Check if user exists and is not already a chef
    $user_check = $conn->query("SELECT * FROM user WHERE UserID = $user_id");
    $chef_check = $conn->query("SELECT * FROM chef WHERE user_id = $user_id");
    
    if ($user_check->num_rows === 0) {
        header("Location: admin.php?error=user_not_found");
        exit();
    }
    
    if ($chef_check->num_rows > 0) {
        header("Location: admin.php?error=already_chef");
        exit();
    }
    
    // Insert new chef
    $sql = "INSERT INTO chef (user_id, specialties, details, status) 
            VALUES ($user_id, '$specialties', '$details', 'approved')";
    
    if ($conn->query($sql)) {
        header("Location: admin.php?success=chef_added");
        exit();
    } else {
        header("Location: admin.php?error=db_error");
        exit();
    }
}

//messages
if (isset($_GET['success'])) {
  switch($_GET['success']) {
      case 'comment_updated':
          $message = 'Comment status updated successfully';
          break;
      case 'recipe_updated':
          $message = 'Recipe status updated successfully';
          break;
      case 'recipe_republished':
          $message = 'Recipe has been republished successfully';
          break;
      case 'chef_updated':
          $message = 'Chef status updated successfully';
          break;
      case 'user_updated':
          $message = 'User status updated successfully';
          break;
      case 'chef_added':
          $message = 'Chef added successfully';
          break;
      case 'chef_rating_updated':
          $message = 'Chef rating status updated successfully';
          break;
      default:
          $message = 'Action completed successfully';
  }
  echo '<div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
          ' . htmlspecialchars($message) . '
        </div>';
}

if (isset($_GET['error'])) {
  switch($_GET['error']) {
      case 'invalid_action':
          $message = 'Invalid action specified';
          break;
      case 'db_error':
          $message = 'Database error occurred';
          break;
      case 'missing_params':
          $message = 'Required parameters missing';
          break;
      case 'user_not_found':
          $message = 'User not found';
          break;
      case 'already_chef':
          $message = 'User is already a chef';
          break;
      default:
          $message = 'An error occurred';
  }
  echo '<div class="alert alert-danger alert-dismissible">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
          ' . htmlspecialchars($message) . '
        </div>';
}

// statistics
$result = $conn->query("SELECT COUNT(*) as total FROM user");
$row = $result->fetch_assoc();
$total_users = $row['total'];
$active_chefs = $conn->query("SELECT COUNT(*) FROM chef WHERE status = 'approved'")->fetch_row()[0];
$published_recipes = $conn->query("SELECT COUNT(*) FROM recipe WHERE status = 'published'")->fetch_row()[0];
$total_ratings = $conn->query("SELECT COUNT(*) FROM rate WHERE status = 'published'")->fetch_row()[0];
$chef_ratings = $conn->query("SELECT COUNT(*) FROM rate_chef ")->fetch_row()[0];

//user management
$users = [];
$result = $conn->query("
    SELECT u.UserID, u.name, u.email, u.status, 
           CASE WHEN c.ChefID IS NOT NULL THEN 'Chef' ELSE 'Customer' END AS role
    FROM user u
    LEFT JOIN chef c ON u.UserID = c.user_id
    WHERE u.UserID NOT IN (67)
    ORDER BY u.created_at DESC
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Get regular users (non-chefs) for the add chef form
$regular_users = [];
$result = $conn->query("
    SELECT u.UserID, u.name, u.email 
    FROM user u
    LEFT JOIN chef c ON u.UserID = c.user_id
    WHERE c.ChefID IS NULL AND u.status = 'active' AND u.UserID NOT IN (67)
    ORDER BY u.name
");
while ($row = $result->fetch_assoc()) {
    $regular_users[] = $row;
}

//chef management
$chefs = [];
$result = $conn->query("
    SELECT c.ChefID, u.name, c.specialties, c.status 
    FROM chef c
    JOIN user u ON c.user_id = u.UserID
    ORDER BY c.status, u.name
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $chefs[] = $row;
}

//recipe management
$recipes = [];
$result = $conn->query("
    SELECT r.RecipeID, r.title, u.name AS chef_name, r.status, r.created_at
    FROM recipe r
    JOIN chef c ON r.chefs_id = c.ChefID
    JOIN user u ON c.user_id = u.UserID
    ORDER BY r.created_at DESC
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $recipes[] = $row;
}

//comments management
$comments = [];
$result = $conn->query("
    SELECT r.user_id, r.recipe_id, u.name AS user_name, re.title AS recipe_title, 
           r.comment, r.rating, r.date, r.status
    FROM rate r
    JOIN user u ON r.user_id = u.UserID
    JOIN recipe re ON r.recipe_id = re.RecipeID
    ORDER BY r.date DESC
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}

//chef ratings and comments management
$chef_ratings_comments = [];
$result = $conn->query("
    SELECT rc.user_id, rc.chef_id, u.name AS user_name, ch.name AS chef_name, 
           rc.comment, rc.rating, rc.date
    FROM rate_chef rc
    JOIN user u ON rc.user_id = u.UserID
    JOIN (
        SELECT c.ChefID, u.name 
        FROM chef c
        JOIN user u ON c.user_id = u.UserID
    ) ch ON rc.chef_id = ch.ChefID
    ORDER BY rc.date DESC
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $chef_ratings_comments[] = $row;
}

//analytics 
$favorite_recipes = [];
$result = $conn->query("
    SELECT r.RecipeID, r.title, COUNT(f.user_id) AS favorite_count
    FROM recipe r
    LEFT JOIN fav f ON r.RecipeID = f.recipe_id
    GROUP BY r.RecipeID
    ORDER BY favorite_count DESC
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $favorite_recipes[] = $row;
}



$top_countries = [];
$result = $conn->query("
    SELECT country, COUNT(*) AS user_count 
    FROM user 
    WHERE country IS NOT NULL 
    GROUP BY country 
    ORDER BY user_count DESC 
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $top_countries[] = $row;
}

$user_growth = [];
$result = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS new_users
    FROM user
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
while ($row = $result->fetch_assoc()) {
    $user_growth[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dish Dash | Admin Dashboard</title>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --primary: #ffbe32;
      --primary-dark: #e6ab2d;
    }
    .navbar-custom {
      background-color: var(--primary);
    }
    .brand-link {
      background-color: var(--primary-dark);
    }
    [class*="sidebar-dark-"] .sidebar a:hover {
      background-color: rgba(255,255,255,0.1);
    }
    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary-dark);
    }
    .btn-primary:hover {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
    }
    .bg-primary {
      background-color: var(--primary) !important;
    }
    .card-primary:not(.card-outline) > .card-header {
      background-color: var(--primary);
    }
    .nav-pills .nav-link.active {
      background-color: var(--primary);
    }
    .custom-shadow {
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .badge.bg-success {
      background-color: #28a745 !important;
    }
    .badge.bg-secondary {
      background-color: #6c757d !important;
    }
    .loading-spinner {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 2px solid rgba(0,0,0,.1);
      border-radius: 50%;
      border-top-color: #fff;
      animation: spin 1s ease-in-out infinite;
      margin-right: 5px;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .add-chef-form {
      display: none;
      margin-top: 20px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 5px;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <nav class="main-header navbar navbar-expand navbar-white navbar-light navbar-custom">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="admin.php" class="nav-link">Home</a>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto">
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="logout.php" role="button">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
    </ul>
  </nav>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="admin.php" class="brand-link">
      <span class="brand-text font-weight-light">Dish Dash Admin</span>
    </a>

    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="info">
          <a href="#" class="d-block">Admin</a>
        </div>
      </div>

      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="#user-management" class="nav-link active">
              <i class="nav-icon fas fa-users"></i>
              <p>User Management</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#chef-management" class="nav-link">
              <i class="nav-icon fas fa-user-tie"></i>
              <p>Chef Management</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#recipe-management" class="nav-link">
              <i class="nav-icon fas fa-book"></i>
              <p>Recipe Management</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#comments-management" class="nav-link">
              <i class="nav-icon fas fa-comments"></i>
              <p>Recipe Comments</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#chef-ratings" class="nav-link">
              <i class="nav-icon fas fa-star"></i>
              <p>Chef Ratings</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#charts" class="nav-link">
              <i class="nav-icon fas fa-chart-pie"></i>
              <p>Analytics</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Dashboard</h1>
          </div>
        </div>
      </div>
    </div>
   
    <div class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h3><?= htmlspecialchars($total_users) ?></h3>
                <p>Total Users</p>
              </div>
              <div class="icon">
                <i class="fas fa-users"></i>
              </div>
              <a href="#user-management" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3><?= htmlspecialchars($active_chefs) ?></h3>
                <p>Active Chefs</p>
              </div>
              <div class="icon">
                <i class="fas fa-user-tie"></i>
              </div>
              <a href="#chef-management" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
              <div class="inner">
                <h3><?= htmlspecialchars($published_recipes) ?></h3>
                <p>Published Recipes</p>
              </div>
              <div class="icon">
                <i class="fas fa-book"></i>
              </div>
              <a href="#recipe-management" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
              <div class="inner">
                <h3><?= htmlspecialchars($chef_ratings) ?></h3>
                <p>Chef Ratings</p>
              </div>
              <div class="icon">
                <i class="fas fa-star"></i>
              </div>
              <a href="#chef-ratings" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
            </div>
          </div>
        </div>

        <div id="user-management" class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title">User Management</h3>
          </div>
          <div class="card-body">
            <table id="user-table" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                  <td><?= htmlspecialchars($user['UserID']) ?></td>
                  <td><?= htmlspecialchars($user['name']) ?></td>
                  <td><?= htmlspecialchars($user['email']) ?></td>
                  <td>
                    <span class="badge <?= $user['role'] === 'Chef' ? 'bg-info' : 'bg-success' ?>">
                      <?= htmlspecialchars($user['role']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                      <?= htmlspecialchars(ucfirst($user['status'])) ?>
                    </span>
                  </td>
                  <td>
                    <a href="update_user_status.php?id=<?= $user['UserID'] ?>&status=<?= $user['status'] === 'active' ? 'inactive' : 'active' ?>" 
                       class="btn btn-sm btn-danger">
                      <i class="fas fa-ban"></i> <?= $user['status'] === 'active' ? 'Ban' : 'Unban' ?>
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary view-all-btn" data-table="user-table" data-endpoint="user_all.php">
              <i class="fas fa-eye"></i> View All Users
            </button>
          </div>
        </div>

        <div id="chef-management" class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title">Chef Management</h3>
            <button id="show-add-chef-form" class="btn btn-success btn-sm float-right">
              <i class="fas fa-plus"></i> Add New Chef
            </button>
          </div>
          <div class="card-body">
            <!-- Add Chef Form -->
            <div id="add-chef-form" class="add-chef-form">
              <form method="POST" action="">
                <div class="form-group">
                  <label for="user_id">Select User</label>
                  <select class="form-control" id="user_id" name="user_id" required>
                    <option value="">Select a user</option>
                    <?php foreach ($regular_users as $user): ?>
                      <option value="<?= $user['UserID'] ?>">
                        <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="specialties">Specialties</label>
                  <input type="text" class="form-control" id="specialties" name="specialties" required 
                         placeholder="e.g. Italian, Baking, Vegan">
                </div>
                <div class="form-group">
                  <label for="details">Details</label>
                  <textarea class="form-control" id="details" name="details" 
                            placeholder="Brief description about the chef"></textarea>
                </div>
                <button type="submit" name="add_chef" class="btn btn-primary">
                  <i class="fas fa-save"></i> Save Chef
                </button>
                <button type="button" id="cancel-add-chef" class="btn btn-secondary">
                  <i class="fas fa-times"></i> Cancel
                </button>
              </form>
            </div>
            
            <table id="chef-table" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Specialty</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($chefs as $chef): ?>
                <tr>
                  <td><?= htmlspecialchars($chef['ChefID']) ?></td>
                  <td><?= htmlspecialchars($chef['name']) ?></td>
                  <td><?= htmlspecialchars($chef['specialties']) ?></td>
                  <td>
                    <span class="badge <?= $chef['status'] === 'approved' ? 'bg-success' : ($chef['status'] === 'pending' ? 'bg-warning' : 'bg-danger') ?>">
                      <?= htmlspecialchars(ucfirst($chef['status'])) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($chef['status'] === 'pending'): ?>
                        <a href="update_chef_status.php?id=<?= $chef['ChefID'] ?>&status=approved" class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> Approve
                        </a>
                        <a href="update_chef_status.php?id=<?= $chef['ChefID'] ?>&status=declined" class="btn btn-sm btn-danger">
                            <i class="fas fa-times"></i> Reject
                        </a>
                    <?php elseif ($chef['status'] === 'approved'): ?>
                        <a href="update_chef_status.php?id=<?= $chef['ChefID'] ?>&status=declined" class="btn btn-sm btn-danger">
                            <i class="fas fa-times"></i> Revoke Approval
                        </a>
                    <?php else: ?>
                        <a href="update_chef_status.php?id=<?= $chef['ChefID'] ?>&status=approved" class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> Approve
                        </a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary view-all-btn" data-table="chef-table" data-endpoint="chef_all.php">
              <i class="fas fa-eye"></i> View All Chefs
            </button>
          </div>
        </div>

        <div id="recipe-management" class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title">Recipe Management</h3>
          </div>
          <div class="card-body">
            <table id="recipe-table" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Recipe Name</th>
                  <th>Chef</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recipes as $recipe): ?>
                <tr>
                  <td><?= htmlspecialchars($recipe['RecipeID']) ?></td>
                  <td><?= htmlspecialchars($recipe['title']) ?></td>
                  <td><?= htmlspecialchars($recipe['chef_name']) ?></td>
                  <td>
                    <span class="badge <?= $recipe['status'] === 'published' ? 'bg-success' : ($recipe['status'] === 'archived' ? 'bg-danger' : 'bg-warning') ?>">
                      <?= htmlspecialchars(ucfirst($recipe['status'])) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($recipe['status'] === 'pending'): ?>
                      <a href="update_recipe_status.php?id=<?= $recipe['RecipeID'] ?>&status=published" class="btn btn-sm btn-success">
                        <i class="fas fa-check"></i> Publish
                      </a>
                      <a href="update_recipe_status.php?id=<?= $recipe['RecipeID'] ?>&status=rejected" class="btn btn-sm btn-danger">
                        <i class="fas fa-times"></i> Reject
                      </a>
                    <?php elseif ($recipe['status'] === 'archived'): ?>
                      <a href="update_recipe_status.php?id=<?= $recipe['RecipeID'] ?>&status=published" class="btn btn-sm btn-success">
                        <i class="fas fa-undo"></i> Re-publish
                      </a>
                    <?php else: ?>
                      <a href="update_recipe_status.php?id=<?= $recipe['RecipeID'] ?>&status=archived" class="btn btn-sm btn-danger">
                        <i class="fas fa-ban"></i> Archive
                      </a>
                    <?php endif; ?>
                        <a href="admin_edit_recipe.php?id=<?= $recipe['RecipeID'] ?>" class="btn btn-sm btn-primary">
      <i class="fas fa-edit"></i> Edit
    </a>

                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary view-all-btn" data-table="recipe-table" data-endpoint="recipe_all.php">
              <i class="fas fa-eye"></i> View All Recipes
            </button>
          </div>
        </div>

        <div id="comments-management" class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title">Recipe Comments</h3>
          </div>
          <div class="card-body">
            <table id="comment-table" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Recipe</th>
                  <th>Comment</th>
                  <th>Rating</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($comments as $comment): ?>
                <tr>
                  <td><?= htmlspecialchars($comment['user_name']) ?></td>
                  <td><?= htmlspecialchars($comment['recipe_title']) ?></td>
                  <td><?= htmlspecialchars($comment['comment']) ?></td>
                  <td>
                    <?php for ($i = 0; $i < 5; $i++): ?>
                      <i class="fas fa-star <?= $i < $comment['rating'] ? 'text-warning' : 'text-secondary' ?>"></i>
                    <?php endfor; ?>
                  </td>
                  <td>
                    <span class="badge <?= $comment['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?>">
                      <?= htmlspecialchars(ucfirst($comment['status'])) ?>
                    </span>
                  </td>
                  <td>
                    <form method="post" action="update_comment_status.php" style="display: inline;">
                      <input type="hidden" name="user_id" value="<?= $comment['user_id'] ?>">
                      <input type="hidden" name="recipe_id" value="<?= $comment['recipe_id'] ?>">
                      <input type="hidden" name="date" value="<?= $comment['date'] ?>">
                      <input type="hidden" name="status" value="<?= $comment['status'] === 'published' ? 'archived' : 'published' ?>">
                      <button type="submit" class="btn btn-sm <?= $comment['status'] === 'published' ? 'btn-danger' : 'btn-success' ?>">
                        <i class="fas <?= $comment['status'] === 'published' ? 'fa-trash' : 'fa-check' ?>"></i>
                        <?= $comment['status'] === 'published' ? 'Archive' : 'Publish' ?>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary view-all-btn" data-table="comment-table" data-endpoint="comment_all.php">
              <i class="fas fa-eye"></i> View All Comments
            </button>
          </div>
        </div>

        <div id="chef-ratings" class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title">Chef Ratings & Comments</h3>
          </div>
          <div class="card-body">
            <table id="chef-rating-table" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Chef</th>
                  <th>Comment</th>
                  <th>Rating</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($chef_ratings_comments as $rating): ?>
                <tr>
                  <td><?= htmlspecialchars($rating['user_name']) ?></td>
                  <td><?= htmlspecialchars($rating['chef_name']) ?></td>
                  <td><?= htmlspecialchars($rating['comment']) ?></td>
                  <td>
                    <?php for ($i = 0; $i < 5; $i++): ?>
                      <i class="fas fa-star <?= $i < $rating['rating'] ? 'text-warning' : 'text-secondary' ?>"></i>
                    <?php endfor; ?>
                  </td>
                
                  
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary view-all-btn" data-table="chef-rating-table" data-endpoint="chef_ratings_all.php">
              <i class="fas fa-eye"></i> View All Chef Ratings
            </button>
          </div>
        </div>

         <div id="charts" class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title">Analytics Dashboard</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">Most Favorited Recipes</h3>
                  </div>
                  <div class="card-body">
                    <canvas id="favoriteRecipesChart" height="250"></canvas>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">Top Countries Using Dish Dash</h3>
                  </div>
                  <div class="card-body">
                    <canvas id="topCountriesChart" height="250"></canvas>
                  </div>
                </div>
              </div>
            </div>
            <div class="row mt-3">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">User Growth</h3>
                  </div>
                  <div class="card-body">
                    <canvas id="userGrowthChart" height="100"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <footer class="main-footer">
    <strong>Copyright &copy; <?= date('Y') ?> <a href="#">Dish Dash</a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 1.0.0
    </div>
  </footer>
</div>



<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(function () {
    // Initialize charts
    const favoriteRecipesCtx = document.getElementById('favoriteRecipesChart').getContext('2d');
    new Chart(favoriteRecipesCtx, {
        type: 'bar',
        data: {
            labels: [<?= implode(',', array_map(function($r) { 
                $words = explode(' ', $r['title']);
                $shortened = implode(' ', array_slice($words, 0, 2)) . (count($words) > 2 ? '...' : '');
                return "'" . htmlspecialchars($shortened) . "'"; 
            }, $favorite_recipes)) ?>],
            datasets: [{
                label: 'Favorites Count',
                data: [<?= implode(',', array_column($favorite_recipes, 'favorite_count')) ?>],
                backgroundColor: [
                    '#ffbe32',
                    '#ff9900',
                    '#ff7700',
                    '#ff5500',
                    '#ff3300'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                },
                x: {
                    ticks: {
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
    
    const topCountriesCtx = document.getElementById('topCountriesChart').getContext('2d');
    new Chart(topCountriesCtx, {
        type: 'pie',
        data: {
            labels: [<?= implode(',', array_map(function($c) { return "'" . htmlspecialchars($c['country']) . "'"; }, $top_countries)) ?>],
            datasets: [{
                data: [<?= implode(',', array_column($top_countries, 'user_count')) ?>],
                backgroundColor: [
                    '#ffbe32',
                    '#ff9900',
                    '#ff7700',
                    '#ff5500',
                    '#ff3300'
                ],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
        }
    });

    const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
    new Chart(userGrowthCtx, {
        type: 'line',
        data: {
            labels: [<?= implode(',', array_map(function($g) { return "'" . htmlspecialchars($g['month']) . "'"; }, $user_growth)) ?>],
            datasets: [{
                label: 'New Users',
                data: [<?= implode(',', array_column($user_growth, 'new_users')) ?>],
                backgroundColor: 'rgba(255, 190, 50, 0.2)',
                borderColor: '#ffbe32',
                borderWidth: 2,
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // View All Button Functionality
    $('.view-all-btn').click(function() {
        const tableId = $(this).data('table');
        const endpoint = $(this).data('endpoint');
        const button = $(this);
        
        // Show loading state
        button.html('<span class="loading-spinner"></span> Loading...').prop('disabled', true);
        
        $.ajax({
            url: endpoint,
            type: 'GET',
            success: function(data) {
                // Replace table body with all records
                $('#' + tableId + ' tbody').html(data);
                // Hide the button after loading all records
                button.hide();
            },
            error: function() {
                alert('Error loading data. Please try again.');
                // Reset button state
                button.html('<i class="fas fa-eye"></i> View All').prop('disabled', false);
            }
        });
    });
    
    // Add Chef Form Toggle
    $('#show-add-chef-form').click(function() {
        $('#add-chef-form').slideDown();
        $(this).hide();
    });
    
    $('#cancel-add-chef').click(function() {
        $('#add-chef-form').slideUp();
        $('#show-add-chef-form').show();
    });
});
</script>
</body>
</html>