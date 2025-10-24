<?php

require_once 'includes/session_handler.php';
require_once 'includes/auth_functions.php';

// Initialize authentication
$auth = new AuthManager();
$is_logged_in = $auth->isAuthenticated();
$current_user = $auth->getCurrentUser();

// User context variables
$user_logged_in = $is_logged_in;
$cart_count = 0;
$notification_count = 0;
$user_favorites = [];
$search_history = [];

if ($is_logged_in && $current_user) {
    // Get user's cart summary
    $cart_summary = $auth->getCartSummary($current_user['id']);
    $cart_count = array_sum(array_column($cart_summary, 'item_count'));
    
    // Get notification count
    $notification_count = $auth->getUnreadNotificationCount($current_user['id']);
    
    // Get user's favorites for heart state
    $favorites = $auth->getUserFavorites($current_user['id']);
    $user_favorites = array_column($favorites, 'restaurant_id');
    
    // Get search history
    $search_history = $auth->getUserSearchHistory($current_user['id'], 5);
}

// Handle search if submitted
$search_results = [];
$search_term = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    
    if ($is_logged_in && $current_user) {
        // Save search history
        $auth->saveSearchHistory($current_user['id'], $search_term, 'general', 0);
    }
    
    // Perform search (you can enhance this with actual search logic)
    $search_results = ['restaurants' => [], 'food_items' => []];
}

