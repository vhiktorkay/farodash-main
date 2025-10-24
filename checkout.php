<?php
require_once 'includes/session_handler.php';
require_once 'includes/auth_functions.php';
require_once 'api/api_handler.php';
require_once 'includes/security.php';
require_once 'includes/database.php';

// Handle address from session
$selected_address_data = null;

// Check PHP session first
if (isset($_SESSION['selected_address'])) {
    $selected_address_data = json_decode($_SESSION['selected_address'], true);
} 
// Check if address data was posted from JavaScript
elseif (isset($_POST['auto_address_data'])) {
    $selected_address_data = json_decode($_POST['auto_address_data'], true);
}
// Also check if it's in GET parameters (for URL passing)
elseif (isset($_GET['address_data'])) {
    $selected_address_data = json_decode(urldecode($_GET['address_data']), true);
}

$current_user = getCurrentUserOrRedirect('auth/login.php');
$auth = new AuthManager();
$api = new APIHandler();
$security = new SecurityManager();

// Get user's cart
try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("
        SELECT 
            uc.*,
            r.name as restaurant_name,
            COALESCE(uc.item_name, fi.name, CONCAT('Item #', uc.food_item_id)) as item_name,
            COALESCE(fi.description, '') as item_description,
            COALESCE(fi.image_url, '') as item_image
        FROM user_cart uc
        LEFT JOIN restaurants r ON uc.restaurant_id = r.id  
        LEFT JOIN food_items fi ON uc.food_item_id = fi.id
        WHERE uc.user_id = ?
        ORDER BY uc.created_at DESC
    ");
    $stmt->execute([$current_user['id']]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback to auth manager method
    $cart_items = $auth->getUserCart($current_user['id']);
}

$user_addresses = $auth->getUserAddresses($current_user['id']);

// FIXED: Move address validation AFTER fetching user addresses
$has_address = !empty($user_addresses) || $selected_address_data;
$has_valid_address = !empty($user_addresses) || $selected_address_data;

if (empty($cart_items)) {
    header('Location: index.php');
    exit;
}

$success_message = '';
$error_message = '';
$order_created = false;
$order_details = null;

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Verify CSRF token
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Security token mismatch. Please try again.';
    } else {
        try {
            // Handle different address types
            $delivery_address = null;
            $delivery_address_value = $_POST['delivery_address'] ?? '';

            if ($delivery_address_value === 'auto_detected') {
                // Handle auto-detected address
                $auto_address_data = $_POST['auto_address_data'] ?? '';
                if ($auto_address_data) {
                    $address_data = json_decode($auto_address_data, true);
                    if ($address_data && isset($address_data['address'])) {
                        // Create a temporary address array for processing
                        $delivery_address = [
                            'address_line_1' => $address_data['address'],
                            'address_line_2' => '',
                            'city' => $address_data['city'] ?? 'Auto-detected',
                            'state' => $address_data['state'] ?? 'Auto-detected'
                        ];
                    }
                }
            } else {
                // Handle saved addresses
                $delivery_address_id = intval($delivery_address_value);
                foreach ($user_addresses as $addr) {
                    if ($addr['id'] == $delivery_address_id) {
                        $delivery_address = $addr;
                        break;
                    }
                }
            }

            if (!$delivery_address) {
                throw new Exception('Please select a valid delivery address');
            }

            // Continue with rest of order processing...
            $payment_method = SecurityManager::sanitizeInput($_POST['payment_method'] ?? '', 'string');
            $delivery_instructions = SecurityManager::sanitizeInput($_POST['delivery_instructions'] ?? '', 'string');
            $notes = SecurityManager::sanitizeInput($_POST['notes'] ?? '', 'string');

            // Group cart items by restaurant
            $restaurants = [];
            foreach ($cart_items as $item) {
                $restaurants[$item['restaurant_id']][] = $item;
            }

            if (count($restaurants) > 1) {
                throw new Exception('Cannot order from multiple restaurants at once');
            }

            $restaurant_id = array_keys($restaurants)[0];
            $restaurant_items = $restaurants[$restaurant_id];

            // Prepare order data for API
            $order_items = [];
            $total_amount = 0;

            foreach ($restaurant_items as $item) {
                $addons = json_decode($item['addons'] ?? '[]', true) ?: [];
                $addon_price = 0;
                
                foreach ($addons as $addon) {
                    $addon_price += floatval($addon['price'] ?? 0);
                }

                $item_total = ($item['unit_price'] + $addon_price) * $item['quantity'];
                $total_amount += $item_total;

                $order_items[] = [
                    'food_item_id' => $item['food_item_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['unit_price'],
                    'special_instructions' => $item['special_instructions'] ?? '',
                    'addons' => $addons
                ];
            }

            // Check minimum order amount (₦1,500)
            if ($total_amount < 1500) {
                throw new Exception('Minimum order amount is ₦1,500.00');
            }

            // Prepare full address string
            $full_address = $delivery_address['address_line_1'];
            if ($delivery_address['address_line_2']) {
                $full_address .= ', ' . $delivery_address['address_line_2'];
            }
            $full_address .= ', ' . $delivery_address['city'] . ', ' . $delivery_address['state'];

            // Prepare order data
            $order_data = [
                'customer' => [
                    'name' => $current_user['first_name'] . ' ' . $current_user['last_name'],
                    'phone' => $current_user['phone'],
                    'email' => $current_user['email'],
                    'address' => $full_address
                ],
                'restaurant_id' => $restaurant_id,
                'items' => $order_items,
                'delivery_address' => $full_address,
                'delivery_instructions' => $delivery_instructions,
                'payment_method' => $payment_method,
                'notes' => $notes,
                'discount_amount' => 0.00
            ];

            // Create order via API
            $result = $api->createOrder($order_data);

            if ($result['success']) {
                // Clear cart
                $auth->clearUserCart($current_user['id'], $restaurant_id);
                
                // Add success notification
                $auth->addNotification(
                    $current_user['id'],
                    'Order Placed Successfully!',
                    'Your order #' . $result['data']['order_number'] . ' has been placed and is being prepared.',
                    'order_update',
                    ['order_id' => $result['data']['order_id']]
                );

                $order_created = true;
                $order_details = $result['data'];
                $success_message = 'Order placed successfully! Order Number: #' . $result['data']['order_number'];
            } else {
                throw new Exception($result['message']);
            }

        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Calculate cart totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $addons = json_decode($item['addons'] ?? '[]', true) ?: [];
    $addon_price = array_sum(array_column($addons, 'price'));
    $subtotal += ($item['unit_price'] + $addon_price) * $item['quantity'];
}

$delivery_fee = 500.00; // Standard delivery fee
$tax_rate = 0.075; // 7.5% VAT
$tax_amount = $subtotal * $tax_rate;
$total = $subtotal + $delivery_fee + $tax_amount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FaroDash</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <link rel="stylesheet" href="assets/css/cart.css">
    <style>
         /* CSS for checkout.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .checkout-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e9ecef;
        }
        .checkout-header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        .back-btn {
            color: #ED1B26;
            text-decoration: none;
            font-weight: 500;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .checkout-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        .checkout-main {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .checkout-section {
            margin-bottom: 32px;
        }
        .checkout-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .address-options, .payment-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .address-option, .payment-option {
            display: block;
        }
        .address-option input, .payment-option input {
            display: none;
        }
        .address-card, .payment-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .address-option input:checked + .address-card,
        .payment-option input:checked + .payment-card {
            border-color: #ED1B26;
            background-color: #fff5f5;
        }
        .address-label, .payment-label {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .address-text, .payment-desc {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Outfit', sans-serif;
            resize: vertical;
            min-height: 80px;
        }
        .checkout-sidebar {
            position: sticky;
            top: 20px;
        }
        .order-summary {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .order-summary h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e9ecef;
        }
        .cart-items {
            margin-bottom: 16px;
        }
        .cart-item {
            padding-bottom: 12px;
            margin-bottom: 12px;
            border-bottom: 1px solid #f1f1f1;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .item-info {
            display: flex;
            justify-content: space-between;
        }
        .item-name {
            font-weight: 500;
        }
        .item-price {
            font-weight: 500;
        }
        .item-addons {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .item-addons span {
            display: block;
        }
        .order-totals .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .order-totals .total-line.final {
            font-size: 18px;
            font-weight: 600;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e9ecef;
        }
        .place-order-btn {
            width: 100%;
            background-color: #ED1B26;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 16px;
        }
        .place-order-btn:hover {
            background-color: #d41420;
        }
        .place-order-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .minimum-order-warning {
            text-align: center;
            padding: 12px;
            background-color: #fff3cd;
            color: #856404;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 16px;
        }
        .order-success {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            max-width: 600px;
            margin: 40px auto;
        }
        .order-success .success-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .order-success h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .order-success .order-number {
            font-size: 16px;
            color: #666;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 16px;
        }
        .order-success p {
            margin-bottom: 24px;
        }
        .success-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background-color: #ED1B26;
            color: white;
        }
        .btn-secondary {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #e9ecef;
        }
        @media (max-width: 768px) {
            .checkout-content {
                grid-template-columns: 1fr;
            }
            .checkout-sidebar {
                position: static;
                margin-top: 24px;
            }
        }

        /* Order Success Popup */
        .order-success-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .order-success-popup.active {
            display: flex;
        }

        .order-success-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
        }

        .order-success-modal {
            position: relative;
            background: white;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: successPopupSlide 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes successPopupSlide {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .success-header {
            padding: 20px 20px 0 20px;
            display: flex;
            justify-content: flex-end;
        }

        .close-success-btn {
            background: none;
            border: none;
            font-size: 28px;
            color: #999;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .close-success-btn:hover {
            background: #f8f9fa;
            color: #666;
        }

        .success-content {
            padding: 0 40px 40px 40px;
            text-align: center;
        }

        .success-icon {
            margin: 0 auto 24px;
            display: flex;
            justify-content: center;
        }

        .success-title {
            font-size: 28px;
            font-weight: 700;
            color: #000;
            margin-bottom: 12px;
        }

        .success-order-number {
            font-size: 20px;
            font-weight: 600;
            color: #ED1B26;
            background: #fff5f5;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 2px solid #ffe6e6;
        }

        .success-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 24px;
            font-size: 16px;
        }

        .success-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .success-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .success-detail-row:last-child {
            border-bottom: none;
            font-weight: 600;
            color: #000;
        }

        .success-detail-label {
            color: #666;
            font-size: 14px;
        }

        .success-detail-value {
            font-weight: 500;
            color: #000;
        }

        .success-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .success-actions .btn {
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .success-actions .btn-primary {
            background: #ED1B26;
            color: white;
            flex: 1;
            max-width: 200px;
        }

        .success-actions .btn-primary:hover {
            background: #d41420;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(237, 27, 38, 0.3);
        }

        .success-actions .btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e9ecef;
        }

        .success-actions .btn-secondary:hover {
            background: #e9ecef;
            color: #333;
        }

        .success-footer {
            color: #999;
            font-size: 14px;
            padding-top: 16px;
            border-top: 1px solid #f1f3f4;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .order-success-modal {
                margin: 20px;
                width: calc(100% - 40px);
            }
            
            .success-content {
                padding: 0 24px 32px 24px;
            }
            
            .success-title {
                font-size: 24px;
            }
            
            .success-actions {
                flex-direction: column;
            }
            
            .success-actions .btn {
                width: 100%;
                max-width: none;
            }
        }

        /* Order Processing Animation */
        .order-processing {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .processing-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
        }

        .processing-content {
            position: relative;
            background: white;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
            max-width: 300px;
            width: 90%;
        }

        .processing-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ED1B26;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        .processing-content h3 {
            margin-bottom: 8px;
            color: #000;
        }

        .processing-content p {
            color: #666;
            font-size: 14px;
        }

        /* Auto Address Card */
        .auto-address-card {
            border: 2px solid #ED1B26 !important;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
        }

        .address-type-badge {
            background: #ED1B26;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 8px;
        }

        .address-divider-checkout {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .address-divider-checkout::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
            z-index: 1;
        }

        .address-divider-checkout span {
            background: white;
            padding: 0 16px;
            color: #666;
            font-size: 14px;
            position: relative;
            z-index: 2;
        }

        .add-address-checkout-btn {
            width: 100%;
            background: transparent;
            color: #ED1B26;
            border: 2px dashed #ED1B26;
            border-radius: 8px;
            padding: 16px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 16px;
            transition: all 0.2s ease;
        }

        .add-address-checkout-btn:hover {
            background: #fff5f5;
        }

        .no-address-section {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #e9ecef;
        }

        /* Address Detector Modal */
        .address-detector-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .address-detector-modal.active {
            display: flex;
        }

        .address-detector-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
        }

        .address-detector-content {
            position: relative;
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: addressDetectorSlide 0.3s ease;
        }

        @keyframes addressDetectorSlide {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .address-detector-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
        }

        .address-detector-header h3 {
            font-size: 20px;
            font-weight: 600;
            color: #000;
        }

        .address-detector-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s ease;
        }

        .address-detector-close:hover {
            background: #f8f9fa;
        }

        .address-detector-body {
            padding: 24px;
        }

        .current-location-option-checkout {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 20px;
        }

        .current-location-option-checkout:hover {
            border-color: #ED1B26;
            background: #fff5f5;
        }

        .current-location-title-checkout {
            font-weight: 600;
            color: #000;
            margin-bottom: 2px;
        }

        .current-location-subtitle-checkout {
            font-size: 14px;
            color: #666;
        }

        .address-search-container-checkout {
            position: relative;
        }

        .address-search-input-checkout {
            width: 100%;
            padding: 16px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Outfit', sans-serif;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .address-search-input-checkout:focus {
            border-color: #ED1B26;
        }

        .address-suggestions-checkout {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e9ecef;
            border-top: none;
            border-radius: 0 0 12px 12px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .address-suggestion-item-checkout {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }

        .address-suggestion-item-checkout:hover,
        .address-suggestion-item-checkout.selected {
            background: #f8f9fa;
        }

        .address-suggestion-item-checkout:last-child {
            border-bottom: none;
        }

        .suggestion-text-checkout {
            flex: 1;
        }

        .suggestion-main-checkout {
            font-weight: 500;
            color: #000;
            margin-bottom: 2px;
        }

        .suggestion-secondary-checkout {
            font-size: 14px;
            color: #666;
        }

        .location-loading-checkout {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ED1B26;
            font-weight: 500;
        }

        .item-notes {
            font-size: 11px;
            color: #666;
            font-style: italic;
            margin-top: 4px;
        }
        </style>
</head>
<body>
    <div class="container">
        <div class="checkout-header">
            <a href="javascript:history.back()" class="back-btn">← Back</a>
            <h1>Checkout</h1>
        </div>

        <?php if ($success_message && $order_created): ?>
            <!-- Order Success -->
            <div class="order-success">
                <div class="success-icon">✅</div>
                <h2>Order Placed Successfully!</h2>
                <div class="order-number">Order #<?php echo htmlspecialchars($order_details['order_number']); ?></div>
                <p>Estimated delivery time: <?php echo date('g:i A', strtotime($order_details['estimated_delivery_time'])); ?></p>
                <div class="success-actions">
                    <a href="user/orders.php" class="btn btn-primary">Track Order</a>
                    <a href="index.php" class="btn btn-secondary">Continue Shopping</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Checkout Form -->
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="checkout-content">
                <div class="checkout-main">
                    <form method="POST" class="checkout-form">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityManager::generateCSRFToken(); ?>">

                        <!-- Delivery Address -->
                        <div class="checkout-section">
                            <h3>Delivery Address</h3>
                            
                            <?php if ($selected_address_data): ?>
                                <!-- Auto-detected address option -->
                                <div class="auto-address-section">
                                    <label class="address-option">
                                        <input type="radio" name="delivery_address" value="auto_detected" checked required>
                                        <input type="hidden" name="auto_address_data" value='<?php echo htmlspecialchars(json_encode($selected_address_data)); ?>'>
                                        <div class="address-card auto-address-card">
                                            <div class="address-label">
                                                📍 Detected Location
                                                <span class="address-type-badge">Current</span>
                                            </div>
                                            <div class="address-text"><?php echo htmlspecialchars($selected_address_data['address']); ?></div>
                                        </div>
                                    </label>
                                </div>
                                
                                <?php if (!empty($user_addresses)): ?>
                                    <div class="address-divider-checkout">
                                        <span>or choose from saved addresses</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (empty($user_addresses) && !$selected_address_data): ?>
                                <div class="no-address-section">
                                    <p>You need to add a delivery address first.</p>
                                    <button type="button" class="btn btn-primary" onclick="openAddressDetector()">Add Address</button>
                                </div>
                            <?php else: ?>
                                <div class="address-options">
                                    <?php foreach ($user_addresses as $address): ?>
                                        <label class="address-option">
                                            <input type="radio" name="delivery_address" value="<?php echo $address['id']; ?>" 
                                                <?php echo ($address['is_default'] && !$selected_address_data) ? 'checked' : ''; ?> required>
                                            <div class="address-card">
                                                <div class="address-label"><?php echo htmlspecialchars($address['label']); ?></div>
                                                <div class="address-text">
                                                    <?php echo htmlspecialchars($address['address_line_1']); ?>
                                                    <?php if ($address['address_line_2']): ?>, <?php echo htmlspecialchars($address['address_line_2']); ?><?php endif; ?>
                                                    <br><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- ALWAYS show the add address button -->
                                <button type="button" class="add-address-checkout-btn" onclick="openAddressDetector()">
                                    + Add New Address
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Payment Method -->
                        <div class="checkout-section payment-section" style="display: none;">
                            <h3>Payment Method</h3>
                            <div class="payment-options">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="paystack" checked required>
                                    <div class="payment-card">
                                        <div class="payment-label">Pay with Card</div>
                                        <div class="payment-desc">Secure payment via Paystack</div>
                                    </div>
                                </label>
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="cash" required>
                                    <div class="payment-card">
                                        <div class="payment-label">Cash on Delivery</div>
                                        <div class="payment-desc">Pay when your order arrives</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Order Instructions -->
                        <div class="checkout-section instructions-section" style="display: none;">
                            <h3>Delivery Instructions (Optional)</h3>
                            <textarea name="delivery_instructions" class="form-textarea" 
                                    placeholder="e.g., Ring the doorbell, Leave at the gate..."></textarea>
                        </div>
                    </form>
                </div>

                <!-- Order Summary -->
                <div class="checkout-sidebar">
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        
                        <div class="cart-items">
                            <?php foreach ($cart_items as $item): ?>
                                <?php 
                                $addons = json_decode($item['addons'] ?? '[]', true) ?: [];
                                $addon_price = array_sum(array_column($addons, 'price'));
                                $item_total = ($item['unit_price'] + $addon_price) * $item['quantity'];
                                ?>
                                <div class="cart-item">
                                    <div class="item-info">
                                        <div class="item-name">
                                            <?php 
                                            // Fix HTML entity decoding
                                            $item_name = $item['item_name'] ?? 'Food Item';
                                            // Decode HTML entities first, then safely encode for display
                                            $item_name = html_entity_decode($item_name, ENT_QUOTES, 'UTF-8');
                                            echo htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8'); 
                                            ?> 
                                            (x<?php echo $item['quantity']; ?>)
                                        </div>
                                        <div class="item-price">₦<?php echo number_format($item_total, 2); ?></div>
                                    </div>
                                    <?php if (!empty($addons)): ?>
                                        <div class="item-addons">
                                            <?php foreach ($addons as $addon): ?>
                                                <span>+ <?php 
                                                    $addon_name = html_entity_decode($addon['name'] ?? '', ENT_QUOTES, 'UTF-8');
                                                    echo htmlspecialchars($addon_name, ENT_QUOTES, 'UTF-8'); 
                                                ?> (₦<?php echo number_format($addon['price'], 2); ?>)</span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['special_instructions'])): ?>
                                        <div class="item-notes">
                                            <small>Note: <?php 
                                                $instructions = html_entity_decode($item['special_instructions'], ENT_QUOTES, 'UTF-8');
                                                echo htmlspecialchars($instructions, ENT_QUOTES, 'UTF-8'); 
                                            ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-totals">
                            <div class="total-line">
                                <span>Subtotal:</span>
                                <span>₦<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="total-line">
                                <span>Delivery Fee:</span>
                                <span>₦<?php echo number_format($delivery_fee, 2); ?></span>
                            </div>
                            <div class="total-line">
                                <span>VAT (7.5%):</span>
                                <span>₦<?php echo number_format($tax_amount, 2); ?></span>
                            </div>
                            <div class="total-line final">
                                <span>Total:</span>
                                <span>₦<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>

                        <?php if ($has_valid_address && $total >= 1500): ?>
                            <button type="submit" name="place_order" form="checkout-form" class="place-order-btn">
                                Place Order - ₦<?php echo number_format($total, 2); ?>
                            </button>
                        <?php elseif (!$has_valid_address): ?>
                            <div class="minimum-order-warning">
                                Please add a delivery address to continue
                            </div>
                        <?php elseif ($total < 1500): ?>
                            <div class="minimum-order-warning">
                                Minimum order amount is ₦1,500.00<br>
                                Add ₦<?php echo number_format(1500 - $subtotal, 2); ?> more to continue
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="order-success-popup" id="orderSuccessPopup">
                    <div class="order-success-overlay" onclick="closeSuccessPopup()"></div>
                    <div class="order-success-modal">
                        <div class="success-header">
                            <button class="close-success-btn" onclick="closeSuccessPopup()">&times;</button>
                        </div>
                        
                        <div class="success-content">
                            <div class="success-icon">
                                <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="40" cy="40" r="40" fill="#28a745"/>
                                    <path d="M25 40L35 50L55 30" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            
                            <h2 class="success-title">Order Placed Successfully!</h2>
                            <div class="success-order-number" id="successOrderNumber"></div>
                            <p class="success-message">
                                Thank you for your order! We're preparing your food and will notify you when it's on the way.
                            </p>
                            
                            <div class="success-details" id="successDetails">
                                <!-- Order details will be populated here -->
                            </div>
                            
                            <div class="success-actions">
                                <button class="btn btn-primary" onclick="trackOrder()">
                                    Track Your Order
                                </button>
                                <button class="btn btn-secondary" onclick="continueShopping()">
                                    Continue Shopping
                                </button>
                            </div>
                            
                            <div class="success-footer">
                                <p>You'll receive email about your order status</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

        <!-- Address Detector Modal -->
    <div class="address-detector-modal" id="addressDetectorModal">
        <div class="address-detector-overlay" onclick="closeAddressDetector()"></div>
        <div class="address-detector-content">
            <div class="address-detector-header">
                <h3>Add Delivery Address</h3>
                <button class="address-detector-close" onclick="closeAddressDetector()">×</button>
            </div>
            
            <div class="address-detector-body">
                <div class="current-location-option-checkout" onclick="useCurrentLocationCheckout()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="3" stroke="#ED1B26" stroke-width="2"/>
                        <circle cx="12" cy="12" r="10" stroke="#ED1B26" stroke-width="2"/>
                        <path d="M12 2v4M12 18v4M2 12h4M18 12h4" stroke="#ED1B26" stroke-width="2"/>
                    </svg>
                    <div>
                        <div class="current-location-title-checkout">Use Current Location</div>
                        <div class="current-location-subtitle-checkout">Detect your precise location</div>
                    </div>
                </div>
                
                <div class="address-divider-checkout">
                    <span>or</span>
                </div>
                
                <div class="address-search-container-checkout">
                    <input type="text" 
                        id="addressSearchInputCheckout" 
                        class="address-search-input-checkout" 
                        placeholder="Search for your address..."
                        autocomplete="off">
                    <div class="address-suggestions-checkout" id="addressSuggestionsCheckout"></div>
                </div>
            </div>
        </div>
    </div>

    <script>

        // Paystack integration
        function initializePayment(orderData) {
            const handler = PaystackPop.setup({
                key: 'sk_test_0a109a4e4b470dc12e0ce7cc4c32135ba9281d04', // Replace with your Paystack public key
                email: orderData.customer_email,
                amount: orderData.amount * 100, // Paystack expects amount in kobo
                currency: 'NGN',
                ref: orderData.reference,
                metadata: {
                    order_data: JSON.stringify(orderData)
                },
                callback: function(response) {
                    // Payment successful
                    verifyPayment(response.reference, orderData);
                },
                onClose: function() {
                    showNotification('Payment cancelled', 'error');
                }
            });
            
            handler.openIframe();
        }

        async function verifyPayment(reference, orderData) {
            try {
                const response = await fetch('api/verify-payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        reference: reference,
                        order_data: orderData
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirect to success page
                    window.location.href = `order-success.php?order=${result.order_number}`;
                } else {
                    showNotification('Payment verification failed', 'error');
                }
            } catch (error) {
                showNotification('Payment verification error', 'error');
            }
        }

        async function applyCoupon() {
            const couponCode = document.querySelector('input[name="coupon_code"]').value.trim();
            if (!couponCode) return;
            
            try {
                const response = await fetch('api/validate-coupon.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ coupon_code: couponCode })
                });
                
                const result = await response.json();
                const feedback = document.getElementById('couponFeedback');
                
                if (result.success) {
                    feedback.innerHTML = `<div class="coupon-success">Coupon applied! ${result.discount_text}</div>`;
                    updateTotalWithDiscount(result.discount_amount);
                } else {
                    feedback.innerHTML = `<div class="coupon-error">${result.message}</div>`;
                }
            } catch (error) {
                document.getElementById('couponFeedback').innerHTML = '<div class="coupon-error">Error applying coupon</div>';
            }
        }


        // notification function
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existing = document.querySelectorAll('.custom-notification');
            existing.forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = 'custom-notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 16px 24px;
                background: ${type === 'error' ? '#f8d7da' : type === 'success' ? '#d4edda' : '#d1ecf1'};
                color: ${type === 'error' ? '#721c24' : type === 'success' ? '#155724' : '#0c5460'};
                border: 1px solid ${type === 'error' ? '#f5c6cb' : type === 'success' ? '#c3e6cb' : '#bee5eb'};
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10001;
                max-width: 400px;
                animation: slideIn 0.3s ease-out;
            `;
            notification.textContent = message;
            
            // Add slide-in animation
            if (!document.getElementById('notificationStyle')) {
                const style = document.createElement('style');
                style.id = 'notificationStyle';
                style.textContent = '@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }';
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Missing coupon discount function
        function updateTotalWithDiscount(discountAmount) {
            // Update the order total display with discount
            const subtotalElement = document.querySelector('.order-totals .total-line:first-child span:last-child');
            const totalElement = document.querySelector('.order-totals .total-line.final span:last-child');
            const placeOrderBtn = document.querySelector('.place-order-btn');
            
            if (subtotalElement && totalElement) {
                // Get current values
                const currentSubtotal = parseFloat(subtotalElement.textContent.replace('₦', '').replace(',', ''));
                const deliveryFee = 500.00;
                const taxRate = 0.075;
                
                // Calculate new totals
                const newSubtotal = currentSubtotal - discountAmount;
                const newTax = newSubtotal * taxRate;
                const newTotal = newSubtotal + deliveryFee + newTax;
                
                // Update display
                subtotalElement.textContent = `₦${newSubtotal.toLocaleString()}`;
                totalElement.textContent = `₦${newTotal.toLocaleString()}`;
                
                if (placeOrderBtn) {
                    placeOrderBtn.textContent = `Place Order - ₦${newTotal.toLocaleString()}`;
                }
                
                // Add discount line if not exists
                if (!document.querySelector('.discount-line')) {
                    const discountHTML = `
                        <div class="total-line discount-line">
                            <span>Discount:</span>
                            <span style="color: #28a745;">-₦${discountAmount.toLocaleString()}</span>
                        </div>
                    `;
                    totalElement.closest('.total-line').insertAdjacentHTML('beforebegin', discountHTML);
                }
            }
        }

        function validateForm() {
            const requiredFields = ['delivery_address'];
            for (const field of requiredFields) {
                const element = document.querySelector(`[name="${field}"]:checked`) || document.querySelector(`[name="${field}"]`);
                if (!element || !element.value) {
                    showNotification(`Please select a ${field.replace('_', ' ')}`, 'error');
                    return false;
                }
            }
            return true;
        }

        function initializePaystackPayment(orderData, paymentReference) {
            console.log('Initializing Paystack payment...');
            console.log('Reference:', paymentReference);
            console.log('Amount:', orderData.amount);
            console.log('Email:', orderData.customer_email);
            
            const handler = PaystackPop.setup({
                key: 'pk_test_0b0e7ff1612229afac29b0245092e225677c4027', 
                email: orderData.customer_email,  // FIXED: Changed from orderData.customer.email
                amount: Math.round(orderData.amount * 100), // Convert to kobo
                currency: 'NGN',
                ref: paymentReference,  // FIXED: Use passed reference
                metadata: {
                    customer_name: orderData.customer_name,  // FIXED
                    customer_phone: orderData.customer_phone,  // FIXED
                    order_items: JSON.stringify(orderData.items),
                    delivery_address: orderData.delivery_address,
                    restaurant_id: orderData.restaurant_id
                },
                callback: function(response) {
                    console.log('Payment successful!', response);
                    verifyPaymentAndCreateOrder(response.reference, orderData);
                },
                onClose: function() {
                    console.log('Payment popup closed');
                    hideOrderProcessing();
                    showNotification('Payment cancelled', 'error');
                }
            });
            
            handler.openIframe();
        }

        async function verifyPaymentAndCreateOrder(reference, orderData) {
            try {
                showOrderProcessing();
                
                console.log('=== PAYMENT VERIFICATION STARTED ===');
                console.log('Reference:', reference);
                console.log('Order data:', orderData);
                
                // FIXED: Correct API endpoint path
                const response = await fetch('api/verify-payment.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        reference: reference,
                        order_data: orderData
                    })
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error('Server returned ' + response.status + ': ' + response.statusText);
                }
                
                const responseText = await response.text();
                console.log('Raw response (first 1000 chars):', responseText.substring(0, 1000));
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Full response:', responseText);
                    hideOrderProcessing();
                    
                    // Show detailed error for debugging
                    alert(
                        'Server Error: Invalid response format\n\n' +
                        'Payment Reference: ' + reference + '\n\n' +
                        'Please screenshot this and contact support.\n\n' +
                        'Technical details: ' + responseText.substring(0, 200)
                    );
                    throw new Error('Invalid JSON response from server');
                }
                
                console.log('Parsed result:', result);
                console.log('=== VERIFICATION COMPLETE ===');
                
                hideOrderProcessing();
                
                if (result.success) {
                    // ✅ SUCCESS - Order created!
                    console.log('✅ Order created successfully!');
                    console.log('Order Number:', result.order_number);
                    console.log('Order ID:', result.order_id);
                    
                    showOrderSuccessPopup(result);
                    
                    // Clear local storage
                    sessionStorage.removeItem('cart');
                    sessionStorage.removeItem('selected_address');
                    localStorage.removeItem('cart_items');
                    
                } else {
                    // ❌ FAILED - Handle error
                    console.error('❌ Order creation failed:', result.message);
                    
                    // Check if retry is possible
                    if (result.retry_possible) {
                        const retry = confirm(
                            '⚠️ Payment Successful but Order Creation Failed\n\n' +
                            'Error: ' + (result.message || 'Unknown error') + '\n\n' +
                            'Your payment reference: ' + reference + '\n\n' +
                            'Would you like to retry creating the order?'
                        );
                        
                        if (retry) {
                            console.log('User chose to retry...');
                            showOrderProcessing();
                            // Wait 2 seconds before retry
                            await new Promise(resolve => setTimeout(resolve, 2000));
                            return verifyPaymentAndCreateOrder(reference, orderData);
                        } else {
                            // User declined retry
                            alert(
                                '⚠️ IMPORTANT: Your payment was SUCCESSFUL!\n\n' +
                                'Payment Reference: ' + reference + '\n\n' +
                                '📸 Please screenshot this message!\n\n' +
                                'Contact support immediately to complete your order.\n' +
                                'Do not make another payment.'
                            );
                        }
                    } else {
                        // Error is not retryable
                        showNotification(result.message || 'Order processing failed', 'error');
                        
                        // If payment was successful, show the reference
                        if (result.payment_reference) {
                            alert(
                                '⚠️ IMPORTANT: Your payment was SUCCESSFUL!\n\n' +
                                'Payment Reference: ' + result.payment_reference + '\n\n' +
                                '📸 Please screenshot this message!\n\n' +
                                'Contact support immediately to complete your order.\n' +
                                'Do not make another payment.\n\n' +
                                'Error: ' + (result.message || 'Unknown error')
                            );
                        }
                    }
                }
            } catch (error) {
                hideOrderProcessing();
                console.error('❌ Order processing error:', error);
                console.error('Error stack:', error.stack);
                
                // Show user-friendly error with technical details
                const errorMsg = 
                    'Error processing your order:\n\n' +
                    error.message + '\n\n' +
                    '⚠️ If payment was deducted:\n' +
                    '1. Screenshot this message\n' +
                    '2. Note your transaction time\n' +
                    '3. Contact support immediately\n\n' +
                    'Do NOT make another payment until you confirm with support.';
                
                alert(errorMsg);
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // Cash on delivery order submission
        async function submitCashOrder(formData) {
            try {
                showOrderProcessing();

                const selectedAddressRadio = document.querySelector('input[name="delivery_address"]:checked');
                if (!selectedAddressRadio) {
                    hideOrderProcessing();
                    showNotification('Please select a delivery address', 'error');
                    return;
                }

                let deliveryAddress = '';
                let addressData = null;

                if (selectedAddressRadio.value === 'auto_detected') {
                    const autoAddressInput = selectedAddressRadio.parentNode.querySelector('input[name="auto_address_data"]');
                    if (autoAddressInput && autoAddressInput.value) {
                        addressData = JSON.parse(autoAddressInput.value);
                        deliveryAddress = addressData.address;
                    } else {
                        const savedAddress = sessionStorage.getItem('selected_address');
                        if (savedAddress) {
                            addressData = JSON.parse(savedAddress);
                            deliveryAddress = addressData.address;
                        }
                    }
                } else {
                    const addressCard = selectedAddressRadio.parentNode.querySelector('.address-text');
                    if (addressCard) {
                        deliveryAddress = addressCard.textContent.trim();
                    }
                }

                if (!deliveryAddress) {
                    hideOrderProcessing();
                    showNotification('Could not get delivery address', 'error');
                    return;
                }

                const userPhone = '<?php echo addslashes($current_user['phone']); ?>';
                
                if (!userPhone || userPhone.includes('@') || userPhone.includes('.com')) {
                    hideOrderProcessing();
                    showNotification('Invalid phone number in your profile. Please update your phone number in account settings.', 'error');
                    return;
                }

                const orderData = {
                    customer: {
                        name: '<?php echo addslashes($current_user['first_name'] . ' ' . $current_user['last_name']); ?>',
                        phone: userPhone,
                        email: '<?php echo addslashes($current_user['email']); ?>',
                        address: deliveryAddress
                    },
                    restaurant_id: parseInt('<?php echo $cart_items[0]['restaurant_id'] ?? 0; ?>'),
                    items: <?php echo json_encode(array_map(function($item) {
                        return [
                            'food_item_id' => intval($item['food_item_id']),
                            'quantity' => intval($item['quantity']),
                            'price' => floatval($item['unit_price']),
                            'special_instructions' => $item['special_instructions'] ?? '',
                            'addons' => json_decode($item['addons'] ?? '[]', true) ?: []
                        ];
                    }, $cart_items)); ?>,
                    delivery_address: deliveryAddress,
                    delivery_instructions: formData.get('delivery_instructions') || '',
                    payment_method: 'cash',
                    notes: formData.get('notes') || '',
                    discount_amount: 0.00
                };

                console.log('Submitting order:', orderData);
                
                const response = await fetch('https://dashboard.farodash.com/api/orders.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin', // ADDED: Include cookies/session
                    body: JSON.stringify(orderData)
                });
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                if (!response.ok) {
                    const errorMatch = responseText.match(/<b>(.+?)<\/b>/);
                    const errorMsg = errorMatch ? errorMatch[1] : `HTTP error! status: ${response.status}`;
                    throw new Error(errorMsg);
                }
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error. Response was:', responseText);
                    throw new Error('Server returned invalid response. Please try again or contact support.');
                }
                
                console.log('Order response:', result);
                
                hideOrderProcessing();
                
                if (result.success) {
                    showOrderSuccessPopup(result);
                } else {
                    showNotification(result.message || 'Order processing failed', 'error');
                }
            } catch (error) {
                hideOrderProcessing();
                console.error('Order processing error:', error);
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // Show order success popup
        function showOrderSuccessPopup(orderResult) {
            const popup = document.getElementById('orderSuccessPopup');
            const orderNumber = document.getElementById('successOrderNumber');
            const orderDetails = document.getElementById('successDetails');
            
            // Set order number
            orderNumber.textContent = `Order #${orderResult.order_number}`;
            
            // Set order details
            const details = orderResult.order_details || {};
            const detailsHTML = `
                <div class="success-detail-row">
                    <span class="success-detail-label">Restaurant:</span>
                    <span class="success-detail-value">${details.restaurant_name || 'Restaurant'}</span>
                </div>
                <div class="success-detail-row">
                    <span class="success-detail-label">Estimated Delivery:</span>
                    <span class="success-detail-value">${details.estimated_delivery || '30-45 mins'}</span>
                </div>
                <div class="success-detail-row">
                    <span class="success-detail-label">Payment Method:</span>
                    <span class="success-detail-value">${details.payment_method === 'cash' ? 'Cash on Delivery' : 'Card Payment'}</span>
                </div>
                <div class="success-detail-row">
                    <span class="success-detail-label">Total Amount:</span>
                    <span class="success-detail-value">₦${parseFloat(details.total_amount || 0).toLocaleString()}</span>
                </div>
            `;
            
            orderDetails.innerHTML = detailsHTML;
            
            // Show popup with animation
            popup.classList.add('active');
            
            // Store order number for tracking
            window.currentOrderNumber = orderResult.order_number;
            
            // Auto-close after 30 seconds (optional)
            setTimeout(() => {
                if (popup.classList.contains('active')) {
                    closeSuccessPopup();
                }
            }, 30000);
        }

        // Close success popup
        function closeSuccessPopup() {
            const popup = document.getElementById('orderSuccessPopup');
            popup.classList.remove('active');
        }

        // Track order function
        function trackOrder() {
            if (window.currentOrderNumber) {
                window.location.href = `user/orders.php?track=${window.currentOrderNumber}`;
            } else {
                window.location.href = 'user/orders.php';
            }
        }

        // Continue shopping function
        function continueShopping() {
            closeSuccessPopup();
            window.location.href = 'index.php';
        }

        // Show order processing state
        function showOrderProcessing() {
            let overlay = document.getElementById('orderProcessingOverlay');
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'orderProcessingOverlay';
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 10000;
                `;
                overlay.innerHTML = `
                    <div style="background: white; padding: 30px; border-radius: 12px; text-align: center;">
                        <div class="spinner" style="width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #ED1B26; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                        <p style="margin: 0; font-size: 18px; font-weight: 500;">Processing your order...</p>
                        <p style="margin: 10px 0 0; font-size: 14px; color: #666;">Please wait, do not close this window</p>
                    </div>
                `;
                document.body.appendChild(overlay);
                
                // Add spinner animation
                if (!document.getElementById('spinnerStyle')) {
                    const style = document.createElement('style');
                    style.id = 'spinnerStyle';
                    style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                    document.head.appendChild(style);
                }
            }
            
            overlay.style.display = 'flex';
        }

        function hideOrderProcessing() {
            const overlay = document.getElementById('orderProcessingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        let checkoutAutocompleteService = null;
        let checkoutPlacesService = null;

        // Initialize Google Maps for checkout
        function initGoogleMapsCheckout() {
            if (typeof google !== 'undefined' && google.maps) {
                checkoutAutocompleteService = new google.maps.places.AutocompleteService();
                const map = new google.maps.Map(document.createElement('div'));
                checkoutPlacesService = new google.maps.places.PlacesService(map);
            }
        }

        // Open address detector
        function openAddressDetector() {
            document.getElementById('addressDetectorModal').classList.add('active');
            document.getElementById('addressSearchInputCheckout').focus();
            
            if (!checkoutAutocompleteService) {
                initGoogleMapsCheckout();
            }
        }

        // Close address detector
        function closeAddressDetector() {
            document.getElementById('addressDetectorModal').classList.remove('active');
            clearAddressSuggestionsCheckout();
        }

        // Use current location for checkout
        function useCurrentLocationCheckout() {
            if (navigator.geolocation) {
                const option = document.querySelector('.current-location-option-checkout');
                option.innerHTML = `
                    <div class="location-loading-checkout">
                        <div class="loading-spinner-small"></div>
                        <div>Getting your location...</div>
                    </div>
                `;
                
                navigator.geolocation.getCurrentPosition(
                    async (position) => {
                        const { latitude, longitude } = position.coords;
                        
                        try {
                            const geocoder = new google.maps.Geocoder();
                            const response = await geocoder.geocode({
                                location: { lat: latitude, lng: longitude }
                            });
                            
                            if (response.results[0]) {
                                const address = response.results[0].formatted_address;
                                
                                // Create address data object with proper structure
                                const addressData = {
                                    address: address,
                                    latitude: latitude,
                                    longitude: longitude,
                                    city: 'Auto-detected',
                                    state: 'Auto-detected',
                                    type: 'auto_detected'
                                };
                                
                                // Add to checkout form
                                addAutoDetectedAddressToCheckout(addressData);
                                
                                // Close modal and show success
                                closeAddressDetector();
                                showNotification('Address added successfully!');
                            }
                        } catch (error) {
                            console.error('Geocoding error:', error);
                            showAddressDetectorError('Could not get your address. Please try searching manually.');
                        }
                    },
                    (error) => {
                        console.error('Geolocation error:', error);
                        showAddressDetectorError('Location access denied. Please search for your address manually.');
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 300000
                    }
                );
            } else {
                showAddressDetectorError('Geolocation is not supported by your browser.');
            }
        }

        // Add auto-detected address to checkout
        function addAutoDetectedAddress(address, latitude, longitude) {
            // Create a new address option
            const addressOptions = document.querySelector('.address-options');
            if (!addressOptions) return;
            
            // Remove any existing auto address
            const existingAuto = document.querySelector('.auto-address-card');
            if (existingAuto) {
                existingAuto.closest('.address-option').remove();
            }
            
            const newAddressHTML = `
                <label class="address-option">
                    <input type="radio" name="delivery_address" value="auto_new" checked required>
                    <div class="address-card auto-address-card">
                        <div class="address-label">
                            📍 New Detected Location
                            <span class="address-type-badge">Current</span>
                        </div>
                        <div class="address-text">${address}</div>
                    </div>
                </label>
            `;
            
            addressOptions.insertAdjacentHTML('afterbegin', newAddressHTML);
            
            // Store the address data
            const addressData = {
                address: address,
                latitude: latitude,
                longitude: longitude,
                type: 'auto_detected'
            };
            
            sessionStorage.setItem('auto_detected_address', JSON.stringify(addressData));
        }

        function handleAddressSearchCheckout(e) {
            const query = e.target.value.trim();
            
            if (query.length < 3) {
                clearAddressSuggestionsCheckout();
                return;
            }
            
            if (checkoutAutocompleteService) {
                const request = {
                    input: query,
                    componentRestrictions: { country: 'ng' },
                    types: ['address']
                };
                
                checkoutAutocompleteService.getPlacePredictions(request, (predictions, status) => {
                    if (status === google.maps.places.PlacesServiceStatus.OK && predictions) {
                        showAddressSuggestionsCheckout(predictions);
                    } else {
                        clearAddressSuggestionsCheckout();
                    }
                });
            }
        }

        function handleAddressKeydownCheckout(e) {
            const suggestions = document.querySelectorAll('.address-suggestion-item-checkout');
            const current = document.querySelector('.address-suggestion-item-checkout.selected');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const next = current ? current.nextElementSibling : suggestions[0];
                if (next) {
                    current?.classList.remove('selected');
                    next.classList.add('selected');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = current ? current.previousElementSibling : suggestions[suggestions.length - 1];
                if (prev) {
                    current?.classList.remove('selected');
                    prev.classList.add('selected');
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (current) {
                    current.click();
                }
            }
        }

        function showAddressSuggestionsCheckout(predictions) {
            const container = document.getElementById('addressSuggestionsCheckout');
            
            const html = predictions.slice(0, 5).map(prediction => `
                <div class="address-suggestion-item-checkout" onclick="selectAddressCheckout('${prediction.place_id}', '${prediction.description.replace(/'/g, "\\'")}')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="#666"/>
                    </svg>
                    <div class="suggestion-text-checkout">
                        <div class="suggestion-main-checkout">${prediction.structured_formatting.main_text}</div>
                        <div class="suggestion-secondary-checkout">${prediction.structured_formatting.secondary_text}</div>
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = html;
            container.style.display = 'block';
        }

        function clearAddressSuggestionsCheckout() {
            const container = document.getElementById('addressSuggestionsCheckout');
            container.innerHTML = '';
            container.style.display = 'none';
        }

        function selectAddressCheckout(placeId, description) {
            if (checkoutPlacesService) {
                checkoutPlacesService.getDetails({
                    placeId: placeId,
                    fields: ['geometry', 'formatted_address', 'name', 'address_components']
                }, (place, status) => {
                    if (status === google.maps.places.PlacesServiceStatus.OK && place.geometry) {
                        // Extract city and state from address components
                        let city = 'Auto-detected';
                        let state = 'Auto-detected';
                        
                        if (place.address_components) {
                            const cityComponent = place.address_components.find(c => 
                                c.types.includes('locality') || c.types.includes('administrative_area_level_2')
                            );
                            const stateComponent = place.address_components.find(c => 
                                c.types.includes('administrative_area_level_1')
                            );
                            
                            if (cityComponent) city = cityComponent.long_name;
                            if (stateComponent) state = stateComponent.long_name;
                        }
                        
                        const addressData = {
                            address: place.formatted_address,
                            latitude: place.geometry.location.lat(),
                            longitude: place.geometry.location.lng(),
                            place_id: placeId,
                            city: city,
                            state: state,
                            type: 'searched'
                        };
                        
                        addAutoDetectedAddressToCheckout(addressData);
                        closeAddressDetector();
                        showNotification('Address added successfully!');
                    }
                });
            }
        }

        // Add this new function
        function addAutoDetectedAddressToCheckout(addressData) {
            const addressOptions = document.querySelector('.address-options');
            const noAddressSection = document.querySelector('.no-address-section');
            
            // If no address options container exists, create the entire structure
            if (!addressOptions) {
                const addressSection = document.querySelector('.checkout-section h3');
                if (addressSection && addressSection.textContent.includes('Delivery Address')) {
                    const addressSectionHTML = `
                        <div class="address-options">
                            <label class="address-option">
                                <input type="radio" name="delivery_address" value="auto_detected" checked required>
                                <input type="hidden" name="auto_address_data" value='${JSON.stringify(addressData)}'>
                                <div class="address-card auto-address-card">
                                    <div class="address-label">
                                        Selected Location
                                        <span class="address-type-badge">Current</span>
                                    </div>
                                    <div class="address-text">${addressData.address}</div>
                                </div>
                            </label>
                        </div>
                        <button type="button" class="add-address-checkout-btn" onclick="openAddressDetector()">
                            + Add New Address
                        </button>
                    `;
                    addressSection.insertAdjacentHTML('afterend', addressSectionHTML);
                }
            } else {
                // Remove any existing auto address
                const existingAuto = document.querySelector('.auto-address-card');
                if (existingAuto) {
                    existingAuto.closest('.address-option').remove();
                }
                
                const newAddressHTML = `
                    <label class="address-option">
                        <input type="radio" name="delivery_address" value="auto_detected" checked required>
                        <input type="hidden" name="auto_address_data" value='${JSON.stringify(addressData)}'>
                        <div class="address-card auto-address-card">
                            <div class="address-label">
                                📍 Selected Location
                                <span class="address-type-badge">Current</span>
                            </div>
                            <div class="address-text">${addressData.address}</div>
                        </div>
                    </label>
                `;
                
                addressOptions.insertAdjacentHTML('afterbegin', newAddressHTML);
            }
            
            // Store the address data for form submission
            sessionStorage.setItem('selected_address', JSON.stringify(addressData));
            
            // Hide no address section if present
            if (noAddressSection) {
                noAddressSection.style.display = 'none';
            }
            
            showPaymentSections();
        }

        function showAddressDetectorError(message) {
            // Reset current location option
            const option = document.querySelector('.current-location-option-checkout');
            option.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="3" stroke="#ED1B26" stroke-width="2"/>
                    <circle cx="12" cy="12" r="10" stroke="#ED1B26" stroke-width="2"/>
                    <path d="M12 2v4M12 18v4M2 12h4M18 12h4" stroke="#ED1B26" stroke-width="2"/>
                </svg>
                <div>
                    <div class="current-location-title-checkout">Use Current Location</div>
                    <div class="current-location-subtitle-checkout">Detect your precise location</div>
                </div>
            `;
            
            showNotification(message, 'error');
        }

        function debounceCheckout(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }


        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.checkout-form');
            if (!form) return;

            // Handle auto-detected address from index page
            const autoAddress = sessionStorage.getItem('selected_address');
            if (autoAddress) {
                try {
                    const addressData = JSON.parse(autoAddress);
                    console.log('Found auto address:', addressData);
                    
                    const existingAuto = document.querySelector('input[value="auto_detected"]');
                    
                    if (addressData.address && !existingAuto) {
                        let addressOptions = document.querySelector('.address-options');
                        const noAddressSection = document.querySelector('.no-address-section');
                        
                        // Create address options container if it doesn't exist
                        if (!addressOptions) {
                            // Create the entire address structure
                            const addressSectionHTML = `
                                <div class="address-options">
                                    <label class="address-option">
                                        <input type="radio" name="delivery_address" value="auto_detected" checked required>
                                        <input type="hidden" name="auto_address_data" value='${JSON.stringify(addressData)}'>
                                        <div class="address-card auto-address-card">
                                            <div class="address-label">
                                                📍 Selected Location
                                                <span class="address-type-badge">Current</span>
                                            </div>
                                            <div class="address-text">${addressData.address}</div>
                                        </div>
                                    </label>
                                </div>
                                <button type="button" class="add-address-checkout-btn" onclick="openAddressDetector()">
                                    + Add New Address
                                </button>
                            `;
                            
                            // Insert after the h3 in the address section
                            const addressSection = document.querySelector('.checkout-section h3');
                            if (addressSection && addressSection.textContent.includes('Delivery Address')) {
                                addressSection.insertAdjacentHTML('afterend', addressSectionHTML);
                            }
                        } else {
                            // Add to existing address options
                            const autoAddressHTML = `
                                <label class="address-option">
                                    <input type="radio" name="delivery_address" value="auto_detected" checked required>
                                    <input type="hidden" name="auto_address_data" value='${JSON.stringify(addressData)}'>
                                    <div class="address-card auto-address-card">
                                        <div class="address-label">
                                            📍 Selected Location
                                            <span class="address-type-badge">Current</span>
                                        </div>
                                        <div class="address-text">${addressData.address}</div>
                                    </div>
                                </label>
                            `;
                            addressOptions.insertAdjacentHTML('afterbegin', autoAddressHTML);
                        }
                        
                        // Hide no address section
                        if (noAddressSection) {
                            noAddressSection.style.display = 'none';
                        }
                        
                        // CRITICAL: Show payment sections and button
                        showPaymentSections();
                        
                        console.log('Auto address added successfully');
                    }
                } catch (error) {
                    console.error('Error parsing auto address:', error);
                }
            }

            // Add the form ID for proper submission handling
            form.setAttribute('id', 'checkout-form');

            // Single form submission handler
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Always prevent default first
                
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
                const selectedAddress = document.querySelector('input[name="delivery_address"]:checked');
                
                // Validate form first
                if (!validateForm()) {
                    return;
                }

                // Validate address is selected
                if (!selectedAddress) {
                    showNotification('Please select a delivery address', 'error');
                    return;
                }

                const formData = new FormData(this);

                // Handle auto-detected address data
                if (selectedAddress && selectedAddress.value === 'auto_detected') {
                    const autoAddressInput = selectedAddress.parentNode.querySelector('input[name="auto_address_data"]');
                    if (!autoAddressInput || !autoAddressInput.value) {
                        // Fallback: get from sessionStorage
                        const autoAddressFromStorage = sessionStorage.getItem('selected_address');
                        if (autoAddressFromStorage) {
                            try {
                                const addressData = JSON.parse(autoAddressFromStorage);
                                
                                // Add auto address data as hidden input if not already present
                                if (!this.querySelector('input[name="auto_address_data"]')) {
                                    const hiddenInput = document.createElement('input');
                                    hiddenInput.type = 'hidden';
                                    hiddenInput.name = 'auto_address_data';
                                    hiddenInput.value = JSON.stringify(addressData);
                                    this.appendChild(hiddenInput);
                                }
                            } catch (error) {
                                console.error('Error processing auto address:', error);
                                showNotification('Error processing address. Please select again.', 'error');
                                return;
                            }
                        } else {
                            showNotification('Address data missing. Please select your address again.', 'error');
                            return;
                        }
                    }
                }

                // Handle different payment methods
                if (paymentMethod === 'paystack') {
                    const selectedAddressRadio = document.querySelector('input[name="delivery_address"]:checked');
                    let fullAddressData = '';
                    
                    if (selectedAddressRadio.value === 'auto_detected') {
                        const autoAddressInput = selectedAddressRadio.parentNode.querySelector('input[name="auto_address_data"]');
                        if (autoAddressInput && autoAddressInput.value) {
                            const addressData = JSON.parse(autoAddressInput.value);
                            fullAddressData = addressData.address;
                        }
                    } else {
                        fullAddressData = selectedAddressRadio.parentNode.querySelector('.address-text').textContent.trim();
                    }

                    // CORRECTED ORDER DATA STRUCTURE
                    const orderData = {
                        customer_name: '<?php echo addslashes($current_user['first_name'] . ' ' . $current_user['last_name']); ?>',
                        customer_phone: '<?php echo addslashes($current_user['phone']); ?>',
                        customer_email: '<?php echo addslashes($current_user['email']); ?>',
                        delivery_address: fullAddressData,
                        restaurant_id: parseInt('<?php echo $cart_items[0]['restaurant_id'] ?? 0; ?>'),
                        items: <?php echo json_encode(array_map(function($item) {
                            return [
                                'food_item_id' => intval($item['food_item_id']),
                                'name' => $item['item_name'] ?? 'Item',
                                'quantity' => intval($item['quantity']),
                                'unit_price' => floatval($item['unit_price']),
                                'special_instructions' => $item['special_instructions'] ?? '',
                                'addons' => json_decode($item['addons'] ?? '[]', true) ?: []
                            ];
                        }, $cart_items)); ?>,
                        subtotal: <?php echo $subtotal; ?>,
                        delivery_fee: <?php echo $delivery_fee; ?>,
                        tax_amount: <?php echo $tax_amount; ?>,
                        amount: <?php echo $total; ?>,
                        delivery_instructions: formData.get('delivery_instructions') || '',
                        payment_method: 'paystack',
                        notes: formData.get('notes') || '',
                        discount_amount: 0.00
                    };
                    
                    console.log('Order data:', orderData);
                    console.log('Email being sent:', orderData.customer_email);
                    console.log('Restaurant ID type:', typeof orderData.restaurant_id);
                    
                    // Generate unique payment reference
                    const paymentReference = 'FD-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9).toUpperCase();
                    
                    initializePaystackPayment(orderData, paymentReference);
                } else if (paymentMethod === 'cash') {  // FIXED: Removed extra closing brace
                    submitCashOrder(formData);
                } else {
                    // Default: regular PHP form submission
                    const submitBtn = document.querySelector('.place-order-btn');
                    if (submitBtn) {
                        submitBtn.textContent = 'Placing Order...';
                        submitBtn.disabled = true;
                    }
                    this.submit();
                }
            });

            // Initialize Google Maps if not already loaded
            if (!window.google) {
                const script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyDnU_9F8OtuZFqavbaZ9-kxd9gRmQ00_c4&libraries=places&callback=initGoogleMapsCheckout';
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            } else {
                initGoogleMapsCheckout();
            }

            // Handle address search input
            const addressInput = document.getElementById('addressSearchInputCheckout');
            if (addressInput) {
                addressInput.addEventListener('input', debounceCheckout(handleAddressSearchCheckout, 300));
                addressInput.addEventListener('keydown', handleAddressKeydownCheckout);
            }

            // Handle form validation on address selection change
            const addressInputs = document.querySelectorAll('input[name="delivery_address"]');
            addressInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const submitBtn = document.querySelector('.place-order-btn');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = `Place Order - ₦<?php echo number_format($total, 2); ?>`;
                    }
                });
            });

            // Handle payment method changes
            const paymentInputs = document.querySelectorAll('input[name="payment_method"]');
            paymentInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const submitBtn = document.querySelector('.place-order-btn');
                    if (submitBtn) {
                        if (this.value === 'paystack') {
                            submitBtn.textContent = `Pay with Card - ₦<?php echo number_format($total, 2); ?>`;
                        } else {
                            submitBtn.textContent = `Place Order - ₦<?php echo number_format($total, 2); ?>`;
                        }
                    }
                });
            });

            // Initialize order success popup handling
            const orderSuccessPopup = document.getElementById('orderSuccessPopup');
            if (orderSuccessPopup) {
                // Handle clicks outside popup to close
                orderSuccessPopup.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeSuccessPopup();
                    }
                });
            }

            // Handle address detector modal
            const addressDetectorModal = document.getElementById('addressDetectorModal');
            if (addressDetectorModal) {
                addressDetectorModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeAddressDetector();
                    }
                });
            }
        });

        function showPaymentSections() {
            // Show payment sections
            const paymentSection = document.querySelector('.payment-section');
            const instructionsSection = document.querySelector('.instructions-section');
            const notesSection = document.querySelector('.notes-section');
            
            if (paymentSection) paymentSection.style.display = 'block';
            if (instructionsSection) instructionsSection.style.display = 'block';
            if (notesSection) notesSection.style.display = 'block';
            
            showPlaceOrderButton();
        }

        // NEW FUNCTION: Show the place order button
        function showPlaceOrderButton() {
            const orderSummary = document.querySelector('.order-summary');
            const existingWarning = document.querySelector('.minimum-order-warning');
            const existingButton = document.querySelector('.place-order-btn');
            
            // Remove any existing warning
            if (existingWarning) {
                existingWarning.remove();
            }
            
            // Add place order button if it doesn't exist
            if (!existingButton && orderSummary) {
                const total = '<?php echo number_format($total, 2); ?>';
                const buttonHTML = `
                    <button type="submit" name="place_order" form="checkout-form" class="place-order-btn">
                        Place Order - ₦${total}
                    </button>
                `;
                orderSummary.insertAdjacentHTML('beforeend', buttonHTML);
            }
        }
    </script>
</body>
</html>