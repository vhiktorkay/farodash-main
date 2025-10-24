<?php
require_once '../includes/config.php';
require_once '../api/api_handler.php';
header('Content-Type: application/json');

$api = new APIHandler();
$id = $_GET['id'] ?? 0;
$lat = $_GET['lat'] ?? null;
$lng = $_GET['lng'] ?? null;

$result = $api->getRestaurantDetails($id, $lat, $lng);
echo json_encode($result);
?>