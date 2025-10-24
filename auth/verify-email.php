<?php
require_once '../includes/auth_functions.php';
require_once '../includes/session_handler.php';

$token = $_GET['token'] ?? '';
$success_message = '';
$error_message = '';

if (empty($token)) {
    $error_message = 'Invalid verification link.';
} else {
    $auth = new AuthManager();
    $result = $auth->verifyEmail($token);
    
    if ($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - FaroDash</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="logo">
            <img src="../images/logo.png" alt="FaroDash">
        </div>

        <?php if ($success_message): ?>
            <h1 class="auth-title">Email Verified!</h1>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <p>Your email has been successfully verified. You can now enjoy all features of FaroDash.</p>
            <a href="login.php" class="auth-button" style="display: inline-block; text-align: center; text-decoration: none; margin-top: 20px;">Continue to Login</a>
        <?php else: ?>
            <h1 class="auth-title">Verification Failed</h1>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <p>The verification link may have expired or is invalid.</p>
            <a href="register.php" class="auth-button" style="display: inline-block; text-align: center; text-decoration: none; margin-top: 20px;">Register Again</a>
        <?php endif; ?>

        <div class="auth-links">
            <p><a href="../index.php">← Back to FaroDash</a></p>
        </div>
    </div>
</body>
</html>