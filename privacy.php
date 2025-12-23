<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - DishDash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #e69500;
            --secondary: #58c286;
            --dark: #2E8B57;
            --light: #F5FFFA;
            --gray: #6C757D;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .privacy-header {
            background: linear-gradient(135deg, #e69500 0%, #2E8B57 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .privacy-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .privacy-section {
            margin-bottom: 40px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .section-title {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 20px;
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
        
        .navbar-brand {
            font-weight: 700;
            color: var(--dark) !important;
            font-size: 1.5rem;
        }
        
        footer {
            background-color: var(--dark);
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="main.php">
                <img src="Recipe Book Logo.jpeg" alt="Logo" class="rounded-circle me-2" width="40" height="40">
                <span class="fw-bold">DishDash</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="main.php"><i class="fas fa-home me-1"></i> Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="allRecipes.php"><i class="fas fa-book me-1"></i> Recipes</a></li>
                    <li class="nav-item"><a class="nav-link" href="fridge.php"><i class="fas fa-ice-cream me-1"></i> My Fridge</a></li>
                    <li class="nav-item"><a class="nav-link" href="weekly_plan.php"><i class="fas fa-calendar-alt me-1"></i> Meal Planner</a></li>
                             <li class="nav-item">
    <a href="chatbot.php" class="nav-link ">
      <i class="fas fa-robot"></i>
      <span>Chef Assistant</span>
    </a>
    </li>
                </ul>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="d-flex">
                        <a href="profile.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-circle me-1"></i> My Profile
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-flex">
                        <a href="login.php" class="btn btn-outline-primary">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <section class="privacy-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Privacy Policy</h1>
            <p class="lead">Last updated: <?php echo date('F j, Y'); ?></p>
        </div>
    </section>

    <div class="privacy-container">
        <div class="privacy-section">
            <h2 class="section-title">Introduction</h2>
            <p>Welcome to DishDash ("we," "our," or "us"). We are committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our website and services.</p>
            <p>By accessing or using our service, you agree to the collection and use of information in accordance with this policy.</p>
        </div>

        <div class="privacy-section">
            <h2 class="section-title">Information We Collect</h2>
            <p>We collect several types of information from and about users of our website, including:</p>
            <ul>
                <li><strong>Personal Information:</strong> Name, email address, profile picture, dietary preferences, and other information you provide when registering or using our services.</li>
                <li><strong>Usage Data:</strong> Information about how you use our website, including pages visited, recipes viewed, and interactions with other users.</li>
                <li><strong>Cookies and Tracking Technologies:</strong> We use cookies and similar tracking technologies to track activity on our website and hold certain information.</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2 class="section-title">How We Use Your Information</h2>
            <p>We use the information we collect for various purposes:</p>
            <ul>
                <li>To provide and maintain our service</li>
                <li>To notify you about changes to our service</li>
                <li>To allow you to participate in interactive features of our service</li>
                <li>To provide customer support</li>
                <li>To gather analysis or valuable information so that we can improve our service</li>
                <li>To monitor the usage of our service</li>
                <li>To detect, prevent and address technical issues</li>
                <li>To personalize your experience with recipe recommendations</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2 class="section-title">Data Sharing and Disclosure</h2>
            <p>We may share your information in the following situations:</p>
            <ul>
                <li><strong>With Service Providers:</strong> We may share your information with service providers to monitor and analyze the use of our service, to contact you.</li>
                <li><strong>For Business Transfers:</strong> If we undergo a merger, acquisition, or asset sale, your personal data may be transferred.</li>
                <li><strong>With Your Consent:</strong> We may disclose your personal information for any other purpose with your consent.</li>
            </ul>
            <p>We do not sell your personal information to third parties.</p>
        </div>

        <div class="privacy-section">
            <h2 class="section-title">Data Security</h2>
            <p>We implement appropriate technical and organizational measures to protect your personal information. However, no method of transmission over the Internet or method of electronic storage is 100% secure, and we cannot guarantee absolute security.</p>
        </div>

        <div class="privacy-section">
            <h2 class="section-title">Your Data Protection Rights</h2>
            <p>Depending on your location, you may have the following rights regarding your personal data:</p>
            <ul>
                <li>The right to access, update or delete your information</li>
                <li>The right of rectification if your information is inaccurate or incomplete</li>
                <li>The right to object to our processing of your personal data</li>
                <li>The right to request restriction of processing your personal information</li>
                <li>The right to data portability</li>
                <li>The right to withdraw consent</li>
            </ul>
            <p>To exercise these rights, please contact us at privacy@dishdash.com.</p>
        </div>

   

        <div class="privacy-section">
            <h2 class="section-title">Children's Privacy</h2>
            <p>Our service is not intended for use by children under the age of 13. We do not knowingly collect personally identifiable information from children under 13. If you are a parent or guardian and you are aware that your child has provided us with personal data, please contact us.</p>
        </div>

        <div class="privacy-section">
            <h2 class="section-title">Changes to This Privacy Policy</h2>
            <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date.</p>
            <p>You are advised to review this Privacy Policy periodically for any changes.</p>
        </div>

    </div>

    <footer>
        <div class="container text-center">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5>DishDash</h5>
                    <p>Your personal recipe assistant for meal planning and cooking.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="about_us.php" class="text-white">About Us</a></li>
                        <li><a href="privacy.php" class="text-white">Privacy Policy</a></li>
                        <li><a href="terms.php" class="text-white">Terms of Service</a></li>
                    </ul>
                </div>
              
            </div>
            <hr class="my-4 bg-light">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> DishDash. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>