<?php
// Disable HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../includes/session_handler.php';
require_once '../includes/auth_functions.php';
require_once '../includes/api_handler.php';  // Include the APIHandler

// Set JSON header first
header('Content-Type: application/json');

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/payment_errors.log');

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Server error occurred',
            'debug' => $error['message']
        ]);
    }
});

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    $reference = $input['reference'] ?? '';
    $order_data = $input['order_data'] ?? [];

    if (empty($reference)) {
        echo json_encode(['success' => false, 'message' => 'Payment reference is required']);
        exit;
    }

    if (empty($order_data)) {
        echo json_encode(['success' => false, 'message' => 'Order data is required']);
        exit;
    }

    // Log the order data for debugging
    error_log("Processing payment verification for reference: " . $reference);
    error_log("Order data: " . json_encode($order_data));

    // Verify payment with Paystack
    $paystack_secret = 'sk_test_0a109a4e4b470dc12e0ce7cc4c32135ba9281d04';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $paystack_secret,
        "Cache-Control: no-cache"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Paystack verification CURL error: " . $error);
        echo json_encode(['success' => false, 'message' => 'Payment verification failed: ' . $error]);
        exit;
    }
    
    curl_close($ch);

    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Paystack returned invalid JSON: " . substr($response, 0, 500));
        echo json_encode(['success' => false, 'message' => 'Invalid response from payment gateway']);
        exit;
    }

    error_log("Paystack verification response: " . json_encode($result));

    // Check if payment was successful
    if (!isset($result['status']) || !$result['status']) {
        error_log("Paystack verification failed: " . ($result['message'] ?? 'Unknown error'));
        echo json_encode([
            'success' => false, 
            'message' => 'Payment verification failed: ' . ($result['message'] ?? 'Unknown error')
        ]);
        exit;
    }

    if ($result['data']['status'] !== 'success') {
        error_log("Payment status not successful: " . $result['data']['status']);
        echo json_encode([
            'success' => false, 
            'message' => 'Payment was not successful: ' . $result['data']['status']
        ]);
        exit;
    }

    // Verify the amount matches (security check)
    $paid_amount = $result['data']['amount'] / 100; // Paystack returns in kobo
    $expected_amount = $order_data['amount'] ?? 0;
    
    if (abs($paid_amount - $expected_amount) > 0.01) { // Allow 1 kobo difference for rounding
        error_log("Amount mismatch - Paid: {$paid_amount}, Expected: {$expected_amount}");
        echo json_encode([
            'success' => false,
            'message' => 'Payment amount mismatch. Please contact support.',
            'reference' => $reference
        ]);
        exit;
    }

    error_log("Payment verified successfully, creating order...");

    // Payment verified successfully - now create the order using APIHandler
    try {
        // Initialize APIHandler
        $api = new APIHandler();
        
        // Validate order data before sending
        $required_fields = ['customer_name', 'customer_phone', 'delivery_address', 'restaurant_id', 'items', 'amount'];
        foreach ($required_fields as $field) {
            if (!isset($order_data[$field]) || empty($order_data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // Ensure items is an array
        if (!is_array($order_data['items']) || count($order_data['items']) === 0) {
            throw new Exception("Order must contain at least one item");
        }

        // Add payment reference to order data
        $order_data['payment_reference'] = $reference;
        $order_data['payment_method'] = 'paystack';
        $order_data['payment_status'] = 'paid';

        error_log("Sending order to API: " . json_encode($order_data));

        // Create order using APIHandler (which has retry logic)
        $order_result = $api->createOrder($order_data);
        
        error_log("Order creation result: " . json_encode($order_result));

        if ($order_result['success']) {
            // Try to clear cart if user is logged in
            if (isset($_SESSION['user_id']) && isset($order_data['restaurant_id'])) {
                try {
                    $auth = new AuthManager();
                    $auth->clearUserCart($_SESSION['user_id'], $order_data['restaurant_id']);
                    error_log("Cart cleared for user: " . $_SESSION['user_id']);
                } catch (Exception $e) {
                    // Cart clearing failed, but order was created, so continue
                    error_log("Cart clear failed: " . $e->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'order_number' => $order_result['data']['order_number'] ?? 'N/A',
                'order_id' => $order_result['data']['order_id'] ?? 0,
                'order_details' => $order_result['data'] ?? [],
                'payment_reference' => $reference,
                'message' => 'Payment verified and order created successfully'
            ]);
        } else {
            // Order creation failed
            error_log("Order creation failed: " . ($order_result['message'] ?? 'Unknown error'));
            echo json_encode([
                'success' => false,
                'message' => 'Payment verified but order creation failed: ' . ($order_result['message'] ?? 'Unknown error'),
                'payment_reference' => $reference,
                'retry_possible' => true // Let frontend know they can retry
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Order creation exception: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Payment verified but order failed: ' . $e->getMessage(),
            'payment_reference' => $reference,
            'retry_possible' => true
        ]);
    }
    
} catch (Exception $e) {
    error_log("Payment verification exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Payment processing error: ' . $e->getMessage()
    ]);
}
?>