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
        $notifications = $auth->getUserNotifications($current_user['id'], 10);
        echo json_encode([
            'success' => true,
            'notifications' => $notifications
        ]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'mark_read') {
            $notification_id = intval($input['notification_id'] ?? 0);
            $result = $auth->markNotificationAsRead($current_user['id'], $notification_id);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>