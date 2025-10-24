<?php
require_once '../includes/session_handler.php';
require_once '../includes/auth_functions.php';

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Check if user is authenticated
    $auth = new AuthManager();
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $current_user = $auth->getCurrentUser();
    if (!$current_user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Check favorite status
        $restaurant_id = intval($_GET['restaurant_id'] ?? 0);
        
        if (!$restaurant_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Restaurant ID required']);
            exit;
        }

        $is_favorite = $auth->isFavorite($current_user['id'], $restaurant_id);
        
        echo json_encode([
            'success' => true,
            'is_favorite' => $is_favorite
        ]);
        
    } elseif ($method === 'POST') {
        // Toggle favorite
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['restaurant_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Restaurant ID required']);
            exit;
        }

        $restaurant_id = intval($input['restaurant_id']);
        $action = $input['action'] ?? 'toggle';

        if ($action === 'toggle') {
            $is_favorite = $auth->isFavorite($current_user['id'], $restaurant_id);
            
            if ($is_favorite) {
                $result = $auth->removeFromFavorites($current_user['id'], $restaurant_id);
                if ($result['success']) {
                    echo json_encode([
                        'success' => true,
                        'is_favorite' => false,
                        'message' => 'Removed from favorites'
                    ]);
                } else {
                    echo json_encode($result);
                }
            } else {
                $result = $auth->addToFavorites($current_user['id'], $restaurant_id);
                if ($result['success']) {
                    echo json_encode([
                        'success' => true,
                        'is_favorite' => true,
                        'message' => 'Added to favorites'
                    ]);
                } else {
                    echo json_encode($result);
                }
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?>