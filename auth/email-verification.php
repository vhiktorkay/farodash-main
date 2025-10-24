<?php
require_once '../includes/auth_functions.php';
require_once '../includes/session_handler.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['unverified_user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit;
}

$auth = new AuthManager();
$success_message = '';
$error_message = '';

// Handle resend verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
    $result = $auth->resendEmailVerification($user_id);
    if ($result['success']) {
        $success_message = 'Verification email sent! Please check your inbox.';
    } else {
        $error_message = $result['message'];
    }
}

// Get user email for display
$stmt = $auth->db->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$user_email = '';
try {
    $stmt = Database::getInstance()->getConnection()->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $user_email = $user ? $user['email'] : '';
} catch (Exception $e) {
    $user_email = 'your registered email';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Required - FaroDash</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .verification-container {
            max-width: 500px;
            margin: 80px auto;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .verification-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: #FFF3CD;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        
        .verification-title {
            font-size: 24px;
            font-weight: 600;
            color: #000;
            margin-bottom: 16px;
        }
        
        .verification-text {
            color: #666;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        
        .user-email {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 16px 0;
            font-weight: 500;
            color: #ED1B26;
        }
        
        .resend-button {
            background: #ED1B26;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 16px;
            transition: background-color 0.3s ease;
        }
        
        .resend-button:hover {
            background: #d41420;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-icon">📧</div>
        <h1 class="verification-title">Email Verification Required</h1>
        
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <p class="verification-text">
            We've sent a verification link to your email address. Please check your inbox and click the link to verify your account before you can start using FaroDash.
        </p>
        
        <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
        
        <p class="verification-text">
            Didn't receive the email? Check your spam folder or click below to resend.
        </p>
        
        <form method="POST">
            <button type="submit" name="resend_verification" class="resend-button">
                Resend Verification Email
            </button>
        </form>
        
        <a href="login.php" class="back-link">← Back to Login</a>
    </div>
</body>
</html>