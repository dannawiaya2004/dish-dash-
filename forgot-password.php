<?php
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        $stmt = $conn->prepare("SELECT UserID FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_stmt = $conn->prepare("UPDATE user SET password = ? WHERE UserID = ?");
            $update_stmt->bind_param("si", $hashed_password, $user['UserID']);
            
            if ($update_stmt->execute()) {
                $success = 'Password updated successfully! You can now <a href="login.php">login</a> with your new password.';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } else {
            $error = 'No account found with that email address';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - DishDash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sunshine: #FFD700;
            --citrus: #FFA500;
            --coal: #333333;
            --smoke: #f5f5f5;
            --pebble: #6c757d;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background-color: var(--smoke);
            color: var(--coal);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .password-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
        }
        
        .password-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .password-logo img {
            height: 60px;
        }
        
        .password-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--coal);
            text-align: center;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-bottom: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--citrus);
            box-shadow: 0 0 0 0.25rem rgba(255, 165, 0, 0.25);
        }
        
        .btn-primary {
            background-color: var(--citrus);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-primary:hover {
            background-color: #e69500;
        }
        
        .alert {
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .password-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--pebble);
        }
        
        .password-footer a {
            color: var(--citrus);
            text-decoration: none;
            font-weight: 600;
        }
        
        .password-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <div class="password-logo">
            <img src="Recipe Book Logo.jpeg" alt="DishDash">
        </div>
        <h1 class="password-title">Reset Your Password</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST" action="forgot-password.php">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        <?php endif; ?>
        
        <div class="password-footer">
            Remember your password? <a href="login.php">Sign in</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>