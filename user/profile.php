<?php
require_once '../includes/session_handler.php';
require_once '../includes/auth_functions.php';

// Require authentication
$current_user = getCurrentUserOrRedirect('../auth/login.php');
$auth = new AuthManager();

// This page will be for advanced profile management
// For now, redirect to account.php
header('Location: ../account.php');
exit();
?>