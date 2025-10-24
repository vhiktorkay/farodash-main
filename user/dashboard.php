<?php
require_once '../includes/session_handler.php';
require_once '../includes/auth_functions.php';

$current_user = getCurrentUserOrRedirect('../auth/login.php');
$auth = new AuthManager();

// Get user analytics
$analytics = $auth->getUserAnalytics($current_user['id']);
$recent_orders = $auth->getUserOrders($current_user['id'], 5, 0);
$notifications = $auth->getUserNotifications($current_user['id'], 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FaroDash</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* SIMILAR BASE STYLES AS OTHER USER PAGES */
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
            max-width: 1200px;
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

        .welcome-section {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .user-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ED1B26, #d41420);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 600;
            background-size: cover;
            background-position: center;
        }

        .welcome-text h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .welcome-text p {
            color: #666;
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: #ED1B26;
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #ED1B26;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e9ecef;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #000;
        }

        .view-all-link {
            color: #ED1B26;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .order-list, .notification-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .order-item, .notification-item {
            padding: 16px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .order-item:hover, .notification-item:hover {
            background-color: #f8f9fa;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .order-number {
            font-weight: 600;
            font-size: 16px;
        }

        .order-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .notification-title {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .notification-message {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-section {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="welcome-section">
                <div class="user-avatar-large" style="<?php if ($current_user['profile_image']): ?>background-image: url('../<?php echo htmlspecialchars($current_user['profile_image']); ?>');<?php endif; ?>">
                    <?php if (!$current_user['profile_image']): ?>
                        <?php echo strtoupper(substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>!</h1>
                    <p><?php if (!empty($analytics['member_since'])): ?>Member since <?php echo $analytics['member_since']; ?><?php endif; ?></p>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $analytics['total_orders'] ?? 0; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">₦<?php echo number_format($analytics['total_spent'] ?? 0, 0); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $analytics['favorite_restaurants'] ?? 0; ?></div>
                <div class="stat-label">Favorite Restaurants</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $analytics['saved_addresses'] ?? 0; ?></div>
                <div class="stat-label">Saved Addresses</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Orders -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">Recent Orders</h2>
                    <a href="orders.php" class="view-all-link">View All</a>
                </div>
                
                <?php if ($recent_orders['success'] && !empty($recent_orders['data'])): ?>
                    <div class="order-list">
                        <?php foreach (array_slice($recent_orders['data'], 0, 3) as $order): ?>
                            <div class="order-item">
                                <div class="order-header">
                                    <span class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </div>
                                <p style="color: #666; margin-bottom: 4px;"><?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                                <p style="font-weight: 500;">₦<?php echo number_format($order['final_amount'], 2); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No orders yet. <a href="../index.php" style="color: #ED1B26;">Start ordering!</a></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Notifications -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">Notifications</h2>
                </div>
                
                <?php if (!empty($notifications)): ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item">
                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No notifications</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
            <a href="../index.php" class="btn btn-primary" style="padding: 12px 24px; text-decoration: none; border-radius: 8px; background-color: #ED1B26; color: white; font-weight: 500;">Browse Restaurants</a>
            <a href="orders.php" class="btn btn-secondary" style="padding: 12px 24px; text-decoration: none; border-radius: 8px; border: 1px solid #e9ecef; color: #666; font-weight: 500;">View Orders</a>
            <a href="addresses.php" class="btn btn-secondary" style="padding: 12px 24px; text-decoration: none; border-radius: 8px; border: 1px solid #e9ecef; color: #666; font-weight: 500;">Manage Addresses</a>
            <a href="../account.php" class="btn btn-secondary" style="padding: 12px 24px; text-decoration: none; border-radius: 8px; border: 1px solid #e9ecef; color: #666; font-weight: 500;">Account Settings</a>
        </div>

        <!-- Back to Main Site -->
        <div style="margin-top: 24px; text-align: center;">
            <a href="../index.php" style="color: #ED1B26; text-decoration: none;">← Back to FaroDash</a>
        </div>
    </div>
</body>
</html>