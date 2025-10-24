<?php
require_once 'database.php';
require_once 'config.php';

class AuthManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Register a new user
     */
    public function register($email, $password, $first_name, $last_name, $phone) {
        try {
            // Validate input
            if (!$this->validateEmail($email)) {
                throw new Exception("Invalid email format");
            }

            if (!$this->validatePassword($password)) {
                throw new Exception("Password must be at least " . PASSWORD_MIN_LENGTH . " characters");
            }

            // Check if user already exists
            if ($this->userExists($email)) {
                throw new Exception("User already exists with this email");
            }

            if ($this->phoneExists($phone)) {
                throw new Exception("User already exists with this phone number");
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, phone, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $result = $stmt->execute([$email, $hashed_password, $first_name, $last_name, $phone]);

            if ($result) {
                $user_id = $this->db->lastInsertId();
                
                // FIX: Send verification email on registration
                $this->sendEmailVerification($user_id);
                
                return [
                    'success' => true,
                    'user_id' => $user_id,
                    'message' => 'Registration successful'
                ];
            }

            throw new Exception("Registration failed");

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Authenticate user login
     */
    public function login($email, $password, $remember_me = false) {
        try {
            // Check login attempts
            if ($this->isAccountLocked($email)) {
                throw new Exception("Account temporarily locked due to too many failed attempts");
            }

            // Get user
            $stmt = $this->db->prepare("
                SELECT id, email, password_hash, first_name, last_name, phone, is_active, created_at
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->recordFailedLogin($email);
                throw new Exception("Invalid email or password");
            }

            if (!$user['is_active']) {
                throw new Exception("Account is deactivated");
            }

            // Clear failed attempts
            $this->clearFailedAttempts($email);

            // Create session
            $this->createUserSession($user, $remember_me);

            // Update last login
            $this->updateLastLogin($user['id']);

            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'phone' => $user['phone']
                ],
                'message' => 'Login successful'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create user session
     */
    private function createUserSession($user, $remember_me) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_first_name'] = $user['first_name'];
        $_SESSION['user_last_name'] = $user['last_name'];
        $_SESSION['user_phone'] = $user['phone'];
        $_SESSION['login_time'] = time();
        $_SESSION['is_authenticated'] = true;

        // Set remember me cookie if requested
        if ($remember_me) {
            $token = $this->generateRememberToken();
            $this->saveRememberToken($user['id'], $token);
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 days
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        // FIX: Check if session is already active
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session
        if (isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'] === true) {
            // Check session timeout
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
                $this->logout();
                return false;
            }
            return true;
        }

        // Check remember me token
        if (isset($_COOKIE['remember_token'])) {
            return $this->validateRememberToken($_COOKIE['remember_token']);
        }

        return false;
    }

    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $stmt = $this->db->prepare("
            SELECT id, email, first_name, last_name, phone, profile_image, created_at, notification_preferences, dietary_preferences
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }

    /**
     * Logout user
     */
    public function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Destroy session
        session_unset();
        session_destroy();

        return true;
    }

    // Helper methods
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validatePassword($password) {
        return strlen($password) >= PASSWORD_MIN_LENGTH;
    }

    private function userExists($email) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    private function phoneExists($phone) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        return $stmt->fetchColumn() > 0;
    }

    private function recordFailedLogin($email) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (email, attempted_at)
            VALUES (?, NOW())
            ON DUPLICATE KEY UPDATE 
            attempts = attempts + 1,
            attempted_at = NOW()
        ");
        $stmt->execute([$email]);
    }

    private function isAccountLocked($email) {
        $stmt = $this->db->prepare("
            SELECT attempts, attempted_at 
            FROM login_attempts 
            WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$email, LOGIN_LOCKOUT_TIME]);
        $result = $stmt->fetch();

        return $result && $result['attempts'] >= LOGIN_MAX_ATTEMPTS;
    }

    private function clearFailedAttempts($email) {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->execute([$email]);
    }

    private function generateRememberToken() {
        return bin2hex(random_bytes(32));
    }

    private function saveRememberToken($user_id, $token) {
        $token_hash = hash('sha256', $token);
        $stmt = $this->db->prepare("
            INSERT INTO remember_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ");
        $stmt->execute([$user_id, $token_hash]);
    }

    private function validateRememberToken($token) {
        $token_hash = hash('sha256', $token);
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name, u.phone
            FROM remember_tokens rt
            JOIN users u ON rt.user_id = u.id
            WHERE rt.token_hash = ? AND rt.expires_at > NOW()
        ");
        $stmt->execute([$token_hash]);
        $user = $stmt->fetch();

        if ($user) {
            $this->createUserSession($user, false);
            return true;
        }

        return false;
    }

    private function removeRememberToken($token) {
        $token_hash = hash('sha256', $token);
        $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE token_hash = ?");
        $stmt->execute([$token_hash]);
    }

    private function updateLastLogin($user_id) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
    }

    /**
     * Update user profile
     */
    public function updateProfile($user_id, $data) {
        try {
            $allowed_fields = ['first_name', 'last_name', 'phone', 'date_of_birth', 'gender', 'preferred_language'];
            $update_fields = [];
            $values = [];

            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($update_fields)) {
                throw new Exception("No valid fields to update");
            }

            $values[] = $user_id;
            $sql = "UPDATE users SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);

            if ($result) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                if (isset($data['first_name']) && isset($data['last_name'])) {
                    $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
                    $_SESSION['user_first_name'] = $data['first_name'];
                    $_SESSION['user_last_name'] = $data['last_name'];
                }
                if (isset($data['phone'])) {
                    $_SESSION['user_phone'] = $data['phone'];
                }

                return ['success' => true, 'message' => 'Profile updated successfully'];
            }

            throw new Exception("Failed to update profile");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Change user password
     */
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            // Validate new password
            if (!$this->validatePassword($new_password)) {
                throw new Exception("New password must be at least " . PASSWORD_MIN_LENGTH . " characters");
            }

            // Verify current password
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current_password, $user['password_hash'])) {
                throw new Exception("Current password is incorrect");
            }

            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$new_hash, $user_id]);

            if ($result) {
                // Invalidate all remember tokens
                $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $stmt->execute([$user_id]);

                return ['success' => true, 'message' => 'Password changed successfully'];
            }

            throw new Exception("Failed to change password");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update user preferences
     */
    public function updatePreferences($user_id, $preferences) {
        try {
            $notification_prefs = $preferences['notifications'] ?? [];
            $dietary_prefs = $preferences['dietary'] ?? [];

            $stmt = $this->db->prepare("
                UPDATE users 
                SET notification_preferences = ?, dietary_preferences = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                json_encode($notification_prefs),
                json_encode($dietary_prefs),
                $user_id
            ]);

            if ($result) {
                return ['success' => true, 'message' => 'Preferences updated successfully'];
            }

            throw new Exception("Failed to update preferences");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Upload profile image
     */
    public function updateProfileImage($user_id, $file) {
        try {
            // Validate file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                return ['success' => false, 'message' => 'Invalid file type. Please upload JPG, PNG, or GIF'];
            }
            
            if ($file['size'] > $max_size) {
                return ['success' => false, 'message' => 'File size must be less than 5MB'];
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = 'uploads/profile_images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old profile image if exists
                $stmt = $this->pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $old_image = $stmt->fetchColumn();
                
                if ($old_image && file_exists($old_image)) {
                    unlink($old_image);
                }
                
                // Update database
                $stmt = $this->pdo->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$filepath, $user_id]);
                
                // Update session
                $_SESSION['user']['profile_image'] = $filepath;
                
                return ['success' => true, 'message' => 'Profile picture updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to upload file'];
            }
            
        } catch (Exception $e) {
            error_log("Profile image upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while uploading the image'];
        }
    }

    /**
     * Get user addresses
     */
    public function getUserAddresses($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM user_addresses 
                WHERE user_id = ? AND is_active = TRUE 
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Add user address
     */
    public function addUserAddress($user_id, $address_data) {
        try {
            $required_fields = ['label', 'address_line_1', 'city', 'state', 'postal_code'];
            foreach ($required_fields as $field) {
                if (empty($address_data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // If this is set as default, remove default from other addresses
            if ($address_data['is_default'] ?? false) {
                $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }

            $stmt = $this->db->prepare("
                INSERT INTO user_addresses 
                (user_id, address_type, label, address_line_1, address_line_2, city, state, postal_code, country, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $user_id,
                $address_data['address_type'] ?? 'home',
                $address_data['label'],
                $address_data['address_line_1'],
                $address_data['address_line_2'] ?? null,
                $address_data['city'],
                $address_data['state'],
                $address_data['postal_code'],
                $address_data['country'] ?? 'Nigeria',
                $address_data['is_default'] ?? false
            ]);

            if ($result) {
                return ['success' => true, 'message' => 'Address added successfully', 'address_id' => $this->db->lastInsertId()];
            }

            throw new Exception("Failed to add address");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete user address
     */
    public function deleteUserAddress($user_id, $address_id) {
        try {
            $stmt = $this->db->prepare("UPDATE user_addresses SET is_active = FALSE WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$address_id, $user_id]);

            if ($result) {
                return ['success' => true, 'message' => 'Address deleted successfully'];
            }

            throw new Exception("Failed to delete address");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Complete address management methods
     */
    public function updateUserAddress($user_id, $address_id, $address_data) {
        try {
            // Verify address belongs to user
            $stmt = $this->db->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
            $stmt->execute([$address_id, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Address not found or access denied");
            }

            $required_fields = ['label', 'address_line_1', 'city', 'state', 'postal_code'];
            foreach ($required_fields as $field) {
                if (empty($address_data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // If setting as default, remove default from other addresses
            if ($address_data['is_default'] ?? false) {
                $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }

            $stmt = $this->db->prepare("
                UPDATE user_addresses 
                SET address_type = ?, label = ?, address_line_1 = ?, address_line_2 = ?, 
                    city = ?, state = ?, postal_code = ?, country = ?, is_default = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            
            $result = $stmt->execute([
                $address_data['address_type'] ?? 'home',
                $address_data['label'],
                $address_data['address_line_1'],
                $address_data['address_line_2'] ?? null,
                $address_data['city'],
                $address_data['state'],
                $address_data['postal_code'],
                $address_data['country'] ?? 'Nigeria',
                $address_data['is_default'] ?? false,
                $address_id,
                $user_id
            ]);

            if ($result) {
                $this->logUserActivity($user_id, 'address_updated', "Updated address: {$address_data['label']}");
                return ['success' => true, 'message' => 'Address updated successfully'];
            }

            throw new Exception("Failed to update address");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function setDefaultAddress($user_id, $address_id) {
        try {
            // Verify address belongs to user
            $stmt = $this->db->prepare("SELECT label FROM user_addresses WHERE id = ? AND user_id = ?");
            $stmt->execute([$address_id, $user_id]);
            $address = $stmt->fetch();
            if (!$address) {
                throw new Exception("Address not found or access denied");
            }

            // Remove default from all addresses
            $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Set new default
            $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = TRUE WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$address_id, $user_id]);

            if ($result) {
                return ['success' => true, 'message' => 'Default address updated successfully'];
            }

            throw new Exception("Failed to set default address");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Favorites management
     */
    public function addToFavorites($user_id, $restaurant_id) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_favorites (user_id, restaurant_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE created_at = NOW()
            ");
            $result = $stmt->execute([$user_id, $restaurant_id]);

            if ($result) {
                $this->logUserActivity($user_id, 'favorite_added', "Added restaurant $restaurant_id to favorites");
                return ['success' => true, 'message' => 'Added to favorites'];
            }

            throw new Exception("Failed to add to favorites");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeFromFavorites($user_id, $restaurant_id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND restaurant_id = ?");
            $result = $stmt->execute([$user_id, $restaurant_id]);

            if ($result) {
                $this->logUserActivity($user_id, 'favorite_removed', "Removed restaurant $restaurant_id from favorites");
                return ['success' => true, 'message' => 'Removed from favorites'];
            }

            throw new Exception("Failed to remove from favorites");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUserFavorites($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT restaurant_id, created_at
                FROM user_favorites 
                WHERE user_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function isFavorite($user_id, $restaurant_id) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_favorites WHERE user_id = ? AND restaurant_id = ?");
            $stmt->execute([$user_id, $restaurant_id]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getUserCart($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT uc.*, 
                    COALESCE(fi.name, 'Food Item') as item_name,
                    COALESCE(fi.image_url, '') as item_image,
                    r.name as restaurant_name
                FROM user_cart uc
                LEFT JOIN food_items fi ON uc.food_item_id = fi.id
                LEFT JOIN restaurants r ON uc.restaurant_id = r.id
                WHERE uc.user_id = ? 
                ORDER BY uc.created_at DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function clearUserCart($user_id, $restaurant_id = null) {
        try {
            if ($restaurant_id) {
                $stmt = $this->db->prepare("DELETE FROM user_cart WHERE user_id = ? AND restaurant_id = ?");
                $stmt->execute([$user_id, $restaurant_id]);
            } else {
                $stmt = $this->db->prepare("DELETE FROM user_cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Complete cart management with user context
     */
    public function getCartSummary($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    restaurant_id,
                    COUNT(*) as item_count,
                    SUM(quantity * unit_price) as subtotal
                FROM user_cart 
                WHERE user_id = ? 
                GROUP BY restaurant_id
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function updateCartItem($user_id, $cart_item_id, $quantity) {
        try {
            if ($quantity <= 0) {
                $stmt = $this->db->prepare("DELETE FROM user_cart WHERE id = ? AND user_id = ?");
                $result = $stmt->execute([$cart_item_id, $user_id]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE user_cart 
                    SET quantity = ?, updated_at = NOW() 
                    WHERE id = ? AND user_id = ?
                ");
                $result = $stmt->execute([$quantity, $cart_item_id, $user_id]);
            }
            
            return $result ? ['success' => true] : ['success' => false, 'message' => 'Failed to update cart'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Enhanced search functionality with history
     */
    public function saveSearchHistory($user_id, $search_term, $search_type = 'general', $results_count = 0) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_search_history (user_id, search_term, search_type, results_count)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $search_term, $search_type, $results_count]);
            
            // Keep only last 50 searches per user
            $stmt = $this->db->prepare("
                DELETE FROM user_search_history 
                WHERE user_id = ? AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM user_search_history 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 50
                    ) tmp
                )
            ");
            $stmt->execute([$user_id, $user_id]);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getUserSearchHistory($user_id, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT search_term, search_type, MAX(created_at) as last_searched
                FROM user_search_history 
                WHERE user_id = ? 
                GROUP BY search_term, search_type
                ORDER BY last_searched DESC 
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * User notifications system
     */
    public function addNotification($user_id, $title, $message, $type = 'system', $data = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_notifications (user_id, title, message, type, data)
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $user_id, 
                $title, 
                $message, 
                $type, 
                json_encode($data)
            ]);
            
            return $result ? ['success' => true] : ['success' => false];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUserNotifications($user_id, $limit = 20, $unread_only = false) {
        try {
            $sql = "
                SELECT * FROM user_notifications 
                WHERE user_id = ?
            ";
            $params = [$user_id];
            
            if ($unread_only) {
                $sql .= " AND is_read = FALSE";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function markNotificationAsRead($user_id, $notification_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_notifications 
                SET is_read = TRUE, read_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $result = $stmt->execute([$notification_id, $user_id]);
            
            return $result ? ['success' => true] : ['success' => false];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUnreadNotificationCount($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_notifications 
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$user_id]);
            return intval($stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Enhanced user preferences
     */
    public function updateUserSettings($user_id, $settings) {
        try {
            $allowed_settings = [
                'app_theme', 'preferred_language', 'default_address_id',
                'notification_preferences', 'dietary_preferences'
            ];
            
            $update_fields = [];
            $values = [];
            
            foreach ($settings as $key => $value) {
                if (in_array($key, $allowed_settings)) {
                    if (in_array($key, ['notification_preferences', 'dietary_preferences'])) {
                        $update_fields[] = "$key = ?";
                        $values[] = json_encode($value);
                    } else {
                        $update_fields[] = "$key = ?";
                        $values[] = $value;
                    }
                }
            }
            
            if (empty($update_fields)) {
                return ['success' => false, 'message' => 'No valid settings to update'];
            }
            
            $values[] = $user_id;
            $sql = "UPDATE users SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                $this->logUserActivity($user_id, 'settings_updated', 'Updated user settings');
                return ['success' => true, 'message' => 'Settings updated successfully'];
            }
            
            throw new Exception('Failed to update settings');
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Enhanced user analytics and insights
     */
    public function getUserAnalytics($user_id) {
        try {
            // Get basic stats
            $stats = [];
            
            // Order count and total spent (from API integration)
            $orders = $this->getUserOrders($user_id, 100, 0); // Get more orders for analytics
            $stats['total_orders'] = count($orders['success'] ? $orders['data'] : []);
            $stats['total_spent'] = 0;
            
            if ($orders['success'] && !empty($orders['data'])) {
                foreach ($orders['data'] as $order) {
                    $stats['total_spent'] += $order['final_amount'];
                }
            }
            
            // Favorite restaurants count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_favorites WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['favorite_restaurants'] = intval($stmt->fetchColumn());
            
            // Addresses count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND is_active = TRUE");
            $stmt->execute([$user_id]);
            $stats['saved_addresses'] = intval($stmt->fetchColumn());
            
            // Account age
            $stmt = $this->db->prepare("SELECT created_at FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $created_at = $stmt->fetchColumn();
            if ($created_at) {
                $stats['member_since'] = date('F Y', strtotime($created_at));
                $stats['days_as_member'] = ceil((time() - strtotime($created_at)) / 86400);
            }
            
            // Recent activity
            $stmt = $this->db->prepare("
                SELECT activity_type, COUNT(*) as count 
                FROM user_activity_log 
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY activity_type
            ");
            $stmt->execute([$user_id]);
            $stats['recent_activity'] = $stmt->fetchAll();
            
            return $stats;
            
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Security enhancements
     */
    public function changeEmail($user_id, $new_email, $password) {
        try {
            // Verify current password
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                throw new Exception("Current password is incorrect");
            }
            
            // Check if email already exists
            if ($this->userExists($new_email)) {
                throw new Exception("Email already in use");
            }
            
            // Update email and mark as unverified
            $stmt = $this->db->prepare("
                UPDATE users 
                SET email = ?, email_verified = FALSE, email_verified_at = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$new_email, $user_id]);
            
            if ($result) {
                // Update session
                session_start();
                $_SESSION['user_email'] = $new_email;
                
                $this->logUserActivity($user_id, 'email_changed', "Email changed to: $new_email");
                return ['success' => true, 'message' => 'Email updated successfully'];
            }
            
            throw new Exception("Failed to update email");
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteAccount($user_id, $password) {
        try {
            // Verify password
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                throw new Exception("Password is incorrect");
            }
            
            // Soft delete - deactivate account
            $stmt = $this->db->prepare("
                UPDATE users 
                SET is_active = FALSE, email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()), updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$user_id]);
            
            if ($result) {
                // Clear all sessions and remember tokens
                $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $this->db->prepare("UPDATE user_sessions SET is_active = FALSE WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Clear current session
                $this->logout();
                
                $this->logUserActivity($user_id, 'account_deleted', 'Account deactivated by user');
                return ['success' => true, 'message' => 'Account deleted successfully'];
            }
            
            throw new Exception("Failed to delete account");
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Security and activity tracking
     */
    public function logUserActivity($user_id, $activity_type, $description = '', $metadata = []) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_log (user_id, activity_type, description, ip_address, user_agent, metadata)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $activity_type,
                $description,
                $ip,
                $user_agent,
                json_encode($metadata)
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to log user activity: " . $e->getMessage());
            return false;
        }
    }

    public function getUserSessions($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM user_sessions 
                WHERE user_id = ? AND is_active = TRUE 
                ORDER BY last_activity DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function terminateSession($user_id, $session_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_sessions 
                SET is_active = FALSE 
                WHERE id = ? AND user_id = ?
            ");
            $result = $stmt->execute([$session_id, $user_id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Session terminated'];
            }
            
            throw new Exception("Failed to terminate session");
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUserActivityLog($user_id, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM user_activity_log 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Enhanced notification settings
     */
    public function updateNotificationSettings($user_id, $settings) {
        try {
            // Clear existing settings
            $stmt = $this->db->prepare("DELETE FROM user_notification_settings WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Insert new settings
            $stmt = $this->db->prepare("
                INSERT INTO user_notification_settings (user_id, notification_type, channel, is_enabled)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($settings as $type => $channels) {
                foreach ($channels as $channel => $enabled) {
                    $stmt->execute([$user_id, $type, $channel, $enabled ? 1 : 0]);
                }
            }

            return ['success' => true, 'message' => 'Notification settings updated'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUserNotificationSettings($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT notification_type, channel, is_enabled
                FROM user_notification_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $results = $stmt->fetchAll();

            $settings = [];
            foreach ($results as $row) {
                $settings[$row['notification_type']][$row['channel']] = (bool)$row['is_enabled'];
            }

            return $settings;
        } catch (Exception $e) {
            return [];
        }
    }

    // Helper method to get user phone
    private function getUserPhone($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT phone FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            return $user ? $user['phone'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Initiate password reset process
     */
    public function initiatePasswordReset($email) {
        try {
            // Check if user exists
            $stmt = $this->db->prepare("SELECT id, first_name FROM users WHERE email = ? AND is_active = TRUE");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Don't reveal if email exists for security
                return ['success' => true, 'message' => 'Password reset email sent if account exists'];
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // Store reset token
            $stmt = $this->db->prepare("
                INSERT INTO password_reset_tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $result = $stmt->execute([$user['id'], $token, $expires_at]);
            
            if ($result) {
                // Send password reset email
                $this->sendPasswordResetEmail($email, $user['first_name'], $token);
                
                // Log the activity
                $this->logUserActivity($user['id'], 'password_reset_requested', 'Password reset token generated');
                
                return ['success' => true, 'message' => 'Password reset email sent'];
            }
            
            throw new Exception("Failed to generate reset token");
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to process password reset request'];
        }
    }

    /**
     * Validate password reset token
     */
    public function validatePasswordResetToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id FROM password_reset_tokens 
                WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
            ");
            $stmt->execute([$token]);
            return $stmt->fetch() !== false;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Reset password using token
     */
    public function resetPassword($token, $new_password) {
        try {
            // Validate password
            if (!$this->validatePassword($new_password)) {
                throw new Exception("New password must be at least " . PASSWORD_MIN_LENGTH . " characters");
            }
            
            // Get token info
            $stmt = $this->db->prepare("
                SELECT user_id FROM password_reset_tokens 
                WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
            ");
            $stmt->execute([$token]);
            $token_info = $stmt->fetch();
            
            if (!$token_info) {
                throw new Exception("Invalid or expired reset token");
            }
            
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $password_updated = $stmt->execute([$new_hash, $token_info['user_id']]);
            
            if ($password_updated) {
                // Mark token as used
                $stmt = $this->db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?");
                $stmt->execute([$token]);
                
                // Invalidate all remember tokens
                $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $stmt->execute([$token_info['user_id']]);
                
                // Log activity
                $this->logUserActivity($token_info['user_id'], 'password_reset_completed', 'Password successfully reset');
                
                return ['success' => true, 'message' => 'Password reset successfully'];
            }
            
            throw new Exception("Failed to update password");
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send password reset email (basic implementation)
     */
    private function sendPasswordResetEmail($email, $name, $token) {
        $reset_url = SITE_URL . "/auth/forgot-password.php?step=reset&token=" . urlencode($token);
        
        $subject = "Password Reset - FaroDash";
        $message = "
            <html>
            <head><title>Password Reset</title></head>
            <body>
                <h2>Password Reset Request</h2>
                <p>Hello {$name},</p>
                <p>You requested a password reset for your FaroDash account.</p>
                <p><a href='{$reset_url}' style='background: #ED1B26; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                <p>If the button doesn't work, copy and paste this link in your browser:</p>
                <p>{$reset_url}</p>
                <p>This link expires in 1 hour.</p>
                <p>If you didn't request this reset, please ignore this email.</p>
                <p>Best regards,<br>FaroDash Team</p>
            </body>
            </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: FaroDash <noreply@farodash.com>',
            'Reply-To: support@farodash.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // For production, use a proper email service like SendGrid, Mailgun, etc.
        mail($email, $subject, $message, implode("\r\n", $headers));
    }

    /**
     * Enhanced getUserOrders with proper API integration
     */
    public function getUserOrders($user_id, $limit = 20, $offset = 0) {
        try {
            $phone = $this->getUserPhone($user_id);
            if (!$phone) {
                return ['success' => false, 'message' => 'User phone number not found'];
            }

            // Use the enhanced API handler
            require_once __DIR__ . '/../api/api_handler.php';
            $api = new APIHandler();
            
            return $api->getCustomerOrders($phone, $limit, $offset);
            
        } catch (Exception $e) {
            error_log("Error fetching user orders: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch orders'];
        }
    }

    /**
     * Enhanced order tracking
     */
    public function trackOrder($order_number, $phone) {
        try {
            require_once __DIR__ . '/../api/api_handler.php';
            $api = new APIHandler();
            
            return $api->trackOrder($order_number, $phone);
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to track order'];
        }
    }

    /**
     * Enhanced cart management with validation
     */
    public function addToCart($user_id, $restaurant_id, $food_item_id, $quantity, $unit_price, $addons = [], $special_instructions = '') {
        try {
            
            // Validate inputs
            if ($quantity <= 0) {
                throw new Exception("Quantity must be greater than 0");
            }
            
            if ($unit_price < 0) {
                throw new Exception("Invalid price");
            }
            
            // Check if user already has items from different restaurant
            $stmt = $this->db->prepare("
                SELECT DISTINCT restaurant_id FROM user_cart WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $existing_restaurants = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // FIX: Prevent adding items from different restaurants. Return an error instead of silently clearing the cart.
            if (!empty($existing_restaurants) && !in_array($restaurant_id, $existing_restaurants)) {
                throw new Exception("You can only order from one restaurant at a time. Please clear your current cart to start a new order.");
            }
            
            // Check if item with the same addons already exists in cart
            $addons_json = json_encode($addons);
            $stmt = $this->db->prepare("
                SELECT id, quantity FROM user_cart 
                WHERE user_id = ? AND food_item_id = ? AND JSON_EXTRACT(addons, '$') = ?
            ");
            $stmt->execute([$user_id, $food_item_id, $addons_json]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                $stmt = $this->db->prepare("
                    UPDATE user_cart 
                    SET quantity = ?, unit_price = ?, special_instructions = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$new_quantity, $unit_price, $special_instructions, $existing['id']]);
            } else {
                // Insert new item
                $stmt = $this->db->prepare("
                    INSERT INTO user_cart (user_id, restaurant_id, food_item_id, quantity, unit_price, addons, special_instructions, item_name)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([$user_id, $restaurant_id, $food_item_id, $quantity, $unit_price, $addons_json, $special_instructions, $item_name]);
            }
            if ($result) {
                $this->logUserActivity($user_id, 'cart_updated', "Added item to cart");
                return ['success' => true, 'message' => 'Item added to cart'];
            }

            throw new Exception("Failed to add item to cart");

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get cart with detailed information
     */
    public function getCartWithDetails($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT uc.*, 'Food Item' as item_name
                FROM user_cart uc
                WHERE uc.user_id = ? 
                ORDER BY uc.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $cart_items = $stmt->fetchAll();
            
            // Calculate totals
            $subtotal = 0;
            foreach ($cart_items as $item) {
                $addons = json_decode($item['addons'] ?? '[]', true) ?: [];
                $addon_price = array_sum(array_column($addons, 'price'));
                $subtotal += ($item['unit_price'] + $addon_price) * $item['quantity'];
            }
            
            return [
                'items' => $cart_items,
                'subtotal' => $subtotal,
                'item_count' => array_sum(array_column($cart_items, 'quantity'))
            ];
            
        } catch (Exception $e) {
            return ['items' => [], 'subtotal' => 0, 'item_count' => 0];
        }
    }

    /**
     * Email verification functionality
     */
    public function sendEmailVerification($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Generate verification token
            $token = bin2hex(random_bytes(32));
            
            // Store token (reuse password_reset_tokens table with different purpose)
            $stmt = $this->db->prepare("
                INSERT INTO password_reset_tokens (user_id, token, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            $result = $stmt->execute([$user_id, $token]);
            
            if ($result) {
                $this->sendVerificationEmail($user['email'], $user['first_name'], $token);
                return ['success' => true, 'message' => 'Verification email sent'];
            }
            
            return ['success' => false, 'message' => 'Failed to send verification email'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify email with token
     */
    public function verifyEmail($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id FROM password_reset_tokens 
                WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
            ");
            $stmt->execute([$token]);
            $token_info = $stmt->fetch();
            
            if (!$token_info) {
                return ['success' => false, 'message' => 'Invalid or expired verification link'];
            }
            
            // Mark email as verified
            $stmt = $this->db->prepare("
                UPDATE users 
                SET email_verified = TRUE, email_verified_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$token_info['user_id']]);
            
            if ($result) {
                // Mark token as used
                $stmt = $this->db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?");
                $stmt->execute([$token]);
                
                return ['success' => true, 'message' => 'Email verified successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to verify email'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $name, $token) {
        $verify_url = SITE_URL . "/auth/verify-email.php?token=" . urlencode($token);
        
        $subject = "Verify Your Email - FaroDash";
        $message = "
            <html>
            <head><title>Email Verification</title></head>
            <body>
                <h2>Welcome to FaroDash!</h2>
                <p>Hello {$name},</p>
                <p>Please verify your email address by clicking the button below:</p>
                <p><a href='{$verify_url}' style='background: #ED1B26; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify Email</a></p>
                <p>If the button doesn't work, copy and paste this link in your browser:</p>
                <p>{$verify_url}</p>
                <p>This link expires in 24 hours.</p>
                <p>Best regards,<br>FaroDash Team</p>
            </body>
            </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: FaroDash <noreply@farodash.com>',
            'Reply-To: support@farodash.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        mail($email, $subject, $message, implode("\r\n", $headers));
    }

    /**
     * Enhanced input validation and sanitization
     */
    public function validateAndSanitizeInput($data, $rules) {
        $cleaned = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? '';
            
            // Required field check
            if ($rule['required'] && empty($value)) {
                $errors[] = ucfirst($field) . " is required";
                continue;
            }
            
            // Type-specific validation and sanitization
            switch ($rule['type']) {
                case 'email':
                    if (!empty($value)) {
                        $cleaned[$field] = filter_var(trim($value), FILTER_SANITIZE_EMAIL);
                        if (!filter_var($cleaned[$field], FILTER_VALIDATE_EMAIL)) {
                            $errors[] = "Invalid email format";
                        }
                    }
                    break;
                    
                case 'phone':
                    if (!empty($value)) {
                        $cleaned[$field] = preg_replace('/[^0-9+\-\s]/', '', trim($value));
                        if (strlen($cleaned[$field]) < 10) {
                            $errors[] = "Invalid phone number";
                        }
                    }
                    break;
                    
                case 'string':
                    $cleaned[$field] = trim(strip_tags($value));
                    if (isset($rule['min_length']) && strlen($cleaned[$field]) < $rule['min_length']) {
                        $errors[] = ucfirst($field) . " must be at least {$rule['min_length']} characters";
                    }
                    if (isset($rule['max_length']) && strlen($cleaned[$field]) > $rule['max_length']) {
                        $errors[] = ucfirst($field) . " must be less than {$rule['max_length']} characters";
                    }
                    break;
                    
                case 'int':
                    $cleaned[$field] = intval($value);
                    if (isset($rule['min']) && $cleaned[$field] < $rule['min']) {
                        $errors[] = ucfirst($field) . " must be at least {$rule['min']}";
                    }
                    break;
                    
                default:
                    $cleaned[$field] = $value;
            }
        }
        
        return ['data' => $cleaned, 'errors' => $errors, 'valid' => empty($errors)];
    }

    /**
     * Check if user needs email verification
     */
    public function requiresEmailVerification($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT email_verified FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            return $user ? !$user['email_verified'] : true;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Resend email verification
     */
    public function resendEmailVerification($user_id) {
        try {
            // Delete old tokens
            $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL");
            $stmt->execute([$user_id]);
            
            return $this->sendEmailVerification($user_id);
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>