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
    <title>Terms of Service - DishDash</title>
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
        
        .terms-header {
            background: linear-gradient(135deg, #e69500 0%, #2E8B57 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .terms-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .terms-section {
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
        
        .highlight {
            background-color: rgba(230, 149, 0, 0.1);
            padding: 2px 5px;
            border-radius: 3px;
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

    <section class="terms-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Terms of Service</h1>
        </div>
    </section>

    <div class="terms-container">
        <div class="terms-section">
            <h2 class="section-title">1. Acceptance of Terms</h2>
            <p>By accessing or using the DishDash website ("Service"), you agree to be bound by these Terms of Service ("Terms"). If you disagree with any part of the terms, you may not access the Service.</p>
            <p>We reserve the right to update or modify these Terms at any time without prior notice. Your continued use of the Service after any such changes constitutes your acceptance of the new Terms.</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">2. Description of Service</h2>
            <p>DishDash provides an online platform for recipe discovery, meal planning, and kitchen inventory management. Our services include:</p>
            <ul>
                <li>Access to a database of recipes</li>
                <li>Personalized meal planning tools</li>
                <li>Kitchen inventory tracking</li>
                <li>Social features for sharing and discovering recipes</li>
            </ul>
            <p>We reserve the right to modify or discontinue, temporarily or permanently, the Service with or without notice.</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">3. User Accounts</h2>
            <p>To access certain features of the Service, you must register for an account. When registering, you agree to:</p>
            <ul>
                <li>Provide accurate, current, and complete information</li>
                <li>Maintain the security of your password and accept all risks of unauthorized access</li>
                <li>Notify us immediately of any unauthorized use of your account</li>
                <li>Be responsible for all activities that occur under your account</li>
            </ul>
            <p>We reserve the right to refuse service, terminate accounts, or remove content at our sole discretion.</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">4. User Content</h2>
            <p>Users may post, upload, or otherwise contribute content to the Service ("User Content"). You retain all rights to your User Content, but by posting it, you grant DishDash a worldwide, non-exclusive, royalty-free license to use, reproduce, modify, adapt, publish, and display such content.</p>
            <p>You agree not to post User Content that:</p>
            <ul>
                <li>Violates any third-party rights, including copyright or trademark</li>
                <li>Is unlawful, threatening, abusive, harassing, defamatory, or otherwise objectionable</li>
                <li>Contains viruses or other harmful components</li>
                <li>Contains false or misleading information</li>
            </ul>
            <p>We may remove any User Content that violates these Terms without notice.</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">5. Prohibited Conduct</h2>
            <p>When using the Service, you agree not to:</p>
            <ul>
                <li>Use the Service for any illegal purpose or in violation of any laws</li>
                <li>Attempt to gain unauthorized access to other accounts or computer systems</li>
                <li>Interfere with or disrupt the Service or servers</li>
                <li>Use any automated means to access the Service without our permission</li>
                <li>Harass, threaten, or intimidate other users</li>
                <li>Impersonate any person or entity</li>
                <li>Engage in any activity that could damage or overburden our systems</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2 class="section-title">6. Intellectual Property</h2>
            <p>The Service and its original content, features, and functionality are owned by DishDash and are protected by international copyright, trademark, patent, trade secret, and other intellectual property or proprietary rights laws.</p>
            <p>Our trademarks and trade dress may not be used in connection with any product or service without our prior written consent.</p>
            <p>All recipe content provided by DishDash is for personal, non-commercial use only. Commercial use requires written permission.</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">7. Termination</h2>
            <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach these Terms.</p>
            <p>Upon termination, your right to use the Service will immediately cease. All provisions of these Terms which by their nature should survive termination shall survive, including ownership provisions, warranty disclaimers, indemnity, and limitations of liability.</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">8. Disclaimers</h2>
            <p>The Service is provided "AS IS" and "AS AVAILABLE" without warranties of any kind, either express or implied. DishDash disclaims all warranties including:</p>
            <ul>
                <li>That the Service will meet your requirements</li>
                <li>That the Service will be uninterrupted, timely, secure, or error-free</li>
                <li>That the results from using the Service will be accurate or reliable</li>
                <li>That the quality of any products, services, or information obtained through the Service will meet your expectations</li>
            </ul>
            <p class="highlight">DishDash does not guarantee the accuracy of nutritional information or recipe outcomes. Users should exercise their own judgment when preparing recipes, especially regarding food allergies and dietary restrictions.</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">9. Limitation of Liability</h2>
            <p>In no event shall DishDash, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential, or punitive damages resulting from:</p>
            <ul>
                <li>Your access to or use of or inability to access or use the Service</li>
                <li>Any conduct or content of any third party on the Service</li>
                <li>Any content obtained from the Service</li>
                <li>Unauthorized access, use, or alteration of your transmissions or content</li>
            </ul>
            <p>This limitation applies whether based on warranty, contract, tort, or any other legal theory.</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">10. Governing Law</h2>
            <p>These Terms shall be governed by and construed in accordance with the laws of [Your Country/State], without regard to its conflict of law provisions.</p>
            <p>Any disputes arising under these Terms will be resolved in the courts located in [Your Jurisdiction].</p>
        </div>

        <div class="terms-section">
            <h2 class="section-title">11. Changes to Terms</h2>
            <p>We reserve the right to modify these Terms at any time. We will provide notice of any changes by updating the "Last updated" date at the top of this page.</p>
            <p>Your continued use of the Service after any such changes constitutes your acceptance of the new Terms.</p>
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