<?php
require_once '../includes/config.php';
require_once '../api/api_handler.php';
header('Content-Type: application/json');

$api = new APIHandler();
$restaurant_id = $_GET['restaurant_id'] ?? 0;

$result = $api->getFoodItems($restaurant_id);
echo json_encode($result);
?>