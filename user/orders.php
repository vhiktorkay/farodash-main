<?php
require_once '../includes/session_handler.php';
require_once '../includes/auth_functions.php';

$current_user = getCurrentUserOrRedirect('../auth/login.php');
$auth = new AuthManager();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get orders
$orders_result = $auth->getUserOrders($current_user['id'], $limit, $offset);
$orders = $orders_result['success'] ? $orders_result['data'] : [];

// Handle order tracking
$tracking_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_order'])) {
    $order_number = trim($_POST['order_number']);
    if ($order_number) {
        $tracking_result = $auth->trackOrder($order_number, $current_user['phone']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - FaroDash</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Outfit', sans-serif;
        background-color: #f8f9fa;
        color: #000000;
        min-height: 100vh;
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }

    .page-header {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .page-title {
        font-size: 28px;
        font-weight: 600;
        color: #000;
        margin-bottom: 8px;
    }

    .page-subtitle {
        color: #666;
        font-size: 16px;
    }

    .message {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .success-message {
        background-color: #d1edff;
        color: #0c63e4;
        border: 1px solid #b8daff;
    }

    .error-message {
        background-color: #fee;
        color: #d63384;
        border: 1px solid #f5c2c7;
    }

    .addresses-grid {
        display: grid;
        gap: 20px;
        margin-bottom: 24px;
    }

    .address-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        position: relative;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .address-card.default {
        border-color: #ED1B26;
    }

    .address-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .address-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .address-label {
        font-size: 18px;
        font-weight: 600;
        color: #000;
    }

    .address-type {
        background-color: #f8f9fa;
        color: #666;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        text-transform: capitalize;
    }

    .default-badge {
        background-color: #ED1B26;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .address-content {
        color: #666;
        line-height: 1.5;
        margin-bottom: 16px;
    }

    .address-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background-color: #ED1B26;
        color: white;
    }

    .btn-primary:hover {
        background-color: #d41420;
    }

    .btn-secondary {
        background-color: #f8f9fa;
        color: #666;
        border: 1px solid #e9ecef;
    }

    .btn-secondary:hover {
        background-color: #e9ecef;
    }

    .btn-danger {
        background-color: #dc3545;
        color: white;
    }

    .btn-danger:hover {
        background-color: #c82333;
    }

    .add-address-btn {
        background-color: #ED1B26;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 16px 24px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
        margin-bottom: 24px;
        transition: background-color 0.3s ease;
    }

    .add-address-btn:hover {
        background-color: #d41420;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
    }

    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e9ecef;
    }

    .modal-title {
        font-size: 20px;
        font-weight: 600;
        color: #000;
    }

    .close-btn {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
        padding: 4px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: #000;
        margin-bottom: 8px;
    }

    .form-input, .form-select {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        font-size: 16px;
        font-family: 'Outfit', sans-serif;
        outline: none;
        transition: border-color 0.3s ease;
    }

    .form-input:focus, .form-select:focus {
        border-color: #ED1B26;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
    }

    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #ED1B26;
    }

    .no-addresses {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .no-addresses-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        opacity: 0.5;
    }

    /* ORDER-SPECIFIC STYLES */
    .order-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .order-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .order-info {
        flex: 1;
    }

    .order-number {
        font-size: 18px;
        font-weight: 600;
        color: #000;
        margin-bottom: 4px;
    }

    .order-restaurant {
        color: #666;
        font-size: 14px;
    }

    .order-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        text-transform: capitalize;
    }

    .status-pending { 
        background-color: #fff3cd; 
        color: #856404; 
    }

    .status-confirmed { 
        background-color: #cff4fc; 
        color: #055160; 
    }

    .status-preparing { 
        background-color: #ffeaa7; 
        color: #b7791f; 
    }

    .status-ready { 
        background-color: #d4edda; 
        color: #155724; 
    }

    .status-picked_up { 
        background-color: #d4edda; 
        color: #155724; 
    }

    .status-delivered { 
        background-color: #d1ecf1; 
        color: #0c5460; 
    }

    .status-cancelled { 
        background-color: #f8d7da; 
        color: #721c24; 
    }

    .order-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .detail-item {
        text-align: center;
    }

    .detail-label {
        font-size: 12px;
        color: #666;
        margin-bottom: 4px;
    }

    .detail-value {
        font-size: 16px;
        font-weight: 600;
        color: #000;
    }

    .track-form {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 24px;
    }

    .track-form h3 {
        margin-bottom: 16px;
        color: #000;
    }

    .track-input-group {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
    }

    .track-input {
        flex: 1;
        padding: 12px 16px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        font-size: 16px;
        outline: none;
    }

    .track-btn {
        background-color: #ED1B26;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 24px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .track-btn:hover {
        background-color: #d41420;
    }

    .tracking-result {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-top: 16px;
        border-left: 4px solid #ED1B26;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        margin-top: 32px;
    }

    .pagination a, .pagination span {
        padding: 8px 16px;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        text-decoration: none;
        color: #666;
        transition: all 0.3s ease;
    }

    .pagination a:hover {
        background-color: #ED1B26;
        color: white;
        border-color: #ED1B26;
    }

    .pagination .current {
        background-color: #ED1B26;
        color: white;
        border-color: #ED1B26;
    }

    /* MOBILE RESPONSIVE */
    @media (max-width: 768px) {
        .container {
            padding: 16px;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .address-actions {
            justify-content: center;
        }

        .modal-content {
            margin: 20px;
            padding: 20px;
        }

        .order-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .order-details {
            grid-template-columns: 1fr 1fr;
        }

        .track-input-group {
            flex-direction: column;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <p class="page-subtitle">Track and manage your food orders</p>
        </div>

        <!-- Order Tracking Form -->
        <div class="track-form">
            <h3>Track an Order</h3>
            <form method="POST">
                <div class="track-input-group">
                    <input type="text" name="order_number" class="track-input" 
                           placeholder="Enter order number (e.g., FD20241201001)" required>
                    <button type="submit" name="track_order" class="track-btn">Track Order</button>
                </div>
            </form>

            <?php if ($tracking_result): ?>
                <div class="tracking-result">
                    <?php if ($tracking_result['success']): ?>
                        <h4 style="color: #ED1B26; margin-bottom: 12px;">Order Found!</h4>
                        <?php $order = $tracking_result['data']; ?>
                        <p><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                        <p><strong>Status:</strong> <span class="order-status status-<?php echo $order['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></p>
                        <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                        <p><strong>Total Amount:</strong> ₦<?php echo number_format($order['final_amount'], 2); ?></p>
                        <?php if (!empty($order['estimated_delivery_time'])): ?>
                            <p><strong>Estimated Delivery:</strong> <?php echo date('M j, Y g:i A', strtotime($order['estimated_delivery_time'])); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #dc3545;">❌ <?php echo htmlspecialchars($tracking_result['message']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="no-addresses">
                <svg class="no-addresses-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7 4V2C7 1.45 7.45 1 8 1H16C16.55 1 17 1.45 17 2V4H20C20.55 4 21 4.45 21 5S20.55 6 20 6H19V19C19 20.1 18.1 21 17 21H7C5.9 21 5 20.1 5 19V6H4C3.45 6 3 5.55 3 5S3.45 4 4 4H7ZM9 3V4H15V3H9ZM7 6V19H17V6H7Z" fill="#ccc"/>
                    <path d="M9 8V17H11V8H9ZM13 8V17H15V8H13Z" fill="#ccc"/>
                </svg>
                <h3>No Orders Yet</h3>
                <p>Your order history will appear here once you place your first order</p>
                <a href="../index.php" class="btn btn-primary" style="margin-top: 16px;">Browse Restaurants</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                            <div class="order-restaurant"><?php echo htmlspecialchars($order['restaurant_name']); ?></div>
                        </div>
                        <span class="order-status status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </div>

                    <div class="order-details">
                        <div class="detail-item">
                            <div class="detail-label">Order Date</div>
                            <div class="detail-value"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Amount</div>
                            <div class="detail-value">₦<?php echo number_format($order['final_amount'], 2); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Items</div>
                            <div class="detail-value"><?php echo $order['item_count'] ?? 1; ?> item(s)</div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($order['order_number']); ?>">
                            <button type="submit" name="track_order" class="btn btn-primary">Track Order</button>
                        </form>
                        
                        <?php if ($order['status'] === 'delivered'): ?>
                            <button class="btn btn-secondary" onclick="alert('Reorder functionality will be implemented in Phase 4')">Reorder</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if (count($orders) >= $limit): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <span class="current">Page <?php echo $page; ?></span>
                    
                    <?php if (count($orders) >= $limit): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Back Button -->
        <a href="../account.php" class="btn btn-secondary" style="display: inline-block; margin-top: 20px;">← Back to Account</a>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a track parameter
            const urlParams = new URLSearchParams(window.location.search);
            const trackOrder = urlParams.get('track');
            
            if (trackOrder) {
                // Auto-fill and submit tracking form
                const trackInput = document.querySelector('input[name="order_number"]');
                const trackForm = trackInput?.closest('form');
                
                if (trackInput && trackForm) {
                    trackInput.value = trackOrder;
                    trackForm.submit();
                }
            }
        });
</script>
</body>
</html>