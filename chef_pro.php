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

// Check if user is a chef
$is_chef_query = "SELECT * FROM chef WHERE user_id = $user_id";
$is_chef_result = $conn->query($is_chef_query);

if ($is_chef_result->num_rows == 0) {
    header("Location: profile.php"); 
    exit();
}

$user_query = "SELECT * FROM user WHERE UserID = $user_id";
$user_result = $conn->query($user_query);
$user = $user_result->fetch_assoc();

$chef_query = "SELECT * FROM chef WHERE user_id = $user_id";
$chef_result = $conn->query($chef_query);
$chef = $chef_result->fetch_assoc();
$chef_id = $chef['ChefID'];

$specialties = explode(',', $chef['specialties']);

$education_query = "SELECT * FROM graduate WHERE chef_id = $chef_id ORDER BY year DESC";
$education_result = $conn->query($education_query);
$education = [];
while ($row = $education_result->fetch_assoc()) {
    $education[] = $row;
}

$experience_query = "SELECT * FROM experience WHERE chef_id = $chef_id ORDER BY year_start DESC";
$experience_result = $conn->query($experience_query);
$experiences = [];
while ($row = $experience_result->fetch_assoc()) {
    $experiences[] = $row;
}

$ratings_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count 
                 FROM rate_chef 
                 WHERE chef_id = $chef_id";
$ratings_result = $conn->query($ratings_query);
$ratings = $ratings_result->fetch_assoc();