$page_title = $search_term ? "Search: $search_term - FaroDash" : "FaroDash - Food Delivery";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f5f5f5;
            color: #000000;
            padding-bottom: 80px;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            padding: 0 16px;
            z-index: 100;
            justify-content: space-between;
            gap: 12px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .menu-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .menu-icon svg {
            width: 20px;
            height: 20px;
        }

        .location-selector {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            flex: 1;
            min-width: 0;
        }

        .location-icon {
            width: 16px;
            height: 20px;
            flex-shrink: 0;
        }

        .location-text {
            font-size: 14px;
            font-weight: 500;
            color: #000;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
            min-width: 0;
        }

        .location-text-content {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dropdown-arrow {
            width: 10px;
            height: 8px;
            flex-shrink: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .header-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-icon-btn svg {
            width: 22px;
            height: 22px;
        }

        .notification-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            background-color: #ED1B26;
            border-radius: 50%;
        }

        /* Main Content */
        .main-content {
            margin-top: 70px;
            padding: 20px;
        }

        /* Search Bar */
        .search-container {
            position: relative;
            margin-bottom: 24px;
        }

        .search-input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            border: none;
            border-radius: 12px;
            background-color: white;
            font-size: 15px;
            outline: none;
            font-family: 'Outfit', sans-serif;
            color: #000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .search-input::placeholder {
            color: #9CA3AF;
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            pointer-events: none;
        }

        /* Promo Banner */
        .promo-banner {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            min-height: 200px;
            display: flex;
            align-items: center;
        }

        .promo-content {
            position: relative;
            z-index: 2;
            max-width: 50%;
        }

        .promo-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #000;
            line-height: 1.1;
        }

        .promo-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .promo-btn {
            background: #ED1B26;
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
        }

        .promo-image {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 50%;
            height: 100%;
            object-fit: contain;
            object-position: right center;
            z-index: 1;
        }

        /* Food Categories */
        .categories-section {
            margin-bottom: 32px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .category-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .category-icon {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            background-size: cover;
            background-position: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .category-icon.active {
            background-color: #ED1B26;
        }

        .category-icon img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .category-name {
            font-size: 13px;
            font-weight: 500;
            text-align: center;
            color: #000;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #000;
        }

        .see-more {
            color: #ED1B26;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
        }

        /* Horizontal Scroll for Popular Restaurants */
        .restaurants-scroll-container {
            position: relative;
            margin-bottom: 32px;
        }

        .restaurants-horizontal {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 10px;
        }

        .restaurants-horizontal::-webkit-scrollbar {
            display: none;
        }

        .restaurant-card-horizontal {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: transform 0.3s ease;
            flex-shrink: 0;
            width: 280px;
        }

        .restaurant-card-horizontal:active {
            transform: scale(0.98);
        }

        /* Vertical Cards for Top Picks */
        .cards-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .food-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .food-card:active {
            transform: scale(0.98);
        }

        .card-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .delivery-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #ED1B26;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .card-logo {
            position: absolute;
            bottom: 12px;
            right: 12px;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
        }

        .card-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .card-info {
            padding: 16px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #000;
        }

        .card-restaurant {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }

        .restaurant-icon {
            width: 16px;
            height: 16px;
        }

        .restaurant-name {
            font-size: 14px;
            color: #666;
        }

        .card-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: #666;
        }

        .rating-badge {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .rating-icon {
            width: 14px;
            height: 14px;
        }

        .card-price {
            font-size: 16px;
            font-weight: 600;
            color: #ED1B26;
            margin-top: 8px;
        }

        .card-delivery-info {
            font-size: 13px;
            color: #666;
            margin-top: 6px;
        }

        .rating-absolute {
            position: absolute;
            top: 12px;
            left: 12px;
            background: white;
            padding: 6px 12px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Sidebar Menu - SAME AS OLD ONE */
        .sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 280px;
            height: 100vh;
            background: white;
            z-index: 1000;
            transition: left 0.3s ease;
            padding: 24px 0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar.open {
            left: 0;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        .sidebar-header {
            padding: 0 24px 24px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }

        .sidebar-logo {
            height: 40px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }

        .sidebar-bottom {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            margin-top: auto;
        }

        .menu-item {
            margin: 0;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 40px;
            text-decoration: none;
            color: #000;
            font-size: 16px;
            font-weight: 400;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .menu-link.small {
            font-size: 14px;
            padding: 10px 40px;
        }

        .menu-link:hover,
        .menu-link.active {
            background: rgba(237, 27, 38, 0.1);
            color: #ED1B26;
            border-left: 6px solid #ED1B26;
        }

        .menu-link:hover svg path,
        .menu-link.active svg path {
            stroke: #ED1B26;
        }

        .menu-link:hover svg,
        .menu-link.active svg {
            fill: #ED1B26;
        }

        .menu-icon {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
        }

        /* Hidden elements */
        .hidden {
            display: none !important;
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #ED1B26;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: fixed;
            top: 70px;
            right: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            width: calc(100% - 40px);
            max-width: 350px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 16px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f1f1;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #fff8f0;
            border-left: 3px solid #ED1B26;
        }

        .notification-title {
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 12px;
            color: #666;
            line-height: 1.3;
        }

        .notification-time {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        /* Cart Dropdown */
        .cart-dropdown {
            position: fixed;
            top: 70px;
            right: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            width: calc(100% - 40px);
            max-width: 350px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .cart-dropdown.show {
            display: block;
        }

        .cart-header {
            padding: 16px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }

        .cart-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .cart-item-restaurant {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .cart-item-price {
            font-size: 13px;
            color: #ED1B26;
            font-weight: 600;
        }

        .cart-empty {
            padding: 40px 20px;
            text-align: center;
            color: #666;
        }

        .cart-footer {
            padding: 16px;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }

        .cart-total {
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .cart-checkout-btn {
            width: 100%;
            padding: 12px;
            background: #ED1B26;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
        }

        .cart-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background-color: #ED1B26;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Location Modal */
        .location-modal {
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

        .location-modal.active {
            display: flex;
        }

        .location-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
        }

        .location-modal-content {
            position: relative;
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .location-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
        }

        .location-modal-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .location-close-btn {
            background: none;
            border: none;
            font-size: 28px;
            color: #666;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            line-height: 1;
        }

        .location-modal-body {
            padding: 24px;
        }

        .current-location-option {
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

        .current-location-option:hover {
            border-color: #ED1B26;
            background: #fff5f5;
        }

        .location-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .location-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
            z-index: 1;
        }

        .location-divider span {
            background: white;
            padding: 0 16px;
            color: #666;
            font-size: 14px;
            position: relative;
            z-index: 2;
        }

        .address-search-container {
            position: relative;
        }

        .address-search-input {
            width: 100%;
            padding: 16px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Outfit', sans-serif;
            outline: none;
        }

        .address-search-input:focus {
            border-color: #ED1B26;
        }

        .address-suggestions {
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

        .address-suggestions.show {
            display: block;
        }

        .address-suggestion-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }

        .address-suggestion-item:hover,
        .address-suggestion-item.selected {
            background: #f8f9fa;
        }

        .address-suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-text {
            flex: 1;
        }

        .suggestion-main {
            font-weight: 500;
            color: #000;
            margin-bottom: 2px;
        }

        .suggestion-secondary {
            font-size: 13px;
            color: #666;
        }

        .location-loading {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ED1B26;
            font-weight: 500;
        }

        .loading-spinner-small {
            width: 20px;
            height: 20px;
            border: 2px solid #ffeaea;
            border-top: 2px solid #ED1B26;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @media (max-width: 480px) {
            .header {
                padding: 0 12px;
            }

            .promo-banner {
                padding: 20px;
                min-height: 180px;
            }

            .promo-title {
                font-size: 28px;
            }

            .promo-subtitle {
                font-size: 12px;
            }

            .promo-btn {
                padding: 12px 24px;
                font-size: 14px;
            }

            .category-icon {
                width: 70px;
                height: 70px;
            }

            .category-icon img {
                width: 40px;
                height: 40px;
            }

            .restaurant-card-horizontal {
                width: 260px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <button class="menu-icon" onclick="toggleSidebar()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M3 12H21M3 6H21M3 18H21" stroke="#000" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            
            <div class="location-selector" onclick="openLocationSelector()">
                <svg class="location-icon" width="16" height="20" viewBox="0 0 14 20" fill="none">
                    <path d="M7 9.5C6.33696 9.5 5.70107 9.23661 5.23223 8.76777C4.76339 8.29893 4.5 7.66304 4.5 7C4.5 6.33696 4.76339 5.70107 5.23223 5.23223C5.70107 4.76339 6.33696 4.5 7 4.5C7.66304 4.5 8.29893 4.76339 8.76777 5.23223C9.23661 5.70107 9.5 6.33696 9.5 7C9.5 7.3283 9.43534 7.65339 9.3097 7.95671C9.18406 8.26002 8.99991 8.53562 8.76777 8.76777C8.53562 8.99991 8.26002 9.18406 7.95671 9.3097C7.65339 9.43534 7.3283 9.5 7 9.5ZM7 0C5.14348 0 3.36301 0.737498 2.05025 2.05025C0.737498 3.36301 0 5.14348 0 7C0 12.25 7 20 7 20C7 20 14 12.25 14 7C14 5.14348 13.2625 3.36301 11.9497 2.05025C10.637 0.737498 8.85652 0 7 0Z" fill="#ED1B26"/>
                </svg>
                <span class="location-text">
                    <span class="location-text-content" id="locationText">Enter your address</span>
                    <svg class="dropdown-arrow" width="10" height="8" viewBox="0 0 12 8" fill="none">
                        <path d="M1 1L6 6L11 1" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="header-right">
            <button class="header-icon-btn" onclick="toggleNotifications()">
                <svg width="22" height="22" viewBox="0 0 16 18" fill="none">
                    <path d="M5.99285 14.7314H2.04672C0.734851 14.7314 0.703259 12.9328 1.68868 12.1524C2.64291 11.3967 2.78912 11.1553 3.42784 7.70551C3.96976 4.77614 5.15891 3.86572 6.63481 3.38946C6.55259 3.20828 6.50399 3.00895 6.50399 2.79641C6.50399 2.41988 6.65078 2.05877 6.91207 1.79253C7.17336 1.52628 7.52774 1.37671 7.89726 1.37671C8.26678 1.37671 8.62117 1.52628 8.88246 1.79253C9.14375 2.05877 9.29054 2.41988 9.29054 2.79641C9.29054 3.00895 9.24153 3.20828 9.15972 3.38946C10.6356 3.86572 11.8244 4.77656 12.3667 7.70551C13.0054 11.1553 13.1516 11.3967 14.1058 12.1524C15.0913 12.9328 15.0597 14.7314 13.7478 14.7314H9.80127M5.99285 14.7314C6.0054 15.2375 6.21152 15.7186 6.56727 16.072C6.92301 16.4255 7.4002 16.6233 7.89706 16.6233C8.39392 16.6233 8.87111 16.4255 9.22685 16.072C9.58259 15.7186 9.78872 15.2375 9.80127 14.7314M5.99285 14.7314H9.80127" stroke="#000" stroke-width="1.08904" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php if ($notification_count > 0): ?>
                    <div class="notification-badge"></div>
                <?php endif; ?>
            </button>

            <button class="header-icon-btn" onclick="toggleCart()">
                <svg width="22" height="22" viewBox="0 0 19 18" fill="none">
                    <path d="M14.7347 14.2274C15.1968 14.2274 15.64 14.411 15.9668 14.7377C16.2935 15.0645 16.4771 15.5077 16.4771 15.9699C16.4771 16.432 16.2935 16.8752 15.9668 17.202C15.64 17.5287 15.1968 17.7123 14.7347 17.7123C14.2725 17.7123 13.8293 17.5287 13.5025 17.202C13.1758 16.8752 12.9922 16.432 12.9922 15.9699C12.9922 15.0028 13.7676 14.2274 14.7347 14.2274ZM0.794922 0.287659H3.64385L4.46281 2.03012H17.3484C17.5794 2.03012 17.801 2.12192 17.9644 2.2853C18.1278 2.44869 18.2196 2.67029 18.2196 2.90136C18.2196 3.04947 18.176 3.19758 18.115 3.33697L14.996 8.97385C14.6998 9.50531 14.1248 9.87122 13.4714 9.87122H6.98068L6.19657 11.2913L6.17043 11.3959C6.17043 11.4536 6.19338 11.509 6.23422 11.5499C6.27507 11.5907 6.33047 11.6137 6.38824 11.6137H16.4771V13.3562H6.02232C5.56019 13.3562 5.11699 13.1726 4.79021 12.8458C4.46343 12.519 4.27985 12.0758 4.27985 11.6137C4.27985 11.3088 4.35827 11.0213 4.48895 10.7773L5.67383 8.64278L2.53739 2.03012H0.794922V0.287659ZM6.02232 14.2274C6.48445 14.2274 6.92765 14.411 7.25443 14.7377C7.58121 15.0645 7.76479 15.5077 7.76479 15.9699C7.76479 16.432 7.58121 16.8752 7.25443 17.202C6.92765 17.5287 6.48445 17.7123 6.02232 17.7123C5.56019 17.7123 5.11699 17.5287 4.79021 17.202C4.46343 16.8752 4.27985 16.432 4.27985 15.9699C4.27985 15.0028 5.05525 14.2274 6.02232 14.2274ZM13.8634 8.12876L16.2854 3.77259H5.27306L7.32917 8.12876H13.8634Z" fill="#000"/>
                </svg>
                <?php if ($cart_count > 0): ?>
                    <div class="cart-badge"><?php echo $cart_count; ?></div>
                <?php endif; ?>
            </button>
        </div>
    </header>

    <!-- Sidebar - SAME AS OLD ONE -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.png" alt="FaroDash" class="sidebar-logo">
        </div>

        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="index.php" class="menu-link active">
                    <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M27.5 27.5H2.5M2.5 13.75L7.57875 9.6875M27.5 13.75L17.3425 5.625C16.6776 5.09311 15.8515 4.80334 15 4.80334C14.1485 4.80334 13.3224 5.09311 12.6575 5.625L11.68 6.40625M19.375 6.875V4.375C19.375 4.20924 19.4408 4.05027 19.5581 3.93306C19.6753 3.81585 19.8342 3.75 20 3.75H23.125C23.2908 3.75 23.4497 3.81585 23.5669 3.93306C23.6842 4.05027 23.75 4.20924 23.75 4.375V10.625M5 27.5V11.875M25 11.875V16.875M25 27.5V21.875" stroke="#F9A825" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M18.75 27.5V21.25C18.75 19.4825 18.75 18.5987 18.2 18.05C17.6525 17.5 16.7687 17.5 15 17.5C13.2313 17.5 12.3487 17.5 11.8 18.05M11.25 27.5V21.25" stroke="#F9A825" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M17.5 11.875C17.5 12.538 17.2366 13.1739 16.7678 13.6428C16.2989 14.1116 15.663 14.375 15 14.375C14.337 14.375 13.7011 14.1116 13.2322 13.6428C12.7634 13.1739 12.5 12.538 12.5 11.875C12.5 11.212 12.7634 10.5761 13.2322 10.1072C13.7011 9.63839 14.337 9.375 15 9.375C15.663 9.375 16.2989 9.63839 16.7678 10.1072C17.2366 10.5761 17.5 11.212 17.5 11.875Z" stroke="#F9A825" stroke-width="1.5"/>
                    </svg>
                    <span>Home</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="#" class="menu-link">
                    <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M26.25 12.1875H25.2691C25.0336 9.6268 23.8498 7.2465 21.95 5.51346C20.0502 3.78041 17.5715 2.81967 15 2.81967C12.4285 2.81967 9.94976 3.78041 8.04997 5.51346C6.15019 7.2465 4.96642 9.6268 4.73086 12.1875H3.75C3.50136 12.1875 3.2629 12.2863 3.08709 12.4621C2.91127 12.6379 2.8125 12.8764 2.8125 13.125C2.81662 15.3545 3.43019 17.5405 4.58685 19.4465C5.74351 21.3525 7.39925 22.906 9.375 23.9391V24.375C9.375 24.8723 9.57254 25.3492 9.92418 25.7008C10.2758 26.0525 10.7527 26.25 11.25 26.25H18.75C19.2473 26.25 19.7242 26.0525 20.0758 25.7008C20.4275 25.3492 20.625 24.8723 20.625 24.375V23.9391C22.6008 22.906 24.2565 21.3525 25.4132 19.4465C26.5698 17.5405 27.1834 15.3545 27.1875 13.125C27.1875 12.8764 27.0887 12.6379 26.9129 12.4621C26.7371 12.2863 26.4986 12.1875 26.25 12.1875ZM23.3836 12.1875H17.3578C18.4944 10.4823 20.214 9.25021 22.1941 8.72226C22.8416 9.77537 23.2478 10.9588 23.3836 12.1875ZM20.3297 6.58945C20.5445 6.76523 20.7504 6.95078 20.9473 7.14609C18.4516 8.024 16.3893 9.82828 15.1875 12.1852H11.7305C12.3164 10.5427 13.3952 9.12118 14.8193 8.11482C16.2435 7.10846 17.9436 6.56633 19.6875 6.5625C19.902 6.5625 20.1164 6.57305 20.3297 6.58945ZM15 4.6875C15.7523 4.68818 16.5011 4.78947 17.2266 4.98867C15.4599 5.42692 13.8399 6.32313 12.53 7.58698C11.2201 8.85082 10.2664 10.4376 9.76523 12.1875H6.61641C6.84896 10.126 7.83202 8.22219 9.37817 6.83899C10.9243 5.4558 12.9254 4.68997 15 4.6875ZM19.2961 22.5C19.1326 22.5751 18.9941 22.6958 18.8973 22.8474C18.8004 22.9991 18.7493 23.1755 18.75 23.3555V24.375H11.25V23.3555C11.2507 23.1755 11.1996 22.9991 11.1027 22.8474C11.0059 22.6958 10.8674 22.5751 10.7039 22.5C9.05522 21.7413 7.63509 20.5623 6.58606 19.0814C5.53703 17.6004 4.89602 15.8695 4.72734 14.0625H25.2691C25.1008 15.8691 24.4603 17.5998 23.4119 19.0807C22.3635 20.5617 20.9441 21.7409 19.2961 22.5Z" fill="black"/>
                    </svg>
                    <span>Breakfast</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="#" class="menu-link">
                    <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.2002 18.6498C19.9255 18.6599 22.6177 18.0532 25.0752 16.875C25.5285 16.6469 25.8826 16.2606 26.0703 15.7891C26.2581 15.3176 26.2665 14.7936 26.094 14.3163C25.9215 13.839 25.58 13.4415 25.1342 13.199C24.6884 12.9565 24.1692 12.8857 23.6748 13.0002C21.8898 13.4148 20.0526 13.5582 18.225 13.425C14.013 13.0626 11.337 10.0998 10.113 8.74979" stroke="#0D0D12" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4.99979 7.5V4.9998C4.99979 4.83562 5.03214 4.67306 5.09499 4.52138C5.15783 4.36971 5.24995 4.23191 5.36606 4.11585C5.48218 3.99978 5.62003 3.90774 5.77173 3.84496C5.92343 3.78219 6.08602 3.74992 6.25019 3.75H8.74979C8.91397 3.74992 9.07655 3.78219 9.22826 3.84496C9.37996 3.90774 9.5178 3.99978 9.63392 4.11585C9.75004 4.23191 9.84215 4.36971 9.905 4.52138C9.96785 4.67306 10.0002 4.83562 10.0002 4.9998V7.5C9.88338 9.47289 10.2831 11.4421 11.1599 13.2133C12.0367 14.9845 13.3603 16.4964 15 17.5998C17.5002 19.2378 21.2502 19.5624 23.7498 19.5876C24.4128 19.5876 25.0486 19.8509 25.5175 20.3197C25.9864 20.7884 26.2498 21.4242 26.25 22.0872C26.2485 22.5632 26.1113 23.029 25.8545 23.4297C25.5976 23.8305 25.2317 24.1497 24.7998 24.3498C21.4374 25.8498 12.975 28.65 6.86219 22.275C1.24979 16.35 4.99979 7.5 4.99979 7.5Z" stroke="#0D0D12" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Groceries</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="#" class="menu-link">
                    <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7.51 15H7.4975M13.75 20.0525C14.3287 20.2437 14.885 20.4637 15.3875 20.815M15.3875 20.815C16.0385 21.2692 16.5705 21.8736 16.9382 22.5771C17.306 23.2806 17.4987 24.0624 17.5 24.8562C17.4998 24.8755 17.4959 24.8944 17.4884 24.9121C17.4809 24.9298 17.47 24.9458 17.4563 24.9593C17.4426 24.9728 17.4264 24.9834 17.4086 24.9906C17.3908 24.9978 17.3717 25.0014 17.3525 25.0012C13.7062 24.985 12.0725 24.3675 11.3863 23.3487L10 21.0712C6.885 20.4425 4.0225 18.4537 2.5 15.1037C6.25 6.8575 18.125 6.8575 21.875 15.1037M15.3875 20.815C18.1 19.99 20.5187 18.0862 21.875 15.1037M21.875 15.1037C22.2913 14.2787 24.5 11.3925 27.5 11.3925C26.4587 12.4237 24.75 16.3412 26.25 18.815C24.75 18.815 22.5 15.9287 21.875 15.1037ZM15.3875 9.3925C16.0384 8.93844 16.5702 8.33417 16.9379 7.63091C17.3057 6.92765 17.4985 6.1461 17.5 5.3525C17.5 4.32 12.115 5.78 11.3875 6.86L10 9.1375" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Protein</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="#" class="menu-link">
                    <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.3727 22.2919C12.1049 21.5596 12.1049 20.3725 11.3727 19.6402C10.6405 18.908 9.45328 18.908 8.72105 19.6402L5.18552 23.1758C4.45328 23.908 4.45328 25.0952 5.18552 25.8274C5.91775 26.5596 7.10493 26.5596 7.83717 25.8274L11.3727 22.2919Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M17.1181 4.17188L18.0019 5.05625M14.025 7.26563L14.9088 8.14938M11.3725 10.8013L12.2563 11.685M10.4888 15.2206L11.3725 16.1044M21.9794 2.84625L22.8631 3.73M21.0956 8.15L22.8631 9.9175M18.0025 11.2438L19.77 13.0113M14.4669 13.895L16.2344 15.6625" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Beauty</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="#" class="menu-link">
                    <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M8.75 2.1875C8.99864 2.1875 9.2371 2.28627 9.41291 2.46209C9.58873 2.6379 9.6875 2.87636 9.6875 3.125V4.07875C10.515 4.0625 11.4263 4.0625 12.43 4.0625H17.57C18.5737 4.0625 19.485 4.0625 20.3125 4.07875V3.125C20.3125 2.87636 20.4113 2.6379 20.5871 2.46209C20.7629 2.28627 21.0014 2.1875 21.25 2.1875C21.4986 2.1875 21.7371 2.28627 21.9129 2.46209C22.0887 2.6379 22.1875 2.87636 22.1875 3.125V4.15875C22.5125 4.18375 22.8204 4.21542 23.1112 4.25375C24.5763 4.45125 25.7625 4.86625 26.6987 5.80125C27.6337 6.7375 28.0487 7.92375 28.2463 9.38875C28.3079 9.85708 28.3525 10.3696 28.38 10.9263C28.448 11.1107 28.4563 11.3118 28.4037 11.5013C28.4375 12.5025 28.4375 13.6413 28.4375 14.93V17.5C28.4375 17.7486 28.3387 17.9871 28.1629 18.1629C27.9871 18.3387 27.7486 18.4375 27.5 18.4375C27.2514 18.4375 27.0129 18.3387 26.8371 18.1629C26.6613 17.9871 26.5625 17.7486 26.5625 17.5V15C26.5625 13.9325 26.5625 13.0037 26.5462 12.1875H3.45375C3.4375 13.0037 3.4375 13.9325 3.4375 15V17.5C3.4375 19.8837 3.44 21.5775 3.6125 22.8625C3.78125 24.1188 4.09875 24.8438 4.6275 25.3725C5.15625 25.9013 5.88125 26.2188 7.13875 26.3875C8.42375 26.56 10.1163 26.5625 12.5 26.5625H17.5C17.7486 26.5625 17.9871 26.6613 18.1629 26.8371C18.3387 27.0129 18.4375 27.2514 18.4375 27.5C18.4375 27.7486 18.3387 27.9871 18.1629 28.1629C17.9871 28.3387 17.7486 28.4375 17.5 28.4375H12.43C10.1325 28.4375 8.3125 28.4375 6.88875 28.2463C5.42375 28.0487 4.2375 27.6337 3.30125 26.6987C2.36625 25.7625 1.95125 24.5763 1.75375 23.1112C1.5625 21.6863 1.5625 19.8675 1.5625 17.57V14.93C1.5625 13.6413 1.5625 12.5025 1.59625 11.5C1.54409 11.3105 1.55283 11.1093 1.62125 10.925C1.64792 10.3692 1.69208 9.85708 1.75375 9.38875C1.95125 7.92375 2.36625 6.7375 3.30125 5.80125C4.2375 4.86625 5.42375 4.45125 6.88875 4.25375C7.18042 4.21542 7.48833 4.18375 7.8125 4.15875V3.125C7.8125 2.87636 7.91127 2.6379 8.08709 2.46209C8.2629 2.28627 8.50136 2.1875 8.75 2.1875ZM3.54 10.3125H26.46C26.44 10.0758 26.4158 9.85083 26.3875 9.6375C26.2188 8.38125 25.9013 7.65625 25.3725 7.1275C24.8438 6.59875 24.1187 6.28125 22.8612 6.1125C21.5775 5.94 19.8837 5.9375 17.5 5.9375H12.5C10.1163 5.9375 8.42375 5.94 7.1375 6.1125C5.88125 6.28125 5.15625 6.59875 4.6275 7.1275C4.09875 7.65625 3.78125 8.38125 3.6125 9.6375C3.58417 9.85 3.56 10.0746 3.54 10.3112M22.5 19.6875C21.7541 19.6875 21.0387 19.9838 20.5113 20.5113C19.9838 21.0387 19.6875 21.7541 19.6875 22.5C19.6875 23.2459 19.9838 23.9613 20.5113 24.4887C21.0387 25.0162 21.7541 25.3125 22.5 25.3125C23.2459 25.3125 23.9613 25.0162 24.4887 24.4887C25.0162 23.9613 25.3125 23.2459 25.3125 22.5C25.3125 21.7541 25.0162 21.0387 24.4887 20.5113C23.9613 19.9838 23.2459 19.6875 22.5 19.6875ZM17.8125 22.5C17.8127 21.7691 17.9838 21.0485 18.3122 20.3955C18.6405 19.7426 19.117 19.1755 19.7035 18.7395C20.2901 18.3035 20.9705 18.0106 21.6903 17.8844C22.4102 17.7582 23.1496 17.802 23.8495 18.0124C24.5494 18.2228 25.1904 18.594 25.7214 19.0962C26.2523 19.5985 26.6584 20.2179 26.9073 20.9051C27.1562 21.5923 27.241 22.3281 27.1548 23.0539C27.0687 23.7796 26.8141 24.4752 26.4112 25.085L28.1625 26.8375C28.2546 26.9233 28.3285 27.0268 28.3797 27.1418C28.431 27.2568 28.4585 27.381 28.4607 27.5068C28.463 27.6327 28.4398 27.7578 28.3927 27.8745C28.3455 27.9912 28.2753 28.0973 28.1863 28.1863C28.0973 28.2753 27.9912 28.3455 27.8745 28.3927C27.7578 28.4398 27.6327 28.463 27.5068 28.4607C27.381 28.4585 27.2568 28.431 27.1418 28.3797C27.0268 28.3285 26.9233 28.2546 26.8375 28.1625L25.085 26.4112C24.3785 26.8783 23.5586 27.1452 22.7125 27.1836C21.8664 27.222 21.0257 27.0305 20.2797 26.6293C19.5338 26.2281 18.9105 25.6323 18.4761 24.9052C18.0416 24.1782 17.8123 23.347 17.8125 22.5Z" fill="black"/>
                    </svg>
                    <span>Browse all</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-bottom">
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="account.php" class="menu-link small">
                        <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 2.5C21.9037 2.5 27.5 8.09625 27.5 15C27.5 21.9037 21.9037 27.5 15 27.5C8.09625 27.5 2.5 21.9037 2.5 15C2.5 8.09625 8.09625 2.5 15 2.5ZM15.2 20C13.9907 19.9985 12.7943 20.2485 11.6868 20.7339C10.5792 21.2194 9.58468 21.9298 8.76625 22.82C10.5359 24.2343 12.7347 25.0032 15 25C17.3654 25.0034 19.6549 24.1651 21.4587 22.635C20.6446 21.7997 19.6713 21.1362 18.5963 20.6836C17.5212 20.231 16.3664 19.9986 15.2 20ZM15 5C13.1401 5.00005 11.3171 5.51878 9.73579 6.49791C8.1545 7.47704 6.8776 8.87776 6.04858 10.5427C5.21957 12.2076 4.8713 14.0707 5.0429 15.9227C5.2145 17.7746 5.89918 19.542 7.02 21.0262C8.07069 19.9114 9.33846 19.0234 10.7452 18.417C12.152 17.8105 13.6681 17.4985 15.2 17.5C16.6771 17.4979 18.14 17.7878 19.5048 18.3528C20.8695 18.9178 22.1092 19.7469 23.1525 20.7925C24.2156 19.2964 24.8465 17.5365 24.9761 15.7057C25.1056 13.8749 24.7288 12.0438 23.8869 10.4129C23.0449 8.78199 21.7704 7.41426 20.2029 6.45951C18.6354 5.50476 16.8354 4.99982 15 5ZM15 6.25C16.3261 6.25 17.5979 6.77678 18.5355 7.71447C19.4732 8.65215 20 9.92392 20 11.25C20 12.5761 19.4732 13.8479 18.5355 14.7855C17.5979 15.7232 16.3261 16.25 15 16.25C13.6739 16.25 12.4021 15.7232 11.4645 14.7855C10.5268 13.8479 10 12.5761 10 11.25C10 9.92392 10.5268 8.65215 11.4645 7.71447C12.4021 6.77678 13.6739 6.25 15 6.25ZM15 8.75C14.337 8.75 13.7011 9.01339 13.2322 9.48223C12.7634 9.95107 12.5 10.587 12.5 11.25C12.5 11.913 12.7634 12.5489 13.2322 13.0178C13.7011 13.4866 14.337 13.75 15 13.75C15.663 13.75 16.2989 13.4866 16.7678 13.0178C17.2366 12.5489 17.5 11.913 17.5 11.25C17.5 10.587 17.2366 9.95107 16.7678 9.48223C16.2989 9.01339 15.663 8.75 15 8.75Z" fill="black"/>
                        </svg>
                        <span>Account</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Notification Dropdown -->
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <span>Notifications</span>
            <span style="font-size: 12px; color: #666;"><?php echo $notification_count; ?> unread</span>
        </div>
        <div id="notificationsList">
            <!-- Notifications will be loaded by JavaScript -->
        </div>
    </div>

    <!-- Cart Dropdown -->
    <div class="cart-dropdown" id="cartDropdown">
        <div class="cart-header">My Cart</div>
        <div id="cartItems">
            <!-- Cart items will be loaded by JavaScript -->
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Search Bar -->
        <div class="search-container">
            <form method="GET" action="" id="searchForm">
                <input type="text" name="search" class="search-input" 
                       placeholder="Search for a dish or restaurant" 
                       value="<?php echo htmlspecialchars($search_term); ?>"
                       autocomplete="off">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none">
                    <circle cx="11" cy="11" r="8" stroke="#9CA3AF" stroke-width="2"/>
                    <path d="M21 21L16.65 16.65" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </form>
        </div>

        <!-- Promo Banner -->
        <div class="promo-banner">
            <div class="promo-content">
                <div class="promo-title">30% OFF</div>
                <div class="promo-subtitle">Discover food discounts in your<br>fvourire local restaurant</div>
                <button class="promo-btn">Order Now</button>
            </div>
            <img src="https://raw.githubusercontent.com/muhammadfarhan199/FaroDash-app/main/app/src/main/res/drawable/grocery_basket.png" alt="Groceries" class="promo-image">
        </div>

        <!-- Food Categories -->
        <section class="categories-section">
            <div class="categories-grid" id="categoriesGrid">
                <div class="category-item" onclick="selectCategory('fast-food', this)">
                    <div class="category-icon">
                        <img src="https://cdn-icons-png.flaticon.com/512/3703/3703377.png" alt="Fast Food">
                    </div>
                    <div class="category-name">Fast Food</div>
                </div>
                <div class="category-item" onclick="selectCategory('snacks', this)">
                    <div class="category-icon">
                        <img src="https://cdn-icons-png.flaticon.com/512/3075/3075977.png" alt="Snacks">
                    </div>
                    <div class="category-name">Snacks</div>
                </div>
                <div class="category-item" onclick="selectCategory('swallow', this)">
                    <div class="category-icon">
                        <img src="https://cdn-icons-png.flaticon.com/512/1046/1046784.png" alt="Swallow">
                    </div>
                    <div class="category-name">Swallow</div>
                </div>
                <div class="category-item" onclick="selectCategory('smoothie', this)">
                    <div class="category-icon">
                        <img src="https://cdn-icons-png.flaticon.com/512/2738/2738046.png" alt="Smoothie">
                    </div>
                    <div class="category-name">Smoothie</div>
                </div>
            </div>
        </section>

        <!-- Popular Restaurants Section - HORIZONTAL SCROLL -->
        <section id="restaurantsSection" class="restaurants-scroll-container">
            <div class="section-header">
                <h2 class="section-title">Popular Restaurants</h2>
                <a href="#" class="see-more">See more</a>
            </div>
            <div class="restaurants-horizontal" id="restaurantsGrid">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <div>Loading restaurants...</div>
                </div>
            </div>
        </section>

        <!-- Top Picks Section - VERTICAL (All Food Items) -->
        <section id="topPicksSection">
            <div class="section-header">
                <h2 class="section-title">Top picks</h2>
                <a href="#" class="see-more">See more</a>
            </div>
            <div class="cards-grid" id="topPicksGrid">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <div>Loading top picks...</div>
                </div>
            </div>
        </section>

        <!-- Food Items Section (Hidden by default - shows when category selected) -->
        <section id="foodItemsSection" class="hidden">
            <div class="section-header">
                <h2 class="section-title" id="categoryTitle">Fast Food</h2>
            </div>
            <div class="cards-grid" id="foodItemsGrid">
                <!-- Food items will be loaded here -->
            </div>
        </section>
    </main>

    <!-- Location Modal - SAME AS OLD ONE -->
    <div class="location-modal" id="locationModal">
        <div class="location-modal-overlay" onclick="closeLocationSelector()"></div>
        <div class="location-modal-content">
            <div class="location-modal-header">
                <h3>Choose Your Location</h3>
                <button class="location-close-btn" onclick="closeLocationSelector()">×</button>
            </div>
            <div class="location-modal-body">
                <div class="current-location-option" onclick="useCurrentLocation()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="3" stroke="#ED1B26" stroke-width="2"/>
                        <circle cx="12" cy="12" r="10" stroke="#ED1B26" stroke-width="2"/>
                        <path d="M12 2v4M12 18v4M2 12h4M18 12h4" stroke="#ED1B26" stroke-width="2"/>
                    </svg>
                    <div>
                        <div style="font-weight: 600; color: #000;">Use Current Location</div>
                        <div style="font-size: 13px; color: #666;">Get precise delivery location</div>
                    </div>
                </div>
                
                <div class="location-divider">
                    <span>or</span>
                </div>
                
                <div class="address-search-container">
                    <input type="text" 
                        id="addressSearchInput" 
                        class="address-search-input" 
                        placeholder="Enter your address..."
                        autocomplete="off">
                    <div class="address-suggestions" id="addressSuggestions"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://maps.googleapis.com/maps/api/js?key=APIKEY&libraries=places"></script>
    <script>
        // Global variables
        const API_BASE_URL = 'https://dashboard.farodash.com/api';
        const IS_LOGGED_IN = <?php echo json_encode($is_logged_in); ?>;
        const CURRENT_USER = <?php echo json_encode($current_user); ?>;
        const USER_FAVORITES = <?php echo json_encode($user_favorites); ?>;

        let restaurants = [];
        let foodItems = [];
        let currentView = 'restaurants';
        let selectedCategory = null;
        let userLocation = { lat: null, lng: null };
        let autocompleteService = null;
        let placesService = null;

        // Initialize app
        document.addEventListener('DOMContentLoaded', function() {
            initGoogleMaps();
            loadRestaurants();
            if (IS_LOGGED_IN) {
                loadNotifications();
                loadCart();
            }
            checkSavedLocation();
        });

        // Initialize Google Maps
        function initGoogleMaps() {
            if (typeof google !== 'undefined' && google.maps) {
                autocompleteService = new google.maps.places.AutocompleteService();
                const map = new google.maps.Map(document.createElement('div'));
                placesService = new google.maps.places.PlacesService(map);
            }
        }

        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }

        // Toggle Notifications
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            const cartDropdown = document.getElementById('cartDropdown');
            cartDropdown.classList.remove('show');
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                loadNotifications();
            }
        }

        // Toggle Cart
        function toggleCart() {
            const dropdown = document.getElementById('cartDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            notificationDropdown.classList.remove('show');
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                loadCart();
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.header-icon-btn') && 
                !e.target.closest('.notification-dropdown') && 
                !e.target.closest('.cart-dropdown')) {
                document.getElementById('notificationDropdown').classList.remove('show');
                document.getElementById('cartDropdown').classList.remove('show');
            }
        });

        // Load Restaurants
        async function loadRestaurants() {
            try {
                const response = await fetch('/api/restaurants_proxy.php');
                const data = await response.json();
                
                if (data.success && data.data) {
                    restaurants = data.data;
                    renderRestaurants();
                    loadTopPicks();
                }
            } catch (error) {
                console.error('Error loading restaurants:', error);
                showError('Unable to load restaurants');
            }
        }

        // Render Restaurants - HORIZONTAL SCROLL
        function renderRestaurants() {
            const grid = document.getElementById('restaurantsGrid');
            
            if (restaurants.length === 0) {
                grid.innerHTML = '<div class="loading">No restaurants available</div>';
                return;
            }

            grid.innerHTML = restaurants.map(restaurant => {
                const coverImage = restaurant.cover_image_url || 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4';
                const rating = Math.round(parseFloat(restaurant.rating) * 20);
                const deliveryTime = restaurant.delivery_time || '20-30';
                
                return `
                    <div class="restaurant-card-horizontal" onclick="navigateToRestaurant(${restaurant.id})">
                        <div class="card-image-container">
                            <img src="${coverImage}" alt="${restaurant.name}" class="card-image">
                            <div class="rating-absolute">
                                <svg class="rating-icon" viewBox="0 0 15 14" fill="none">
                                    <path d="M7.5 1.32065L9.4285 5.37773L14 5.90417L10.6201 8.93733L11.5173 13.32L7.5 11.1377L3.4827 13.3206L4.37985 8.93793L1 5.90356L5.57212 5.37713L7.5 1.32065Z" fill="#ED1B26"/>
                                </svg>
                                ${rating}%
                            </div>
                            <div class="delivery-badge">${deliveryTime} mins</div>
                            ${restaurant.logo_url ? `
                                <div class="card-logo">
                                    <img src="${restaurant.logo_url}" alt="${restaurant.name}">
                                </div>
                            ` : ''}
                        </div>
                        <div class="card-info">
                            <div class="card-title">${restaurant.name}</div>
                            <div class="card-meta">
                                <span class="rating-badge">
                                    <svg class="rating-icon" viewBox="0 0 15 14" fill="none">
                                        <path d="M7.5 1.32065L9.4285 5.37773L14 5.90417L10.6201 8.93733L11.5173 13.32L7.5 11.1377L3.4827 13.3206L4.37985 8.93793L1 5.90356L5.57212 5.37713L7.5 1.32065Z" fill="#666"/>
                                    </svg>
                                    ${restaurant.rating}
                                </span>
                                <span>(${restaurant.total_reviews || 250}+ reviews)</span>
                            </div>
                            <div class="card-price">₦${restaurant.min_price || '10,000'}-₦${restaurant.max_price || '15,000'}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Load Top Picks (All Food Items from All Restaurants)
        async function loadTopPicks() {
            const grid = document.getElementById('topPicksGrid');
            
            try {
                // Create food items from all restaurants
                const allFoodItems = [];
                
                restaurants.forEach(restaurant => {
                    // Mock food items for each restaurant
                    const mockItems = [
                        {
                            id: `${restaurant.id}_1`,
                            name: 'Jollof Rice Special',
                            restaurant_id: restaurant.id,
                            restaurant_name: restaurant.name,
                            image: 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136',
                            delivery_time: restaurant.delivery_time || '20-30'
                        },
                        {
                            id: `${restaurant.id}_2`,
                            name: 'Grilled Chicken',
                            restaurant_id: restaurant.id,
                            restaurant_name: restaurant.name,
                            image: 'https://images.unsplash.com/photo-1598103442097-8b74394b95c6',
                            delivery_time: restaurant.delivery_time || '20-30'
                        }
                    ];
                    
                    allFoodItems.push(...mockItems);
                });
                
                // Shuffle and take first 6 items
                const topPicks = allFoodItems.sort(() => 0.5 - Math.random()).slice(0, 6);
                
                grid.innerHTML = topPicks.map(item => `
                    <div class="food-card" onclick="navigateToRestaurant(${item.restaurant_id})">
                        <div class="card-image-container">
                            <img src="${item.image}" alt="${item.name}" class="card-image">
                            <div class="delivery-badge">${item.delivery_time} mins</div>
                        </div>
                        <div class="card-info">
                            <div class="card-title">${item.name}</div>
                            <div class="card-restaurant">
                                <svg class="restaurant-icon" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 0L10.1 5.9L16 8L10.1 10.1L8 16L5.9 10.1L0 8L5.9 5.9L8 0Z" fill="#ED1B26"/>
                                </svg>
                                <span class="restaurant-name">${item.restaurant_name}</span>
                            </div>
                            <div class="card-delivery-info">Free delivery fee over N5,000</div>
                        </div>
                    </div>
                `).join('');
                
            } catch (error) {
                console.error('Error loading top picks:', error);
                grid.innerHTML = '<div class="loading">Error loading top picks</div>';
            }
        }

        // Select Category
        function selectCategory(category, element) {
            document.querySelectorAll('.category-item').forEach(item => {
                item.querySelector('.category-icon').classList.remove('active');
            });
            element.querySelector('.category-icon').classList.add('active');

            selectedCategory = category;
            
            document.getElementById('restaurantsSection').classList.add('hidden');
            document.getElementById('topPicksSection').classList.add('hidden');
            document.getElementById('foodItemsSection').classList.remove('hidden');
            
            const titles = {
                'fast-food': 'Fast Food',
                'snacks': 'Snacks',
                'swallow': 'Swallow',
                'smoothie': 'Smoothie'
            };
            document.getElementById('categoryTitle').textContent = titles[category];
            
            loadFoodItems(category);
        }

        // Load Food Items
        async function loadFoodItems(category) {
            const grid = document.getElementById('foodItemsGrid');
            grid.innerHTML = '<div class="loading"><div class="loading-spinner"></div><div>Loading items...</div></div>';

            try {
                const mockFoodItems = [
                    {
                        id: 1,
                        name: 'Brown Sausages',
                        restaurant: 'Amala Ibadan',
                        image: 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38',
                        delivery_time: '10-15',
                        delivery_fee: 'Free delivery fee over N5,000'
                    },
                    {
                        id: 2,
                        name: 'Jollof Rice Special',
                        restaurant: 'Mama\'s Kitchen',
                        image: 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136',
                        delivery_time: '15-20',
                        delivery_fee: 'Free delivery fee over N3,000'
                    },
                    {
                        id: 3,
                        name: 'Grilled Chicken',
                        restaurant: 'Chicken Republic',
                        image: 'https://images.unsplash.com/photo-1598103442097-8b74394b95c6',
                        delivery_time: '20-25',
                        delivery_fee: 'Free delivery fee over N4,000'
                    }
                ];

                setTimeout(() => {
                    renderFoodItems(mockFoodItems);
                }, 500);
            } catch (error) {
                console.error('Error loading food items:', error);
                grid.innerHTML = '<div class="loading">Error loading items</div>';
            }
        }

        // Render Food Items
        function renderFoodItems(items) {
            const grid = document.getElementById('foodItemsGrid');
            
            grid.innerHTML = items.map(item => `
                <div class="food-card" onclick="navigateToFoodItem(${item.id})">
                    <div class="card-image-container">
                        <img src="${item.image}" alt="${item.name}" class="card-image">
                        <div class="delivery-badge">${item.delivery_time} mins</div>
                    </div>
                    <div class="card-info">
                        <div class="card-title">${item.name}</div>
                        <div class="card-restaurant">
                            <svg class="restaurant-icon" viewBox="0 0 16 16" fill="none">
                                <path d="M8 0L10.1 5.9L16 8L10.1 10.1L8 16L5.9 10.1L0 8L5.9 5.9L8 0Z" fill="#ED1B26"/>
                            </svg>
                            <span class="restaurant-name">${item.restaurant}</span>
                        </div>
                        <div class="card-delivery-info">${item.delivery_fee}</div>
                    </div>
                </div>
            `).join('');
        }

        // Navigate to Restaurant
        function navigateToRestaurant(restaurantId) {
            window.location.href = `restaurant.php?id=${restaurantId}`;
        }

        // Navigate to Food Item
        function navigateToFoodItem(itemId) {
            window.location.href = `food-item.php?id=${itemId}`;
        }

        // Load Notifications
        async function loadNotifications() {
            try {
                const response = await fetch('api/notifications.php');
                const data = await response.json();
                
                const container = document.getElementById('notificationsList');
                if (data.success && data.notifications.length > 0) {
                    container.innerHTML = data.notifications.map(notification => 
                        `<div class="notification-item ${!notification.is_read ? 'unread' : ''}" 
                              onclick="markNotificationAsRead(${notification.id})">
                            <div class="notification-title">${notification.title}</div>
                            <div class="notification-message">${notification.message}</div>
                            <div class="notification-time">${formatTime(notification.created_at)}</div>
                        </div>`
                    ).join('');
                } else {
                    container.innerHTML = '<div class="cart-empty">No notifications</div>';
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }

        // Load Cart
        async function loadCart() {
            try {
                const response = await fetch('api/cart.php');
                const data = await response.json();
                
                const container = document.getElementById('cartItems');
                if (data.success && data.cart.length > 0) {
                    const cartHTML = data.cart.map(item => {
                        const addons = JSON.parse(item.addons || '[]');
                        const addonPrice = addons.reduce((sum, addon) => sum + parseFloat(addon.price || 0), 0);
                        const itemTotal = (parseFloat(item.unit_price) + addonPrice) * parseInt(item.quantity);
                        
                        return `<div class="cart-item">
                            <div class="cart-item-info">
                                <div class="cart-item-name">${item.item_name || 'Food Item'} (x${item.quantity})</div>
                                <div class="cart-item-restaurant">${item.restaurant_name || ''}</div>
                                <div class="cart-item-price">₦${itemTotal.toLocaleString()}</div>
                            </div>
                        </div>`;
                    }).join('');
                    
                    const total = data.cart.reduce((sum, item) => {
                        const addons = JSON.parse(item.addons || '[]');
                        const addonPrice = addons.reduce((sum, addon) => sum + parseFloat(addon.price || 0), 0);
                        return sum + ((parseFloat(item.unit_price) + addonPrice) * parseInt(item.quantity));
                    }, 0);
                    
                    container.innerHTML = cartHTML + 
                        `<div class="cart-footer">
                            <div class="cart-total">Total: ₦${total.toLocaleString()}</div>
                            <button onclick="window.location.href='checkout.php'" class="cart-checkout-btn">Checkout</button>
                        </div>`;
                        
                    updateCartBadge(data.cart.reduce((sum, item) => sum + parseInt(item.quantity), 0));
                } else {
                    container.innerHTML = '<div class="cart-empty">Your cart is empty</div>';
                    updateCartBadge(0);
                }
            } catch (error) {
                console.error('Error loading cart:', error);
            }
        }

        function updateCartBadge(count) {
            const badges = document.querySelectorAll('.cart-badge');
            badges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            });
        }

        // Mark Notification as Read
        async function markNotificationAsRead(notificationId) {
            try {
                await fetch('api/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
                });
                loadNotifications();
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }

        // Location Functions - SAME AS OLD ONE
        function openLocationSelector() {
            document.getElementById('locationModal').classList.add('active');
        }

        function closeLocationSelector() {
            document.getElementById('locationModal').classList.remove('active');
        }

        function useCurrentLocation() {
            if (navigator.geolocation) {
                const option = document.querySelector('.current-location-option');
                option.innerHTML = '<div class="location-loading"><div class="loading-spinner-small"></div><div>Getting your location...</div></div>';
                
                navigator.geolocation.getCurrentPosition(
                    async (position) => {
                        const { latitude, longitude } = position.coords;
                        userLocation.lat = latitude;
                        userLocation.lng = longitude;
                        
                        try {
                            const geocoder = new google.maps.Geocoder();
                            const response = await geocoder.geocode({
                                location: { lat: latitude, lng: longitude }
                            });
                            
                            if (response.results[0]) {
                                const address = response.results[0].formatted_address;
                                saveLocation(address, { latitude, longitude });
                                closeLocationSelector();
                                showNotification('Location updated successfully!');
                            }
                        } catch (error) {
                            showError('Could not get your address');
                            resetLocationOption();
                        }
                    },
                    (error) => {
                        showError('Location access denied');
                        resetLocationOption();
                    }
                );
            }
        }

        function resetLocationOption() {
            const option = document.querySelector('.current-location-option');
            option.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="3" stroke="#ED1B26" stroke-width="2"/>
                    <circle cx="12" cy="12" r="10" stroke="#ED1B26" stroke-width="2"/>
                    <path d="M12 2v4M12 18v4M2 12h4M18 12h4" stroke="#ED1B26" stroke-width="2"/>
                </svg>
                <div>
                    <div style="font-weight: 600; color: #000;">Use Current Location</div>
                    <div style="font-size: 13px; color: #666;">Get precise delivery location</div>
                </div>
            `;
        }

        function checkSavedLocation() {
            const saved = localStorage.getItem('user_location');
            if (saved) {
                const location = JSON.parse(saved);
                updateLocationDisplay(location.address);
            }
        }

        function saveLocation(address, coords) {
            const location = {
                address: address,
                latitude: coords.latitude,
                longitude: coords.longitude
            };
            localStorage.setItem('user_location', JSON.stringify(location));
            sessionStorage.setItem('selected_address', JSON.stringify(location));
            updateLocationDisplay(address);
        }

        function updateLocationDisplay(address) {
            const shortAddress = address.length > 25 ? address.substring(0, 25) + '...' : address;
            document.getElementById('locationText').textContent = shortAddress;
        }

        // Address Search
        const addressInput = document.getElementById('addressSearchInput');
        if (addressInput) {
            addressInput.addEventListener('input', debounce(function(e) {
                const query = e.target.value.trim();
                
                if (query.length < 3) {
                    document.getElementById('addressSuggestions').classList.remove('show');
                    return;
                }
                
                if (autocompleteService) {
                    autocompleteService.getPlacePredictions({
                        input: query,
                        componentRestrictions: { country: 'ng' }
                    }, (predictions, status) => {
                        if (status === google.maps.places.PlacesServiceStatus.OK && predictions) {
                            showAddressSuggestions(predictions);
                        }
                    });
                }
            }, 300));
        }

        function showAddressSuggestions(predictions) {
            const container = document.getElementById('addressSuggestions');
            
            const html = predictions.slice(0, 5).map(prediction => `
                <div class="address-suggestion-item" onclick="selectAddress('${prediction.place_id}', '${prediction.description.replace(/'/g, "\\'")}')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="#666"/>
                    </svg>
                    <div class="suggestion-text">
                        <div class="suggestion-main">${prediction.structured_formatting.main_text}</div>
                        <div class="suggestion-secondary">${prediction.structured_formatting.secondary_text}</div>
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = html;
            container.classList.add('show');
        }

        function selectAddress(placeId, description) {
            if (placesService) {
                placesService.getDetails({
                    placeId: placeId,
                    fields: ['geometry', 'formatted_address']
                }, (place, status) => {
                    if (status === google.maps.places.PlacesServiceStatus.OK && place.geometry) {
                        saveLocation(place.formatted_address, {
                            latitude: place.geometry.location.lat(),
                            longitude: place.geometry.location.lng()
                        });
                        closeLocationSelector();
                        showNotification('Location updated successfully!');
                    }
                });
            }
        }

        // Utility Functions
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
            if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
            return date.toLocaleDateString();
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 90px;
                left: 50%;
                transform: translateX(-50%);
                padding: 12px 24px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                ${type === 'success' ? 'background-color: #28a745;' : 'background-color: #dc3545;'}
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
rest
        function showError(message) {
            showNotification(message, 'error');
        }

        function debounce(func, wait) {
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
    </script>
</body>

</html>
