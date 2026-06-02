<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include 'config.php';

$action = $_GET['action'] ?? '';

if ($action == 'get_status') {
    // Get latest status from database
    $query = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'status' => 'success',
            'data' => [
                'mode' => $row['system_mode'],
                'relay' => $row['relay_state'],
                'temperature' => $row['temperature'],
                'humidity' => $row['humidity'],
                'voltage' => $row['voltage'],
                'flame' => $row['flame']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No data found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}
?>