<?php
require_once '../includes/session_handler.php';
require_once '../includes/auth_functions.php';

header('Content-Type: application/json');

$current_user = getCurrentUserOrRedirect();
$input = json_decode(file_get_contents('php://input'), true);

$coupon_code = strtoupper(trim($input['coupon_code'] ?? ''));

// Placeholder coupon validation (implement with admin dashboard)
$valid_coupons = [
    'WELCOME10' => ['type' => 'percentage', 'value' => 10, 'description' => '10% off'],
    'SAVE500' => ['type' => 'fixed', 'value' => 500, 'description' => '₦500 off'],
];

if (isset($valid_coupons[$coupon_code])) {
    $coupon = $valid_coupons[$coupon_code];
    echo json_encode([
        'success' => true,
        'discount_amount' => $coupon['value'],
        'discount_type' => $coupon['type'],
        'discount_text' => $coupon['description']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid coupon code'
    ]);
}
?>