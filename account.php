<?php
require_once 'includes/session_handler.php';
require_once 'includes/auth_functions.php';
require_once 'includes/security.php';

// Require authentication
$current_user = getCurrentUserOrRedirect('auth/login.php');
$auth = new AuthManager();

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_profile':
            $profile_data = [
                'first_name' => SecurityManager::sanitizeInput($_POST['first_name'] ?? '', 'string'),
                'last_name' => SecurityManager::sanitizeInput($_POST['last_name'] ?? '', 'string'),
                'phone' => SecurityManager::sanitizeInput($_POST['phone'] ?? '', 'phone'),
                'date_of_birth' => $_POST['date_of_birth'] ?? null,
                'gender' => SecurityManager::sanitizeInput($_POST['gender'] ?? '', 'string'),
                'preferred_language' => SecurityManager::sanitizeInput($_POST['preferred_language'] ?? 'en', 'string')
            ];

            $result = $auth->updateProfile($current_user['id'], $profile_data);
            if ($result['success']) {
                $success_message = $result['message'];
                $current_user = $auth->getCurrentUser(); // Refresh user data
            } else {
                $error_message = $result['message'];
            }
            break;

        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if ($new_password !== $confirm_password) {
                $error_message = 'New passwords do not match';
            } else {
                $result = $auth->changePassword($current_user['id'], $current_password, $new_password);
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
            }
            break;

        case 'update_preferences':
            $preferences = [
                'notifications' => [
                    'email_orders' => isset($_POST['email_orders']),
                    'email_promotions' => isset($_POST['email_promotions']),
                    'sms_orders' => isset($_POST['sms_orders']),
                    'push_notifications' => isset($_POST['push_notifications'])
                ],
                'dietary' => $_POST['dietary_preferences'] ?? []
            ];

            $result = $auth->updatePreferences($current_user['id'], $preferences);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
            break;

        case 'upload_image':
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $result = $auth->updateProfileImage($current_user['id'], $_FILES['profile_image']);
                if ($result['success']) {
                    $success_message = $result['message'];
                    $current_user = $auth->getCurrentUser(); // Refresh user data
                } else {
                    $error_message = $result['message'];
                }
            } else {
                $error_message = 'Please select a valid image file';
            }
            break;
    }
}

