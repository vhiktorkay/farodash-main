<?php
require_once 'includes/session_handler.php';
require_once 'includes/auth_functions.php';
require_once 'api/api_handler.php';
require_once 'includes/security.php';

$current_user = getCurrentUserOrRedirect('auth/login.php');
$auth = new AuthManager();
$api = new APIHandler();
$security = new SecurityManager();

// Get user's cart
$cart_items = $auth->getUserCart($current_user['id']);
$user_addresses = $auth->getUserAddresses($current_user['id']);

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
            // Validate and sanitize input
            $delivery_address_id = intval($_POST['delivery_address'] ?? 0);
            $payment_method = SecurityManager::sanitizeInput($_POST['payment_method'] ?? '', 'string');
            $delivery_instructions = SecurityManager::sanitizeInput($_POST['delivery_instructions'] ?? '', 'string');
            $notes = SecurityManager::sanitizeInput($_POST['notes'] ?? '', 'string');

            // Get delivery address
            $delivery_address = null;
            foreach ($user_addresses as $addr) {
                if ($addr['id'] == $delivery_address_id) {
                    $delivery_address = $addr;
                    break;
                }
            }

            if (!$delivery_address) {
                throw new Exception('Please select a valid delivery address');
            }

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
                            <?php if (empty($user_addresses)): ?>
                                <p>You need to add a delivery address first.</p>
                                <a href="user/addresses.php" class="btn btn-primary">Add Address</a>
                            <?php else: ?>
                                <div class="address-options">
                                    <?php foreach ($user_addresses as $address): ?>
                                        <label class="address-option">
                                            <input type="radio" name="delivery_address" value="<?php echo $address['id']; ?>" 
                                                   <?php echo $address['is_default'] ? 'checked' : ''; ?> required>
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
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($user_addresses)): ?>
                            <!-- Payment Method -->
                            <div class="checkout-section">
                                <h3>Payment Method</h3>
                                <div class="payment-options">
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="cash" checked required>
                                        <div class="payment-card">
                                            <div class="payment-label">Cash on Delivery</div>
                                            <div class="payment-desc">Pay when your order arrives</div>
                                        </div>
                                    </label>
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="card" required>
                                        <div class="payment-card">
                                            <div class="payment-label">Card Payment</div>
                                            <div class="payment-desc">Pay with debit/credit card</div>
                                        </div>
                                    </label>
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="transfer" required>
                                        <div class="payment-card">
                                            <div class="payment-label">Bank Transfer</div>
                                            <div class="payment-desc">Pay via bank transfer</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Order Instructions -->
                            <div class="checkout-section">
                                <h3>Delivery Instructions (Optional)</h3>
                                <textarea name="delivery_instructions" class="form-textarea" 
                                          placeholder="e.g., Ring the doorbell, Leave at the gate..."></textarea>
                            </div>

                            <!-- Special Notes -->
                            <div class="checkout-section">
                                <h3>Special Notes (Optional)</h3>
                                <textarea name="notes" class="form-textarea" 
                                          placeholder="Any special requests for your order..."></textarea>
                            </div>
                        <?php endif; ?>
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
                                        <div class="item-name">Food Item (x<?php echo $item['quantity']; ?>)</div>
                                        <div class="item-price">₦<?php echo number_format($item_total, 2); ?></div>
                                    </div>
                                    <?php if (!empty($addons)): ?>
                                        <div class="item-addons">
                                            <?php foreach ($addons as $addon): ?>
                                                <span>+ <?php echo htmlspecialchars($addon['name']); ?></span>
                                            <?php endforeach; ?>
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

                        <?php if (!empty($user_addresses) && $total >= 1500): ?>
                            <button type="submit" name="place_order" form="checkout-form" class="place-order-btn">
                                Place Order - ₦<?php echo number_format($total, 2); ?>
                            </button>
                        <?php elseif ($total < 1500): ?>
                            <div class="minimum-order-warning">
                                Minimum order amount is ₦1,500.00<br>
                                Add ₦<?php echo number_format(1500 - $subtotal, 2); ?> more to continue
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add form ID for external submit button
        document.querySelector('.checkout-form').id = 'checkout-form';
        
        // Show loading state when placing order
        document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
            const submitBtn = document.querySelector('.place-order-btn');
            if (submitBtn) {
                submitBtn.textContent = 'Placing Order...';
                submitBtn.disabled = true;
            }
        });
    </script>
</body>
</html>