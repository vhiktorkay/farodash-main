<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'farocgrl_farodash');
define('DB_USER', 'farocgrl_main');
define('DB_PASS', '3QJIN&oo70+D');

// Site configuration
define('SITE_URL', 'http://farodash.com');
define('SESSION_TIMEOUT', 7200); // 2 hours

// Security settings
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// API Configuration
define('API_BASE_URL', 'https://dashboard.farodash.com/api');
?>