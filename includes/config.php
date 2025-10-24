<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'farodash_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site configuration
define('SITE_URL', 'http://localhost/FEROSASH-MAIN/farodash-main');
define('SESSION_TIMEOUT', 7200); // 2 hours

// Security settings
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// API Configuration
define('API_BASE_URL', 'http://localhost/FEROSASH-MAIN/farodash-main');
?>