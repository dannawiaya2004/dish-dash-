<?php
session_start();
include 'db.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
 
    $name = mysqli_real_escape_string($conn, $_POST["name"]);
    $email = mysqli_real_escape_string($conn, $_POST["email"]);
    $password = $_POST["password"];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $phone = mysqli_real_escape_string($conn, $_POST["phone"]);
    $country = mysqli_real_escape_string($conn, $_POST["country"]);
    $experience = isset($_POST["experience"]) ? mysqli_real_escape_string($conn, $_POST["experience"]) : '';
    $role = isset($_POST["role"]) ? mysqli_real_escape_string($conn, $_POST["role"]) : '';
    $details = mysqli_real_escape_string($conn, $_POST["details"]);
    $restrictions = isset($_POST["restrictions"]) ? array_filter($_POST["restrictions"], function($r) {
        return !empty(trim($r));
    }) : [];
    $created_at = date("Y-m-d H:i:s");

    if (empty($email)) {
        $message = "<div class='alert alert-danger text-center'>❌ Email is required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^@]+@[^@]+\.[^@]+$/', $email)) {
        $message = "<div class='alert alert-danger text-center'>❌ Please enter a valid email address (example@domain.com).</div>";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $message = "<div class='alert alert-danger text-center'>❌ Password must be at least 8 characters with 1 uppercase and 1 number.</div>";
    } elseif (!preg_match('/^\d{8}$/', $phone)) {
        $message = "<div class='alert alert-danger text-center'>❌ Phone must be 8 digits.</div>";
    } elseif (empty($experience) || empty($role)) {
        $message = "<div class='alert alert-danger text-center'>❌ Experience and Role are required.</div>";
    } else {

        $checkEmailQuery = "SELECT email FROM user WHERE email = '$email'";
        $checkEmailResult = mysqli_query($conn, $checkEmailQuery);
        
        if (mysqli_num_rows($checkEmailResult) > 0) {
            $message = "<div class='alert alert-danger text-center'>❌ Email already exists. <a href='login.php'>Login instead?</a></div>";
        } else {

       $insertUserQuery = "INSERT INTO user (name, email, password, phone, country, created_at, experience, details, status) 
                                VALUES ('$name', '$email', '$hashedPassword', '$phone', '$country', '$created_at', '$experience', '$details', 'active')";

            if (mysqli_query($conn, $insertUserQuery)) {
                $user_id = mysqli_insert_id($conn);
                
                //  restrictions
                if (!empty($restrictions)) {
                    foreach ($restrictions as $restriction) {
                        $restriction = trim(mysqli_real_escape_string($conn, $restriction));
                        if (!empty($restriction)) {
                            $checkRestrictionQuery = "SELECT RestrictionID FROM restriction WHERE name = '$restriction'";
                            $checkRestrictionResult = mysqli_query($conn, $checkRestrictionQuery);
                            
                            if (mysqli_num_rows($checkRestrictionResult) > 0) {
                                $row = mysqli_fetch_assoc($checkRestrictionResult);
                                $restriction_id = $row['RestrictionID'];
                            } else {
                                $insertRestrictionQuery = "INSERT INTO restriction (name, description) VALUES ('$restriction', '')";
                                mysqli_query($conn, $insertRestrictionQuery);
                                $restriction_id = mysqli_insert_id($conn);
                            }

                            $insertUserRestrictionQuery = "INSERT INTO user_restriction (user_id, restriction_id) VALUES ($user_id, $restriction_id)";
                            mysqli_query($conn, $insertUserRestrictionQuery);
                        }
                    }
                }

                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role;
                $_SESSION['details'] = $details;
                $_SESSION['signup_success'] = true;

                if ($role === "chef") {
                    $insertChefQuery = "INSERT INTO chef (details, specialties, user_id) VALUES ('$details', '', $user_id)";
                    mysqli_query($conn, $insertChefQuery);
                    header("Location: chef_signup.php");
                    exit();
                } else {
                    header("Location: main.php");
                    exit();
                }
            } else {
                $message = "<div class='alert alert-danger text-center'>❌ Error: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}

$current_step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$selected_role = isset($_POST['role']) ? $_POST['role'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dish Dash - Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff9900;
            --secondary-color: #ffcc00;
        }
        
        body {
            background: url('WhatsApp Image 2025-03-07 at 3.50.21 PM.jpeg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .signup-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            transition: all 0.3s;
        }
        
        /* Role Selector Styling */
        .role-selector {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .role-btn {
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
            flex: 1;
            text-align: center;
        }
        
        .role-btn.regular {
            border-color: #ff9900;
        }
        
        .role-btn.chef {
            border-color: #ffcc00;
        }
        
        .role-btn.active.regular {
            background: #ff9900;
            color: white;
        }
        
        .role-btn.active.chef {
            background: #ffcc00;
            color: white;
        }
        
        /* Form Steps */
        .form-step {
            display: none;
        }
        
        .form-step.active {
            display: block;
        }
        
        /* Dynamic Button Colors */
        .btn-next {
            background: var(--primary-color);
            border: none;
        }
        
        .btn-next:hover {
            background: #e68a00;
        }
        
        .chef-theme .btn-next {
            background: var(--secondary-color);
        }
        
        .chef-theme .btn-next:hover {
            background: #e6b800;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
        }
        
        .chef-theme .btn-submit {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        }
        
        /* Progress Indicator */
        .progress-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .progress-step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            color: white;
        }
        
        .progress-step.active {
            background: var(--primary-color);
        }
        
        .chef-theme .progress-step.active {
            background: var(--secondary-color);
        }
        
        .progress-step.completed {
            background: var(--secondary-color);
        }
        
        .chef-theme .progress-step.completed {
            background: var(--primary-color);
        }
        
        .progress-line {
            flex: 1;
            height: 4px;
            background: #ddd;
            margin: 0 -5px;
            align-self: center;
        }
        
        .progress-line.active {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }
        
        .chef-theme .progress-line.active {
            background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
        }
        
        /* Password field styling */
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 30%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 5;
        }

        .restriction-input {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .restriction-input .form-control {
            flex: 1;
        }
        
        /* Validation styles */
        .is-invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }

        .is-invalid + .invalid-feedback {
            display: block;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .chef-theme .login-link a {
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="signup-container <?= ($selected_role === 'chef') ? 'chef-theme' : '' ?>">
        <h2 class="text-center mb-4">Create Your Account</h2>
        <?= $message ?>
        
        <div class="progress-container">
            <div class="progress-step <?= ($current_step >= 1) ? 'active' : '' ?>">1</div>
            <div class="progress-line <?= ($current_step >= 2) ? 'active' : '' ?>"></div>
            <div class="progress-step <?= ($current_step >= 2) ? 'active' : '' ?>">2</div>
        </div>
        
        <div class="role-selector">
            <button type="button" class="role-btn regular <?= ($selected_role === 'regular user') ? 'active' : '' ?>" 
                    onclick="selectRole('regular user')">
                <i class="fas fa-user me-2"></i>Regular User
            </button>
            <button type="button" class="role-btn chef <?= ($selected_role === 'chef') ? 'active' : '' ?>" 
                    onclick="selectRole('chef')">
                <i class="fas fa-utensils me-2"></i>Chef
            </button>
        </div>
        
        <form method="POST" id="signupForm">
            <input type="hidden" name="step" value="<?= $current_step ?>">
            <input type="hidden" name="role" id="formRole" value="<?= htmlspecialchars($selected_role) ?>">
            
            <!-- Step 1 -->
            <div class="form-step <?= ($current_step === 1) ? 'active' : '' ?>" id="step1">
                <div class="mb-3">
                    <input type="text" class="form-control" name="name" placeholder="Full Name" required
                           value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                    <div class="invalid-feedback">Please enter your name</div>
                </div>
                
                <div class="mb-3">
                    <input type="email" class="form-control" name="email" placeholder="Email" required
                           pattern="[^@]+@[^@]+\.[^@]+"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    <div class="invalid-feedback">Please enter a valid email address (example@domain.com)</div>
                </div>
                
                <div class="mb-3 password-container">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required
                           value="<?= isset($_POST['password']) ? htmlspecialchars($_POST['password']) : '' ?>">
                    <span class="password-toggle" id="toggle-password">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </span>
                    <div class="form-text">8+ characters with 1 uppercase and 1 number</div>
                    <div class="invalid-feedback">Password must be at least 8 characters with 1 uppercase and 1 number</div>
                </div>
                
                <div class="mb-3">
                    <input type="tel" class="form-control" name="phone" placeholder="Phone (8 digits)" required
                           pattern="\d{8}"
                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                    <div class="invalid-feedback">Phone must be exactly 8 digits</div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-next btn-primary px-4" onclick="validateStep1()" 
                            <?= empty($selected_role) ? 'disabled' : '' ?>>
                        Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
                <div class="login-link">
            Already have an account? <a href="login.php">Log in here</a>
        </div>
            </div>
            
            <!-- Step 2 -->
            <div class="form-step <?= ($current_step === 2) ? 'active' : '' ?>" id="step2">
                <div class="mb-3">
                    <select class="form-select" name="country" required>
                        <option value="" disabled selected>Select Country</option>
                    </select>
                    <div class="invalid-feedback">Please select a country</div>
                </div>
                
                <div class="mb-3">
                    <select class="form-select" name="experience" required>
                        <option value="" disabled selected>Experience Level</option>
                        <option value="Beginner" <?= (isset($_POST['experience']) && $_POST['experience'] === 'Beginner') ? 'selected' : '' ?>>Beginner</option>
                        <option value="Intermediate" <?= (isset($_POST['experience']) && $_POST['experience'] === 'Intermediate') ? 'selected' : '' ?>>Intermediate</option>
                        <option value="Advanced" <?= (isset($_POST['experience']) && $_POST['experience'] === 'Advanced') ? 'selected' : '' ?>>Advanced</option>
                    </select>
                    <div class="invalid-feedback">Please select your experience level</div>
                </div>
                
                <div class="mb-3">
                    <textarea class="form-control" name="details" placeholder="<?= ($selected_role === 'chef') ? 'Tell us about your culinary background...' : 'Tell us about yourself...' ?>" 
                              rows="3"><?= isset($_POST['details']) ? htmlspecialchars($_POST['details']) : '' ?></textarea>
                </div>
                
                <div class="mb-3" id="restrictions-container">
                    <label class="form-label">Dietary Restrictions/Allergies</label>
                    <div class="restriction-input">
                        <input type="text" class="form-control" name="restrictions[]" placeholder="e.g., Vegetarian, Nut Allergy"
                               value="<?= isset($_POST['restrictions'][0]) ? htmlspecialchars($_POST['restrictions'][0]) : '' ?>">
                        <button type="button" class="btn btn-outline-primary" onclick="addRestrictionField()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <?php if (isset($_POST['restrictions']) && count($_POST['restrictions']) > 1): ?>
                        <?php for ($i = 1; $i < count($_POST['restrictions']); $i++): ?>
                            <?php if (!empty(trim($_POST['restrictions'][$i]))): ?>
                                <div class="restriction-input mt-2">
                                    <input type="text" class="form-control" name="restrictions[]" 
                                           value="<?= htmlspecialchars($_POST['restrictions'][$i]) ?>">
                                    <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
             
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep()">
                        <i class="fas fa-arrow-left me-2"></i> Back
                    </button>
                    <button type="submit" class="btn btn-submit btn-primary px-4" id="submitBtn">
                        Sign Up <i class="fas fa-user-plus ms-2"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function selectRole(role) {
            const form = document.getElementById('signupForm');
            document.getElementById('formRole').value = role;
            
            if (role === 'chef') {
                document.querySelector('.signup-container').classList.add('chef-theme');
            } else {
                document.querySelector('.signup-container').classList.remove('chef-theme');
            }
            
            document.querySelectorAll('.role-btn').forEach(btn => {
                btn.classList.remove('active');
                if ((role === 'chef' && btn.classList.contains('chef')) || 
                    (role === 'regular user' && btn.classList.contains('regular'))) {
                    btn.classList.add('active');
                }
            });
            
            document.querySelector('[onclick="validateStep1()"]').disabled = false;
        }
        
        function validateEmail(email) {
            const re = /^[^@]+@[^@]+\.[^@]+$/;
            return re.test(String(email).toLowerCase());
        }
        
        function validateStep1() {
            const form = document.getElementById('signupForm');
            let isValid = true;
            
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            document.querySelectorAll('#step1 [required]').forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                }
            });
            
            const email = document.querySelector('[name="email"]').value;
            if (email && !validateEmail(email)) {
                isValid = false;
                document.querySelector('[name="email"]').classList.add('is-invalid');
            }
            
            const password = document.getElementById('password').value;
            if (password && !/(?=.*[A-Z])(?=.*\d).{8,}/.test(password)) {
                isValid = false;
                document.getElementById('password').classList.add('is-invalid');
            }
            
            const phone = document.querySelector('[name="phone"]').value;
            if (phone && !/^\d{8}$/.test(phone)) {
                isValid = false;
                document.querySelector('[name="phone"]').classList.add('is-invalid');
            }
            
            if (isValid) {
                nextStep();
            } else {
                const firstError = document.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }
        
        function nextStep() {
            document.querySelector('input[name="step"]').value = 2;
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            
            document.querySelectorAll('.progress-step')[1].classList.add('active');
            document.querySelector('.progress-line').classList.add('active');
        }
        
        function prevStep() {
            document.querySelector('input[name="step"]').value = 1;
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step1').classList.add('active');
            
            document.querySelectorAll('.progress-step')[1].classList.remove('active');
            document.querySelector('.progress-line').classList.remove('active');
        }
        
        async function loadCountries() {
            try {
                const response = await fetch('https://restcountries.com/v3.1/all');
                const countries = await response.json();
                const select = document.querySelector('[name="country"]');
                
                countries.sort((a, b) => a.name.common.localeCompare(b.name.common));
                
                while (select.options.length > 1) {
                    select.remove(1);
                }
                
                countries.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country.name.common;
                    option.textContent = country.name.common;
                    select.appendChild(option);
                });
                
                <?php if (isset($_POST['country'])): ?>
                    select.value = <?= json_encode($_POST['country']) ?>;
                <?php endif; ?>
            } catch (error) {
                console.error("Failed to load countries:", error);
                const fallbackCountries = ['United States', 'Canada', 'United Kingdom', 'Australia', 'Germany', 'France'];
                const select = document.querySelector('[name="country"]');
                fallbackCountries.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country;
                    option.textContent = country;
                    select.appendChild(option);
                });
            }
        }
        
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });
        
        function addRestrictionField() {
            const container = document.getElementById("restrictions-container");
            const newField = document.createElement("div");
            newField.className = "restriction-input mt-2";
            newField.innerHTML = `
                <input type="text" class="form-control" name="restrictions[]" placeholder="Another restriction">
                <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
                    <i class="fas fa-minus"></i>
                </button>
            `;
            container.appendChild(newField);
        }
        
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            document.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            });
            
            const email = document.querySelector('[name="email"]').value;
            if (email && !validateEmail(email)) {
                document.querySelector('[name="email"]').classList.add('is-invalid');
                isValid = false;
            }
            
            const password = document.getElementById('password').value;
            if (password && !/(?=.*[A-Z])(?=.*\d).{8,}/.test(password)) {
                document.getElementById('password').classList.add('is-invalid');
                isValid = false;
            }
            
            const phone = document.querySelector('[name="phone"]').value;
            if (phone && !/^\d{8}$/.test(phone)) {
                document.querySelector('[name="phone"]').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstError = document.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                document.querySelectorAll('[name="restrictions[]"]').forEach(input => {
                    if (input.value.trim() === '') {
                        input.disabled = true;
                    }
                });
                
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
                submitBtn.disabled = true;
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            loadCountries();
            
            const role = document.getElementById('formRole').value;
            if (role) {
                selectRole(role);
            }
            
            <?php if ($message && $current_step == 2): ?>
                document.getElementById('step1').classList.remove('active');
                document.getElementById('step2').classList.add('active');
                document.querySelectorAll('.progress-step')[1].classList.add('active');
                document.querySelector('.progress-line').classList.add('active');
            <?php endif; ?>
        });
    </script>
</body>
</html>