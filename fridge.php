<?php
session_start();
require_once 'db.php'; 
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$ingredients_query = $conn->query("SELECT IngredientsID, name FROM ingredients ORDER BY name");
$all_ingredients = $ingredients_query->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_ingredients'])) {
    if (!empty($_POST['ingredient_id']) && is_array($_POST['ingredient_id'])) {
        $duplicates_found = false;
        
        foreach ($_POST['ingredient_id'] as $key => $ingredient_id) {
            if (!empty($ingredient_id) && !empty($_POST['quantity'][$key]) && !empty($_POST['expiry_date'][$key])) {
                $ingredient_id = intval($ingredient_id);
                $quantity = floatval($_POST['quantity'][$key]);
                $expiry_date = $_POST['expiry_date'][$key];
                
                // Check if ingredient with same expiry date already exists
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM user_ingredients 
                                            WHERE user_id = ? AND ingredient_id = ? AND expiry_date = ?");
                $check_stmt->bind_param("iis", $user_id, $ingredient_id, $expiry_date);
                $check_stmt->execute();
                $check_stmt->bind_result($count);
                $check_stmt->fetch();
                $check_stmt->close();
                
                if ($count > 0) {
                    $duplicates_found = true;
                    // Get ingredient name for error message
                    $name_stmt = $conn->prepare("SELECT name FROM ingredients WHERE IngredientsID = ?");
                    $name_stmt->bind_param("i", $ingredient_id);
                    $name_stmt->execute();
                    $name_stmt->bind_result($ingredient_name);
                    $name_stmt->fetch();
                    $name_stmt->close();
                    
                    $_SESSION['error_message'] = "You already have '$ingredient_name' with expiry date $expiry_date in your fridge!";
                    continue;
                }
                
                $stmt = $conn->prepare("INSERT INTO user_ingredients (user_id, ingredient_id, quantity, expiry_date) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iids", $user_id, $ingredient_id, $quantity, $expiry_date);
                $stmt->execute();
            }
        }
        
        if ($duplicates_found) {
            header("Location: fridge.php");
            exit();
        }
        
        $_SESSION['success_message'] = "Ingredients added successfully!";
        header("Location: fridge.php");
        exit();
    }
}
if (isset($_GET['remove_ingredient'])) {
    $user_ingredient_id = intval($_GET['remove_ingredient']);
    
    // First get the ingredient name for the notification
    $name_stmt = $conn->prepare("SELECT i.name FROM user_ingredients ui
                               JOIN ingredients i ON ui.ingredient_id = i.IngredientsID
                               WHERE ui.UserIngredientsID = ?");
    $name_stmt->bind_param("i", $user_ingredient_id);
    $name_stmt->execute();
    $name_stmt->bind_result($ingredient_name);
    $name_stmt->fetch();
    $name_stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM user_ingredients WHERE UserIngredientsID = ? AND user_id = ?");
    $stmt->bind_param("ii", $user_ingredient_id, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // We'll handle the success message via JavaScript now
        exit(json_encode(['success' => true, 'message' => "$ingredient_name has been removed"]));
    } else {
        exit(json_encode(['success' => false, 'message' => "Failed to remove ingredient"]));
    }
}

$stmt = $conn->prepare("SELECT ui.UserIngredientsID, i.name, i.image, ui.quantity, ui.expiry_date, i.units 
                       FROM user_ingredients ui 
                       JOIN ingredients i ON ui.ingredient_id = i.IngredientsID 
                       WHERE ui.user_id = ? 
                       ORDER BY ui.expiry_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_ingredients = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dish Dash - My Fridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>

:root {
            --primary: #FFA500; /* Orange */
            --secondary: #FFD700; /* Yellow */
            --dark: #2E8B57; /* Sea Green */
            --light: #F5FFFA; /* Mint Cream */
            --accent: #32CD32; /* Lime Green */
        }
        body {
            background: url('fridge.jpg') no-repeat center center fixed;
            background-size: cover;
            height: 50vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container1 {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0px 8px 32px rgba(0, 0, 0, 0.3);
            margin-top: 50px;
            margin-bottom: 50px;
        }

        .floating-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #ffcc00;
            color: white;
            width: 60px;
            height: 60px;
            font-size: 2rem;
            border-radius: 50%;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .floating-button:hover {
            background-color: #ff9900;
            transform: scale(1.05);
            transition: all 0.2s ease;
        }

        .ingredient-item {
            background: rgba(255, 255, 255, 0.95);
            margin: 10px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            height: 220px;
            transition: all 0.3s ease;
        }

        .ingredient-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .ingredients-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            justify-items: center;
        }

        @media (max-width: 768px) {
            .ingredients-list {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        .ingredient-item .remove-btn {
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #ff6600;
            color: white;
            padding: 6px 15px;
            font-size: 0.9rem;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ingredient-item .remove-btn:hover {
            background-color: #ff4500;
            transform: translateX(-50%) scale(1.05);
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
        

        .ingredient-form-group {
            margin-bottom: 20px;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 8px;
            position: relative;
            background-color:rgb(249, 249, 249);
        }

        .ingredient-form-group .remove-ingredient-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: #ff6666;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ingredient-form-group .remove-ingredient-btn:hover {
            background-color: #ff3333;
        }
        
        .expired {
            color: #dc3545;
            font-weight: bold;
        }
        
        .expiring-soon {
            color: #fd7e14;
            font-weight: bold;
        }
        
        .ingredient-image {
            width: 55px;
            height: 55px;
            object-fit: scale-down;
            border-radius: 50%;
            margin-bottom: 0px;
            border: 3px solid #ffcc00;
        }

        .ingredient-image-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-bottom: 0px;
            font-size: 1.5rem;
            border: 3px solid #ffcc00;
        }

        .text-warning {
            color: #ff9900 !important;
        }

        .btn-warning {
            background-color: #ffcc00;
            border-color: #ffcc00;
        }

        .btn-warning:hover {
            background-color: #ff9900;
            border-color: #ff9900;
        }

        .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }

        .modal-header {
            background-color: #ffcc00;
            color: white;
        }

        .form-control:focus {
            border-color: #ffcc00;
            box-shadow: 0 0 0 0.25rem rgba(255, 204, 0, 0.25);
        }
        
        .dropdown-menu {
            min-width: 200px;
        }
        
        .account-dropdown {
            margin-left: 15px;
        }
        .is-invalid {
    border-color: #dc3545 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.is-invalid + .invalid-feedback {
    display: block;
    color: #dc3545;
    font-size: 0.875em;
    margin-top: 0.25rem;
}
.custom-notification {
    opacity: 0;
    transform: translateY(-20px);
}

.custom-notification.show {
    opacity: 1;
    transform: translateY(0);
}

.custom-notification .btn-close {
    padding: 0.5rem;
    font-size: 0.75rem;
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
                        <a class="nav-link" href="main.php"><i class="fas fa-home me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="allRecipes.php"><i class="fas fa-book me-1"></i> Recipes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="fridge.php"><i class="fas fa-ice-cream me-1"></i> My Fridge</a>
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
                <div class="d-flex">
                    <a href="logout.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container container1">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <h1 class="text-center text-warning mb-4">My Fridge</h1>
        <div class="d-flex justify-content-center mb-4">
    <a href="find_recipes.php" class="btn btn-warning btn-lg text-white">
        <i class="fas fa-utensils me-2"></i> Find Recipes I Can Make
    </a>
</div>
        <div class="ingredients-list" id="ingredients-list">
            <?php if (empty($user_ingredients)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
                    <h4 class="text-muted">Your fridge is empty</h4>
                    <p class="text-muted">Add some ingredients to get started!</p>
                </div>
                
            <?php else: ?>
                <?php foreach ($user_ingredients as $ingredient): 
                    $expiry_date = new DateTime($ingredient['expiry_date']);
                    $today = new DateTime();
                    $interval = $today->diff($expiry_date);
                    $days_remaining = $interval->format('%r%a');
                    
                    $expiry_class = '';
                    if ($days_remaining < 0) {
                        $expiry_class = 'expired';
                        $expiry_text = 'Expired';
                    } elseif ($days_remaining <= 3) {
                        $expiry_class = 'expiring-soon';
                        $expiry_text = 'Expires soon';
                    } else {
                        $expiry_text = 'Expires in ' . $days_remaining . ' days';
                    }
                ?>
                    <div class="ingredient-item">
                        <?php if (!empty($ingredient['image'])): ?>
                            <img src="./ingredients/<?php echo $ingredient['image'];  ?>" alt="<?= htmlspecialchars($ingredient['name']) ?>" class="ingredient-image">
                        <?php else: ?>
                            <div class="ingredient-image-placeholder bg-secondary text-white d-flex align-items-center justify-content-center">
                                <i class="fas fa-carrot"></i>
                            </div>
                        <?php endif; ?>
                        <h5 class="text-center"><strong><?= htmlspecialchars($ingredient['name']) ?></strong></h5>
                        <p class="text-center mb-1"><?= htmlspecialchars($ingredient['quantity']) ?> <?= htmlspecialchars($ingredient['units']) ?></p>
                        <p class="text-center mb-1 <?= $expiry_class ?>"><small><?= $expiry_text ?></small></p>
                        <p class="text-center text-muted"><small><?= $expiry_date->format('M d, Y') ?></small></p>
                       <button class="remove-btn mt-2" onclick="removeIngredient(event, <?= $ingredient['UserIngredientsID'] ?>, '<?= htmlspecialchars($ingredient['name']) ?>')">
    <i class="fas fa-trash-alt me-1"></i> 
</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <button class="floating-button" data-bs-toggle="modal" data-bs-target="#ingredientModal">
        <i class="fas fa-plus"></i>
    </button>

    <div class="modal fade" id="ingredientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">Add Ingredients</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="ingredient-form" method="POST" action="fridge.php">
                    <div class="modal-body">
                        <div id="ingredient-fields">
                            <div class="ingredient-form-group">
                                <div class="mb-3">
                                    <label for="ingredient-1" class="form-label">Ingredient Name</label>
                                    <select class="form-select" name="ingredient_id[]" id="ingredient-1" required>
                                        <option value="">Select an ingredient</option>
                                        <?php foreach ($all_ingredients as $ingredient): ?>
                                            <option value="<?= $ingredient['IngredientsID'] ?>"><?= htmlspecialchars($ingredient['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="quantity-1" class="form-label">Quantity</label>
                                        <input type="number" step="0.01" class="form-control" name="quantity[]" id="quantity-1" placeholder="0.00" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="expiry-1" class="form-label">Expiry Date</label>
                                        <input type="date" class="form-control" name="expiry_date[]" id="expiry-1" required>
                                    </div>
                                </div>
                                <button type="button" class="remove-ingredient-btn" onclick="removeIngredientField(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="addIngredientField()">
                            <i class="fas fa-plus-circle me-2"></i>Add Another Ingredient
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-white" name="add_ingredients">
                            <i class="fas fa-save me-1"></i> Save All
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let ingredientCounter = 1;

    function addIngredientField() {
        ingredientCounter++;
        const fieldsContainer = document.getElementById('ingredient-fields');
        const newField = document.createElement('div');
        newField.className = 'ingredient-form-group';
        newField.innerHTML = `
            <div class="mb-3">
                <label for="ingredient-${ingredientCounter}" class="form-label">Ingredient Name</label>
                <select class="form-select" name="ingredient_id[]" id="ingredient-${ingredientCounter}" required>
                    <option value="">Select an ingredient</option>
                    <?php foreach ($all_ingredients as $ingredient): ?>
                        <option value="<?= $ingredient['IngredientsID'] ?>"><?= htmlspecialchars($ingredient['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="quantity-${ingredientCounter}" class="form-label">Quantity</label>
                    <input type="number" step="0.01" class="form-control" name="quantity[]" id="quantity-${ingredientCounter}" placeholder="0.00" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="expiry-${ingredientCounter}" class="form-label">Expiry Date</label>
                    <input type="date" class="form-control" name="expiry_date[]" id="expiry-${ingredientCounter}" required>
                </div>
            </div>
            <button type="button" class="remove-ingredient-btn" onclick="removeIngredientField(this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        fieldsContainer.appendChild(newField);
        
        const today = new Date();
        today.setDate(today.getDate() + 7);
        const nextWeek = today.toISOString().split('T')[0];
        document.getElementById(`expiry-${ingredientCounter}`).value = nextWeek;
        
        newField.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function removeIngredientField(button) {
        const fieldGroup = button.closest('.ingredient-form-group');
        if (document.querySelectorAll('.ingredient-form-group').length > 1) {
            fieldGroup.remove();
        } else {
            fieldGroup.querySelector('select').value = '';
            fieldGroup.querySelector('input[type="number"]').value = '';
            
            const today = new Date();
            today.setDate(today.getDate() + 7);
            const nextWeek = today.toISOString().split('T')[0];
            fieldGroup.querySelector('input[type="date"]').value = nextWeek;
        }
    }
    
    // Function to handle ingredient removal - Moved to global scope
    function removeIngredient(event, ingredientId, ingredientName) {
        event.preventDefault();
        
        // Create confirmation modal
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'confirmRemoveModal';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title">Confirm Removal</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to remove <strong>${ingredientName}</strong> from your fridge?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmRemoveBtn">Remove</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Show the modal
        const confirmModal = new bootstrap.Modal(modal);
        confirmModal.show();
        
        // Handle confirmation
        document.getElementById('confirmRemoveBtn').addEventListener('click', function() {
            // Show loading state
            const btn = this;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Removing...';
            btn.disabled = true;
            
            // Perform the removal
            fetch(`fridge.php?remove_ingredient=${ingredientId}`, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success notification
                    showNotification(data.message, 'success');
                    
                    // Remove the ingredient item from the UI
                    const ingredientItem = event.target.closest('.ingredient-item');
                    if (ingredientItem) {
                        ingredientItem.style.opacity = '0';
                        setTimeout(() => {
                            ingredientItem.remove();
                            
                            // If no ingredients left, show empty state
                            if (document.querySelectorAll('.ingredient-item').length === 0) {
                                document.getElementById('ingredients-list').innerHTML = `
                                    <div class="col-12 text-center py-5">
                                        <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
                                        <h4 class="text-muted">Your fridge is empty</h4>
                                        <p class="text-muted">Add some ingredients to get started!</p>
                                    </div>
                                `;
                            }
                        }, 300);
                    }
                } else {
                    showNotification(data.message || 'Failed to remove ingredient', 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred while removing the ingredient', 'error');
            })
            .finally(() => {
                confirmModal.hide();
                setTimeout(() => modal.remove(), 300);
            });
        });
        
        // Clean up when modal is closed
        modal.addEventListener('hidden.bs.modal', function() {
            setTimeout(() => modal.remove(), 300);
        });
    }

    // Notification system
    function showNotification(message, type = 'success') {
        // Remove any existing notifications first
        document.querySelectorAll('.custom-notification').forEach(el => el.remove());
        
        const notification = document.createElement('div');
        notification.className = `custom-notification alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.maxWidth = '350px';
        notification.style.transition = 'all 0.3s ease';
        
        // Add appropriate icon based on type
        let icon;
        switch(type) {
            case 'success':
                icon = 'fa-check-circle';
                break;
            case 'error':
                icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                break;
            default:
                icon = 'fa-info-circle';
        }
        
        notification.innerHTML = `
            <i class="fas ${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Trigger the show animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Auto-remove after 5 seconds
        const autoRemove = setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Close button handler
        notification.querySelector('.btn-close').addEventListener('click', () => {
            clearTimeout(autoRemove);
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date();
        today.setDate(today.getDate() + 7);
        const nextWeek = today.toISOString().split('T')[0];
        document.getElementById('expiry-1').value = nextWeek;
        
        // Form validation for duplicates
        document.getElementById('ingredient-form').addEventListener('submit', function(e) {
            const ingredientSelects = document.querySelectorAll('select[name="ingredient_id[]"]');
            const expiryInputs = document.querySelectorAll('input[name="expiry_date[]"]');
            
            // Create a map to track ingredient-date combinations
            const ingredientDateMap = new Map();
            let hasDuplicates = false;
            
            ingredientSelects.forEach((select, index) => {
                if (select.value && expiryInputs[index].value) {
                    const key = `${select.value}-${expiryInputs[index].value}`;
                    
                    if (ingredientDateMap.has(key)) {
                        hasDuplicates = true;
                        // Highlight the duplicate fields
                        select.classList.add('is-invalid');
                        expiryInputs[index].classList.add('is-invalid');
                        
                        // Find the first occurrence and highlight it too
                        const firstIndex = Array.from(ingredientSelects).findIndex(
                            (s, i) => `${s.value}-${expiryInputs[i].value}` === key
                        );
                        if (firstIndex !== index) {
                            ingredientSelects[firstIndex].classList.add('is-invalid');
                            expiryInputs[firstIndex].classList.add('is-invalid');
                        }
                    } else {
                        ingredientDateMap.set(key, true);
                    }
                }
            });
            
            if (hasDuplicates) {
                e.preventDefault();
                showNotification('You cannot add the same ingredient with the same expiry date multiple times', 'error');
                
                // Scroll to the first error
                document.querySelector('.is-invalid').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    });
</script>
</body>
</html>