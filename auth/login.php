<?php
require_once '../includes/auth_functions.php';
require_once '../includes/session_handler.php';

// Redirect if already authenticated
redirectIfAuthenticated('../index.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $auth = new AuthManager();
        $result = $auth->login($email, $password, $remember_me);
        
        if ($result['success']) {
            // Check if email is verified
            if ($auth->requiresEmailVerification($result['user']['id'])) {
                $_SESSION['unverified_user_id'] = $result['user']['id'];
                header("Location: email-verification.php");
                exit();
            }
            
            // Redirect to intended page or dashboard
            $redirect = $_GET['redirect'] ?? '../index.php';
            header("Location: $redirect");
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FaroDash</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo img {
            height: 50px;
        }

        .auth-title {
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 30px;
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Outfit', sans-serif;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            border-color: #ED1B26;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .auth-button {
            width: 100%;
            background-color: #ED1B26;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .auth-button:hover {
            background-color: #d41420;
        }

        .auth-links {
            text-align: center;
            margin-top: 20px;
        }

        .auth-links a {
            color: #ED1B26;
            text-decoration: none;
        }

        .error-message {
            background-color: #fee;
            color: #d63384;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c2c7;
        }

        .success-message {
            background-color: #d1edff;
            color: #0c63e4;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #b8daff;
        }

        /* ADD MOBILE RESPONSIVE STYLES */
        @media (max-width: 480px) {
            .auth-container {
                padding: 30px 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="logo">
            <img src="../images/logo.png" alt="FaroDash">
        </div>

        <h1 class="auth-title">Welcome Back</h1>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="email" name="email" class="form-input" placeholder="Email Address" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <input type="password" name="password" class="form-input" placeholder="Password" required>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" name="remember_me" id="remember_me">
                <label for="remember_me">Remember me</label>
            </div>

            <button type="submit" class="auth-button">Login</button>
        </form>

        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Sign up</a></p>
            <p><a href="forgot-password.php">Forgot your password?</a></p>
        </div>
    </div>
</body>
</html>