// Get user addresses and preferences
$user_addresses = $auth->getUserAddresses($current_user['id']);
$current_user_full = $auth->getCurrentUser(); // Get full details including preferences
$notification_prefs = json_decode($current_user_full['notification_preferences'] ?? '{}', true);
$dietary_prefs = json_decode($current_user_full['dietary_preferences'] ?? '[]', true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - FaroDash</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ==========================================================================
           BASE STYLES
           ========================================================================== */

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
            overflow-x: hidden;
        }

        .container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* ==========================================================================
           HEADER STYLES
           ========================================================================== */

        .account-header {
            background-color: white;
            border-bottom: 1px solid #e9ecef;
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .account-title {
            font-size: 20px;
            font-weight: 600;
            color: #000;
            margin: 0;
            flex: 1;
            text-align: center;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #ED1B26;
            padding: 5px;
            flex-shrink: 0;
            transition: all 0.3s ease;
            display: none;
        }

        .mobile-menu-toggle svg {
            width: 16px;
            height: 12px;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle.back-mode svg {
            width: 20px;
            height: 20px;
        }

        /* ==========================================================================
           SIDEBAR STYLES
           ========================================================================== */

        .sidebar {
            width: 280px;
            background-color: white;
            border-right: 1px solid #e9ecef;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            padding: 30px 0;
            z-index: 200;
            transition: all 0.3s ease;
            transform: translateX(-100%);
            display: flex;
            flex-direction: column;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar-logo {
            display: block;
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }

        .sidebar-logo img {
            height: 40px;
            width: auto;
        }

        .sidebar-menu {
            list-style: none;
            flex: 1;
        }

        .sidebar-bottom {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            margin-top: auto;
            padding-bottom: 100px;
        }

        .menu-item {
            position: relative;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 40px;
            text-decoration: none;
            color: #000000;
            font-weight: 400;
            font-size: 16px;
            transition: all 0.3s ease;
            gap: 15px;
        }

        .menu-link.small {
            font-size: 14px;
            padding: 10px 40px;
        }

        .menu-link:hover,
        .menu-link.active {
            color: #ED1B26;
            border-left: 6px solid #ED1B26;
            background-color: rgba(237, 27, 38, 0.1);
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

        /* ==========================================================================
           MAIN CONTENT STYLES
           ========================================================================== */

        .main-content {
            width: 100%;
            padding: 0;
            min-height: 100vh;
            position: relative;
        }

        /* ==========================================================================
           ACCOUNT PAGE STYLES
           ========================================================================== */

        .account-container {
            background-color: white;
            padding: 0;
            overflow: hidden;
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.4s ease;
            position: relative;
            z-index: 1;
        }

        .account-container.hide {
            transform: translateX(-100%);
            opacity: 0;
            pointer-events: none;
        }

        .account-menu {
            list-style: none;
            margin: 0;
            padding: 40px 0;
        }

        .account-menu-item {
            border-bottom: 1px solid #f1f1f1;
        }

        .account-menu-item:last-child {
            border-bottom: none;
        }

        .account-menu-link {
            display: flex;
            align-items: center;
            padding: 20px;
            text-decoration: none;
            color: #000;
            font-size: 24px;
            font-weight: 400;
            gap: 16px;
            transition: background-color 0.3s ease;
        }

        .account-menu-link:hover {
            background-color: #f8f9fa;
        }

        .account-menu-link:active {
            background-color: #e9ecef;
        }

        .account-menu-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .account-menu-text {
            flex: 1;
        }

        .account-menu-arrow {
            width: 8px;
            height: 12px;
            flex-shrink: 0;
        }

        .account-menu-item + .account-menu-item {
            margin-top: 40px;
        }

        .account-menu-item:first-child {
            margin-top: 0;
        }

        /* Personal Information Page */
        .personal-info-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white;
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.4s ease;
            transform: translateX(100%);
            opacity: 0;
            z-index: 2;
            pointer-events: none;
        }

        .personal-info-container.show {
            transform: translateX(0);
            opacity: 1;
            pointer-events: all;
        }

        .personal-info-content {
            padding: 40px 20px 100px 20px;
            margin-top: 80px;
            min-height: calc(100vh - 160px);
            display: flex;
            flex-direction: column;
        }

        .form-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-field {
            height: 52px;
            border: 0.5px solid rgba(237, 27, 37, 0.5);
            border-radius: 10px;
            padding: 0 16px;
            font-size: 16px;
            font-family: 'Outfit', sans-serif;
            color: #000;
            background-color: white;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-field:focus {
            border-color: rgba(237, 27, 37, 0.8);
        }

        .form-field::placeholder {
            color: #666;
        }

        .phone-container {
            display: flex;
            gap: 20px;
        }

        .country-code {
            width: 80px;
            flex-shrink: 0;
        }

        .phone-number {
            flex: 1;
        }

        /* Orders Page */
        .orders-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white;
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.4s ease;
            transform: translateX(100%);
            opacity: 0;
            z-index: 2;
            pointer-events: none;
        }

        .orders-container.show {
            transform: translateX(0);
            opacity: 1;
            pointer-events: all;
        }

        .orders-content {
            padding: 40px 20px 100px 20px;
            margin-top: 80px;
            min-height: calc(100vh - 160px);
            display: flex;
            flex-direction: column;
        }

        .orders-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 16px;
            background-color: #F9FAFB;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            gap: 16px;
            position: relative;
        }

        .order-item-image {
            flex-shrink: 0;
        }

        .order-item-image img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }

        .order-item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .order-item-title {
            font-size: 16px;
            font-weight: 600;
            color: #000;
            margin: 0;
        }

        .order-item-quantity {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        .order-item-price {
            font-size: 16px;
            font-weight: 600;
            color: #000;
            margin: 0;
        }

        .order-item-status {
            position: absolute;
            bottom: 16px;
            right: 16px;
            font-size: 12px;
            font-weight: 500;
        }

        .order-item-status.in-progress {
            color: #2E7CF6;
        }

        .order-item-status.your-review {
            color: #11AD3A;
        }

        .order-item-status.canceled {
            color: #ED1B25;
        }

        /* Support Services Pages */
        .support-services-container,
        .live-chat-container,
        .faq-container,
        .contact-form-container,
        .report-issue-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white;
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.4s ease;
            transform: translateX(100%);
            opacity: 0;
            z-index: 2;
            pointer-events: none;
        }

        .support-services-container.show,
        .live-chat-container.show,
        .faq-container.show,
        .contact-form-container.show,
        .report-issue-container.show {
            transform: translateX(0);
            opacity: 1;
            pointer-events: all;
        }

        .support-services-content,
        .faq-content,
        .contact-form-content,
        .report-issue-content {
            padding: 20px 20px 100px 20px;
            margin-top: 80px;
            min-height: calc(100vh - 160px);
            display: flex;
            flex-direction: column;
        }

        /* Support Services Menu */
        .support-menu {
            list-style: none;
            margin: 0;
            padding: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .support-menu-item {
            border-bottom: 1px solid #f1f1f1;
        }

        .support-menu-item:last-child {
            border-bottom: none;
        }

        .support-menu-link {
            display: flex;
            align-items: center;
            padding: 20px;
            text-decoration: none;
            color: #000;
            font-size: 20px;
            font-weight: 400;
            gap: 16px;
            transition: background-color 0.3s ease;
            border-radius: 12px;
        }

        .support-menu-link:hover {
            background-color: #f8f9fa;
        }

        .support-menu-link:active {
            background-color: #e9ecef;
        }

        .support-menu-icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .support-menu-text {
            flex: 1;
        }

        .support-menu-arrow {
            width: 8px;
            height: 12px;
            flex-shrink: 0;
        }

        /* Live Chat Styles */
        .live-chat-content {
            padding: 0;
            margin-top: 80px;
            height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
            background-color: #f2f2f2;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            gap: 16px;
            background-color: #f2f2f2;
            min-height: 0;
        }

        .message-bubble {
            background-color: #D9D9D9;
            border-radius: 10px;
            padding: 16px;
            max-width: 70%;
            align-self: flex-start;
            min-height: 50px;
            display: flex;
            align-items: center;
        }

        .message-bubble.user {
            align-self: flex-end;
            background-color: #ED1B26;
        }

        .message-bubble.user .message-text {
            color: white;
        }

        .message-bubble.agent {
            background-color: white;
            color: #959595;
        }

        .message-bubble.loading {
            background-color: #D9D9D9;
        }

        .loading-dots {
            display: flex;
            gap: 4px;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .loading-dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #666;
            animation: loading 1.4s infinite ease-in-out both;
        }

        .loading-dots span:nth-child(1) {
            animation-delay: -0.32s;
        }

        .loading-dots span:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes loading {
            0%, 80%, 100% {
                transform: scale(0);
            }
            40% {
                transform: scale(1);
            }
        }

        .message-text {
            font-size: 20px;
            color: #959595;
            line-height: 1.4;
        }

        .chat-input-container {
            padding: 20px;
            background-color: white;
            display: flex;
            flex-direction: column;
            gap: 16px;
            flex-shrink: 0;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .chat-input {
            width: 100%;
            border: none;
            border-radius: 0;
            padding: 16px;
            font-size: 20px;
            font-family: 'Outfit', sans-serif;
            outline: none;
            color: #959595;
            background-color: white;
        }

        .chat-input::placeholder {
            color: #959595;
            font-size: 20px;
        }

        .chat-icons-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-image-btn,
        .chat-send-btn {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }

        .chat-image-btn:hover,
        .chat-send-btn:hover {
            transform: scale(1.1);
        }

        .chat-image-btn:active,
        .chat-send-btn:active {
            transform: scale(0.95);
        }

        /* FAQ Styles */
        .faq-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .faq-item {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            background-color: white;
        }

        .faq-question {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #000;
            transition: background-color 0.3s ease;
        }

        .faq-question:hover {
            background-color: #f8f9fa;
        }

        .faq-arrow {
            transition: transform 0.3s ease;
        }

        .faq-item.active .faq-arrow {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item.active .faq-answer {
            padding: 0 20px 20px 20px;
            max-height: 200px;
        }

        .faq-answer p {
            margin: 0;
            color: #666;
            line-height: 1.5;
        }

        /* Form Styles */
        .contact-form,
        .report-form {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 16px;
            font-weight: 500;
            color: #000;
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
            padding: 16px;
            line-height: 1.5;
        }

        .contact-submit-btn,
        .report-submit-btn {
            background-color: #ED1B25;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 16px;
            font-size: 18px;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        .contact-submit-btn:hover,
        .report-submit-btn:hover {
            background-color: #d41420;
        }

        .contact-submit-btn:active,
        .report-submit-btn:active {
            background-color: #c01218;
        }

        .continue-button-container {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            z-index: 10;
            display: none;
        }

        .continue-button-container.show {
            display: block;
        }

        .continue-button {
            background-color: #ED1B25;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 16px;
            font-size: 24px;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        .continue-button:hover {
            background-color: #d41420;
        }

        .continue-button:active {
            background-color: #c01218;
        }

        /* Success Popup */
        .success-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .success-popup.show {
            opacity: 1;
            visibility: visible;
        }

        .success-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #ED1B25;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }

        .success-icon svg {
            width: 30px;
            height: 30px;
            color: white;
        }

        .success-text {
            font-size: 18px;
            font-weight: 500;
            color: #000;
            text-align: center;
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .popup-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* NEW FEATURES STYLES */
        .profile-image-upload {
            position: relative;
            display: inline-block;
            margin: 0 auto 30px;
        }

        .current-profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid #ED1B25;
            background-color: #f8f9fa;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            cursor: pointer;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .current-profile-image:hover {
            transform: scale(1.05);
        }

        /* Default avatar icon - only shows when no image is set */
        .current-profile-image.no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: none;
        }

        .current-profile-image.no-image::before {
            content: '';
            width: 60%;
            height: 60%;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="%23666"><circle cx="50" cy="30" r="15"/><path d="M50 50c-15 0-25 10-25 20v10h50V70c0-10-10-20-25-20z"/></svg>');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 35px;
            height: 35px;
            background-color: #ED1B25;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid white;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            background-color: #d41420;
            transform: scale(1.1);
        }

        .file-input-hidden {
            display: none;
        }

        /* Loading state */
        .profile-image-upload.uploading .current-profile-image {
            opacity: 0.6;
            pointer-events: none;
        }

        .profile-image-upload.uploading .upload-overlay {
            background-color: #999;
        }

        /* Upload spinner */
        .upload-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ED1B25;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }

        .profile-image-upload.uploading .upload-spinner {
            display: block;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .addresses-grid {
            display: grid;
            gap: 16px;
            margin-bottom: 20px;
        }

        .address-card {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e9ecef;
            position: relative;
        }

        .address-card.default {
            border-color: #ED1B25;
            background-color: rgba(237, 27, 38, 0.05);
        }

        .address-label {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
            color: #000;
        }

        .address-text {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }

        .default-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background-color: #ED1B25;
            color: white;
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .preferences-grid {
            display: grid;
            gap: 24px;
            margin-bottom: 20px;
        }

        .preference-section {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
        }

        .preference-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #000;
        }

        .checkbox-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #ED1B25;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-select {
            height: 52px;
            border: 0.5px solid rgba(237, 27, 37, 0.5);
            border-radius: 10px;
            padding: 0 16px;
            font-size: 16px;
            font-family: 'Outfit', sans-serif;
            color: #000;
            background-color: white;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-select:focus {
            border-color: rgba(237, 27, 37, 0.8);
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

        .section-divider {
            height: 1px;
            background-color: #e9ecef;
            margin: 30px 0;
        }

        /* ==========================================================================
           MOBILE RESPONSIVE STYLES
           ========================================================================== */

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .mobile-menu-toggle svg {
                width: 16px;
                height: 12px;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                top: 0;
                height: 100vh;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-logo {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }

            .account-header {
                padding: 20px 16px;
            }

            .account-menu-link {
                padding: 18px 16px;
                font-size: 22px;
            }

            .account-menu {
                padding: 32px 0;
            }

            .account-menu-item + .account-menu-item {
                margin-top: 32px;
            }

            .personal-info-content {
                padding: 32px 16px;
                margin-top: 80px;
            }

            .orders-content {
                padding: 32px 16px;
                margin-top: 80px;
            }

            .order-item {
                padding: 12px;
                gap: 12px;
            }

            .order-item-image img {
                width: 50px;
                height: 50px;
            }

            .order-item-title {
                font-size: 15px;
            }

            .order-item-quantity {
                font-size: 13px;
            }

            .order-item-price {
                font-size: 15px;
            }

            .order-item-status {
                bottom: 12px;
                right: 12px;
                font-size: 11px;
            }

            .menu-link {
                font-size: 16px;
                padding: 10px 30px;
            }

            .menu-link.small {
                font-size: 14px;
            }

            .sidebar-bottom {
                padding-bottom: 100px;
            }

            .support-services-content,
            .faq-content,
            .contact-form-content,
            .report-issue-content {
                padding: 16px 16px;
                margin-top: 80px;
            }

            .live-chat-content {
                margin-top: 80px;
                background-color: #f2f2f2;
            }

            .chat-messages {
                padding: 16px;
            }

            .message-bubble {
                max-width: 85%;
                padding: 12px;
            }

            .chat-input-container {
                padding: 16px;
                border-top-left-radius: 20px;
                border-top-right-radius: 20px;
            }

            .chat-input {
                font-size: 20px;
                padding: 12px;
                border: none;
            }

            .chat-input::placeholder {
                font-size: 20px;
            }

            .support-menu-link {
                padding: 16px;
                font-size: 18px;
            }

            .faq-question {
                padding: 16px;
                font-size: 15px;
            }

            .faq-item.active .faq-answer {
                padding: 0 16px 16px 16px;
            }

            .form-label {
                font-size: 15px;
            }

            .contact-submit-btn,
            .report-submit-btn {
                font-size: 16px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .preferences-grid {
                gap: 20px;
            }
        }

        @media (max-width: 480px) {
            .account-header {
                padding: 16px 12px;
            }

            .personal-info-content {
                padding: 24px 12px;
            }

            .orders-content {
                padding: 24px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <!-- Mobile Logo in Sidebar -->
            <div class="sidebar-logo">
                <img src="images/logo.png" alt="FaroDash">
            </div>

            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M27.5 27.5H2.5M2.5 13.75L7.57875 9.6875M27.5 13.75L17.3425 5.625C16.6776 5.09311 15.8515 4.80334 15 4.80334C14.1485 4.80334 13.3224 5.09311 12.6575 5.625L11.68 6.40625M19.375 6.875V4.375C19.375 4.20924 19.4408 4.05027 19.5581 3.93306C19.6753 3.81585 19.8342 3.75 20 3.75H23.125C23.2908 3.75 23.4497 3.81585 23.5669 3.93306C23.6842 4.05027 23.75 4.20924 23.75 4.375V10.625M5 27.5V11.875M25 11.875V16.875M25 27.5V21.875" stroke="#F9A825" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18.75 27.5V21.25C18.75 19.4825 18.75 18.5987 18.2 18.05C17.6525 17.5 16.7687 17.5 15 17.5C13.2313 17.5 12.3487 17.5 11.8 18.05M11.25 27.5V21.25" stroke="#F9A825" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M17.5 11.875C17.5 12.538 17.2366 13.1739 16.7678 13.6428C16.2989 14.1116 15.663 14.375 15 14.375C14.337 14.375 13.7011 14.1116 13.2322 13.6428C12.7634 13.1739 12.5 12.538 12.5 11.875C12.5 11.212 12.7634 10.5761 13.2322 10.1072C13.7011 9.63839 14.337 9.375 15 9.375C15.663 9.375 16.2989 9.63839 16.7678 10.1072C17.2366 10.5761 17.5 11.212 17.5 11.875Z" stroke="#F9A825" stroke-width="1.5"/>
                        </svg>
                        <span class="menu-text">Home</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M26.25 12.1875H25.2691C25.0336 9.6268 23.8498 7.2465 21.95 5.51346C20.0502 3.78041 17.5715 2.81967 15 2.81967C12.4285 2.81967 9.94976 3.78041 8.04997 5.51346C6.15019 7.2465 4.96642 9.6268 4.73086 12.1875H3.75C3.50136 12.1875 3.2629 12.2863 3.08709 12.4621C2.91127 12.6379 2.8125 12.8764 2.8125 13.125C2.81662 15.3545 3.43019 17.5405 4.58685 19.4465C5.74351 21.3525 7.39925 22.906 9.375 23.9391V24.375C9.375 24.8723 9.57254 25.3492 9.92418 25.7008C10.2758 26.0525 10.7527 26.25 11.25 26.25H18.75C19.2473 26.25 19.7242 26.0525 20.0758 25.7008C20.4275 25.3492 20.625 24.8723 20.625 24.375V23.9391C22.6008 22.906 24.2565 21.3525 25.4132 19.4465C26.5698 17.5405 27.1834 15.3545 27.1875 13.125C27.1875 12.8764 27.0887 12.6379 26.9129 12.4621C26.7371 12.2863 26.4986 12.1875 26.25 12.1875ZM23.3836 12.1875H17.3578C18.4944 10.4823 20.214 9.25021 22.1941 8.72226C22.8416 9.77537 23.2478 10.9588 23.3836 12.1875ZM20.3297 6.58945C20.5445 6.76523 20.7504 6.95078 20.9473 7.14609C18.4516 8.024 16.3893 9.82828 15.1875 12.1852H11.7305C12.3164 10.5427 13.3952 9.12118 14.8193 8.11482C16.2435 7.10846 17.9436 6.56633 19.6875 6.5625C19.902 6.5625 20.1164 6.57305 20.3297 6.58945ZM15 4.6875C15.7523 4.68818 16.5011 4.78947 17.2266 4.98867C15.4599 5.42692 13.8399 6.32313 12.53 7.58698C11.2201 8.85082 10.2664 10.4376 9.76523 12.1875H6.61641C6.84896 10.126 7.83202 8.22219 9.37817 6.83899C10.9243 5.4558 12.9254 4.68997 15 4.6875ZM19.2961 22.5C19.1326 22.5751 18.9941 22.6958 18.8973 22.8474C18.8004 22.9991 18.7493 23.1755 18.75 23.3555V24.375H11.25V23.3555C11.2507 23.1755 11.1996 22.9991 11.1027 22.8474C11.0059 22.6958 10.8674 22.5751 10.7039 22.5C9.05522 21.7413 7.63509 20.5623 6.58606 19.0814C5.53703 17.6004 4.89602 15.8695 4.72734 14.0625H25.2691C25.1008 15.8691 24.4603 17.5998 23.4119 19.0807C22.3635 20.5617 20.9441 21.7409 19.2961 22.5Z" fill="black"/>
                        </svg>
                        <span class="menu-text">Breakfast</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.2002 18.6498C19.9255 18.6599 22.6177 18.0532 25.0752 16.875C25.5285 16.6469 25.8826 16.2606 26.0703 15.7891C26.2581 15.3176 26.2665 14.7936 26.094 14.3163C25.9215 13.839 25.58 13.4415 25.1342 13.199C24.6884 12.9565 24.1692 12.8857 23.6748 13.0002C21.8898 13.4148 20.0526 13.5582 18.225 13.425C14.013 13.0626 11.337 10.0998 10.113 8.74979" stroke="#0D0D12" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4.99979 7.5V4.9998C4.99979 4.83562 5.03214 4.67306 5.09499 4.52138C5.15783 4.36971 5.24995 4.23191 5.36606 4.11585C5.48218 3.99978 5.62003 3.90774 5.77173 3.84496C5.92343 3.78219 6.08602 3.74992 6.25019 3.75H8.74979C8.91397 3.74992 9.07655 3.78219 9.22826 3.84496C9.37996 3.90774 9.5178 3.99978 9.63392 4.11585C9.75004 4.23191 9.84215 4.36971 9.905 4.52138C9.96785 4.67306 10.0002 4.83562 10.0002 4.9998V7.5C9.88338 9.47289 10.2831 11.4421 11.1599 13.2133C12.0367 14.9845 13.3603 16.4964 15 17.5998C17.5002 19.2378 21.2502 19.5624 23.7498 19.5876C24.4128 19.5876 25.0486 19.8509 25.5175 20.3197C25.9864 20.7884 26.2498 21.4242 26.25 22.0872C26.2485 22.5632 26.1113 23.029 25.8545 23.4297C25.5976 23.8305 25.2317 24.1497 24.7998 24.3498C21.4374 25.8498 12.975 28.65 6.86219 22.275C1.24979 16.35 4.99979 7.5 4.99979 7.5Z" stroke="#0D0D12" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="menu-text">Groceries</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.51 15H7.4975M13.75 20.0525C14.3287 20.2437 14.885 20.4637 15.3875 20.815M15.3875 20.815C16.0385 21.2692 16.5705 21.8736 16.9382 22.5771C17.306 23.2806 17.4987 24.0624 17.5 24.8562C17.4998 24.8755 17.4959 24.8944 17.4884 24.9121C17.4809 24.9298 17.47 24.9458 17.4563 24.9593C17.4426 24.9728 17.4264 24.9834 17.4086 24.9906C17.3908 24.9978 17.3717 25.0014 17.3525 25.0012C13.7062 24.985 12.0725 24.3675 11.3863 23.3487L10 21.0712C6.885 20.4425 4.0225 18.4537 2.5 15.1037C6.25 6.8575 18.125 6.8575 21.875 15.1037M15.3875 20.815C18.1 19.99 20.5187 18.0862 21.875 15.1037M21.875 15.1037C22.2913 14.2787 24.5 11.3925 27.5 11.3925C26.4587 12.4237 24.75 16.3412 26.25 18.815C24.75 18.815 22.5 15.9287 21.875 15.1037ZM15.3875 9.3925C16.0384 8.93844 16.5702 8.33417 16.9379 7.63091C17.3057 6.92765 17.4985 6.1461 17.5 5.3525C17.5 4.32 12.115 5.78 11.3875 6.86L10 9.1375" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="menu-text">Protein</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.3731 19.64C13.3256 21.5925 18.0744 20.01 21.9794 16.1044C25.885 12.1994 27.4675 7.45063 25.515 5.49813M17.1181 4.17188L18.0019 5.05625M14.025 7.26563L14.9088 8.14938M11.3725 10.8013L12.2563 11.685M10.4888 15.2206L11.3725 16.1044M21.9794 2.84625L22.8631 3.73M21.0956 8.15L22.8631 9.9175M18.0025 11.2438L19.77 13.0113M14.4669 13.895L16.2344 15.6625" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M11.3727 22.2919C12.1049 21.5596 12.1049 20.3725 11.3727 19.6402C10.6405 18.908 9.45328 18.908 8.72105 19.6402L5.18552 23.1758C4.45328 23.908 4.45328 25.0952 5.18552 25.8274C5.91775 26.5596 7.10493 26.5596 7.83717 25.8274L11.3727 22.2919Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="menu-text">Beauty</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M8.75 2.1875C8.99864 2.1875 9.2371 2.28627 9.41291 2.46209C9.58873 2.6379 9.6875 2.87636 9.6875 3.125V4.07875C10.515 4.0625 11.4263 4.0625 12.43 4.0625H17.57C18.5737 4.0625 19.485 4.0625 20.3125 4.07875V3.125C20.3125 2.87636 20.4113 2.6379 20.5871 2.46209C20.7629 2.28627 21.0014 2.1875 21.25 2.1875C21.4986 2.1875 21.7371 2.28627 21.9129 2.46209C22.0887 2.6379 22.1875 2.87636 22.1875 3.125V4.15875C22.5125 4.18375 22.8204 4.21542 23.1112 4.25375C24.5763 4.45125 25.7625 4.86625 26.6987 5.80125C27.6337 6.7375 28.0487 7.92375 28.2463 9.38875C28.3079 9.85708 28.3525 10.3696 28.38 10.9263C28.448 11.1107 28.4563 11.3118 28.4037 11.5013C28.4375 12.5025 28.4375 13.6413 28.4375 14.93V17.5C28.4375 17.7486 28.3387 17.9871 28.1629 18.1629C27.9871 18.3387 27.7486 18.4375 27.5 18.4375C27.2514 18.4375 27.0129 18.3387 26.8371 18.1629C26.6613 17.9871 26.5625 17.7486 26.5625 17.5V15C26.5625 13.9325 26.5625 13.0037 26.5462 12.1875H3.45375C3.4375 13.0037 3.4375 13.9325 3.4375 15V17.5C3.4375 19.8837 3.44 21.5775 3.6125 22.8625C3.78125 24.1188 4.09875 24.8438 4.6275 25.3725C5.15625 25.9013 5.88125 26.2188 7.13875 26.3875C8.42375 26.56 10.1163 26.5625 12.5 26.5625H17.5C17.7486 26.5625 17.9871 26.6613 18.1629 26.8371C18.3387 27.0129 18.4375 27.2514 18.4375 27.5C18.4375 27.7486 18.3387 27.9871 18.1629 28.1629C17.9871 28.3387 17.7486 28.4375 17.5 28.4375H12.43C10.1325 28.4375 8.3125 28.4375 6.88875 28.2463C5.42375 28.0487 4.2375 27.6337 3.30125 26.6987C2.36625 25.7625 1.95125 24.5763 1.75375 23.1112C1.5625 21.6863 1.5625 19.8675 1.5625 17.57V14.93C1.5625 13.6413 1.5625 12.5025 1.59625 11.5C1.54409 11.3105 1.55283 11.1093 1.62125 10.925C1.64792 10.3692 1.69208 9.85708 1.75375 9.38875C1.95125 7.92375 2.36625 6.7375 3.30125 5.80125C4.2375 4.86625 5.42375 4.45125 6.88875 4.25375C7.18042 4.21542 7.48833 4.18375 7.8125 4.15875V3.125C7.8125 2.87636 7.91127 2.6379 8.08709 2.46209C8.2629 2.28627 8.50136 2.1875 8.75 2.1875ZM3.54 10.3125H26.46C26.44 10.0758 26.4158 9.85083 26.3875 9.6375C26.2188 8.38125 25.9013 7.65625 25.3725 7.1275C24.8438 6.59875 24.1187 6.28125 22.8612 6.1125C21.5775 5.94 19.8837 5.9375 17.5 5.9375H12.5C10.1163 5.9375 8.42375 5.94 7.1375 6.1125C5.88125 6.28125 5.15625 6.59875 4.6275 7.1275C4.09875 7.65625 3.78125 8.38125 3.6125 9.6375C3.58417 9.85 3.56 10.0746 3.54 10.3112M22.5 19.6875C21.7541 19.6875 21.0387 19.9838 20.5113 20.5113C19.9838 21.0387 19.6875 21.7541 19.6875 22.5C19.6875 23.2459 19.9838 23.9613 20.5113 24.4887C21.0387 25.0162 21.7541 25.3125 22.5 25.3125C23.2459 25.3125 23.9613 25.0162 24.4887 24.4887C25.0162 23.9613 25.3125 23.2459 25.3125 22.5C25.3125 21.7541 25.0162 21.0387 24.4887 20.5113C23.9613 19.9838 23.2459 19.6875 22.5 19.6875ZM17.8125 22.5C17.8127 21.7691 17.9838 21.0485 18.3122 20.3955C18.6405 19.7426 19.117 19.1755 19.7035 18.7395C20.2901 18.3035 20.9705 18.0106 21.6903 17.8844C22.4102 17.7582 23.1496 17.802 23.8495 18.0124C24.5494 18.2228 25.1904 18.594 25.7214 19.0962C26.2523 19.5985 26.6584 20.2179 26.9073 20.9051C27.1562 21.5923 27.241 22.3281 27.1548 23.0539C27.0687 23.7796 26.8141 24.4752 26.4112 25.085L28.1625 26.8375C28.2546 26.9233 28.3285 27.0268 28.3797 27.1418C28.431 27.2568 28.4585 27.381 28.4607 27.5068C28.463 27.6327 28.4398 27.7578 28.3927 27.8745C28.3455 27.9912 28.2753 28.0973 28.1863 28.1863C28.0973 28.2753 27.9912 28.3455 27.8745 28.3927C27.7578 28.4398 27.6327 28.463 27.5068 28.4607C27.381 28.4585 27.2568 28.431 27.1418 28.3797C27.0268 28.3285 26.9233 28.2546 26.8375 28.1625L25.085 26.4112C24.3785 26.8783 23.5586 27.1452 22.7125 27.1836C21.8664 27.222 21.0257 27.0305 20.2797 26.6293C19.5338 26.2281 18.9105 25.6323 18.4761 24.9052C18.0416 24.1782 17.8123 23.347 17.8125 22.5Z" fill="black"/>
                        </svg>
                        <span class="menu-text">Browse all</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-bottom">
                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="#" class="menu-link small active">
                            <svg class="menu-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 2.5C21.9037 2.5 27.5 8.09625 27.5 15C27.5 21.9037 21.9037 27.5 15 27.5C8.09625 27.5 2.5 21.9037 2.5 15C2.5 8.09625 8.09625 2.5 15 2.5ZM15.2 20C13.9907 19.9985 12.7943 20.2485 11.6868 20.7339C10.5792 21.2194 9.58468 21.9298 8.76625 22.82C10.5359 24.2343 12.7347 25.0032 15 25C17.3654 25.0034 19.6549 24.1651 21.4587 22.635C20.6446 21.7997 19.6713 21.1362 18.5963 20.6836C17.5212 20.231 16.3664 19.9986 15.2 20ZM15 5C13.1401 5.00005 11.3171 5.51878 9.73579 6.49791C8.1545 7.47704 6.8776 8.87776 6.04858 10.5427C5.21957 12.2076 4.8713 14.0707 5.0429 15.9227C5.2145 17.7746 5.89918 19.542 7.02 21.0262C8.07069 19.9114 9.33846 19.0234 10.7452 18.417C12.152 17.8105 13.6681 17.4985 15.2 17.5C16.6771 17.4979 18.14 17.7878 19.5048 18.3528C20.8695 18.9178 22.1092 19.7469 23.1525 20.7925C24.2156 19.2964 24.8465 17.5365 24.9761 15.7057C25.1056 13.8749 24.7288 12.0438 23.8869 10.4129C23.0449 8.78199 21.7704 7.41426 20.2029 6.45951C18.6354 5.50476 16.8354 4.99982 15 5ZM15 6.25C16.3261 6.25 17.5979 6.77678 18.5355 7.71447C19.4732 8.65215 20 9.92392 20 11.25C20 12.5761 19.4732 13.8479 18.5355 14.7855C17.5979 15.7232 16.3261 16.25 15 16.25C13.6739 16.25 12.4021 15.7232 11.4645 14.7855C10.5268 13.8479 10 12.5761 10 11.25C10 9.92392 10.5268 8.65215 11.4645 7.71447C12.4021 6.77678 13.6739 6.25 15 6.25ZM15 8.75C14.337 8.75 13.7011 9.01339 13.2322 9.48223C12.7634 9.95107 12.5 10.587 12.5 11.25C12.5 11.913 12.7634 12.5489 13.2322 13.0178C13.7011 13.4866 14.337 13.75 15 13.75C15.663 13.75 16.2989 13.4866 16.7678 13.0178C17.2366 12.5489 17.5 11.913 17.5 11.25C17.5 10.587 17.2366 9.95107 16.7678 9.48223C16.2989 9.01339 15.663 8.75 15 8.75Z" fill="#ED1B26"/>
                            </svg>
                            <span class="menu-text-small">Account</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Account Header -->
            <div class="account-header">
                <button class="mobile-menu-toggle" id="menuToggle" onclick="handleMenuClick()">
                    <svg id="menuIcon" width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0.78125 1.53125H15.2188M0.78125 6H15.2188M0.78125 10.4688H15.2188" stroke="black" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round"/>
                    </svg>
                    <svg id="backIcon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                        <path d="M12.5 15L7.5 10L12.5 5" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <h1 class="account-title" id="headerTitle">Account</h1>
            </div>
            

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            
            <!-- Account Container -->
            <div class="account-container" id="accountContainer">
                <ul class="account-menu">
                    <li class="account-menu-item">
                        <a href="#" class="account-menu-link" onclick="openPersonalInfo(event)">
                            <svg class="account-menu-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 8C10.4596 8 10.9148 7.90947 11.3394 7.73358C11.764 7.55769 12.1499 7.29988 12.4749 6.97487C12.7999 6.64987 13.0577 6.26403 13.2336 5.83939C13.4095 5.41475 13.5 4.95963 13.5 4.5C13.5 4.04037 13.4095 3.58525 13.2336 3.16061C13.0577 2.73597 12.7999 2.35013 12.4749 2.02513C12.1499 1.70012 11.764 1.44231 11.3394 1.26642C10.9148 1.09053 10.4596 1 10 1C9.07174 1 8.1815 1.36875 7.52513 2.02513C6.86875 2.6815 6.5 3.57174 6.5 4.5C6.5 5.42826 6.86875 6.3185 7.52513 6.97487C8.1815 7.63125 9.07174 8 10 8ZM1 18.4V19H19V18.4C19 16.16 19 15.04 18.564 14.184C18.1805 13.4314 17.5686 12.8195 16.816 12.436C15.96 12 14.84 12 12.6 12H7.4C5.16 12 4.04 12 3.184 12.436C2.43139 12.8195 1.81949 13.4314 1.436 14.184C1 15.04 1 16.16 1 18.4Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="account-menu-text">Personal information</span>
                            <svg class="account-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                    
                    <li class="account-menu-item">
                        <a href="user/orders.php" class="account-menu-link">
                            <svg class="account-menu-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5.25 1.5C5.05109 1.5 4.86032 1.57902 4.71967 1.71967C4.57902 1.86032 4.5 2.05109 4.5 2.25V21.75C4.5 21.9489 4.57902 22.1397 4.71967 22.2803C4.86032 22.421 5.05109 22.5 5.25 22.5H9.75V21H6V3H18V10.5H19.5V2.25C19.5 2.05109 19.421 1.86032 19.2803 1.71967C19.1397 1.57902 18.9489 1.5 18.75 1.5H5.25Z" fill="black"/>
                                <path d="M7.5 7.5H16.5V6H7.5V7.5ZM7.5 10.5H13.5V9H7.5V10.5Z" fill="black"/>
                            </svg>
                            <span class="account-menu-text">My orders</span>
                            <svg class="account-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>

                    <li class="account-menu-item">
                        <a href="user/addresses.php" class="account-menu-link">
                            <svg class="account-menu-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 10.5C9.33696 10.5 8.70107 10.2366 8.23223 9.76777C7.76339 9.29893 7.5 8.66304 7.5 8C7.5 7.33696 7.76339 6.70107 8.23223 6.23223C8.70107 5.76339 9.33696 5.5 10 5.5C10.663 5.5 11.2989 5.76339 11.7678 6.23223C12.2366 6.70107 12.5 7.33696 12.5 8C12.5 8.66304 12.2366 9.29893 11.7678 9.76777C11.2989 10.2366 10.663 10.5 10 10.5ZM10 1C7.87827 1 5.84344 1.84285 4.34315 3.34315C2.84285 4.84344 2 6.87827 2 9C2 14.25 10 19 10 19C10 19 18 14.25 18 9C18 6.87827 17.1571 4.84344 15.6569 3.34315C14.1566 1.84285 12.1217 1 10 1Z" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="account-menu-text">My addresses</span>
                            <svg class="account-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                    
                    <li class="account-menu-item">
                        <a href="#" class="account-menu-link">
                            <svg class="account-menu-icon" width="18" height="17" viewBox="0 0 18 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8.98 16.094C8.786 16.094 8.59367 16.0587 8.403 15.988C8.21233 15.918 8.04467 15.8103 7.9 15.665L6.752 14.63C4.972 13.0133 3.402 11.441 2.042 9.91299C0.680666 8.38566 0 6.79799 0 5.14999C0 3.87799 0.432 2.80999 1.296 1.94599C2.16 1.08199 3.228 0.649994 4.5 0.649994C5.22933 0.649994 5.989 0.834327 6.779 1.20299C7.569 1.57166 8.30933 2.26699 9 3.28899C9.69133 2.26699 10.4317 1.57166 11.221 1.20299C12.0103 0.834327 12.77 0.649994 13.5 0.649994C14.772 0.649994 15.84 1.08199 16.704 1.94599C17.568 2.80999 18 3.87799 18 5.14999C18 6.83599 17.2917 8.45666 15.875 10.012C14.4583 11.5673 12.9077 13.108 11.223 14.634L10.081 15.665C9.93567 15.8103 9.76467 15.918 9.568 15.988C9.37133 16.058 9.17567 16.0933 8.981 16.094" fill="black"/>
                            </svg>
                            <span class="account-menu-text">Favourites</span>
                            <svg class="account-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>

                    <li class="account-menu-item">
                        <a href="#" class="account-menu-link" onclick="openSettings(event)">
                            <svg class="account-menu-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 12.5C11.3807 12.5 12.5 11.3807 12.5 10C12.5 8.61929 11.3807 7.5 10 7.5C8.61929 7.5 7.5 8.61929 7.5 10C7.5 11.3807 8.61929 12.5 10 12.5Z" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16.1667 12.5C16.0557 12.7763 16.0297 13.0789 16.0923 13.3691C16.155 13.6593 16.3028 13.9254 16.5167 14.1333L16.5667 14.1833C16.7433 14.3598 16.8838 14.5695 16.9806 14.8007C17.0774 15.0319 17.1286 15.2804 17.1311 15.5317C17.1336 15.7829 17.0875 16.0324 16.9952 16.2655C16.9029 16.4987 16.7664 16.7113 16.5933 16.8917C16.4203 17.0721 16.2141 17.2168 15.9864 17.3174C15.7587 17.418 15.5139 17.4725 15.2658 17.4779C15.0177 17.4833 14.7708 17.4395 14.5396 17.3491C14.3084 17.2587 14.0977 17.1234 13.92 16.9517L13.87 16.9017C13.6621 16.6878 13.396 16.54 13.1058 16.4773C12.8156 16.4147 12.513 16.4407 12.2367 16.5517C11.9685 16.6565 11.7358 16.8376 11.5675 17.0739C11.3992 17.3103 11.3025 17.5921 11.2883 17.8833V18C11.2883 18.5304 11.0776 19.0391 10.7026 19.4142C10.3275 19.7893 9.8188 20 9.28834 20C8.75788 20 8.24918 19.7893 7.87411 19.4142C7.49904 19.0391 7.28834 18.5304 7.28834 18V17.925C7.26741 17.6239 7.16244 17.3346 6.98617 17.0892C6.8099 16.8437 6.5698 16.6516 6.29167 16.5333C6.01537 16.4223 5.71276 16.3963 5.42254 16.459C5.13233 16.5216 4.86625 16.6694 4.65834 16.8833L4.60834 16.9333C4.43067 17.105 4.21998 17.2403 3.98879 17.3307C3.7576 17.4211 3.51073 17.4649 3.26261 17.4595C3.01449 17.4541 2.76971 17.3996 2.54198 17.299C2.31424 17.1984 2.10796 17.0537 1.93495 16.8807C1.76193 16.7076 1.62518 16.5089 1.53283 16.2961C1.44049 16.0834 1.3944 15.8609 1.39685 15.6369C1.39931 15.4129 1.45028 15.1915 1.54714 14.9808C1.644 14.7701 1.78502 14.5745 1.96167 14.405L2.01167 14.355C2.22559 14.1471 2.37333 13.881 2.43598 13.5908C2.49863 13.3006 2.47262 12.998 2.36167 12.7217C2.25686 12.4535 2.07577 12.2208 1.83942 12.0525C1.60308 11.8842 1.32127 11.7875 1.03 11.7733H1C0.469563 11.7733 -0.0391323 11.5626 -0.414202 11.1876C-0.789271 10.8125 -1 10.3038 -1 9.77333C-1 9.24287 -0.789271 8.73417 -0.414202 8.3591C-0.0391323 7.98403 0.469563 7.77333 1 7.77333H1.075C1.37609 7.75907 1.66539 7.65409 1.91083 7.46783C2.15628 7.28156 2.34837 7.02146 2.46667 6.74333C2.57762 6.46704 2.60362 6.16443 2.54098 5.87421C2.47833 5.584 2.33059 5.31792 2.11667 5.11L2.06667 5.06C1.89002 4.88233 1.759 4.68164 1.68214 4.47045C1.60529 4.25926 1.5715 4.03239 1.58692 3.80427C1.60234 3.57615 1.66671 3.35137 1.77731 3.14363C1.88791 2.9359 2.04263 2.74962 2.23167 2.59333C2.40479 2.42031 2.60351 2.28356 2.81625 2.19122C3.02899 2.09887 3.25146 2.05278 3.47548 2.05524C3.6995 2.0577 3.92089 2.10866 4.13161 2.20552C4.34234 2.30238 4.53794 2.44341 4.70834 2.62L4.75834 2.67C4.96625 2.88392 5.23233 3.03166 5.52254 3.09431C5.81276 3.15696 6.11537 3.13095 6.39167 3.02H6.43167C6.69986 2.91519 6.93256 2.7341 7.10088 2.50775C7.2692 2.28141 7.36586 2.0096 7.38 1.73V1.66667C7.38 1.13621 7.5907 0.627507 7.96577 0.25244C8.34084 -0.122629 8.84954 -0.333333 9.38 -0.333333C9.91046 -0.333333 10.4192 -0.122629 10.7942 0.25244C11.1693 0.627507 11.38 1.13621 11.38 1.66667V1.74167C11.3941 2.0096 11.4908 2.28141 11.6591 2.50775C11.8274 2.7341 12.0601 2.91519 12.3283 3.02C12.6046 3.13095 12.9072 3.15696 13.1975 3.09431C13.4877 3.03166 13.7538 2.88392 13.9617 2.67L14.0117 2.62C14.1821 2.44341 14.3777 2.30238 14.5884 2.20552C14.7991 2.10866 15.0205 2.0577 15.2445 2.05524C15.4685 2.05278 15.691 2.09887 15.9037 2.19122C16.1165 2.28356 16.3152 2.42031 16.4883 2.59333C16.6774 2.74962 16.8321 2.9359 16.9427 3.14363C17.0533 3.35137 17.1177 3.57615 17.1331 3.80427C17.1485 4.03239 17.1147 4.25926 17.0379 4.47045C16.961 4.68164 16.83 4.88233 16.6533 5.06L16.6033 5.11C16.3894 5.31792 16.2417 5.584 16.179 5.87421C16.1164 6.16443 16.1424 6.46704 16.2533 6.74333C16.3582 7.01153 16.5393 7.24422 16.7656 7.41255C16.992 7.58087 17.2638 7.67753 17.5383 7.69167H17.6017C18.1321 7.69167 18.6408 7.90237 19.0159 8.27744C19.391 8.65251 19.6017 9.16121 19.6017 9.69167C19.6017 10.2221 19.391 10.7308 19.0159 11.1059C18.6408 11.481 18.1321 11.6917 17.6017 11.6917H17.5267C17.2522 11.7058 16.9804 11.8025 16.754 11.9708C16.5277 12.1391 16.3466 12.3718 16.2417 12.64C16.1307 12.9163 16.1047 13.2189 16.1674 13.5091C16.23 13.7994 16.3778 14.0654 16.5917 14.2733L16.6417 14.3233C16.8183 14.4999 16.9494 14.7106 17.0262 14.9218C17.1031 15.133 17.1369 15.3599 17.1215 15.588C17.106 15.8161 17.0417 16.0409 16.9311 16.2486C16.8205 16.4564 16.6658 16.6427 16.4767 16.799C16.3035 16.9721 16.1048 17.1088 15.892 17.2011C15.6793 17.2935 15.4568 17.3396 15.2328 17.3371C15.0088 17.3347 14.7873 17.2837 14.5766 17.1869C14.3659 17.09 14.1703 16.949 14.0017 16.7733L13.9517 16.7233C13.7438 16.5094 13.4777 16.3616 13.1875 16.299C12.8973 16.2363 12.5947 16.2623 12.3183 16.3733H12.2783C12.0101 16.4781 11.7774 16.6592 11.6091 16.8956C11.4408 17.1319 11.3442 17.4137 11.33 17.705V17.7683C11.33 18.2988 11.1193 18.8075 10.7442 19.1826C10.3692 19.5576 9.8605 19.7683 9.33 19.7683C8.79954 19.7683 8.29084 19.5576 7.91577 19.1826C7.5407 18.8075 7.33 18.2988 7.33 17.7683V17.6933C7.31586 17.4188 7.2192 17.147 7.05088 16.9207C6.88256 16.6943 6.64986 16.5132 6.38167 16.4083C6.10537 16.2974 5.80276 16.2714 5.51254 16.334C5.22233 16.3967 4.95625 16.5444 4.74834 16.7583L4.69834 16.8083C4.52067 16.98 4.30998 17.1153 4.07879 17.2057C3.8476 17.2961 3.60073 17.3399 3.35261 17.3345C3.10449 17.3291 2.85971 17.2746 2.63198 17.174C2.40424 17.0734 2.19796 16.9287 2.02495 16.7557C1.83583 16.5994 1.68111 16.4131 1.57051 16.2054C1.4599 15.9976 1.39553 15.7729 1.38011 15.5447C1.36469 15.3166 1.39848 15.0897 1.47533 14.8785C1.55219 14.6673 1.6832 14.4766 1.86 14.299L1.91 14.249C2.12392 14.0411 2.27166 13.775 2.33431 13.4848C2.39696 13.1946 2.37095 12.892 2.26 12.6157V12.5757C2.15519 12.3075 1.9741 12.0748 1.74775 11.9065C1.52141 11.7382 1.2496 11.6415 0.976667 11.6273H0.910001C0.379563 11.6273 -0.129137 11.4166 -0.504206 11.0415C-0.879276 10.6665 -1.09 10.1578 -1.09 9.62733C-1.09 9.09687 -0.879276 8.58817 -0.504206 8.2131C-0.129137 7.83803 0.379563 7.62733 0.910001 7.62733H0.985001C1.25946 7.61319 1.53127 7.51652 1.75762 7.3482C1.98396 7.17988 2.16505 6.94718 2.27 6.679V6.639C2.38095 6.36271 2.40696 6.0601 2.34431 5.76988C2.28166 5.47967 2.13392 5.21359 1.92 5.00567L1.87 4.95567C1.69333 4.778 1.56232 4.56731 1.48546 4.35612C1.40861 4.14493 1.37482 3.91806 1.39024 3.68994C1.40566 3.46183 1.47003 3.23704 1.58064 3.02931C1.69124 2.82158 1.84596 2.6353 2.035 2.479C2.20812 2.30598 2.40684 2.16923 2.61958 2.07688C2.83232 1.98454 3.0548 1.93845 3.27881 1.9409C3.50283 1.94336 3.72422 1.99432 3.93495 2.09118C4.14568 2.18804 4.34128 2.32907 4.51167 2.50567L4.56167 2.55567C4.76958 2.76959 5.03566 2.91733 5.32587 2.97998C5.61608 3.04263 5.91869 3.01662 6.195 2.90567H6.235C6.50319 2.80086 6.73589 2.61977 6.90421 2.39342C7.07253 2.16708 7.16919 1.89527 7.18334 1.621V1.55433C7.18334 1.02387 7.39404 0.515174 7.7691 0.140104C8.14417 -0.234966 8.65287 -0.445669 9.18334 -0.445669C9.71379 -0.445669 10.2225 -0.234966 10.5976 0.140104C10.9726 0.515174 11.1833 1.02387 11.1833 1.55433V1.629C11.1975 1.90327 11.2941 2.17508 11.4625 2.40142C11.6308 2.62777 11.8635 2.80886 12.1317 2.91367C12.408 3.02462 12.7106 3.05063 13.0008 2.98798C13.291 2.92533 13.5571 2.77759 13.765 2.56367L13.815 2.51367C13.9854 2.33707 14.181 2.19604 14.3917 2.09918C14.6025 2.00232 14.8238 1.95136 15.0478 1.9539C15.2718 1.95645 15.4921 2.00254 15.7048 2.09488C15.9176 2.18723 16.1162 2.32398 16.2893 2.497C16.4784 2.6533 16.6331 2.83958 16.7437 3.04731C16.8543 3.25505 16.9187 3.47983 16.9341 3.70794C16.9495 3.93606 16.9157 4.16293 16.8389 4.37412C16.762 4.58531 16.631 4.786 16.4543 4.96367L16.4043 5.01367C16.1904 5.22159 16.0427 5.48767 15.98 5.77788C15.9174 6.06809 15.9434 6.3707 16.0543 6.647V6.687C16.1592 6.95519 16.3403 7.18789 16.5666 7.35621C16.793 7.52453 17.0648 7.62119 17.3393 7.63533H17.406C17.9364 7.63533 18.4451 7.84604 18.8202 8.2211C19.1953 8.59617 19.406 9.10487 19.406 9.63533C19.406 10.1658 19.1953 10.6745 18.8202 11.0496C18.4451 11.4246 17.9364 11.6353 17.406 11.6353H17.331C17.0565 11.6495 16.7847 11.7461 16.5583 11.9145C16.332 12.0828 16.1509 12.3155 16.046 12.5837C15.935 12.86 15.909 13.1626 15.9717 13.4528C16.0343 13.743 16.1821 14.0091 16.396 14.217L16.446 14.267C16.6226 14.4436 16.7537 14.6543 16.8305 14.8655C16.9074 15.0767 16.9412 15.3036 16.9258 15.5317C16.9103 15.7598 16.846 15.9846 16.7354 16.1923C16.6248 16.4001 16.4701 16.5864 16.281 16.7427C16.1079 16.9157 15.9091 17.0525 15.6964 17.1448C15.4837 17.2372 15.2612 17.2833 15.0372 17.2808C14.8132 17.2784 14.5917 17.2274 14.381 17.1305C14.1703 17.0337 13.9747 16.8927 13.806 16.717L13.756 16.667C13.5481 16.4531 13.282 16.3053 12.9918 16.2427C12.7016 16.18 12.399 16.206 12.1227 16.317H12.0827C11.8145 16.4218 11.5818 16.6029 11.4135 16.8393C11.2452 17.0756 11.1485 17.3574 11.1343 17.6497V17.713C11.1343 18.2435 10.9236 18.7522 10.5485 19.1272C10.1735 19.5023 9.66478 19.713 9.13432 19.713C8.60386 19.713 8.09516 19.5023 7.72009 19.1272C7.34502 18.7522 7.13432 18.2435 7.13432 17.713V17.638C7.12018 17.3635 7.02351 17.0917 6.85519 16.8653C6.68687 16.639 6.45417 16.4579 6.18598 16.353C5.90968 16.242 5.60707 16.216 5.31686 16.2787C5.02665 16.3413 4.76057 16.4891 4.55265 16.703L4.50265 16.753C4.32498 16.9247 4.11429 17.06 3.8831 17.1504C3.65191 17.2408 3.40504 17.2846 3.15692 17.2792C2.9088 17.2738 2.66402 17.2193 2.43628 17.1187C2.20855 17.0181 2.00227 16.8734 1.82926 16.7003C1.64014 16.544 1.48542 16.3578 1.37482 16.15C1.26421 15.9423 1.19984 15.7175 1.18442 15.4894C1.16901 15.2612 1.20279 15.0344 1.27965 14.8232C1.3565 14.612 1.48752 14.4213 1.66432 14.2437L1.71432 14.1937C1.92824 13.9858 2.07598 13.7197 2.13863 13.4295C2.20128 13.1393 2.17527 12.8367 2.06432 12.5603V12.5203C1.95951 12.2521 1.77842 12.0194 1.55208 11.8511C1.32574 11.6828 1.05393 11.5861 0.780599 11.572H0.713932C0.183495 11.572 -0.325205 11.3613 -0.700275 10.9862C-1.07534 10.6112 -1.28605 10.1025 -1.28605 9.572C-1.28605 9.04154 -1.07534 8.53284 -0.700275 8.15777C-0.325205 7.7827 0.183495 7.572 0.713932 7.572H0.7889C1.06337 7.55786 1.33518 7.46119 1.56152 7.29287C1.78787 7.12455 1.96896 6.89185 2.07377 6.624V6.584C2.18472 6.30771 2.21073 6.0051 2.14808 5.71488C2.08543 5.42467 1.93769 5.15859 1.72377 4.95067L1.67377 4.90067C1.4971 4.723 1.36608 4.51231 1.28923 4.30112C1.21237 4.08993 1.17859 3.86306 1.194 3.63494C1.20942 3.40683 1.27379 3.18204 1.38439 2.97431C1.495 2.76658 1.64972 2.5803 1.83877 2.424C2.01189 2.25098 2.2106 2.11423 2.42334 2.02188C2.63608 1.92954 2.85856 1.88345 3.08257 1.8859C3.30659 1.88836 3.52798 1.93932 3.73871 2.03618C3.94944 2.13304 4.14504 2.27407 4.31543 2.45067L4.36543 2.50067C4.57335 2.71459 4.83942 2.86233 5.12964 2.92498C5.41985 2.98763 5.72246 2.96162 5.99877 2.85067H6.03877C6.30696 2.74586 6.53966 2.56477 6.70798 2.33842C6.8763 2.11208 6.97296 1.84027 6.9871 1.566V1.50067C6.9871 0.970229 7.1978 0.461529 7.57287 0.0864596C7.94794 -0.28861 8.45664 -0.499313 8.9871 -0.499313C9.51756 -0.499313 10.0263 -0.28861 10.4013 0.0864596C10.7764 0.461529 10.9871 0.970229 10.9871 1.50067V1.575C11.0013 1.84927 11.0979 2.12108 11.2662 2.34742C11.4346 2.57377 11.6673 2.75486 11.9355 2.85967C12.2118 2.97062 12.5144 2.99663 12.8046 2.93398C13.0948 2.87133 13.3609 2.72359 13.5688 2.50967L13.6188 2.45967C13.7892 2.28307 13.9848 2.14204 14.1955 2.04518C14.4062 1.94832 14.6276 1.89736 14.8516 1.8999C15.0756 1.90245 15.2959 1.94854 15.5086 2.04088C15.7214 2.13323 15.92 2.26998 16.0932 2.443C16.2822 2.5993 16.437 2.78558 16.5476 2.99331C16.6582 3.20105 16.7225 3.42583 16.738 3.65394C16.7534 3.88206 16.7196 4.10893 16.6427 4.32012C16.5659 4.53131 16.4349 4.732 16.2582 4.90967L16.2082 4.95967C15.9943 5.16759 15.8466 5.43367 15.7839 5.72388C15.7213 6.01409 15.7473 6.3167 15.8582 6.59233V6.63233C15.9631 6.90052 16.1442 7.13322 16.3705 7.30154C16.5969 7.46986 16.8687 7.56652 17.1432 7.58067H17.2099C17.7403 7.58067 18.249 7.79137 18.6241 8.16644C18.9992 8.54151 19.2099 9.05021 19.2099 9.58067C19.2099 10.1111 18.9992 10.6198 18.6241 10.9949C18.249 11.37 17.7403 11.5807 17.2099 11.5807H17.1349C16.8604 11.5948 16.5886 11.6915 16.3622 11.8598C16.1359 12.0282 15.9548 12.2609 15.8499 12.529C15.7389 12.8053 15.7129 13.1079 15.7756 13.3981C15.8382 13.6884 15.986 13.9544 16.1999 14.1623L16.2499 14.2123C16.4265 14.389 16.5576 14.5996 16.6344 14.8108C16.7113 15.022 16.7451 15.2489 16.7297 15.477C16.7142 15.7051 16.6499 15.9299 16.5393 16.1376C16.4287 16.3454 16.274 16.5317 16.0849 16.688C15.9118 16.861 15.713 16.9978 15.5003 17.0901C15.2876 17.1825 15.0651 17.2286 14.8411 17.2261C14.6171 17.2237 14.3956 17.1727 14.1849 17.0758C13.9742 16.979 13.7786 16.838 13.6099 16.6623L13.5599 16.6123C13.352 16.3984 13.0859 16.2507 12.7957 16.188C12.5055 16.1254 12.2029 16.1514 11.9266 16.2623H11.8866C11.6184 16.3671 11.3857 16.5482 11.2174 16.7846C11.0491 17.0209 10.9524 17.3027 10.9382 17.595V17.6583C10.9382 18.1888 10.7275 18.6975 10.3524 19.0725C9.97737 19.4476 9.46867 19.6583 8.9382 19.6583C8.40774 19.6583 7.89904 19.4476 7.52397 19.0725C7.1489 18.6975 6.9382 18.1888 6.9382 17.6583V17.583C6.92406 17.3085 6.82739 17.0367 6.65907 16.8104C6.49075 16.584 6.25805 16.4029 5.98986 16.298C5.71357 16.187 5.41096 16.161 5.12074 16.2237C4.83053 16.2863 4.56445 16.4341 4.35653 16.648L4.30653 16.698C4.12886 16.8697 3.91817 17.005 3.68698 17.0954C3.45579 17.1858 3.20892 17.2296 2.9608 17.2242C2.71268 17.2188 2.4679 17.1643 2.24017 17.0637C2.01243 16.9631 1.80615 16.8184 1.63314 16.6453C1.44402 16.4891 1.2893 16.3028 1.1787 16.0951C1.06809 15.8873 1.00372 15.6625 0.988304 15.4344C0.972888 15.2063 1.00668 14.9794 1.08353 14.7682C1.16039 14.557 1.29141 14.3663 1.46821 14.1887L1.51821 14.1387C1.73213 13.9308 1.87987 13.6647 1.94252 13.3745C2.00517 13.0843 1.97916 12.7817 1.86821 12.5053V12.4653C1.7634 12.1971 1.58231 11.9644 1.35596 11.7961C1.12962 11.6278 0.857814 11.5311 0.584476 11.517H0.517809C-0.0126283 11.517 -0.521328 11.3063 -0.896398 10.9312C-1.27147 10.5562 -1.48217 10.0475 -1.48217 9.517C-1.48217 8.98654 -1.27147 8.47784 -0.896398 8.10277C-0.521328 7.7277 -0.0126283 7.517 0.517809 7.517H0.59281" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="account-menu-text">Settings & Preferences</span>
                            <svg class="account-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                    
                    <li class="account-menu-item">
                        <a href="#" class="account-menu-link" onclick="openSupportServices(event)">
                            <svg class="account-menu-icon" width="19" height="20" viewBox="0 0 19 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M17.1507 8.33169C16.8665 5.41669 15.1432 0.833344 9.81736 0.833344C4.49151 0.833344 2.76816 5.41669 2.48401 8.33169C1.48713 8.71003 0.829581 9.66712 0.834007 10.7333V12.0167C0.834007 13.4342 1.98316 14.5833 3.40066 14.5833C4.8182 14.5833 5.96735 13.4342 5.96735 12.0167V10.7333C5.96271 9.69011 5.32935 8.7527 4.36316 8.35919C4.54651 6.6725 5.44485 2.66669 9.81736 2.66669C14.1899 2.66669 15.079 6.6725 15.2624 8.35919C14.2981 8.75356 13.668 9.69161 13.6674 10.7333V12.0167C13.6693 12.4996 13.807 12.9723 14.0647 13.3808C14.3224 13.7892 14.6898 14.117 15.1249 14.3267C14.7399 15.0508 13.759 16.0317 11.3482 16.325C10.8663 15.5932 9.93389 15.3047 9.12289 15.6365C8.31194 15.9682 7.84904 16.8275 8.01817 17.6872C8.18729 18.5469 8.94114 19.1667 9.81736 19.1667C10.1569 19.1648 10.4892 19.0687 10.7773 18.889C11.0654 18.7094 11.2979 18.4532 11.449 18.1492C15.3815 17.7 16.6374 15.6742 17.0315 14.4825C18.0979 14.1371 18.8151 13.1375 18.8007 12.0167V10.7333C18.8051 9.66712 18.1475 8.71003 17.1507 8.33169ZM4.13401 12.0167C4.13401 12.4217 3.80568 12.75 3.40066 12.75C2.99564 12.75 2.66735 12.4217 2.66735 12.0167V10.7333C2.66661 10.6366 2.68503 10.5406 2.72156 10.451C2.75808 10.3614 2.81198 10.2799 2.88014 10.2112C2.94831 10.1425 3.02941 10.0879 3.11874 10.0507C3.20808 10.0135 3.3039 9.99438 3.40068 9.99438C3.49746 9.99438 3.59328 10.0135 3.68262 10.0507C3.77196 10.0879 3.85305 10.1425 3.92122 10.2112C3.98939 10.2799 4.04328 10.3614 4.07981 10.451C4.11633 10.5406 4.13475 10.6366 4.13401 10.7333V12.0167ZM15.5007 10.7333C15.5007 10.3283 15.829 10 16.234 10C16.639 10 16.9674 10.3283 16.9674 10.7333V12.0167C16.9674 12.4217 16.639 12.75 16.234 12.75C15.829 12.75 15.5007 12.4217 15.5007 12.0167V10.7333Z" fill="black"/>
                            </svg>
                            <span class="account-menu-text">Support services</span>
                            <svg class="account-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                    
                    <li class="account-menu-item">
                        <a href="auth/logout.php" class="account-menu-link" style="color: #ED1B26;">
                            <svg class="account-menu-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7.5 17.5H4.16667C3.72464 17.5 3.30072 17.3244 2.98816 17.0118C2.67559 16.6993 2.5 16.2754 2.5 15.8333V4.16667C2.5 3.72464 2.67559 3.30072 2.98816 2.98816C3.30072 2.67559 3.72464 2.5 4.16667 2.5H7.5M13.3333 14.1667L17.5 10M17.5 10L13.3333 5.83333M17.5 10H7.5" stroke="#ED1B26" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="account-menu-text">Logout</span>
                            <svg class="account-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Personal Information Container -->
            <div class="personal-info-container" id="personalInfoContainer">
                <div class="personal-info-content">
                    <!-- Profile Image Upload Section -->
                    <div class="profile-image-upload" id="profileImageUpload" style="text-align: center; margin-bottom: 30px;">
                        <div class="current-profile-image <?php echo empty($current_user['profile_image']) ? 'no-image' : ''; ?>" 
                            id="profileImagePreview"
                            onclick="document.getElementById('imageUpload').click();"
                            <?php if (!empty($current_user['profile_image'])): ?>
                            style="background-image: url('<?php echo htmlspecialchars($current_user['profile_image']); ?>');"
                            <?php endif; ?>>
                            <div class="upload-overlay">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 3V13M3 8H13" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="upload-spinner"></div>
                        </div>
                        <form method="POST" enctype="multipart/form-data" id="imageUploadForm">
                            <input type="hidden" name="action" value="upload_image">
                            <input type="file" name="profile_image" id="imageUpload" class="file-input-hidden" 
                                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                                onchange="handleImageSelect(event)">
                        </form>
                        <div style="margin-top: 10px;">
                            <small style="color: #666;">Click to upload a new profile picture</small><br>
                            <small style="color: #999;">JPG, PNG or GIF. Max 5MB</small>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <form class="form-container" method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <input type="text" name="first_name" class="form-field" 
                                   value="<?php echo htmlspecialchars($current_user['first_name']); ?>" 
                                   placeholder="First Name" required>
                            <input type="text" name="last_name" class="form-field" 
                                   value="<?php echo htmlspecialchars($current_user['last_name']); ?>" 
                                   placeholder="Last Name" required>
                        </div>
                        
                        <input type="email" name="email" class="form-field" 
                               value="<?php echo htmlspecialchars($current_user['email']); ?>" 
                               placeholder="Email Address" readonly>
                        
                        <input type="tel" name="phone" class="form-field" 
                               value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" 
                               placeholder="Phone Number" required>
                        
                        <div class="form-row">
                            <input type="date" name="date_of_birth" class="form-field" 
                                   value="<?php echo htmlspecialchars($current_user['date_of_birth'] ?? ''); ?>">
                            <select name="gender" class="form-select">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($current_user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($current_user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($current_user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                <option value="prefer_not_to_say" <?php echo ($current_user['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>

                        <select name="preferred_language" class="form-select">
                            <option value="en" <?php echo ($current_user['preferred_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="yo" <?php echo ($current_user['preferred_language'] ?? '') === 'yo' ? 'selected' : ''; ?>>Yoruba</option>
                            <option value="ig" <?php echo ($current_user['preferred_language'] ?? '') === 'ig' ? 'selected' : ''; ?>>Igbo</option>
                            <option value="ha" <?php echo ($current_user['preferred_language'] ?? '') === 'ha' ? 'selected' : ''; ?>>Hausa</option>
                        </select>

                        <div class="section-divider"></div>

                        <!-- Password Change Section -->
                        <h3 style="margin-bottom: 20px; color: #000; font-size: 18px;">Change Password</h3>
                        
                        <button type="submit" style="margin-top: 20px;" class="continue-button">Update Profile</button>
                    </form>

                    <!-- Separate Password Change Form -->
                    <div class="section-divider"></div>
                    <form method="POST" action="" style="margin-top: 30px;">
                        <input type="hidden" name="action" value="change_password">
                        <h3 style="margin-bottom: 20px; color: #000; font-size: 18px;">Change Password</h3>
                        <input type="password" name="current_password" class="form-field" placeholder="Current Password" style="margin-bottom: 20px;">
                        <input type="password" name="new_password" class="form-field" placeholder="New Password" style="margin-bottom: 20px;">
                        <input type="password" name="confirm_password" class="form-field" placeholder="Confirm New Password" style="margin-bottom: 20px;">
                        <button type="submit" class="continue-button">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Orders Container -->
            <div class="orders-container" id="ordersContainer">
                <div class="orders-content">
                    <div class="orders-list">
                        <div class="order-item">
                            <div class="order-item-image">
                                <img src="images/coke.png" alt="Coca-cola 50CL">
                            </div>
                            <div class="order-item-details">
                                <h3 class="order-item-title">Coca-cola 50CL</h3>
                                <p class="order-item-quantity">1 item</p>
                                <p class="order-item-price">N500.00</p>
                            </div>
                            <div class="order-item-status in-progress">In progress</div>
                        </div>

                        <div class="order-item">
                            <div class="order-item-image">
                                <img src="images/coke.png" alt="Coca-cola 50CL">
                            </div>
                            <div class="order-item-details">
                                <h3 class="order-item-title">Coca-cola 50CL</h3>
                                <p class="order-item-quantity">1 item</p>
                                <p class="order-item-price">N500.00</p>
                            </div>
                            <div class="order-item-status your-review">Your review</div>
                        </div>

                        <div class="order-item">
                            <div class="order-item-image">
                                <img src="images/coke.png" alt="Coca-cola 50CL">
                            </div>
                            <div class="order-item-details">
                                <h3 class="order-item-title">Coca-cola 50CL</h3>
                                <p class="order-item-quantity">1 item</p>
                                <p class="order-item-price">N500.00</p>
                            </div>
                            <div class="order-item-status canceled">Canceled</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Addresses Container -->
            <div class="personal-info-container" id="addressesContainer">
                <div class="personal-info-content">
                    <h3 style="margin-bottom: 20px; color: #000; font-size: 18px;">My Addresses</h3>
                    
                    <div class="addresses-grid">
                        <?php if (empty($user_addresses)): ?>
                            <p style="text-align: center; color: #666; padding: 40px 0;">No addresses added yet</p>
                        <?php else: ?>
                            <?php foreach ($user_addresses as $address): ?>
                                <div class="address-card <?php echo $address['is_default'] ? 'default' : ''; ?>">
                                    <?php if ($address['is_default']): ?>
                                        <span class="default-badge">Default</span>
                                    <?php endif; ?>
                                    <div class="address-label"><?php echo htmlspecialchars($address['label']); ?></div>
                                    <div class="address-text">
                                        <?php echo htmlspecialchars($address['address_line_1']); ?>
                                        <?php if ($address['address_line_2']): ?>, <?php echo htmlspecialchars($address['address_line_2']); ?><?php endif; ?><br>
                                        <?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?> <?php echo htmlspecialchars($address['postal_code']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button class="continue-button" onclick="alert('Add Address functionality will be implemented in Phase 3')">Add New Address</button>
                </div>
            </div>

            <!-- Settings Container -->
            <div class="personal-info-container" id="settingsContainer">
                <div class="personal-info-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_preferences">
                        
                        <div class="preferences-grid">
                            <div class="preference-section">
                                <h3 class="preference-title">Notifications</h3>
                                <div class="checkbox-list">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="email_orders" id="email_orders" 
                                               <?php echo ($notification_prefs['email_orders'] ?? false) ? 'checked' : ''; ?>>
                                        <label for="email_orders">Email notifications for orders</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="email_promotions" id="email_promotions" 
                                               <?php echo ($notification_prefs['email_promotions'] ?? false) ? 'checked' : ''; ?>>
                                        <label for="email_promotions">Email promotions and offers</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="sms_orders" id="sms_orders" 
                                               <?php echo ($notification_prefs['sms_orders'] ?? false) ? 'checked' : ''; ?>>
                                        <label for="sms_orders">SMS notifications for orders</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="push_notifications" id="push_notifications" 
                                               <?php echo ($notification_prefs['push_notifications'] ?? false) ? 'checked' : ''; ?>>
                                        <label for="push_notifications">Push notifications</label>
                                    </div>
                                </div>
                            </div>

                            <div class="preference-section">
                                <h3 class="preference-title">Dietary Preferences</h3>
                                <div class="checkbox-list">
                                    <?php 
                                    $dietary_options = ['vegetarian', 'vegan', 'gluten_free', 'dairy_free', 'nut_free'];
                                    foreach ($dietary_options as $option): 
                                    ?>
                                        <div class="checkbox-item">
                                            <input type="checkbox" name="dietary_preferences[]" value="<?php echo $option; ?>" 
                                                   id="<?php echo $option; ?>" 
                                                   <?php echo in_array($option, $dietary_prefs) ? 'checked' : ''; ?>>
                                            <label for="<?php echo $option; ?>"><?php echo ucfirst(str_replace('_', ' ', $option)); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="continue-button">Save Preferences</button>
                    </form>
                </div>
            </div>

            <!-- Support Services Container -->
            <div class="support-services-container" id="supportServicesContainer">
                <div class="support-services-content">
                    <ul class="support-menu">
                        <li class="support-menu-item">
                            <a href="#" class="support-menu-link" onclick="openLiveChat(event)">
                                <svg class="support-menu-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 2H4C2.9 2 2 2.9 2 4V16C2 17.1 2.9 18 4 18H6L10 22L14 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H13.17L10 19.17L6.83 16H4V4H20V16Z" fill="black"/>
                                    <circle cx="8" cy="10" r="1" fill="black"/>
                                    <circle cx="12" cy="10" r="1" fill="black"/>
                                    <circle cx="16" cy="10" r="1" fill="black"/>
                                </svg>
                                <span class="support-menu-text">Live Chat</span>
                                <svg class="support-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </li>
                        
                        <li class="support-menu-item">
                            <a href="#" class="support-menu-link" onclick="openFAQ(event)">
                                <svg class="support-menu-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 19H11V17H13V19ZM15.07 11.25L14.17 12.17C13.45 12.9 13 13.5 13 15H11V14.5C11 13.4 11.45 12.4 12.17 11.67L13.41 10.41C13.78 10.05 14 9.55 14 9C14 7.9 13.1 7 12 7C10.9 7 10 7.9 10 9H8C8 6.79 9.79 5 12 5C14.21 5 16 6.79 16 9C16 9.88 15.64 10.68 15.07 11.25Z" fill="black"/>
                                </svg>
                                <span class="support-menu-text">FAQ / Help Center</span>
                                <svg class="support-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </li>
                        
                        <li class="support-menu-item">
                            <a href="#" class="support-menu-link" onclick="openContactForm(event)">
                                <svg class="support-menu-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 4H4C2.9 4 2.01 4.9 2.01 6L2 18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z" fill="black"/>
                                </svg>
                                <span class="support-menu-text">Contact Support</span>
                                <svg class="support-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </li>
                        
                        <li class="support-menu-item">
                            <a href="#" class="support-menu-link" onclick="openReportIssue(event)">
                                <svg class="support-menu-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 21H23L12 2L1 21ZM13 18H11V16H13V18ZM13 14H11V10H13V14Z" fill="black"/>
                                </svg>
                                <span class="support-menu-text">Report an Issue</span>
                                <svg class="support-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </li>
                        
                        <li class="support-menu-item">
                            <a href="tel:+234800FARODASH" class="support-menu-link">
                                <svg class="support-menu-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6.62 10.79C8.06 13.62 10.38 15.94 13.21 17.38L15.41 15.18C15.69 14.9 16.08 14.82 16.43 14.93C17.55 15.3 18.75 15.5 20 15.5C20.55 15.5 21 15.95 21 16.5V20C21 20.55 20.55 21 20 21C10.61 21 3 13.39 3 4C3 3.45 3.45 3 4 3H7.5C8.05 3 8.5 3.45 8.5 4C8.5 5.25 8.7 6.45 9.07 7.57C9.18 7.92 9.1 8.31 8.82 8.59L6.62 10.79Z" fill="black"/>
                                </svg>
                                <span class="support-menu-text">Call Support</span>
                                <svg class="support-menu-arrow" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L1 11" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Live Chat Container -->
            <div class="live-chat-container" id="liveChatContainer">
                <div class="live-chat-content">
                    <div class="chat-messages" id="chatMessages">
                        <div class="message-bubble loading">
                            <div class="loading-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                        <div class="message-bubble loading">
                            <div class="loading-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-input-container">
                        <input type="text" class="chat-input" placeholder="Write a message..." id="chatInput">
                        <div class="chat-icons-row">
                            <button class="chat-image-btn" onclick="selectImage()">
                                <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7.70833 32.375C6.86042 32.375 6.13481 32.0733 5.5315 31.47C4.92819 30.8667 4.62603 30.1406 4.625 29.2917V7.70833C4.625 6.86042 4.92717 6.13481 5.5315 5.5315C6.13583 4.92819 6.86144 4.62603 7.70833 4.625H29.2917C30.1396 4.625 30.8657 4.92717 31.47 5.5315C32.0744 6.13583 32.376 6.86144 32.375 7.70833V29.2917C32.375 30.1396 32.0733 30.8657 31.47 31.47C30.8667 32.0744 30.1406 32.376 29.2917 32.375H7.70833ZM7.70833 29.2917H29.2917V7.70833H7.70833V29.2917ZM10.7917 26.2083H26.2083C26.5167 26.2083 26.7479 26.067 26.9021 25.7844C27.0563 25.5017 27.0306 25.2319 26.825 24.975L22.5854 19.3094C22.4312 19.1038 22.2257 19.001 21.9688 19.001C21.7118 19.001 21.5063 19.1038 21.3521 19.3094L17.3438 24.6667L14.4917 20.851C14.3375 20.6455 14.1319 20.5427 13.875 20.5427C13.6181 20.5427 13.4125 20.6455 13.2583 20.851L10.175 24.975C9.96944 25.2319 9.94375 25.5017 10.0979 25.7844C10.2521 26.067 10.4833 26.2083 10.7917 26.2083Z" fill="#3C3C3C" fill-opacity="0.74"/>
                                </svg>
                            </button>
                            <button class="chat-send-btn" onclick="sendMessage()">
                                <svg width="29" height="29" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M24.2145 2.80696C25.4422 2.378 26.6215 3.55734 26.1926 4.785L19.0332 25.2421C18.568 26.5688 16.7192 26.6438 16.1489 25.3593L12.6943 17.5873L17.5566 12.7238C17.7167 12.552 17.8038 12.3247 17.7997 12.09C17.7956 11.8552 17.7004 11.6312 17.5344 11.4651C17.3684 11.2991 17.1444 11.204 16.9096 11.1998C16.6748 11.1957 16.4476 11.2828 16.2758 11.4429L11.4122 16.3053L3.64024 12.8506C2.35578 12.2791 2.4319 10.4315 3.75745 9.96634L24.2145 2.80696Z" fill="#3C3C3C" fill-opacity="0.74"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Container -->
            <div class="faq-container" id="faqContainer">
                <div class="faq-content">
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFAQ(this)">
                                <span>How do I track my order?</span>
                                <svg class="faq-arrow" width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L11 1" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="faq-answer">
                                <p>You can track your order by going to "My Orders" in your account menu. There you'll see real-time updates on your order status including preparation, dispatch, and delivery times.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFAQ(this)">
                                <span>What are your delivery hours?</span>
                                <svg class="faq-arrow" width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L11 1" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="faq-answer">
                                <p>We deliver 24/7! Our standard delivery hours are from 6:00 AM to 11:00 PM, with limited late-night delivery available in select areas until 2:00 AM.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFAQ(this)">
                                <span>How can I cancel my order?</span>
                                <svg class="faq-arrow" width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L11 1" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="faq-answer">
                                <p>You can cancel your order within 5 minutes of placing it through the "My Orders" section. After this time, please contact our support team for assistance.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFAQ(this)">
                                <span>What payment methods do you accept?</span>
                                <svg class="faq-arrow" width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L11 1" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="faq-answer">
                                <p>We accept all major credit/debit cards, bank transfers, mobile money (MTN, Airtel, 9mobile), and cash on delivery in select areas.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFAQ(this)">
                                <span>Is there a minimum order amount?</span>
                                <svg class="faq-arrow" width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1L6 6L11 1" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="faq-answer">
                                <p>Yes, our minimum order amount is ₦1,500. This helps us maintain quality service and cover delivery costs.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Form Container -->
            <div class="contact-form-container" id="contactFormContainer">
                <div class="contact-form-content">
                    <form class="contact-form" onsubmit="handleContactSubmit(event)">
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <select class="form-field" required>
                                <option value="">Select a topic</option>
                                <option value="order">Order Issue</option>
                                <option value="payment">Payment Problem</option>
                                <option value="delivery">Delivery Issue</option>
                                <option value="account">Account Help</option>
                                <option value="technical">Technical Support</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-field" placeholder="your.email@example.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number (Optional)</label>
                            <input type="tel" class="form-field" placeholder="+234 800 000 0000">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea class="form-field form-textarea" placeholder="Describe your issue in detail..." rows="6" required></textarea>
                        </div>
                        
                        <button type="submit" class="contact-submit-btn">Send Message</button>
                    </form>
                </div>
            </div>

            <!-- Report Issue Container -->
            <div class="report-issue-container" id="reportIssueContainer">
                <div class="report-issue-content">
                    <form class="report-form" onsubmit="handleReportSubmit(event)">
                        <div class="form-group">
                            <label class="form-label">Issue Type</label>
                            <select class="form-field" required>
                                <option value="">Select issue type</option>
                                <option value="wrong-order">Wrong Order Delivered</option>
                                <option value="missing-items">Missing Items</option>
                                <option value="food-quality">Food Quality Issue</option>
                                <option value="late-delivery">Late Delivery</option>
                                <option value="driver-issue">Delivery Driver Issue</option>
                                <option value="app-bug">App/Website Bug</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Order Number (if applicable)</label>
                            <input type="text" class="form-field" placeholder="e.g., FD123456789">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Restaurant Name (if applicable)</label>
                            <input type="text" class="form-field" placeholder="Restaurant name">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Describe the Issue</label>
                            <textarea class="form-field form-textarea" placeholder="Please provide as much detail as possible about what went wrong..." rows="6" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Preferred Resolution</label>
                            <select class="form-field">
                                <option value="">How would you like us to resolve this?</option>
                                <option value="refund">Full Refund</option>
                                <option value="partial-refund">Partial Refund</option>
                                <option value="reorder">Reorder the Food</option>
                                <option value="credit">Account Credit</option>
                                <option value="contact">Just Want to Report</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="report-submit-btn">Submit Report</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Continue Button (Fixed at bottom) -->
    <div class="continue-button-container" id="continueButtonContainer">
        <button type="button" class="continue-button" onclick="submitActiveForm()">Continue</button>
    </div>

    <!-- Success Popup -->
    <div class="popup-overlay" id="popupOverlay"></div>
    <div class="success-popup" id="successPopup">
        <div class="success-icon">
            <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M25 7.5L12.5 20L5 12.5" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="success-text">Changes saved</div>
    </div>

    <script>
        let mobileMenuOpen = false;
        let personalInfoOpen = false;
        let ordersOpen = false;
        let addressesOpen = false;
        let settingsOpen = false;
        let supportServicesOpen = false;
        let liveChatOpen = false;
        let faqOpen = false;
        let contactFormOpen = false;
        let reportIssueOpen = false;

        // Handle menu/back button click
        function handleMenuClick() {
            if (personalInfoOpen) {
                closePersonalInfo();
            } else if (ordersOpen) {
                closeOrders();
            } else if (addressesOpen) {
                closeAddresses();
            } else if (settingsOpen) {
                closeSettings();
            } else if (supportServicesOpen) {
                closeSupportServices();
            } else if (liveChatOpen) {
                closeLiveChat();
            } else if (faqOpen) {
                closeFAQ();
            } else if (contactFormOpen) {
                closeContactForm();
            } else if (reportIssueOpen) {
                closeReportIssue();
            } else {
                toggleMobileMenu();
            }
        }

        // Submit the currently active form
        function submitActiveForm() {
            if (personalInfoOpen) {
                // Find the profile update form
                const forms = document.querySelectorAll('.personal-info-container form');
                forms.forEach(form => {
                    const actionInput = form.querySelector('input[name="action"]');
                    if (actionInput && actionInput.value === 'update_profile') {
                        form.submit();
                    }
                });
            }
        }

        // Toggle mobile menu
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            mobileMenuOpen = !mobileMenuOpen;
            sidebar.classList.toggle('open', mobileMenuOpen);
        }

        // Open personal information page
        function openPersonalInfo(event) {
            event.preventDefault();
            const accountContainer = document.getElementById('accountContainer');
            const personalInfoContainer = document.getElementById('personalInfoContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            const continueButtonContainer = document.getElementById('continueButtonContainer');
            
            accountContainer.classList.add('hide');
            personalInfoContainer.classList.add('show');
            
            headerTitle.textContent = 'Personal information';
            menuToggle.classList.add('back-mode');
            menuIcon.style.display = 'none';
            backIcon.style.display = 'block';
            
            personalInfoOpen = true;
        }

        // Close personal information page
        function closePersonalInfo() {
            const accountContainer = document.getElementById('accountContainer');
            const personalInfoContainer = document.getElementById('personalInfoContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            const continueButtonContainer = document.getElementById('continueButtonContainer');
            
            accountContainer.classList.remove('hide');
            personalInfoContainer.classList.remove('show');
            
            headerTitle.textContent = 'Account';
            menuToggle.classList.remove('back-mode');
            menuIcon.style.display = 'block';
            backIcon.style.display = 'none';
            
            personalInfoOpen = false;
        }

        // Open orders page
        function openOrders(event) {
            event.preventDefault();
            const accountContainer = document.getElementById('accountContainer');
            const ordersContainer = document.getElementById('ordersContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            
            accountContainer.classList.add('hide');
            ordersContainer.classList.add('show');
            
            headerTitle.textContent = 'My orders';
            menuToggle.classList.add('back-mode');
            menuIcon.style.display = 'none';
            backIcon.style.display = 'block';
            
            ordersOpen = true;
        }

        // Close orders page
        function closeOrders() {
            const accountContainer = document.getElementById('accountContainer');
            const ordersContainer = document.getElementById('ordersContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            
            accountContainer.classList.remove('hide');
            ordersContainer.classList.remove('show');
            
            headerTitle.textContent = 'Account';
            menuToggle.classList.remove('back-mode');
            menuIcon.style.display = 'block';
            backIcon.style.display = 'none';
            
            ordersOpen = false;
        }

        // Open addresses page
        function openAddresses(event) {
            event.preventDefault();
            const accountContainer = document.getElementById('accountContainer');
            const addressesContainer = document.getElementById('addressesContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            
            accountContainer.classList.add('hide');
            addressesContainer.classList.add('show');
            
            headerTitle.textContent = 'My Addresses';
            menuToggle.classList.add('back-mode');
            menuIcon.style.display = 'none';
            backIcon.style.display = 'block';
            
            addressesOpen = true;
        }

        // Close addresses page
        function closeAddresses() {
            const accountContainer = document.getElementById('accountContainer');
            const addressesContainer = document.getElementById('addressesContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            
            accountContainer.classList.remove('hide');
            addressesContainer.classList.remove('show');
            
            headerTitle.textContent = 'Account';
            menuToggle.classList.remove('back-mode');
            menuIcon.style.display = 'block';
            backIcon.style.display = 'none';
            
            addressesOpen = false;
        }

        // Open settings page
        function openSettings(event) {
            event.preventDefault();
            const accountContainer = document.getElementById('accountContainer');
            const settingsContainer = document.getElementById('settingsContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            
            accountContainer.classList.add('hide');
            settingsContainer.classList.add('show');
            
            headerTitle.textContent = 'Settings & Preferences';
            menuToggle.classList.add('back-mode');
            menuIcon.style.display = 'none';
            backIcon.style.display = 'block';
            
            settingsOpen = true;
        }

        // Close settings page
        function closeSettings() {
            const accountContainer = document.getElementById('accountContainer');
            const settingsContainer = document.getElementById('settingsContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            
            accountContainer.classList.remove('hide');
            settingsContainer.classList.remove('show');
            
            headerTitle.textContent = 'Account';
            menuToggle.classList.remove('back-mode');
            menuIcon.style.display = 'block';
            backIcon.style.display = 'none';
            
            settingsOpen = false;
        }

        // Open support services page
        function openSupportServices(event) {
            event.preventDefault();
            const accountContainer = document.getElementById('accountContainer');
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            
            accountContainer.classList.add('hide');
            supportServicesContainer.classList.add('show');
            
            headerTitle.textContent = 'Support services';
            menuToggle.classList.add('back-mode');
            menuIcon.style.display = 'none';
            backIcon.style.display = 'block';
            
            supportServicesOpen = true;
        }

        // Close support services page
        function closeSupportServices() {
            const accountContainer = document.getElementById('accountContainer');
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const headerTitle = document.getElementById('headerTitle');
            const menuToggle = document.getElementById('menuToggle');
            const menuIcon = document.getElementById('menuIcon');
            const backIcon = document.getElementById('backIcon');
            
            accountContainer.classList.remove('hide');
            supportServicesContainer.classList.remove('show');
            
            headerTitle.textContent = 'Account';
            menuToggle.classList.remove('back-mode');
            menuIcon.style.display = 'block';
            backIcon.style.display = 'none';
            
            supportServicesOpen = false;
        }

        // Open live chat page
        function openLiveChat(event) {
            event.preventDefault();
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const liveChatContainer = document.getElementById('liveChatContainer');
            const headerTitle = document.getElementById('headerTitle');
            
            supportServicesContainer.classList.remove('show');
            liveChatContainer.classList.add('show');
            
            headerTitle.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 20px; font-weight: 600; color: #000000; margin-bottom: 4px;">Tatiana</div>
                    <div style="font-size: 20px; font-weight: 400; color: #959595;">Support manager</div>
                </div>
            `;
            
            supportServicesOpen = false;
            liveChatOpen = true;
            
            setTimeout(startChatSimulation, 1000);
        }

        // Close live chat page
        function closeLiveChat() {
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const liveChatContainer = document.getElementById('liveChatContainer');
            const headerTitle = document.getElementById('headerTitle');
            
            supportServicesContainer.classList.add('show');
            liveChatContainer.classList.remove('show');
            
            headerTitle.textContent = 'Support services';
            
            supportServicesOpen = true;
            liveChatOpen = false;
        }

        // Open FAQ page
        function openFAQ(event) {
            event.preventDefault();
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const faqContainer = document.getElementById('faqContainer');
            const headerTitle = document.getElementById('headerTitle');
            
            supportServicesContainer.classList.remove('show');
            faqContainer.classList.add('show');
            
            headerTitle.textContent = 'FAQ / Help Center';
            
            supportServicesOpen = false;
            faqOpen = true;
        }

        // Close FAQ page
        function closeFAQ() {
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const faqContainer = document.getElementById('faqContainer');
            const headerTitle = document.getElementById('headerTitle');
            
            supportServicesContainer.classList.add('show');
            faqContainer.classList.remove('show');
            
            headerTitle.textContent = 'Support services';
            
            supportServicesOpen = true;
            faqOpen = false;
        }

        // Open contact form page
        function openContactForm(event) {
            event.preventDefault();
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const contactFormContainer = document.getElementById('contactFormContainer');
            const headerTitle = document.getElementById('headerTitle');
            
            supportServicesContainer.classList.remove('show');
            contactFormContainer.classList.add('show');
            
            headerTitle.textContent = 'Contact Support';
            
            supportServicesOpen = false;
            contactFormOpen = true;
        }

        // Close contact form page
        function closeContactForm() {
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const contactFormContainer = document.getElementById('contactFormContainer');
            const headerTitle = document.getElementById('headerTitle');
            
            supportServicesContainer.classList.add('show');
            contactFormContainer.classList.remove('show');
            
            headerTitle.textContent = 'Support services';
            
            supportServicesOpen = true;
            contactFormOpen = false;
        }

        // Open report issue page
        function openReportIssue(event) {
            event.preventDefault();
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const reportIssueContainer = document.getElementById('reportIssueContainer');
            const headerTitle = document.getElementById('headerTitle');
            
            supportServicesContainer.classList.remove('show');
            reportIssueContainer.classList.add('show');
            
            headerTitle.textContent = 'Report an Issue';
            
            supportServicesOpen = false;
            reportIssueOpen = true;
        }

        // Close report issue page
        function closeReportIssue() {
            const supportServicesContainer = document.getElementById('supportServicesContainer');
            const reportIssueContainer = document.getElementById('reportIssueContainer');
            const headerTitle = document.getElementById('headerTitle');
            
            supportServicesContainer.classList.add('show');
            reportIssueContainer.classList.remove('show');
            
            headerTitle.textContent = 'Support services';
            
            supportServicesOpen = true;
            reportIssueOpen = false;
        }

        // Chat functionality
        function startChatSimulation() {
            const chatMessages = document.getElementById('chatMessages');
            
            setTimeout(() => {
                chatMessages.innerHTML = `
                    <div class="message-bubble agent">
                        <div class="message-text">Hello! I'm Tatiana, your support manager. How can I help you today?</div>
                    </div>
                `;
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }, 1500);
        }

        function sendMessage() {
            const chatInput = document.getElementById('chatInput');
            const chatMessages = document.getElementById('chatMessages');
            const message = chatInput.value.trim();
            
            if (message) {
                const userMessage = document.createElement('div');
                userMessage.className = 'message-bubble user';
                userMessage.innerHTML = `<div class="message-text">${message}</div>`;
                chatMessages.appendChild(userMessage);
                
                chatInput.value = '';
                chatMessages.scrollTop = chatMessages.scrollHeight;
                
                setTimeout(() => {
                    const agentMessage = document.createElement('div');
                    agentMessage.className = 'message-bubble agent';
                    agentMessage.innerHTML = `<div class="message-text">Thank you for your message! I'm looking into this for you. Is there anything specific I can help you with regarding your order or account?</div>`;
                    chatMessages.appendChild(agentMessage);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }, 2000);
            }
        }

        function selectImage() {
            alert('Image selection feature coming soon!');
        }

        // Handle image selection and preview
        function handleImageSelect(event) {
            const file = event.target.files[0];
            
            if (!file) {
                return;
            }
            
            // Validate file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, PNG, or GIF)');
                event.target.value = '';
                return;
            }
            
            // Validate file size (5MB max)
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            if (file.size > maxSize) {
                alert('Image size must be less than 5MB');
                event.target.value = '';
                return;
            }
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('profileImagePreview');
                preview.style.backgroundImage = `url('${e.target.result}')`;
                preview.classList.remove('no-image');
                
                // Show confirmation
                if (confirm('Do you want to upload this image as your profile picture?')) {
                    uploadProfileImage();
                } else {
                    // Reset if user cancels
                    event.target.value = '';
                    // Restore original image if exists
                    const originalImage = '<?php echo !empty($current_user['profile_image']) ? addslashes($current_user['profile_image']) : ''; ?>';
                    if (originalImage) {
                        preview.style.backgroundImage = `url('${originalImage}')`;
                    } else {
                        preview.style.backgroundImage = '';
                        preview.classList.add('no-image');
                    }
                }
            };
            reader.readAsDataURL(file);
        }

        // Upload profile image
        function uploadProfileImage() {
            const form = document.getElementById('imageUploadForm');
            const formData = new FormData(form);
            const uploadContainer = document.getElementById('profileImageUpload');
            
            // Show loading state
            uploadContainer.classList.add('uploading');
            
            // Submit via AJAX
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Parse the response to check for success/error messages
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const successMsg = doc.querySelector('.success-message');
                const errorMsg = doc.querySelector('.error-message');
                
                uploadContainer.classList.remove('uploading');
                
                if (successMsg) {
                    showSuccessPopup('Profile picture updated successfully!');
                    // The page will reload or the image is already updated
                } else if (errorMsg) {
                    alert('Error: ' + errorMsg.textContent);
                    // Reset to original image
                    location.reload();
                } else {
                    // Success but no message, reload to get the updated image
                    showSuccessPopup('Profile picture updated!');
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => {
                uploadContainer.classList.remove('uploading');
                console.error('Upload error:', error);
                alert('Failed to upload image. Please try again.');
                location.reload();
            });
        }

        // Alternative: If you prefer immediate form submission without AJAX
        function uploadProfileImageSimple() {
            const form = document.getElementById('imageUploadForm');
            const uploadContainer = document.getElementById('profileImageUpload');
            
            uploadContainer.classList.add('uploading');
            form.submit();
        }

        // Allow sending message with Enter key
        document.addEventListener('DOMContentLoaded', function() {
            const chatInput = document.getElementById('chatInput');
            if (chatInput) {
                chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        sendMessage();
                    }
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const profileForms = document.querySelectorAll('.personal-info-container form');
            profileForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Saving...';
                    }
                });
            });
        });

        // FAQ functionality
        function toggleFAQ(element) {
            const faqItem = element.parentElement;
            const isActive = faqItem.classList.contains('active');
            
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
            });
            
            if (!isActive) {
                faqItem.classList.add('active');
            }
        }

        // Form submission handlers
        function handleContactSubmit(event) {
            event.preventDefault();
            showSuccessPopup('Message sent successfully! We\'ll get back to you within 24 hours.');
            event.target.reset();
        }

        function handleReportSubmit(event) {
            event.preventDefault();
            showSuccessPopup('Report submitted successfully! We\'ll investigate this issue and follow up with you.');
            event.target.reset();
        }

        // Handle form submission (for personal info)
        function handleSubmit(event) {
            event.preventDefault();
            
            // Find and submit the active form
            if (personalInfoOpen) {
                // Find the profile update form and submit it
                const profileForm = document.querySelector('form[action=""] input[name="action"][value="update_profile"]');
                if (profileForm) {
                    profileForm.closest('form').submit();
                }
            }
        }

        // Show success popup
        function showSuccessPopup(message = 'Changes saved') {
            const popup = document.getElementById('successPopup');
            const overlay = document.getElementById('popupOverlay');
            const successText = document.querySelector('.success-text');
            
            successText.textContent = message;
            popup.classList.add('show');
            overlay.classList.add('show');
            
            setTimeout(() => {
                popup.classList.remove('show');
                overlay.classList.remove('show');
            }, 2000);
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (mobileMenuOpen && sidebar && toggle && 
                !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
                mobileMenuOpen = false;
            }
        });

        // Handle account menu link clicks
        document.querySelectorAll('.account-menu-link').forEach(link => {
            if (!link.onclick) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const text = this.querySelector('.account-menu-text').textContent;
                    console.log('Clicked:', text);
                });
            }
        });

        // Handle sidebar menu interactions
        document.querySelectorAll('.menu-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (!this.classList.contains('active')) {
                    document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Add haptic feedback for mobile
        if ('vibrate' in navigator) {
            document.querySelectorAll('.account-menu-link, .mobile-menu-toggle, .continue-button').forEach(element => {
                element.addEventListener('click', function() {
                    navigator.vibrate(50);
                });
            });
        }

        // Handle back button on mobile browsers
        window.addEventListener('popstate', function(event) {
            if (personalInfoOpen) {
                closePersonalInfo();
            } else if (ordersOpen) {
                closeOrders();
            } else if (addressesOpen) {
                closeAddresses();
            } else if (settingsOpen) {
                closeSettings();
            } else if (liveChatOpen) {
                closeLiveChat();
            } else if (faqOpen) {
                closeFAQ();
            } else if (contactFormOpen) {
                closeContactForm();
            } else if (reportIssueOpen) {
                closeReportIssue();
            } else if (supportServicesOpen) {
                closeSupportServices();
            }
        });
    </script>
</body>
</html>