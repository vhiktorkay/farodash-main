<?php
/**
 * Enhanced API Handler for FaroDash
 * Handles all API communications with restaurant dashboard
 */
class APIHandler {
    private $base_url;
    private $timeout;
    private $retry_attempts;

    public function __construct() {
        $this->base_url = API_BASE_URL;
        $this->timeout = 30;
        $this->retry_attempts = 3;
    }

    /**
     * Make API request with retry logic and error handling
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null, $headers = []) {
        $url = $this->base_url . $endpoint;
        $default_headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: FaroDash-Web/1.0'
        ];
        $headers = array_merge($default_headers, $headers);

        $last_error = null;

        for ($attempt = 1; $attempt <= $this->retry_attempts; $attempt++) {
            try {
                error_log("API Request Attempt {$attempt}/{$this->retry_attempts} - {$method} {$url}");
                
                if ($data) {
                    error_log("Request data: " . json_encode($data));
                }

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false, // For development - enable in production
                    CURLOPT_SSL_VERIFYHOST => false, // For development - enable in production
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_VERBOSE => false
                ]);

                if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                    $json_data = json_encode($data);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                    error_log("JSON payload: " . $json_data);
                }

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                $curl_errno = curl_errno($ch);
                
                error_log("Response HTTP Code: {$http_code}");
                
                if ($curl_errno) {
                    error_log("CURL Error #{$curl_errno}: {$curl_error}");
                }
                
                curl_close($ch);

                if ($curl_error) {
                    throw new Exception("CURL Error: $curl_error");
                }

                if ($http_code === 0) {
                    throw new Exception("Network error - unable to connect to API");
                }

                // Log response for debugging
                error_log("Response (first 500 chars): " . substr($response, 0, 500));

                $decoded = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    error_log("Raw response: " . $response);
                    
                    // If it's HTML, it might be an error page
                    if (stripos($response, '<html') !== false || stripos($response, '<!doctype') !== false) {
                        throw new Exception("API returned HTML instead of JSON. Server may have an error.");
                    }
                    
                    throw new Exception("Invalid JSON response from API");
                }
                
                // Success - HTTP 2xx
                if ($http_code >= 200 && $http_code < 300) {
                    error_log("Request successful");
                    return [
                        'success' => true,
                        'http_code' => $http_code,
                        'data' => $decoded,
                        'raw_response' => $response
                    ];
                }

                // Client or server error - but we got a response
                error_log("HTTP Error {$http_code}: " . json_encode($decoded));
                
                return [
                    'success' => false,
                    'http_code' => $http_code,
                    'data' => $decoded,
                    'raw_response' => $response,
                    'error' => "HTTP {$http_code}: " . ($decoded['message'] ?? 'Unknown error')
                ];

            } catch (Exception $e) {
                $last_error = $e->getMessage();
                error_log("Request attempt {$attempt} failed: " . $last_error);
                
                if ($attempt === $this->retry_attempts) {
                    // Last attempt failed
                    error_log("All retry attempts exhausted");
                    return [
                        'success' => false,
                        'error' => $last_error,
                        'data' => null,
                        'attempts' => $attempt
                    ];
                }
                
                // Wait before retry with exponential backoff
                $wait_time = pow(2, $attempt - 1);
                error_log("Waiting {$wait_time} seconds before retry...");
                sleep($wait_time);
            }
        }
    }

    /**
     * Get restaurants with location filtering
     */
    public function getRestaurants($lat = null, $lng = null, $limit = 20, $offset = 0) {
        require_once '../includes/config.php';

        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            return [
                'success' => false,
                'data' => [],
                'message' => 'Database connection failed: ' . $conn->connect_error
            ];
        }

        $sql = "SELECT id, name, address, cover_image_url, rating,logo_url, rating 
                FROM restaurants 
                ORDER BY id DESC 
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $restaurants = [];
            while ($row = $result->fetch_assoc()) {
                $restaurants[] = $row;
            }

            $stmt->close();
            $conn->close();

