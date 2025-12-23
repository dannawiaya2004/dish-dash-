<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'messages' => ['You must be logged in to complete this action']]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'messages' => ['Invalid request method']]);
    exit();
}

if (!isset($_POST['recipe_id'])) {
    echo json_encode(['success' => false, 'messages' => ['Recipe ID is required']]);
    exit();
}

$user_id = $_SESSION['user_id'];
$recipe_id = intval($_POST['recipe_id']);

try {
    // Get all ingredients needed for this recipe
    $ingredients_query = $conn->prepare("
        SELECT u.ingredient_id, u.quantity, i.name, i.units 
        FROM used u
        JOIN ingredients i ON u.ingredient_id = i.IngredientsID
        WHERE u.recipe_id = ?
    ");
    $ingredients_query->bind_param("i", $recipe_id);
    $ingredients_query->execute();
    $ingredients_result = $ingredients_query->get_result();

    if (!$ingredients_result) {
        throw new Exception("Failed to get recipe ingredients");
    }

    $success = true;
    $messages = [];

    // Process each ingredient
    while ($ingredient = $ingredients_result->fetch_assoc()) {
        $ingredient_id = $ingredient['ingredient_id'];
        $quantity_needed = floatval($ingredient['quantity']);
        $ingredient_name = $ingredient['name'];
        $ingredient_units = $ingredient['units'];
        
        // Deduct from user's fridge
        $remaining_to_deduct = $quantity_needed;
        $had_ingredient = false;
        
        $user_ingredient_query = $conn->prepare("
            SELECT UserIngredientsID, quantity 
            FROM user_ingredients 
            WHERE user_id = ? AND ingredient_id = ?
            ORDER BY expiry_date ASC
        ");
        $user_ingredient_query->bind_param("ii", $user_id, $ingredient_id);
        $user_ingredient_query->execute();
        $user_ingredient_result = $user_ingredient_query->get_result();
        
        while ($user_ingredient = $user_ingredient_result->fetch_assoc()) {
            if ($remaining_to_deduct <= 0) break;
            
            $had_ingredient = true;
            $user_quantity = floatval($user_ingredient['quantity']);
            $deduct_amount = min($user_quantity, $remaining_to_deduct);
            
            // Update quantity
            $new_quantity = $user_quantity - $deduct_amount;
            $update_query = $conn->prepare("
                UPDATE user_ingredients 
                SET quantity = ? 
                WHERE UserIngredientsID = ?
            ");
            $update_query->bind_param("di", $new_quantity, $user_ingredient['UserIngredientsID']);
            if (!$update_query->execute()) {
                throw new Exception("Failed to update ingredient: {$ingredient_name}");
            }
            
            // Remove if quantity is 0 or less
            if ($new_quantity <= 0) {
                $delete_query = $conn->prepare("
                    DELETE FROM user_ingredients 
                    WHERE UserIngredientsID = ?
                ");
                $delete_query->bind_param("i", $user_ingredient['UserIngredientsID']);
                $delete_query->execute();
            }
            
            $remaining_to_deduct -= $deduct_amount;
        }
        
        if (!$had_ingredient) {
            $messages[] = "You didn't have any {$ingredient_name} (needed {$quantity_needed} {$ingredient_units})";
            $success = false;
        } elseif ($remaining_to_deduct > 0) {
            $messages[] = "You didn't have enough {$ingredient_name} (needed {$quantity_needed} {$ingredient_units}, was short by {$remaining_to_deduct})";
            $success = false;
        }
    }

    echo json_encode([
        'success' => $success,
        'messages' => $messages
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'messages' => [$e->getMessage()]
    ]);
}

exit();
?>