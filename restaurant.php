<?php
// Get restaurant ID from URL parameter
$restaurant_id = $_GET['id'] ?? null;
$user_lat = $_GET['lat'] ?? null;
$user_lng = $_GET['lng'] ?? null;

// Redirect to home if no restaurant ID provided
if (!$restaurant_id) {
    header('Location: index.php');
    exit;
}

// Set page title
$page_title = "Restaurant - FaroDash";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/cart.css">
    <style>
* {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #ffffff;
            color: #000000;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .restaurant-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            background-color: white;
        }

        /* Loading and Error States */
        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            gap: 20px;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ED1B26;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 16px;
            color: #666;
        }

        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            gap: 20px;
            padding: 40px;
            text-align: center;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #fee;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .error-title {
            font-size: 24px;
            font-weight: 600;
            color: #ED1B26;
            margin-bottom: 10px;
        }

        .error-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
        }

        .error-button {
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

        .error-button:hover {
            background-color: #d41420;
        }

        /* Header */
        .restaurant-header {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }

        .back-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            transition: transform 0.2s ease;
        }

        .back-btn:hover {
            transform: scale(1.1);
        }

        .favorite-btn {
            width: 37px;
            height: 37px;
            border-radius: 50%;
            background-color: #A5A5A5;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }

        .favorite-btn:hover {
            transform: scale(1.1);
        }

        .favorite-btn.active {
            background-color: #ffffff;
        }

        /* Cover Image */
        .cover-image {
            width: 100%;
            height: 200px;
            background-color: #D9D9D9;
            position: relative;
            background-size: cover;
            background-position: center;
        }

        /* Restaurant Info Section */
        .restaurant-info {
            padding: 20px;
            position: relative;
        }

        .profile-image {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            border: 1px solid #ED1B25;
            background-color: white;
            position: absolute;
            top: -44px;
            left: 20px;
            background-size: cover;
            background-position: center;
            background-image: url('images/hifeh.png');
        }

        .restaurant-details {
            margin-top: 50px;
        }

        .restaurant-name {
            font-size: 24px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 16px;
        }

        .restaurant-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
            position: relative;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .meta-left {
            display: flex;
            gap: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-text {
            font-size: 12px;
            font-weight: 400;
            color: #000000;
        }

        .distance-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Search Box */
        .search-container {
            padding: 0 20px;
            margin-bottom: 30px;
        }

        .search-box {
            width: 100%;
            height: 45px;
            background-color: #D9D9D9;
            border: none;
            border-radius: 20px;
            padding: 0 20px;
            font-size: 14px;
            font-family: 'Outfit', sans-serif;
            outline: none;
            color: #666;
        }

        .search-box::placeholder {
            color: #666;
        }

        /* Food Section */
        .food-section {
            padding: 0 20px 40px 20px;
        }

        .category-section {
            margin-bottom: 30px;
        }

        .category-title {
            font-size: 20px;
            font-weight: 600;
            color: #000;
            margin-bottom: 15px;
            padding-left: 5px;
        }

        .food-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .food-item {
            background-color: #f2f2f2;
            padding: 12px;
            border-radius: 12px;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .food-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .food-image {
            width: 100%;
            aspect-ratio: 4/3;
            background-color: #D9D9D9;
            border-radius: 8px;
            margin-bottom: 12px;
            background-size: cover;
            background-position: center;
            background-image: url('images/food.png');
        }

        .food-name {
            font-size: 7.47px;
            font-weight: 400;
            color: #000000;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .food-price {
            font-size: 12.81px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 8px;
        }

        .add-btn {
            position: absolute;
            bottom: 12px;
            right: 12px;
            width: 28px;
            height: 20px;
            background-color: #ED1B25;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }

        .add-btn:hover {
            transform: scale(1.1);
        }

        .add-btn svg {
            width: 12px;
            height: 12px;
        }

        /* Food Additions Slide Page */
        .additions-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1100;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .additions-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .additions-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            right: 0;
            background-color: white;
            border-radius: 20px 20px 0 0;
            padding: 20px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            max-height: 80vh;
            overflow-y: auto;
        }

        .additions-overlay.active .additions-panel {
            transform: translateY(0);
        }

        .additions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .additions-title {
            font-size: 18px;
            font-weight: 600;
            color: #000000;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 5px;
        }

        .selected-food {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
        }

        .selected-food-name {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 5px;
        }

        .selected-food-price {
            font-size: 14px;
            font-weight: 500;
            color: #ED1B25;
        }

        .additions-section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 15px;
        }

        .additions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }

        .addition-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .addition-item:hover {
            background-color: #e9ecef;
        }

        .addition-item.selected {
            background-color: #ED1B25;
            color: white;
        }

        .addition-info {
            flex: 1;
        }

        .addition-name {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .addition-price {
            font-size: 12px;
            opacity: 0.8;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
        }

        .quantity-label {
            font-size: 16px;
            font-weight: 500;
            color: #000000;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background-color: white;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .quantity-btn:hover {
            background-color: #ED1B25;
            color: white;
            border-color: #ED1B25;
        }

        .quantity-display {
            font-size: 18px;
            font-weight: 600;
            min-width: 30px;
            text-align: center;
        }

        .checkout-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .total-price {
            font-size: 18px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 15px;
            text-align: center;
        }

        .checkout-btn {
            width: 100%;
            background-color: #ED1B25;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .checkout-btn:hover {
            background-color: #d41420;
        }

        /* No Food Items State */
        .no-food-message {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-food-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            opacity: 0.5;
        }

        /* Desktop improvements */
        @media (min-width: 768px) {
            .restaurant-container {
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                margin: 20px auto;
                border-radius: 16px;
                overflow: hidden;
            }

            .cover-image {
                height: 250px;
            }

            .restaurant-info {
                padding: 30px;
            }

            .profile-image {
                left: 30px;
            }

            .restaurant-details {
                margin-top: 60px;
            }

            .search-container {
                padding: 0 30px;
            }

            .food-section {
                padding: 0 30px 40px 30px;
            }

            .food-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 25px;
            }

            .food-item {
                padding: 16px;
            }

            .food-name {
                font-size: 9px;
            }

            .food-price {
                font-size: 15px;
            }

            .additions-panel {
                max-width: 500px;
                left: 50%;
                transform: translateX(-50%) translateY(100%);
            }

            .additions-overlay.active .additions-panel {
                transform: translateX(-50%) translateY(0);
            }
        }

        /* Mobile responsive */
        @media (max-width: 480px) {
            .restaurant-header {
                top: 15px;
                left: 15px;
                right: 15px;
            }

            .restaurant-info {
                padding: 15px;
            }

            .profile-image {
                left: 15px;
            }

            .search-container {
                padding: 0 15px;
            }

            .food-section {
                padding: 0 15px 40px 15px;
            }

            .food-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .meta-left {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }

            .meta-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .distance-item {
                align-self: flex-end;
                margin-top: -30px;
            }
        }

        /* Enhanced Cart Container with Collapse/Expand functionality */
        .cart-container-sticky {
            position: fixed;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 350px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 900; /* Lower than additions overlay (1100) */
            transition: all 0.3s ease;
            border: 2px solid #ED1B26;
            max-height: 50vh;
            display: flex;
            flex-direction: column;
            animation: slideUpCart 0.3s ease;
        }

        @keyframes slideUpCart {
            from {
                transform: translateY(100%);
            }
            to {
                transform: translateY(0);
            }
        }

        /* Collapsed state */
        .cart-container-sticky.collapsed {
            width: 280px;
            height: auto;
        }

        .cart-sticky-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
        }

        .cart-sticky-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #000;
            margin: 0;
        }

        .cart-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cart-toggle-btn {
            background: none;
            border: none;
            color: #ED1B26;
            cursor: pointer;
            font-size: 18px;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .cart-toggle-btn:hover {
            background: #f0f0f0;
        }

        .cart-items-sticky {
            flex: 1;
            overflow-y: auto;
            padding: 12px 20px;
            max-height: 250px;
            transition: all 0.3s ease;
        }

        .cart-container-sticky.collapsed .cart-items-sticky {
            display: none;
        }

        .cart-item-sticky {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .cart-item-sticky:last-child {
            border-bottom: none;
        }

        .cart-item-details-sticky {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name-sticky {
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-addons-sticky {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }

        .addon-tag-sticky {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
        }

        .cart-item-notes-sticky {
            font-size: 11px;
            color: #666;
            font-style: italic;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-controls-sticky {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .quantity-btn-sticky {
            width: 22px;
            height: 22px;
            border: 1px solid #e9ecef;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 500;
            font-size: 12px;
            transition: all 0.2s ease;
        }

        .quantity-btn-sticky:hover:not(:disabled) {
            background: #f8f9fa;
            border-color: #ED1B26;
        }

        .quantity-btn-sticky:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .quantity-sticky {
            min-width: 16px;
            text-align: center;
            font-weight: 500;
            font-size: 12px;
        }

        .cart-item-price-sticky {
            font-weight: 600;
            color: #ED1B26;
            font-size: 13px;
            min-width: 60px;
            text-align: right;
        }

        .remove-item-sticky {
            width: 18px;
            height: 18px;
            border: none;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            transition: background-color 0.2s ease;
        }

        .remove-item-sticky:hover {
            background: #c82333;
        }

        .cart-footer-sticky {
            padding: 16px 20px;
            border-top: 1px solid #e9ecef;
            background: white;
            border-radius: 0 0 10px 10px;
        }

        .cart-container-sticky.collapsed .cart-footer-sticky {
            padding: 12px 20px;
        }

        .cart-summary-collapsed {
            display: none;
            text-align: center;
        }

        .cart-container-sticky.collapsed .cart-summary-collapsed {
            display: block;
        }

        .cart-container-sticky.collapsed .checkout-button-sticky {
            display: none;
        }

        .view-items-btn {
            background: none;
            border: 1px solid #ED1B26;
            color: #ED1B26;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .view-items-btn:hover {
            background: #ED1B26;
            color: white;
        }

        .checkout-button-sticky {
            width: 100%;
            background: #ED1B26;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .checkout-button-sticky:hover {
            background: #d41420;
        }

        .clear-cart-btn {
            background: none;
            border: 1px solid #dc3545;
            color: #dc3545;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .clear-cart-btn:hover {
            background: #dc3545;
            color: white;
        }

        .special-instructions {
            margin: 20px 0;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #000;
        }

        .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #ED1B26;
        }

        /* Loading overlay */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        #loadingOverlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        /* No search results styling */
        .no-search-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .no-results-icon {
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .view-all-menu-btn {
            background: #ED1B26;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .view-all-menu-btn:hover {
            background: #d41420;
        }

        /* Mobile responsive adjustments for enhanced cart */
        @media (max-width: 768px) {
            .cart-container-sticky {
                position: fixed;
                right: 10px;
                left: 10px;
                width: auto;
                bottom: 20px;
                top: auto;
                transform: none;
                max-height: 50vh;
            }
            
            .cart-container-sticky.collapsed {
                max-height: 80px;
            }
            
            .cart-sticky-header {
                padding: 12px 16px;
            }
            
            .cart-items-sticky {
                padding: 8px 16px;
            }
            
            .cart-footer-sticky {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading State -->
    <div class="loading-container" id="loadingContainer">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading restaurant details...</div>
    </div>

    <!-- Error State -->
    <div class="error-container" id="errorContainer" style="display: none;">
        <div class="error-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="#ED1B25" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="error-title">Restaurant Not Found</div>
        <div class="error-message" id="errorMessage">The restaurant you're looking for could not be found.</div>
        <button class="error-button" onclick="goToHome()">Back to Home</button>
    </div>

    <!-- Main Restaurant Content -->
    <div class="restaurant-container" id="restaurantContainer" style="display: none;">
        <!-- Header -->
        <div class="restaurant-header">
            <button class="back-btn" onclick="goBack()">
                <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="30" height="30" rx="15" fill="#A5A5A5"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12.3435 15L19.4145 22.071L18.0005 23.485L10.2225 15.707C10.035 15.5195 9.92969 15.2652 9.92969 15C9.92969 14.7348 10.035 14.4805 10.2225 14.293L18.0005 6.51501L19.4145 7.92901L12.3435 15Z" fill="white"/>
                </svg>
            </button>

            <button class="favorite-btn" id="favoriteBtn" onclick="toggleFavorite()">
                <svg width="20" height="19" viewBox="0 0 20 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 19L8.55 17.7C6.86667 16.1833 5.475 14.875 4.375 13.775C3.275 12.675 2.4 11.6873 1.75 10.812C1.1 9.93666 0.646 9.13266 0.388 8.39999C0.13 7.66733 0.000666667 6.91733 0 6.14999C0 4.58333 0.525 3.27499 1.575 2.22499C2.625 1.17499 3.93333 0.649994 5.5 0.649994C6.36667 0.649994 7.19167 0.833327 7.975 1.19999C8.75833 1.56666 9.43333 2.08333 10 2.74999C10.5667 2.08333 11.2417 1.56666 12.025 1.19999C12.8083 0.833327 13.6333 0.649994 14.5 0.649994C16.0667 0.649994 17.375 1.17499 18.425 2.22499C19.475 3.27499 20 4.58333 20 6.14999C20 6.91666 19.871 7.66666 19.613 8.39999C19.355 9.13333 18.9007 9.93733 18.25 10.812C17.5993 11.6867 16.7243 12.6743 15.625 13.775C14.5257 14.8757 13.134 16.184 11.45 17.7L10 19ZM10 16.3C11.6 14.8667 12.9167 13.6377 13.95 12.613C14.9833 11.5883 15.8 10.6967 16.4 9.93799C17 9.17933 17.4167 8.50399 17.65 7.91199C17.8833 7.31999 18 6.73266 18 6.14999C18 5.14999 17.6667 4.31666 17 3.64999C16.3333 2.98333 15.5 2.64999 14.5 2.64999C13.7167 2.64999 12.9917 2.87066 12.325 3.31199C11.6583 3.75333 11.2 4.31599 10.95 4.99999H9.05C8.8 4.31666 8.34167 3.75433 7.675 3.31299C7.00833 2.87166 6.28333 2.65066 5.5 2.64999C4.5 2.64999 3.66667 2.98333 3 3.64999C2.33333 4.31666 2 5.14999 2 6.14999C2 6.73333 2.11667 7.32099 2.35 7.91299C2.58333 8.50499 3 9.17999 3.6 9.93799C4.2 10.696 5.01667 11.5877 6.05 12.613C7.08333 13.6383 8.4 14.8673 10 16.3Z" fill="black"/>
                </svg>
            </button>
        </div>

        <!-- Cover Image -->
        <div class="cover-image" id="coverImage"></div>

        <!-- Restaurant Info -->
        <div class="restaurant-info">
            <div class="profile-image" id="profileImage"></div>
            
            <div class="restaurant-details">
                <h1 class="restaurant-name" id="restaurantName">Loading...</h1>
                
                <div class="restaurant-meta" id="restaurantMeta">
                    <!-- Meta information will be populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Search Box -->
        <div class="search-container">
            <input type="text" class="search-box" placeholder="Search for food..." id="searchBox" onkeyup="searchFood()">
        </div>

        <!-- Food Section -->
        <div class="food-section" id="foodSection">
            <!-- Food categories and items will be populated by JavaScript -->
        </div>
    </div>

    <!-- Food Additions Overlay -->
    <div class="additions-overlay" id="additionsOverlay">
        <div class="additions-panel">
            <div class="additions-header">
                <h2 class="additions-title">Customize Your Order</h2>
                <button class="close-btn" onclick="closeAdditions()">&times;</button>
            </div>

            <div class="selected-food">
                <div class="selected-food-name" id="selectedFoodName"></div>
                <div class="selected-food-price" id="selectedFoodPrice"></div>
            </div>

            <div id="additionsContainer">
                <!-- Addons will be populated dynamically -->
            </div>

            <div class="quantity-controls">
                <span class="quantity-label">Quantity:</span>
                <button class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                <span class="quantity-display" id="quantityDisplay">1</span>
                <button class="quantity-btn" onclick="changeQuantity(1)">+</button>
            </div>

            <div class="checkout-section">
                <div class="total-price" id="totalPrice">Total: ₦0</div>
                <button class="checkout-btn" onclick="addItemToCart()">Add to Cart</button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        const API_BASE_URL = 'http://localhost/Ferodash/farodash-vendors';
        const RESTAURANT_ID = <?php echo json_encode($restaurant_id); ?>;
        const USER_LAT = <?php echo json_encode($user_lat); ?>;
        const USER_LNG = <?php echo json_encode($user_lng); ?>;
        
        let restaurantData = null;
        let foodData = [];
        let allFoodItems = [];
        let isFavorite = false;
        let currentFood = {};
        let selectedAdditions = [];
        let quantity = 1;
        let basePrice = 0;
        let cartContainer = null;
        let cartItems = [];


        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadRestaurantData();
            // Check favorite status on page load
            if (RESTAURANT_ID) {
                checkFavoriteStatus();
            }
            createCartContainer();
            loadExistingCart();
        });


        async function loadExistingCart() {
            try {
                const response = await fetch('../api/cart.php');
                const data = await response.json();
                
                if (data.success && data.cart.length > 0) {
                    cartItems = data.cart;
                    updateCartDisplay();
                    document.getElementById('cartContainer').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading cart:', error);
            }
        }


        async function clearCart() {
            if (!confirm('Are you sure you want to clear your cart?')) return;
            
            try {
                const response = await fetch('../api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear' })
                });
                
                const result = await response.json();
                if (result.success) {
                    cartItems = [];
                    updateCartDisplay();
                    showNotification('Cart cleared', 'success');
                }
            } catch (error) {
                showNotification('Error clearing cart', 'error');
            }
        }


        async function updateCartQuantity(index, change) {
            const item = cartItems[index];
            const newQuantity = parseInt(item.quantity) + change;
            
            if (newQuantity <= 0) {
                removeCartItem(index);
                return;
            }
            
            try {
                const response = await fetch('../api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_quantity',
                        cart_item_id: item.id,
                        quantity: newQuantity
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    item.quantity = newQuantity;
                    updateCartDisplay();
                }
            } catch (error) {
                console.error('Error updating quantity:', error);
            }
        }

        async function removeCartItem(index) {
            const item = cartItems[index];
            
            try {
                const response = await fetch('../api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'remove_item',
                        cart_item_id: item.id
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    cartItems.splice(index, 1);
                    updateCartDisplay();
                }
            } catch (error) {
                console.error('Error removing item:', error);
            }
        }

        // Load restaurant data and food items
        async function loadRestaurantData() {
            try {
                // Show loading state
                document.getElementById('loadingContainer').style.display = 'flex';
                document.getElementById('restaurantContainer').style.display = 'none';
                document.getElementById('errorContainer').style.display = 'none';

                // Load restaurant details and food items
                const [restaurantSuccess, foodSuccess] = await Promise.all([
                    loadRestaurantDetails(),
                    loadFoodItems()
                ]);
                
                if (restaurantSuccess) {
                    document.getElementById('loadingContainer').style.display = 'none';
                    document.getElementById('restaurantContainer').style.display = 'block';
                    
                    if (!foodSuccess) {
                        // Show restaurant but indicate no menu
                        renderNoFoodItems();
                    }
                } else {
                    throw new Error('Failed to load restaurant details');
                }
            } catch (error) {
                console.error('Error loading restaurant data:', error);
                showError('Failed to load restaurant. Please try again later.');
            }
        }


        // Load restaurant details from API
        async function loadRestaurantDetails() {
            try {
                let apiUrl = `${API_BASE_URL}/restaurants.php?endpoint=details&id=${RESTAURANT_ID}`;
                
                if (USER_LAT && USER_LNG) {
                    apiUrl += `&lat=${USER_LAT}&lng=${USER_LNG}`;
                }

                console.log('Fetching restaurant details from:', apiUrl);

                const response = await fetch(apiUrl);
                const data = await response.json();

                console.log('Restaurant API Response:', data);

                if (data.success && data.data) {
                    restaurantData = data.data;
                    renderRestaurantInfo(restaurantData);
                    return true;
                } else {
                    throw new Error(data.message || 'Restaurant not found');
                }
            } catch (error) {
                console.error('Error loading restaurant details:', error);
                return false;
            }
        }

        // Load food items from API
        async function loadFoodItems() {
            try {
                const response = await fetch(`/api/food_proxy.php?restaurant_id=${RESTAURANT_ID}`);
                const data = await response.json();

                if (data.success && data.data && data.data.length > 0) {
                    foodData = data.data;
                    allFoodItems = []; // Reset for search functionality
                    
                    // Flatten items for search
                    foodData.forEach(category => {
                        if (category.items && category.items.length > 0) {
                            category.items.forEach(item => {
                                allFoodItems.push({...item, category: category.category_name});
                            });
                        }
                    });
                    
                    renderFoodItems(foodData);
                    return true;
                } else {
                    renderNoFoodItems();
                    return true; // Still return true so restaurant info shows
                }
            } catch (error) {
                console.error('Error loading food items:', error);
                renderNoFoodItems();
                return true; // Still return true so restaurant info shows
            }
        }

        // Render restaurant information
        function renderRestaurantInfo(restaurant) {
            // Update page title
            document.title = `${restaurant.name} - FaroDash`;

            // Set restaurant name
            document.getElementById('restaurantName').textContent = restaurant.name;

            // Set cover image
            const coverImage = document.getElementById('coverImage');
            if (restaurant.cover_image_url) {
                coverImage.style.backgroundImage = `url('${restaurant.cover_image_url}')`;
            }

            // Set profile image
            const profileImage = document.getElementById('profileImage');
            if (restaurant.logo_url) {
                profileImage.style.backgroundImage = `url('${restaurant.logo_url}')`;
            }

            // Build meta information
            const metaContainer = document.getElementById('restaurantMeta');
            const deliveryTime = restaurant.delivery_time ? `${restaurant.delivery_time}mins` : '20-30mins';
            const distance = restaurant.distance ? `${restaurant.distance.toFixed(2)} km away` : '';

            metaContainer.innerHTML = `
                <div class="meta-row">
                    <div class="meta-left">
                        <div class="meta-item">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.5 2.25V1.6875C1.35082 1.6875 1.20774 1.74676 1.10225 1.85225C0.996763 1.95774 0.9375 2.10082 0.9375 2.25H1.5ZM9.75 2.25H10.3125C10.3125 2.10082 10.2532 1.95774 10.1477 1.85225C10.0423 1.74676 9.89918 1.6875 9.75 1.6875V2.25ZM9.75 6.75V6.1875C9.60082 6.1875 9.45774 6.24676 9.35225 6.35225C9.24676 6.45774 9.1875 6.60082 9.1875 6.75H9.75ZM1.5 2.8125H9.75V1.6875H1.5V2.8125ZM9.1875 2.25V14.25H10.3125V2.25H9.1875ZM2.0625 12.75V2.25H0.9375V12.75H2.0625ZM9.75 7.3125H13.5V6.1875H9.75V7.3125ZM15.9375 9.75V12.75H17.0625V9.75H15.9375ZM10.3125 14.25V6.75H9.1875V14.25H10.3125ZM14.163 14.913C14.0759 15.0001 13.9726 15.0692 13.8588 15.1163C13.7451 15.1634 13.6231 15.1877 13.5 15.1877C13.3769 15.1877 13.2549 15.1634 13.1412 15.1163C13.0274 15.0692 12.9241 15.0001 12.837 14.913L12.042 15.708C12.4288 16.0948 12.9534 16.3121 13.5004 16.3121C14.0474 16.3121 14.572 16.0948 14.9587 15.708L14.163 14.913ZM12.837 13.587C12.9241 13.4999 13.0274 13.4308 13.1412 13.3837C13.2549 13.3366 13.3769 13.3123 13.5 13.3123C13.6231 13.3123 13.7451 13.3366 13.8588 13.3837C13.9726 13.4308 14.0759 13.4999 14.163 13.587L14.958 12.792C14.5712 12.4052 14.0466 12.1879 13.4996 12.1879C12.9526 12.1879 12.428 12.4052 12.0413 12.792L12.837 13.587ZM5.163 14.913C5.07594 15.0001 4.97258 15.0692 4.85882 15.1163C4.74506 15.1634 4.62314 15.1877 4.5 15.1877C4.37686 15.1877 4.25494 15.1634 4.14118 15.1163C4.02742 15.0692 3.92406 15.0001 3.837 14.913L3.042 15.708C3.42879 16.0948 3.95338 16.3121 4.50038 16.3121C5.04737 16.3121 5.57196 16.0948 5.95875 15.708L5.163 14.913ZM3.837 13.587C3.92406 13.4999 4.02742 13.4308 4.14118 13.3837C4.25494 13.3366 4.37686 13.3123 4.5 13.3123C4.62314 13.3123 4.74506 13.3366 4.85882 13.3837C4.97258 13.4308 5.07594 13.4999 5.163 13.587L5.958 12.792C5.57121 12.4052 5.04662 12.1879 4.49962 12.1879C3.95263 12.1879 3.42804 12.4052 3.04125 12.792L3.837 13.587ZM14.163 13.587C14.346 13.77 14.4375 14.0092 14.4375 14.25H15.5625C15.5625 13.7227 15.3608 13.194 14.9587 12.7913L14.163 13.587ZM14.4375 14.25C14.4375 14.4908 14.346 14.73 14.163 14.913L14.9587 15.708C15.1506 15.5168 15.3021 15.2896 15.4058 15.0393C15.5096 14.7891 15.5628 14.5209 15.5625 14.25H14.4375ZM12 13.6875H9.75V14.8125H12V13.6875ZM12.837 14.913C12.7496 14.8262 12.6804 14.7229 12.6332 14.609C12.5861 14.4952 12.5621 14.3732 12.5625 14.25H11.4375C11.4375 14.7773 11.6392 15.306 12.0413 15.7087L12.837 14.913ZM12.5625 14.25C12.5625 14.0092 12.654 13.77 12.837 13.587L12.0413 12.792C11.8494 12.9832 11.6979 13.2104 11.5942 13.4607C11.4904 13.7109 11.4372 13.9791 11.4375 14.25H12.5625ZM3.837 14.913C3.74962 14.8262 3.68035 14.7229 3.63323 14.609C3.58611 14.4952 3.56207 14.3732 3.5625 14.25H2.4375C2.4375 14.7773 2.63925 15.306 3.04125 15.7087L3.837 14.913ZM3.5625 14.25C3.5625 14.0092 3.654 13.77 3.837 13.587L3.042 12.792C2.85012 12.9832 2.69793 13.2104 2.59419 13.4607C2.49045 13.7109 2.4372 13.9791 2.4375 14.25H3.5625ZM9.75 13.6875H6V14.8125H9.75V13.6875ZM5.163 13.587C5.346 13.77 5.4375 14.0092 5.4375 14.25H6.5625C6.5625 13.7227 6.36075 13.194 5.95875 12.7913L5.163 13.587ZM5.4375 14.25C5.4375 14.4908 5.346 14.73 5.163 14.913L5.958 15.708C6.14988 15.5168 6.30207 15.2896 6.40581 15.0393C6.50955 14.7891 6.5628 14.5209 6.5625 14.25H5.4375ZM15.9375 12.75C15.9375 13.2675 15.5175 13.6875 15 13.6875V14.8125C15.547 14.8125 16.0716 14.5952 16.4584 14.2084C16.8452 13.8216 17.0625 13.297 17.0625 12.75H15.9375ZM13.5 7.3125C14.1465 7.3125 14.7665 7.56931 15.2236 8.02643C15.6807 8.48355 15.9375 9.10353 15.9375 9.75H17.0625C17.0625 8.80517 16.6872 7.89903 16.0191 7.23093C15.351 6.56283 14.4448 6.1875 13.5 6.1875V7.3125ZM0.9375 12.75C0.9375 13.297 1.1548 13.8216 1.54159 14.2084C1.92839 14.5952 2.45299 14.8125 3 14.8125V13.6875C2.4825 13.6875 2.0625 13.2675 2.0625 12.75H0.9375Z" fill="#ED1B25"/>
                            </svg>
                            <span class="meta-text">Delivery, ${deliveryTime}</span>
                        </div>
                    </div>
                    ${distance ? `<div class="distance-item">
                        <svg width="16" height="21" viewBox="0 0 16 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.262 20.134C7.262 20.134 0 14.018 0 8C0 5.87827 0.842855 3.84344 2.34315 2.34315C3.84344 0.842855 5.87827 0 8 0C10.1217 0 12.1566 0.842855 13.6569 2.34315C15.1571 3.84344 16 5.87827 16 8C16 14.018 8.738 20.134 8.738 20.134C8.334 20.506 7.669 20.502 7.262 20.134ZM8 11.5C8.45963 11.5 8.91475 11.4095 9.33939 11.2336C9.76403 11.0577 10.1499 10.7999 10.4749 10.4749C10.7999 10.1499 11.0577 9.76403 11.2336 9.33939C11.4095 8.91475 11.5 8.45963 11.5 8C11.5 7.54037 11.4095 7.08525 11.2336 6.66061C11.0577 6.23597 10.7999 5.85013 10.4749 5.52513C10.1499 5.20012 9.76403 4.94231 9.33939 4.76642C8.91475 4.59053 8.45963 4.5 8 4.5C7.07174 4.5 6.1815 4.86875 5.52513 5.52513C4.86875 6.1815 4.5 7.07174 4.5 8C4.5 8.92826 4.86875 9.8185 5.52513 10.4749C6.1815 11.1313 7.07174 11.5 8 11.5Z" fill="#ED1B25"/>
                        </svg>
                        <span class="meta-text">${distance}</span>
                    </div>` : ''}
                </div>
                
                <div class="meta-item">
                    <svg width="18" height="17" viewBox="0 0 18 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8.55696 14.109L12.707 16.619C13.467 17.079 14.397 16.399 14.197 15.539L13.097 10.819L16.767 7.639C17.437 7.059 17.077 5.959 16.197 5.889L11.367 5.479L9.47696 1.019C9.13696 0.208999 7.97696 0.208999 7.63696 1.019L5.74696 5.469L0.916957 5.879C0.0369575 5.949 -0.323043 7.049 0.346957 7.629L4.01696 10.809L2.91696 15.529C2.71696 16.389 3.64696 17.069 4.40696 16.609L8.55696 14.109Z" fill="#ED1B25"/>
                    </svg>
                    <span class="meta-text">${restaurant.rating} Reviews</span>
                </div>
            `;
        }

        // Render food items by category
        function renderFoodItems(foodCategories) {
            const foodSection = document.getElementById('foodSection');
            
            if (!foodCategories || foodCategories.length === 0) {
                renderNoFoodItems();
                return;
            }

            let foodHTML = '';
            allFoodItems = []; // Reset for search functionality

            foodCategories.forEach(category => {
                if (category.items && category.items.length > 0) {
                    foodHTML += `
                        <div class="category-section">
                            <h3 class="category-title">${category.category_name}</h3>
                            <div class="food-grid">
                    `;

                    category.items.forEach(item => {
                        // Add to search array
                        allFoodItems.push({...item, category: category.category_name});

                        const imageUrl = item.image_url || 'images/food.png';
                        const formattedPrice = `₦${parseFloat(item.price).toLocaleString()}`;

                        foodHTML += `
                            <div class="food-item" data-food-id="${item.id}">
                                <div class="food-image" style="background-image: url('${imageUrl}');"></div>
                                <div class="food-name">${item.name}</div>
                                <div class="food-price">${formattedPrice}</div>
                                <button class="add-btn" onclick="openAdditions('${item.id}', '${item.name.replace(/'/g, "\\'")}', ${item.price}, ${JSON.stringify(item.addons || []).replace(/"/g, '&quot;')})">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 5V19M5 12H19" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        `;
                    });

                    foodHTML += `
                            </div>
                        </div>
                    `;
                }
            });

            foodSection.innerHTML = foodHTML;
        }

        // Render no food items message
        function renderNoFoodItems() {
            const foodSection = document.getElementById('foodSection');
            foodSection.innerHTML = `
                <div class="no-food-message">
                    <svg class="no-food-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="#ccc"/>
                    </svg>
                    <h3>No Menu Available</h3>
                    <p>This restaurant hasn't uploaded their menu yet. Please check back later or contact the restaurant directly.</p>
                </div>
            `;
        }

        // Search functionality
        function searchFood() {
            const searchTerm = document.getElementById('searchBox').value.toLowerCase().trim();
            
            if (!searchTerm) {
                // Show all items
                renderFoodItems(foodData);
                return;
            }

            // Filter items based on search term
            const filteredCategories = [];
            let totalResults = 0;

            foodData.forEach(category => {
                const filteredItems = category.items.filter(item => 
                    item.name.toLowerCase().includes(searchTerm) ||
                    (item.description && item.description.toLowerCase().includes(searchTerm)) ||
                    category.category_name.toLowerCase().includes(searchTerm)
                );
                
                if (filteredItems.length > 0) {
                    filteredCategories.push({
                        ...category,
                        items: filteredItems
                    });
                    totalResults += filteredItems.length;
                }
            });

            if (totalResults === 0) {
                renderNoSearchResults(searchTerm);
            } else {
                renderFoodItems(filteredCategories);
            }
        }

        function renderNoSearchResults(searchTerm) {
            const foodSection = document.getElementById('foodSection');
            foodSection.innerHTML = `
                <div class="no-search-results">
                    <div class="no-results-icon">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.5 14H14.71L14.43 13.73C15.41 12.59 16 11.11 16 9.5C16 5.91 13.09 3 9.5 3S3 5.91 3 9.5S5.91 16 9.5 16C11.11 16 12.59 15.41 13.73 14.43L14 14.71V15.5L19 20.49L20.49 19L15.5 14ZM9.5 14C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5S14 7.01 14 9.5S11.99 14 9.5 14Z" fill="#ccc"/>
                            <path d="M7 9H12V10H7V9Z" fill="#ccc"/>
                        </svg>
                    </div>
                    <h3>No Results Found</h3>
                    <p>We couldn't find any items matching "<strong>${searchTerm}</strong>"</p>
                    <button class="view-all-menu-btn" onclick="clearSearch()">View All Menu</button>
                </div>
            `;
        }

        // Add function to clear search
        function clearSearch() {
            document.getElementById('searchBox').value = '';
            renderFoodItems(foodData);
        }

        // Show error state
        function showError(message) {
            document.getElementById('loadingContainer').style.display = 'none';
            document.getElementById('restaurantContainer').style.display = 'none';
            document.getElementById('errorContainer').style.display = 'flex';
            document.getElementById('errorMessage').textContent = message;
        }

        // Navigation functions
        function goBack() {
            history.back();
        }

        function goToHome() {
            window.location.href = 'index.php';
        }

        // Favorites functionality - API-based
        async function toggleRestaurantFavorite(restaurantId) {
            try {
                const response = await fetch('api/favorites.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'toggle',
                        restaurant_id: restaurantId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update heart icon
                    updateFavoriteIcon(result.is_favorite);
                    
                    // Show feedback
                    showNotification(result.message);
                } else {
                    showNotification(result.message || 'Failed to update favorite', 'error');
                }
            } catch (error) {
                console.error('Error toggling favorite:', error);
                showNotification('Failed to update favorite', 'error');
            }
        }

        function updateFavoriteIcon(isFavorite) {
            const favoriteBtn = document.getElementById('favoriteBtn');
            if (isFavorite) {
                favoriteBtn.classList.add('active');
                favoriteBtn.innerHTML = `
                    <svg width="20" height="19" viewBox="0 0 20 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 18.3375C9.76667 18.3375 9.52933 18.2958 9.288 18.2125C9.04667 18.1292 8.834 17.9958 8.65 17.8125L6.925 16.2375C5.15833 14.6208 3.56233 13.0168 2.137 11.4255C0.711667 9.83417 -0.000666199 8.07984 4.67508e-07 6.16251C4.67508e-07 4.59584 0.525001 3.28751 1.575 2.23751C2.625 1.18751 3.93333 0.662506 5.5 0.662506C6.38333 0.662506 7.21667 0.849839 8 1.22451C8.78333 1.59917 9.45 2.11184 10 2.76251C10.55 2.11251 11.2167 1.60017 12 1.22551C12.7833 0.850839 13.6167 0.663173 14.5 0.662506C16.0667 0.662506 17.375 1.18751 18.425 2.23751C19.475 3.28751 20 4.59584 20 6.16251C20 8.07917 19.2917 9.83751 17.875 11.4375C16.4583 13.0375 14.85 14.6458 13.05 16.2625L11.35 17.8125C11.1667 17.9958 10.9543 18.1292 10.713 18.2125C10.4717 18.2958 10.234 18.3375 10 18.3375Z" fill="#ED1B25"/>
                    </svg>
                `;
            } else {
                favoriteBtn.classList.remove('active');
                favoriteBtn.innerHTML = `
                    <svg width="20" height="19" viewBox="0 0 20 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 19L8.55 17.7C6.86667 16.1833 5.475 14.875 4.375 13.775C3.275 12.675 2.4 11.6873 1.75 10.812C1.1 9.93666 0.646 9.13266 0.388 8.39999C0.13 7.66733 0.000666667 6.91733 0 6.14999C0 4.58333 0.525 3.27499 1.575 2.22499C2.625 1.17499 3.93333 0.649994 5.5 0.649994C6.36667 0.649994 7.19167 0.833327 7.975 1.19999C8.75833 1.56666 9.43333 2.08333 10 2.74999C10.5667 2.08333 11.2417 1.56666 12.025 1.19999C12.8083 0.833327 13.6333 0.649994 14.5 0.649994C16.0667 0.649994 17.375 1.17499 18.425 2.22499C19.475 3.27499 20 4.58333 20 6.14999C20 6.91666 19.871 7.66666 19.613 8.39999C19.355 9.13333 18.9007 9.93733 18.25 10.812C17.5993 11.6867 16.7243 12.6743 15.625 13.775C14.5257 14.8757 13.134 16.184 11.45 17.7L10 19ZM10 16.3C11.6 14.8667 12.9167 13.6377 13.95 12.613C14.9833 11.5883 15.8 10.6967 16.4 9.93799C17 9.17933 17.4167 8.50399 17.65 7.91199C17.8833 7.31999 18 6.73266 18 6.14999C18 5.14999 17.6667 4.31666 17 3.64999C16.3333 2.98333 15.5 2.64999 14.5 2.64999C13.7167 2.64999 12.9917 2.87066 12.325 3.31199C11.6583 3.75333 11.2 4.31599 10.95 4.99999H9.05C8.8 4.31666 8.34167 3.75433 7.675 3.31299C7.00833 2.87166 6.28333 2.65066 5.5 2.64999C4.5 2.64999 3.66667 2.98333 3 3.64999C2.33333 4.31666 2 5.14999 2 6.14999C2 6.73333 2.11667 7.32099 2.35 7.91299C2.58333 8.50499 3 9.17999 3.6 9.93799C4.2 10.696 5.01667 11.5877 6.05 12.613C7.08333 13.6383 8.4 14.8673 10 16.3Z" fill="black"/>
                    </svg>
                `;
            }
        }

        function showNotification(message, type = 'success') {
            // Simple notification - you can enhance this
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                transition: all 0.3s ease;
                ${type === 'success' ? 'background-color: #28a745;' : 'background-color: #dc3545;'}
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Updated toggleFavorite function to use API
        function toggleFavorite() {
            if (RESTAURANT_ID) {
                toggleRestaurantFavorite(RESTAURANT_ID);
            }
        }

        // Check if restaurant is favorited on page load
        async function checkFavoriteStatus() {
            try {
                const response = await fetch(`api/favorites.php?restaurant_id=${RESTAURANT_ID}`);
                const result = await response.json();
                
                if (result.success) {
                    updateFavoriteIcon(result.is_favorite);
                }
            } catch (error) {
                console.error('Error checking favorite status:', error);
            }
        }

        async function addItemToCart() {
            if (!RESTAURANT_ID || !currentFood.id) {
                showNotification('Invalid item selection', 'error');
                return;
            }

            const additionsTotal = selectedAdditions.reduce((sum, item) => sum + item.price, 0);
            const specialInstructions = document.getElementById('specialInstructions')?.value || '';

            try {
                showLoading('Adding to cart...');
                
                const response = await fetch('api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add',
                        restaurant_id: RESTAURANT_ID,
                        food_item_id: currentFood.id,
                        quantity: quantity,
                        unit_price: basePrice,
                        addons: selectedAdditions,
                        special_instructions: specialInstructions
                    })
                });
                
                const result = await response.json();
                hideLoading();

                if (result.success) {
                    showNotification('Item added to your cart!', 'success');
                    closeAdditions();
                    loadExistingCart(); // Refresh cart display
                } else {
                    showNotification(result.message || 'Could not add item.', 'error');
                }
            } catch (error) {
                hideLoading();
                showNotification('An error occurred.', 'error');
                console.error('Error adding to cart:', error);
            }
        }

        // Open additions modal
        function openAdditions(foodId, foodName, price, addons) {
            try {
                const parsedAddons = typeof addons === 'string' ? JSON.parse(addons) : addons;
                
                currentFood = { id: foodId, name: foodName, price: price, addons: parsedAddons || [] };
                basePrice = parseFloat(price);
                selectedAdditions = [];
                quantity = 1;

                document.getElementById('selectedFoodName').textContent = foodName;
                document.getElementById('selectedFoodPrice').textContent = `₦${basePrice.toLocaleString()}`;
                document.getElementById('quantityDisplay').textContent = quantity;
                
                renderAddons(currentFood.addons);
                updateTotalPrice();
                
                // Add special instructions field if it doesn't exist
                let instructionsContainer = document.getElementById('specialInstructionsContainer');
                if (!instructionsContainer) {
                    const instructionsHTML = `
                        <div id="specialInstructionsContainer" class="special-instructions">
                            <label for="specialInstructions" class="form-label">Special Instructions (Optional)</label>
                            <textarea id="specialInstructions" class="form-textarea" placeholder="Any special requests for this item..." rows="3"></textarea>
                        </div>
                    `;
                    document.getElementById('additionsContainer').insertAdjacentHTML('afterend', instructionsHTML);
                }

                // Clear previous instructions
                document.getElementById('specialInstructions').value = '';
                
                document.getElementById('additionsOverlay').classList.add('active');
            } catch (error) {
                console.error('Error opening additions:', error);
                openAdditions(foodId, foodName, price, []);
            }
        }


        // Render addons in categories
        function renderAddons(addons) {
            const container = document.getElementById('additionsContainer');
            
            if (!addons || addons.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No additions available for this item.</p>';
                return;
            }

            // Group addons by category (if available) or create default categories
            const categorizedAddons = groupAddonsByCategory(addons);
            
            let addonsHTML = '';
            
            Object.keys(categorizedAddons).forEach(categoryName => {
                addonsHTML += `
                    <div class="additions-section">
                        <h3 class="section-title">${categoryName}</h3>
                        <div class="additions-grid">
                `;
                
                categorizedAddons[categoryName].forEach(addon => {
                    addonsHTML += `
                        <div class="addition-item" onclick="toggleAddition(this, '${addon.name.replace(/'/g, "\\'")}', ${addon.price}, '${addon.id}')">
                            <div class="addition-info">
                                <div class="addition-name">${addon.name}</div>
                                <div class="addition-price">+₦${parseFloat(addon.price).toLocaleString()}</div>
                            </div>
                        </div>
                    `;
                });
                
                addonsHTML += `
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = addonsHTML;
        }

        // Group addons by category
        function groupAddonsByCategory(addons) {
            const categorized = {};
            
            addons.forEach(addon => {
                // Use addon category if available, otherwise categorize by name
                let category = addon.category || 'Extras';
                
                // Simple categorization based on name if no category
                if (category === 'Extras') {
                    const name = addon.name.toLowerCase();
                    if (name.includes('chicken') || name.includes('beef') || name.includes('fish') || name.includes('turkey') || name.includes('meat')) {
                        category = 'Protein Additions';
                    } else if (name.includes('drink') || name.includes('water') || name.includes('juice') || name.includes('soda') || name.includes('yogurt')) {
                        category = 'Drinks';
                    } else if (name.includes('salad') || name.includes('coleslaw') || name.includes('plantain') || name.includes('moi moi') || name.includes('side')) {
                        category = 'Sides & Extras';
                    }
                }
                
                if (!categorized[category]) {
                    categorized[category] = [];
                }
                categorized[category].push(addon);
            });
            
            return categorized;
        }

        // Close additions modal
        function closeAdditions() {
            document.getElementById('additionsOverlay').classList.remove('active');
        }

        // Toggle addon selection
        function toggleAddition(element, name, price, id) {
            element.classList.toggle('selected');
            
            const existingIndex = selectedAdditions.findIndex(item => item.id === id);
            
            if (existingIndex > -1) {
                selectedAdditions.splice(existingIndex, 1);
            } else {
                selectedAdditions.push({ id, name, price: parseFloat(price) });
            }
            
            updateTotalPrice();
        }

        // Change quantity
        function changeQuantity(change) {
            quantity = Math.max(1, quantity + change);
            document.getElementById('quantityDisplay').textContent = quantity;
            updateTotalPrice();
        }

        // Update total price
        function updateTotalPrice() {
            const additionsTotal = selectedAdditions.reduce((sum, item) => sum + item.price, 0);
            const total = (basePrice + additionsTotal) * quantity;
            document.getElementById('totalPrice').textContent = `Total: ₦${total.toLocaleString()}`;
        }

        function proceedToCheckout() {
            if (cartItems.length === 0) {
                showNotification('Your cart is empty', 'error');
                return;
            }
            
            // Store restaurant context
            sessionStorage.setItem('checkout_restaurant_id', RESTAURANT_ID);
            sessionStorage.setItem('checkout_source', 'restaurant');
            
            window.location.href = 'checkout.php';
        }

        function showLoading(message = 'Loading...') {
            // Create loading overlay if it doesn't exist
            let loadingOverlay = document.getElementById('loadingOverlay');
            if (!loadingOverlay) {
                loadingOverlay = document.createElement('div');
                loadingOverlay.id = 'loadingOverlay';
                loadingOverlay.innerHTML = `
                    <div class="loading-content">
                        <div class="loading-spinner"></div>
                        <div class="loading-message">${message}</div>
                    </div>
                `;
                document.body.appendChild(loadingOverlay);
            }
            loadingOverlay.classList.add('active');
        }

        function hideLoading() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.remove('active');
            }
        }


        // Close additions when clicking overlay
        document.getElementById('additionsOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAdditions();
            }
        });

        // Enhanced cart functionality with collapse/expand
        let isCartCollapsed = false;

        function toggleCartCollapse() {
            const cartContainer = document.getElementById('cartContainer');
            const toggleBtn = document.querySelector('.cart-toggle-btn');
            
            isCartCollapsed = !isCartCollapsed;
            
            if (isCartCollapsed) {
                cartContainer.classList.add('collapsed');
                toggleBtn.innerHTML = '▲';
                toggleBtn.title = 'Expand cart';
            } else {
                cartContainer.classList.remove('collapsed');
                toggleBtn.innerHTML = '▼';
                toggleBtn.title = 'Collapse cart';
            }
        }

        function autoCollapseCart() {
            const cartContainer = document.getElementById('cartContainer');
            const toggleBtn = document.querySelector('.cart-toggle-btn');
            
            if (!isCartCollapsed) {
                cartContainer.classList.add('collapsed');
                toggleBtn.innerHTML = '▲';
                isCartCollapsed = true;
            }
        }

        function autoExpandCart() {
            const cartContainer = document.getElementById('cartContainer');
            const toggleBtn = document.querySelector('.cart-toggle-btn');
            
            if (isCartCollapsed) {
                cartContainer.classList.remove('collapsed');
                toggleBtn.innerHTML = '▼';
                isCartCollapsed = false;
            }
        }

        // Update createCartContainer function
        function createCartContainer() {
            if (document.getElementById('cartContainer')) return;
            
            const cartHTML = `
                <div class="cart-container-sticky" id="cartContainer" style="display: none;">
                    <div class="cart-sticky-header">
                        <h3>Your Order</h3>
                        <div class="cart-header-actions">
                            <button class="clear-cart-btn" onclick="clearCart()">Clear</button>
                            <button class="cart-toggle-btn" onclick="toggleCartCollapse()" title="Collapse cart">▼</button>
                        </div>
                    </div>
                    <div class="cart-items-sticky" id="cartItemsList">
                        <div class="empty-cart">
                            <p>Add items to start building your order</p>
                        </div>
                    </div>
                    <div class="cart-footer-sticky" id="cartFooter" style="display: none;">
                        <div class="cart-summary-collapsed">
                            <div class="cart-total" id="cartTotalCollapsed">₦0</div>
                            <button class="view-items-btn" onclick="autoExpandCart()">View Items</button>
                        </div>
                        <button class="checkout-button-sticky" onclick="proceedToCheckout()">
                            Proceed to Checkout • <span id="footerTotal">₦0</span>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', cartHTML);
        }

        // Update updateCartDisplay function to handle collapsed state
        function updateCartDisplay() {
            const cartItemsList = document.getElementById('cartItemsList');
            const cartTotal = document.getElementById('cartTotal');
            const cartTotalCollapsed = document.getElementById('cartTotalCollapsed');
            const footerTotal = document.getElementById('footerTotal');
            const cartFooter = document.getElementById('cartFooter');
            const cartContainer = document.getElementById('cartContainer');
            
            if (cartItems.length === 0) {
                cartContainer.style.display = 'none';
                return;
            }
            
            cartContainer.style.display = 'block';
            let total = 0;
            let itemsHTML = '';
            
            cartItems.forEach((item, index) => {
                const addons = JSON.parse(item.addons || '[]');
                const addonPrice = addons.reduce((sum, addon) => sum + parseFloat(addon.price || 0), 0);
                const itemTotal = (parseFloat(item.unit_price) + addonPrice) * parseInt(item.quantity);
                total += itemTotal;
                
                itemsHTML += `
                    <div class="cart-item-sticky" data-index="${index}">
                        <div class="cart-item-details-sticky">
                            <div class="cart-item-name-sticky">${item.item_name || 'Food Item'}</div>
                            <div class="cart-item-addons-sticky">
                                ${addons.map(addon => `<span class="addon-tag-sticky">${addon.name}</span>`).join('')}
                            </div>
                            ${item.special_instructions ? `<div class="cart-item-notes-sticky">Note: ${item.special_instructions}</div>` : ''}
                        </div>
                        <div class="cart-item-controls-sticky">
                            <button class="quantity-btn-sticky" onclick="updateCartQuantity(${index}, -1)" ${item.quantity <= 1 ? 'disabled' : ''}>−</button>
                            <span class="quantity-sticky">${item.quantity}</span>
                            <button class="quantity-btn-sticky" onclick="updateCartQuantity(${index}, 1)">+</button>
                        </div>
                        <div class="cart-item-price-sticky">₦${itemTotal.toLocaleString()}</div>
                        <button class="remove-item-sticky" onclick="removeCartItem(${index})" title="Remove item">×</button>
                    </div>
                `;
            });
            
            cartItemsList.innerHTML = itemsHTML;
            
            // Update all total displays
            if (cartTotalCollapsed) cartTotalCollapsed.textContent = `₦${total.toLocaleString()}`;
            if (footerTotal) footerTotal.textContent = `₦${total.toLocaleString()}`;
            
            cartFooter.style.display = 'block';
        }

        // Override openAdditions function to auto-collapse cart
        const originalOpenAdditions = window.openAdditions;
        window.openAdditions = function(foodId, foodName, price, addons) {
            autoCollapseCart();
            originalOpenAdditions.call(this, foodId, foodName, price, addons);
        };

        // Override closeAdditions function to auto-expand cart if it was collapsed
        const originalCloseAdditions = window.closeAdditions;
        window.closeAdditions = function() {
            // Only auto-expand if cart has items and was auto-collapsed
            if (cartItems.length > 0 && isCartCollapsed) {
                setTimeout(() => {
                    autoExpandCart();
                }, 300); // Small delay for smooth transition
            }
            originalCloseAdditions.call(this);
        };
    </script>
</body>
</html>