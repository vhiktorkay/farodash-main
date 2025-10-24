<?php
/**
 * Security utility functions for FaroDash
 */

class SecurityManager {
    
    /**
     * Rate limiting for API endpoints
     */
    public static function checkRateLimit($key, $max_attempts = 10, $time_window = 3600) {
        $cache_file = sys_get_temp_dir() . '/farodash_rate_limit_' . md5($key) . '.json';
        
        $current_time = time();
        $attempts = [];
        
        if (file_exists($cache_file)) {
            $data = json_decode(file_get_contents($cache_file), true);
            if ($data && isset($data['attempts'])) {
                // Filter out old attempts
                $attempts = array_filter($data['attempts'], function($timestamp) use ($current_time, $time_window) {
                    return ($current_time - $timestamp) < $time_window;
                });
            }
        }
        
        if (count($attempts) >= $max_attempts) {
            return false; // Rate limit exceeded
        }
        
        // Add current attempt
        $attempts[] = $current_time;
        
        // Save updated attempts
        file_put_contents($cache_file, json_encode(['attempts' => $attempts]));
        
        return true;
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize user input
     */
    public static function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'phone':
                return preg_replace('/[^0-9+\-\s]/', '', trim($input));
            case 'int':
                return intval($input);
            case 'float':
                return floatval($input);
            case 'html':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            default:
                return trim(strip_tags($input));
        }
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event_type, $details, $ip_address = null) {
        $ip_address = $ip_address ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $event_type,
            'details' => $details,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent
        ];
        
        $log_file = sys_get_temp_dir() . '/farodash_security.log';
        file_put_contents($log_file, json_encode($log_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
?>