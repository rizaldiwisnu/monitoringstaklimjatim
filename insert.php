<?php
// insert.php - Menerima data dari ESP32
header('Content-Type: text/plain; charset=utf-8');

// =====================
// 1. SET TIMEZONE WIB
// =====================
date_default_timezone_set('Asia/Jakarta');

// =====================
// 2. KONEKSI DATABASE
// =====================
$host = "localhost";
$username = "root";
$password = "";
$database = "monitoringskjatim";

$conn = mysqli_connect($host, $username, $password, $database, 3306);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =====================
// 3. LOG REQUEST MASUK
// =====================
$log_message = "[" . date('Y-m-d H:i:s') . " WIB] ";
$log_message .= "REQUEST: " . print_r($_GET, true) . "\n";
file_put_contents("debug.log", $log_message, FILE_APPEND);

// =====================
// 4. AMBIL PARAMETER
// =====================
$client_id    = $_GET['client_id']    ?? 'A';
$temperature  = isset($_GET['temperature']) ? floatval($_GET['temperature']) : 0;
$humidity     = isset($_GET['humidity'])    ? floatval($_GET['humidity'])    : 0;
$flame        = isset($_GET['flame'])       ? intval($_GET['flame'])         : 0;
$voltage      = isset($_GET['voltage'])     ? floatval($_GET['voltage'])     : 0;

$relay        = $_GET['relay']        ?? 'ON';
$system_mode  = $_GET['system_mode']  ?? 'auto';
$relay_state  = $_GET['relay_state']  ?? 'ON';

// =====================
// 5. ESCAPE INPUT
// =====================
$client_id   = mysqli_real_escape_string($conn, $client_id);
$relay       = mysqli_real_escape_string($conn, $relay);
$system_mode = mysqli_real_escape_string($conn, $system_mode);
$relay_state = mysqli_real_escape_string($conn, $relay_state);

// =====================
// 6. INSERT KE DATABASE
// =====================
// Timestamp sekarang WIB
$timestamp = date("Y-m-d H:i:s");

$query = "
INSERT INTO sensor_data 
(client_id, temperature, humidity, flame, voltage, relay, system_mode, relay_state, timestamp)
VALUES
('$client_id', '$temperature', '$humidity', '$flame', '$voltage', '$relay', '$system_mode', '$relay_state', '$timestamp')
";

// =====================
// 7. EKSEKUSI QUERY
// =====================
if (mysqli_query($conn, $query)) {

    $response = "Data berhasil disimpan: $timestamp | Mode: $system_mode | Relay: $relay_state | V: $voltage";
    echo $response;

    file_put_contents('debug.log', "SUCCESS: $response\n", FILE_APPEND);

} else {

    $error = "ERROR SQL: " . mysqli_error($conn);
    echo $error;

    file_put_contents('debug.log', "$error\n", FILE_APPEND);
}

mysqli_close($conn);
?>
