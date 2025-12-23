<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        $query = "SELECT UserID, email, password, status FROM user WHERE email = '".mysqli_real_escape_string($conn, $email)."'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            if ($user['status'] != 'active') {
                $error = "Your account is inactive. Please contact support.";
            } else {
                $user_id = $user['UserID'];
                $db_email = $user['email'];
                $hashed_password = $user['password'];

                if (password_verify($password, $hashed_password)) {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_email'] = $db_email;
                    
                    $admin_query = "SELECT AdminID FROM admin WHERE user_id = $user_id";
                    $admin_result = mysqli_query($conn, $admin_query);
                    
                    if (mysqli_num_rows($admin_result) > 0) {
                        $_SESSION['role'] = 'admin';
                        header("Location: admin.php");
                        exit();
                    }
                    
                    $chef_query = "SELECT ChefID, status FROM chef WHERE user_id = $user_id";
                    $chef_result = mysqli_query($conn, $chef_query);
                    

                    if (mysqli_num_rows($chef_result) > 0) {
                        $chef = mysqli_fetch_assoc($chef_result);
                        $status = $chef['status'];
                        
                        if ($status == 'approved') {
                            $_SESSION['role'] = 'chef';
                            $_SESSION['chef_id'] = $chef['ChefID'];
                            header("Location: chef.php");
                            exit();
                        }
                        elseif ($status == 'pending') {
                            $_SESSION['role'] = 'user'; 
                            header("Location: chef_pending.php");
                            exit();
                        }
                        elseif ($status == 'declined') {
                            $_SESSION['role'] = 'user';
                            header("Location: main.php");
                            exit();
                        }
                    }
                    
                    $_SESSION['role'] = 'user';
                    header("Location: main.php");
                    exit();
                } else {
                    $error = "Invalid email or password!";
                }
            }
        } else {
            $error = "No account found with this email!";
        }
    } else {
        $error = "All fields are required!";
    }
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Dish Dash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: url('61c0027b-aa3a-4142-b5b4-0dd506427f1a.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }
        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .logo img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 2px solid #fff;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #FF6B00, #FFD700);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 15px;
            box-shadow: 0 3px 12px rgba(255, 107, 0, 0.3);
        }
        .signup-link a, .forgot-password-link a {
            color: #FF6B00;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .signup-link a:hover, .forgot-password-link a:hover {
            color: #e05d00;
        }
        .additional-links {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-top: 10px;
        }
        #toggle-password {
            cursor: pointer;
            background-color: transparent;
            border: none;
            padding: 0 10px;
            display: flex;
            align-items: center;
        }
        #eye-icon {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="login-container">
        <div class="logo">
            <img src="Recipe Book Logo.jpeg" alt="Logo">
        </div>
        <h1>Login</h1>
        <?php if (isset($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>
        <form action="login.php" method="POST">
            <div class="mb-3">
                <input type="email" class="form-control" name="email" placeholder="Email" required>
            </div>
            <div class="mb-3">
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required aria-label="Password">
                    <button class="input-group-text" id="toggle-password" type="button" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="login-btn">Login</button>
            
            <div class="additional-links">
                <p class="signup-link">Don't have an account? <a href="signup.php">Sign up</a></p>
                <p class="forgot-password-link"><a href="forgot-password.php">Forgot password?</a></p>
            </div>
        </form>
    </div>
    <script>
        document.querySelector('form').addEventListener('submit', function() {
            document.querySelector('.login-btn').disabled = true;
            document.querySelector('.login-btn').innerHTML = 
                '<span class="spinner-border spinner-border-sm"></span> Logging in...';
        });
        
        document.getElementById('toggle-password').addEventListener('click', function () {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const isPassword = passwordField.type === 'password';
            passwordField.type = isPassword ? 'text' : 'password';
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>