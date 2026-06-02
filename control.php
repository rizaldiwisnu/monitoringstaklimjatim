<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Include config
include 'config.php';

// Get parameters
$mode = $_GET['mode'] ?? '';
$relay = $_GET['relay'] ?? '';

// Validate parameters
if (empty($mode) || empty($relay)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

// ESP32 IP address - GANTI DENGAN IP ESP32 ANDA
$esp32_ip = "192.168.1.14"; // <- IMPORTANT: GANTI INI!

// URL untuk kontrol ESP32
$control_url = "http://" . $esp32_ip . "/control?mode=" . $mode . "&relay=" . $relay;

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $control_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    echo json_encode(['status' => 'success', 'message' => 'Control command sent', 'response' => $response]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send command to ESP32', 'http_code' => $http_code]);
}
?>