            return [
                'success' => true,
                'data' => $restaurants,
                'message' => 'Restaurants loaded successfully'
            ];
        } else {
            $stmt->close();
            $conn->close();

            return [
                'success' => false,
                'data' => [],
                'message' => 'No restaurants found'
            ];
        }
    }

    /**
     * Get restaurant details by ID
     */
    public function getRestaurantDetails($restaurant_id, $lat = null, $lng = null) {
    require_once '../includes/config.php'; // Ensure DB credentials are available

    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        return [
            'success' => false,
            'data' => null,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ];
    }

    // Prepare query
    $sql = "SELECT id, name, address, description, image_url, rating, category, phone, email, website, opening_hours 
            FROM restaurants 
            WHERE id = ? 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $restaurant = $result->fetch_assoc();

        // Optional: you can add distance calculation here later if $lat and $lng are used
        $stmt->close();
        $conn->close();

        return [
            'success' => true,
            'data' => $restaurant,
            'message' => 'Restaurant details loaded successfully'
        ];
    } else {
        $stmt->close();
        $conn->close();

        return [
            'success' => false,
            'data' => null,
            'message' => 'Restaurant not found'
        ];
    }
}

    /**
     * Get food items for restaurant
     */
    public function getFoodItems($restaurant_id) {
        $endpoint = '/food_items.php?endpoint=restaurant&restaurant_id=' . intval($restaurant_id);
        $result = $this->makeRequest($endpoint);

        if ($result['success'] && isset($result['data']['success']) && $result['data']['success']) {
            return [
                'success' => true,
                'data' => $result['data']['data'] ?? [],
                'message' => 'Food items loaded successfully'
            ];
        }

        return [
            'success' => false,
            'data' => [],
            'message' => $result['error'] ?? 'No food items available'
        ];
    }

    /**
     * Search food items
     */
    public function searchFoodItems($query, $limit = 20) {
        $params = [
            'endpoint' => 'search',
            'q' => $query,
            'limit' => $limit
        ];

        $endpoint = '/food_items.php?' . http_build_query($params);
        $result = $this->makeRequest($endpoint);

        if ($result['success'] && isset($result['data']['success']) && $result['data']['success']) {
            return [
                'success' => true,
                'data' => $result['data']['data'] ?? [],
                'message' => 'Search completed'
            ];
        }

        return [
            'success' => false,
            'data' => [],
            'message' => $result['error'] ?? 'Search failed'
        ];
    }

    /**
     * Create order - ENHANCED VERSION
     */
    public function createOrder($order_data) {
        error_log("APIHandler::createOrder called with data: " . json_encode($order_data));
        
        // Validate order data
        $required_fields = ['customer_name', 'customer_phone', 'delivery_address', 'restaurant_id', 'items'];
        foreach ($required_fields as $field) {
            if (!isset($order_data[$field]) || empty($order_data[$field])) {
                error_log("Missing required field: {$field}");
                return [
                    'success' => false,
                    'data' => null,
                    'message' => "Missing required field: {$field}"
                ];
            }
        }

        // Make the API request
        $result = $this->makeRequest('/orders.php', 'POST', $order_data);

        error_log("APIHandler::createOrder result: " . json_encode($result));

        if ($result['success'] && isset($result['data']['success']) && $result['data']['success']) {
            return [
                'success' => true,
                'data' => $result['data']['data'],
                'message' => $result['data']['message'] ?? 'Order created successfully'
            ];
        }

        // Handle various error scenarios
        $error_message = 'Failed to create order';
        
        if (isset($result['data']['message'])) {
            $error_message = $result['data']['message'];
        } elseif (isset($result['error'])) {
            $error_message = $result['error'];
        }

        return [
            'success' => false,
            'data' => $result['data'] ?? null,
            'message' => $error_message,
            'http_code' => $result['http_code'] ?? null
        ];
    }

    /**
     * Track order
     */
    public function trackOrder($order_number, $phone) {
        $params = [
            'endpoint' => 'track',
            'order_number' => $order_number,
            'phone' => urlencode($phone)
        ];

        $endpoint = '/orders.php?' . http_build_query($params);
        $result = $this->makeRequest($endpoint);

        if ($result['success'] && isset($result['data']['success']) && $result['data']['success']) {
            return [
                'success' => true,
                'data' => $result['data']['data'],
                'message' => 'Order found'
            ];
        }

        return [
            'success' => false,
            'data' => null,
            'message' => $result['data']['message'] ?? 'Order not found'
        ];
    }

    /**
     * Get customer orders
     */
    public function getCustomerOrders($phone, $limit = 20, $offset = 0) {
        $params = [
            'endpoint' => 'customer',
            'phone' => urlencode($phone),
            'limit' => $limit,
            'offset' => $offset
        ];

        $endpoint = '/orders.php?' . http_build_query($params);
        $result = $this->makeRequest($endpoint);

        if ($result['success'] && isset($result['data']['success']) && $result['data']['success']) {
            return [
                'success' => true,
                'data' => $result['data']['data'] ?? [],
                'message' => 'Orders retrieved'
            ];
        }

        return [
            'success' => false,
            'data' => [],
            'message' => $result['error'] ?? 'Failed to load orders'
        ];
    }

    /**
     * Test API connection
     */
    public function testConnection() {
        error_log("Testing API connection to: " . $this->base_url);
        $result = $this->makeRequest('/restaurants.php?limit=1');
        return $result['success'];
    }
}
?>