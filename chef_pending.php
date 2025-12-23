<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

$chefQuery = "SELECT status FROM chef WHERE user_id = $user_id";
$chefResult = mysqli_query($conn, $chefQuery);
$chef = mysqli_fetch_assoc($chefResult);

if (!$chef || $chef['status'] !== 'pending') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Pending | DishDash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #FF6B6B;
            --secondary: #4ECDC4;
            --accent: #FFE66D;
            --dark: #292F36;
            --light: #F7FFF7;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        
        .pending-card {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .pending-header {
            background: linear-gradient(135deg, var(--accent) 0%, var(--secondary) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .pending-icon {
            font-size: 5rem;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }
        
        .pending-body {
            padding: 40px;
        }
        
        .timeline {
            position: relative;
            margin: 40px 0;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            left: 50px;
            height: 100%;
            width: 3px;
            background: var(--accent);
        }
        
        .timeline-item {
            position: relative;
            padding-left: 80px;
            margin-bottom: 30px;
        }
        
        .timeline-icon {
            position: absolute;
            left: 38px;
            top: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            font-size: 14px;
            box-shadow: 0 0 0 5px white;
        }
        
        .action-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
            height: 100%;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 28px rgba(0,0,0,0.1), 0 10px 10px rgba(0,0,0,0.08);
        }
        
        .action-card .icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
        }
        
        .btn-custom {
            border-radius: 50px;
            padding: 12px 25px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-primary-custom {
            background: var(--accent);
        }
        
        .btn-secondary-custom {
            background: var(--secondary);
        }
        
        .btn-outline-custom {
            border: 2px solid var(--accent);
            color: var(--accent);
        }
        
        .btn-outline-custom:hover {
            background: var(--accent);
            color: white;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .progress-container {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin: 30px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--secondary));
            width: 60%;
            border-radius: 4px;
            animation: progressAnimation 2s ease-in-out infinite;
        }
        
        @keyframes progressAnimation {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
    </style>
</head>
<body>
    <div class="pending-card">
        <div class="pending-header">
            <div class="pending-icon">
                <i class="fas fa-clock"></i>
            </div>
            <h2>Chef Application Under Review</h2>
            <p class="lead mb-0">We're excited to have you join our culinary community!</p>
        </div>
        
        <div class="pending-body">
            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>
            
            <p class="text-center text-muted mb-4">
                Our team is carefully reviewing your application. This process typically takes <strong>24-48 hours</strong>.
            </p>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h5>Application Submitted</h5>
                    <p class="text-muted">We've received your chef application and all required information.</p>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <h5>Under Review</h5>
                    <p class="text-muted">Our team is currently reviewing your qualifications and experience.</p>
                </div>
                
          
            </div>
            
            <h4 class="text-center mb-4">What You Can Do Now</h4>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <a href="recipes.php" class="text-decoration-none">
                        <div class="action-card p-4 text-center">
                            <div class="icon-wrapper" style="background: rgba(78, 205, 196, 0.1); color: var(--secondary);">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <h5>Browse Recipes</h5>
                            <p class="small text-muted">Get inspired by our community recipes</p>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4">
                    <a href="chef_signup.php" class="text-decoration-none">
                        <div class="action-card p-4 text-center">
                            <div class="icon-wrapper" style="background: rgba(255, 107, 107, 0.1); color: var(--primary);">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <h5>Update Profile</h5>
                            <p class="small text-muted">Enhance your application details</p>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4">
                    <a href="main.php" class="text-decoration-none">
                        <div class="action-card p-4 text-center">
                            <div class="icon-wrapper" style="background: rgba(255, 230, 109, 0.1); color: #d4a017;">
                                <i class="fas fa-home"></i>
                            </div>
                            <h5>Return Home</h5>
                            <p class="small text-muted">Continue as a regular user</p>
                        </div>
                    </a>
                </div>
            </div>
            
        
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>