$cuisine_query = "SELECT * FROM cuisine_type";
$cuisine_result = $conn->query($cuisine_query);
$cuisines = [];
while ($row = $cuisine_result->fetch_assoc()) {
    $cuisines[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $country = $conn->real_escape_string($_POST['country']);
    $bio = $conn->real_escape_string($_POST['bio']);
    $chef_details = $conn->real_escape_string($_POST['chef_details']);
    $specialties = isset($_POST['specialties']) ? $_POST['specialties'] : [];

    $update_user_query = "UPDATE user SET 
                        name = '$username',
                        email = '$email',
                        phone = '$phone',
                        country = '$country',
                        details = '$bio'
                        WHERE UserID = $user_id";
    
    $update_chef_query = "UPDATE chef SET 
                         details = '$chef_details',
                         specialties = '" . implode(',', $specialties) . "'
                         WHERE user_id = $user_id";
    
    if ($conn->query($update_user_query) && $conn->query($update_chef_query)) {
        $_SESSION['success'] = "Profile updated successfully!";
        $user_result = $conn->query($user_query);
        $user = $user_result->fetch_assoc();
        $chef_result = $conn->query($chef_query);
        $chef = $chef_result->fetch_assoc();
        $specialties = explode(',', $chef['specialties']);
    } else {
        $_SESSION['error'] = "Error updating profile: " . $conn->error;
    }
}



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_education'])) {
    $university = $conn->real_escape_string($_POST['university']);
    $major = $conn->real_escape_string($_POST['major']);
    $year = (int)$_POST['year'];
    
    $insert_query = "INSERT INTO graduate (university, major, year, chef_id) 
                    VALUES ('$university', '$major', $year, $chef_id)";
    
    if ($conn->query($insert_query)) {
        $_SESSION['success'] = "Education added successfully!";
        // Refresh education data
        $education_result = $conn->query($education_query);
        $education = [];
        while ($row = $education_result->fetch_assoc()) {
            $education[] = $row;
        }
    } else {
        $_SESSION['error'] = "Error adding education: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_experience'])) {
    $place = $conn->real_escape_string($_POST['place']);
    $position = $conn->real_escape_string($_POST['position']);
    $year_start = (int)$_POST['year_start'];
    $year_stop = !empty($_POST['year_stop']) ? (int)$_POST['year_stop'] : NULL;
    
    $insert_query = "INSERT INTO experience (place, position, year_start, year_stop, chef_id) 
                    VALUES ('$place', '$position', $year_start, " . ($year_stop ? $year_stop : 'NULL') . ", $chef_id)";
    
    if ($conn->query($insert_query)) {
        $_SESSION['success'] = "Experience added successfully!";
        // Refresh experience data
        $experience_result = $conn->query($experience_query);
        $experiences = [];
        while ($row = $experience_result->fetch_assoc()) {
            $experiences[] = $row;
        }
    } else {
        $_SESSION['error'] = "Error adding experience: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_education'])) {
    $education_id = (int)$_POST['education_id'];
    
    $delete_query = "DELETE FROM graduate WHERE GraduateID = $education_id AND chef_id = $chef_id";
    
    if ($conn->query($delete_query)) {
        $_SESSION['success'] = "Education deleted successfully!";
        // Refresh education data
        $education_result = $conn->query($education_query);
        $education = [];
        while ($row = $education_result->fetch_assoc()) {
            $education[] = $row;
        }
    } else {
        $_SESSION['error'] = "Error deleting education: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_experience'])) {
    $experience_id = (int)$_POST['experience_id'];
    
    $delete_query = "DELETE FROM experience WHERE ExperienceID = $experience_id AND chef_id = $chef_id";
    
    if ($conn->query($delete_query)) {
        $_SESSION['success'] = "Experience deleted successfully!";
        // Refresh experience data
        $experience_result = $conn->query($experience_query);
        $experiences = [];
        while ($row = $experience_result->fetch_assoc()) {
            $experiences[] = $row;
        }
    } else {
        $_SESSION['error'] = "Error deleting experience: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Profile - DishDash</title>
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
        
        .specialty-badge {
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
            padding: 5px 10px;
            background-color: #d4edda;
            color: #155724;
            border-radius: 10px;
        }
        
        .rating-badge {
            font-size: 1rem;
            padding: 5px 10px;
            background-color: #fff3cd;
            color: #856404;
            border-radius: 10px;
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
        
        .nav-tabs .nav-link {
            font-weight: 500;
            color: #495057;
            border: none;
            padding: 12px 20px;
        }
        
        .nav-tabs .nav-link.active {
            color: #2E8B57;
            border-bottom: 3px solid #2E8B57;
            background-color: transparent;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e69500;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #e69500;
            border: 3px solid white;
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
            <a class="navbar-brand" href="#">
                <img src="Recipe Book Logo.jpeg" alt="Logo" width="40" height="40" class="rounded-circle"> DishDash
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link " href="chef.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="chefAddRecipe.php"><i class="fas fa-plus-circle"></i> Add Recipe</a>
                    </li>
                  
                    <li class="nav-item">
                        <a class="nav-link active" href="chef_pro.php"><i class="fas fa-user"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="profile-header">
        <div class="container text-center">
            <h1 class="fw-bold mb-2"><?= htmlspecialchars($user['name']) ?></h1>
            <p class="lead mb-0">Professional Chef</p>
            <?php if ($ratings['rating_count'] > 0): ?>
                <div class="mt-3">
                    <span class="rating-badge">
                        <i class="fas fa-star"></i> <?= number_format($ratings['avg_rating'], 1) ?> 
                        (<?= $ratings['rating_count'] ?> ratings)
                    </span>
                </div>
            <?php endif; ?>
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
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#background">
                    <i class="fas fa-graduation-cap me-2"></i>Background
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
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Country</label>
                                        <select class="form-select" name="country">
                                            <option value="">Select Country</option>
                                            <?php 
                                            $countries = [
                                                "United States", "Canada", "United Kingdom", "Australia", "France", 
                                                "Germany", "Italy", "Spain", "Japan", "China", "India", "Brazil", 
                                                "Mexico", "South Africa", "Other"
                                            ];
                                            foreach ($countries as $c): ?>
                                                <option value="<?= $c ?>" <?= ($user['country'] ?? '') == $c ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Personal Bio</label>
                                        <textarea class="form-control" name="bio" rows="3"><?= htmlspecialchars($user['details']) ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Chef Bio</label>
                                        <textarea class="form-control" name="chef_details" rows="3"><?= htmlspecialchars($chef['details']) ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Specialties</label>
                                        <select class="form-select" name="specialties[]" multiple>
                                            <?php foreach ($cuisines as $cuisine): ?>
                                                <option value="<?= $cuisine['name'] ?>" 
                                                    <?= in_array($cuisine['name'], $specialties) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cuisine['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
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
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-award me-2"></i>Chef Information
                            </div>
                            <div class="card-body">
                                <h5 class="fw-bold">Specialties</h5>
                                <div class="d-flex flex-wrap mb-3">
                                    <?php foreach ($specialties as $specialty): ?>
                                        <span class="specialty-badge"><?= htmlspecialchars($specialty) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                
                                <h5 class="fw-bold">Chef Rating</h5>
                                <?php if ($ratings['rating_count'] > 0): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <span class="display-4 fw-bold"><?= number_format($ratings['avg_rating'], 1) ?></span>
                                            <span class="text-muted">/5</span>
                                        </div>
                                        <div>
                                            <div class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i > floor($ratings['avg_rating']) ? ($i - 0.5 <= $ratings['avg_rating'] ? '-half-alt' : '') : '' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?= $ratings['rating_count'] ?> ratings</small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No ratings yet</p>
                                <?php endif; ?>
                                
                               
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
          
            
            <!-- Background Tab -->
            <div class="tab-pane fade" id="background">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-graduation-cap me-2"></i>Education
                                </div>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEducationModal">
                                    <i class="fas fa-plus me-1"></i>Add
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($education)): ?>
                                    <div class="timeline">
                                        <?php foreach ($education as $edu): ?>
                                            <div class="timeline-item mb-4">
                                                <h5 class="fw-bold"><?= htmlspecialchars($edu['university']) ?></h5>
                                                <p class="mb-1"><?= htmlspecialchars($edu['major']) ?></p>
                                                <p class="text-muted">Graduated: <?= $edu['year'] ?></p>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="education_id" value="<?= $edu['GraduateID'] ?>">
                                                    <button type="submit" name="delete_education" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-graduation-cap text-muted mb-3" style="font-size: 2rem;"></i>
                                        <h5 class="fw-bold mb-2">No Education Added</h5>
                                        <p class="text-muted">Add your culinary education background</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-briefcase me-2"></i>Experience
                                </div>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addExperienceModal">
                                    <i class="fas fa-plus me-1"></i>Add
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($experiences)): ?>
                                    <div class="timeline">
                                        <?php foreach ($experiences as $exp): ?>
                                            <div class="timeline-item mb-4">
                                                <h5 class="fw-bold"><?= htmlspecialchars($exp['place']) ?></h5>
                                                <p class="mb-1"><?= htmlspecialchars($exp['position']) ?></p>
                                                <p class="text-muted">
                                                    <?= $exp['year_start'] ?> - <?= $exp['year_stop'] ? $exp['year_stop'] : 'Present' ?>
                                                </p>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="experience_id" value="<?= $exp['ExperienceID'] ?>">
                                                    <button type="submit" name="delete_experience" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-briefcase text-muted mb-3" style="font-size: 2rem;"></i>
                                        <h5 class="fw-bold mb-2">No Experience Added</h5>
                                        <p class="text-muted">Add your professional culinary experience</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Education Modal -->
    <div class="modal fade" id="addEducationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Education</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">University/Institution</label>
                            <input type="text" class="form-control" name="university" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Major/Program</label>
                            <input type="text" class="form-control" name="major" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Graduation Year</label>
                            <input type="number" class="form-control" name="year" min="1900" max="<?= date('Y') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_education" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Experience Modal -->
    <div class="modal fade" id="addExperienceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Experience</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Place of Work</label>
                            <input type="text" class="form-control" name="place" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year Started</label>
                            <input type="number" class="form-control" name="year_start" min="1900" max="<?= date('Y') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year Ended (leave blank if current)</label>
                            <input type="number" class="form-control" name="year_stop" min="1900" max="<?= date('Y') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_experience" class="btn btn-primary">Save</button>
                    </div>
                </form>
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