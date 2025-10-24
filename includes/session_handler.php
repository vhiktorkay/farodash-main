<?php
require_once 'auth_functions.php';

/**
 * Redirect if not authenticated
 */
function requireAuth($redirect_url = 'auth/login.php') {
    $auth = new AuthManager();
    if (!$auth->isAuthenticated()) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Redirect if authenticated (for login/register pages)
 */
function redirectIfAuthenticated($redirect_url = 'index.php') {
    $auth = new AuthManager();
    if ($auth->isAuthenticated()) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Get current user or redirect
 */
function getCurrentUserOrRedirect($redirect_url = 'auth/login.php') {
    $auth = new AuthManager();
    $user = $auth->getCurrentUser();
    
    if (!$user) {
        header("Location: $redirect_url");
        exit();
    }
    
    return $user;
}

/**
 * Start secure session
 */
function startSecureSession() {
    if (session_status() == PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

/**
 * Get current user or redirect (with email verification check)
 */
function getCurrentUserOrRedirectWithVerification($redirect_url = 'auth/login.php') {
    $auth = new AuthManager();
    $user = $auth->getCurrentUser();
    
    if (!$user) {
        header("Location: $redirect_url");
        exit();
    }
    
    // Check if email verification is required
    if ($auth->requiresEmailVerification($user['id'])) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['unverified_user_id'] = $user['id'];
        header("Location: auth/email-verification.php");
        exit();
    }
    
    return $user;
}
?>