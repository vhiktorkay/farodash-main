<?php
require_once '../includes/session_handler.php';
require_once '../includes/auth_functions.php';

header('Content-Type: application/json');

try {
    $auth = new AuthManager();
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $current_user = $auth->getCurrentUser();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $cart_items = $auth->getUserCart($current_user['id']);
        echo json_encode(['success' => true, 'cart' => $cart_items]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        switch ($action) {
            case 'add':
                $result = $auth->addToCart(
                    $current_user['id'],
                    $input['restaurant_id'] ?? 0,
                    $input['food_item_id'] ?? 0,
                    $input['quantity'] ?? 1,
                    $input['unit_price'] ?? 0.0,
                    $input['addons'] ?? [],
                    $input['special_instructions'] ?? ''
                );
                echo json_encode($result);
                break;

            case 'update_quantity':
                $result = $auth->updateCartItem(
                    $current_user['id'],
                    $input['cart_item_id'] ?? 0,
                    $input['quantity'] ?? 1
                );
                echo json_encode($result);
                break;

            case 'remove_item':
                $result = $auth->updateCartItem(
                    $current_user['id'],
                    $input['cart_item_id'] ?? 0,
                    0 // quantity 0 removes item
                );
                echo json_encode($result);
                break;

            case 'clear':
                $result = $auth->clearUserCart($current_user['id']);
                echo json_encode($result);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>