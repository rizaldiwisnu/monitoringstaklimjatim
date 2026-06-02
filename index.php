<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    // Redirect ke halaman login
    header('Location: login.php');
    exit();
}

// Koneksi ke database
$host = "localhost";
$username = "root";
$password = "";
$database = "monitoringskjatim";

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Ambil data terbaru
$query_latest = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
$result_latest = mysqli_query($conn, $query_latest);

// Periksa apakah ada data
if ($result_latest && mysqli_num_rows($result_latest) > 0) {
    $latest_data = mysqli_fetch_assoc($result_latest);
} else {
    // Data default jika database kosong
    $latest_data = [
        'client_id' => 'A',
        'temperature' => 0,
        'humidity' => 0,
        'flame' => 0,
        'voltage' => 0,
        'relay' => 'ON',
        'system_mode' => 'auto',
        'relay_state' => 'ON'
    ];
}

// Ambil data untuk grafik (24 jam terakhir) - HANYA 6 DATA
$query_chart = "SELECT temperature, humidity, voltage, flame, timestamp, system_mode, relay_state 
                FROM sensor_data 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                ORDER BY timestamp DESC 
                LIMIT 6";
$result_chart = mysqli_query($conn, $query_chart);

$chart_data = [];
if ($result_chart && mysqli_num_rows($result_chart) > 0) {
    while ($row = mysqli_fetch_assoc($result_chart)) {
        $chart_data[] = $row;
    }
    // Balik urutan untuk grafik (dari terlama ke terbaru)
    $chart_data = array_reverse($chart_data);
} else {
    // Data dummy untuk grafik jika tidak ada data
    $chart_data = [
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-5 hours')), 'temperature' => 25, 'humidity' => 60, 'voltage' => 220],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-4 hours')), 'temperature' => 26, 'humidity' => 62, 'voltage' => 221],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')), 'temperature' => 27, 'humidity' => 61, 'voltage' => 219],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'temperature' => 28, 'humidity' => 63, 'voltage' => 222],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'temperature' => 29, 'humidity' => 64, 'voltage' => 223],
        ['timestamp' => date('Y-m-d H:i:s'), 'temperature' => 30, 'humidity' => 65, 'voltage' => 224]
    ];
}

// Ambil status mode dan relay dari data terbaru
$current_mode = isset($latest_data['system_mode']) ? $latest_data['system_mode'] : 'auto';
$current_relay = isset($latest_data['relay_state']) ? $latest_data['relay_state'] : 'ON';

// Handle POST request untuk mengubah mode atau relay
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mode']) || isset($_POST['relay'])) {
        $new_mode = isset($_POST['mode']) ? $_POST['mode'] : $current_mode;
        $new_relay = isset($_POST['relay']) ? $_POST['relay'] : $current_relay;
        
        // TAMBAHKAN: Jika berpindah ke mode otomatis, reset relay berdasarkan kondisi sensor
        if ($new_mode === 'auto' && $current_mode === 'manual') {
            $new_relay = determineAutoRelayState($latest_data);
        }
        
        // Update data terbaru dengan mode/relay baru
        $client_id = isset($latest_data['client_id']) ? $latest_data['client_id'] : 'A';
        $temperature = isset($latest_data['temperature']) ? $latest_data['temperature'] : 0;
        $humidity = isset($latest_data['humidity']) ? $latest_data['humidity'] : 0;
        $flame = isset($latest_data['flame']) ? $latest_data['flame'] : 0;
        $voltage = isset($latest_data['voltage']) ? $latest_data['voltage'] : 0;
        $relay_status = isset($latest_data['relay']) ? $latest_data['relay'] : 'ON';
        
        $query = "INSERT INTO sensor_data (client_id, temperature, humidity, flame, voltage, relay, system_mode, relay_state) 
                  VALUES ('$client_id', '$temperature', '$humidity', '$flame', '$voltage', '$relay_status', '$new_mode', '$new_relay')";
        
        if (mysqli_query($conn, $query)) {
            $current_mode = $new_mode;
            $current_relay = $new_relay;
            
            // Debug log
            error_log("Mengirim ke ESP32 - Mode: $current_mode, Relay: $current_relay");
            
            // Kirim perintah ke ESP32
            sendCommandToESP($current_mode, $current_relay);
            
            // Redirect untuk menghindari resubmit form
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// FUNGSI BARU: Tentukan state relay otomatis berdasarkan sensor
function determineAutoRelayState($sensor_data) {
    $flame = isset($sensor_data['flame']) ? $sensor_data['flame'] : 0;
    $temperature = isset($sensor_data['temperature']) ? $sensor_data['temperature'] : 0;
    $voltage = isset($sensor_data['voltage']) ? $sensor_data['voltage'] : 0;
    
    // Logika relay otomatis
    if ($flame == 1) {
        return 'ON';  // Aktifkan relay jika ada api
    } elseif ($temperature > 27) {
        return 'ON';  // Aktifkan relay jika suhu tinggi
    } elseif ($voltage > 235) {
        return 'OFF'; // Matikan relay jika tegangan tinggi
    } else {
        return 'ON';  // Default ON dalam kondisi normal
    }
}

function sendCommandToESP($mode, $relay) {
    // Debug detail
    error_log("=== MENGIRIM PERINTAH KE ESP32 ===");
    error_log("Mode: $mode, Relay: $relay");
    
    // URL ESP32 Anda - SESUAIKAN DENGAN IP ESP32 ANDA!
    $esp_ip = "192.168.1.14"; // ⚠️ GANTI DENGAN IP ESP32 YANG BENAR!
    $esp_url = "http://" . $esp_ip . "/control?mode=" . urlencode($mode) . "&relay=" . urlencode($relay);
    
    error_log("URL: " . $esp_url);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $esp_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Tambah timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    error_log("HTTP Code: " . $http_code);
    error_log("Response: " . $response);
    error_log("Error: " . $error);
    
    curl_close($ch);
    
    return $response;
}

// Fungsi untuk menentukan status relay berdasarkan mode
function getRelayStatusDescription($mode, $relay_state, $sensor_data) {
    if ($mode === 'auto') {
        // Dalam mode otomatis, tentukan status berdasarkan kondisi sensor
        $flame = isset($sensor_data['flame']) ? $sensor_data['flame'] : 0;
        $temperature = isset($sensor_data['temperature']) ? $sensor_data['temperature'] : 0;
        $humidity = isset($sensor_data['humidity']) ? $sensor_data['humidity'] : 0;
        $voltage = isset($sensor_data['voltage']) ? $sensor_data['voltage'] : 0;
        
        if ($flame == 1) {
            return "🚨 TERDETEKSI API : Relay non-aktif";
        } elseif ($temperature > 27) {
            return "🌡️ SUHU TINGGI : Relay non-aktif";
        } elseif ($humidity > 60) {
            return "💧 KELEMBABAN TINGGI : Relay non-aktif";
        } elseif ($voltage > 240) {
            return "⚡ TEGANGAN TINGGI : Relay non-aktif";
        } elseif ($voltage < 180) {
            return "⚡ TEGANGAN RENDAH : Relay non-aktif";
        } else {
            return "✅ KONDISI NORMAL : Relay mengikuti kondisi lingkungan";
        }
    } else {
        // Dalam mode manual, tampilkan status berdasarkan input user
        if ($relay_state === 'ON') {
            return "🎮 MODE MANUAL - Relay diaktifkan secara manual";
        } else {
            return "🎮 MODE MANUAL - Relay dinon-aktifkan secara manual";
        }
    }
}

// Dapatkan deskripsi status relay
$relay_status_description = getRelayStatusDescription($current_mode, $current_relay, $latest_data);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEM MONITORING - BMKG Jawa Timur</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Flatpickr CSS dan JS untuk date picker yang menarik -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <!-- Font Awesome untuk ikon yang lebih kaya -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #06196e 0%, #490424 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
            color: #333;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Glassmorphism effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* HEADER STYLING */
        .header-container {
            margin-bottom: 40px;
        }

        .top-logos {
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            margin-bottom: 30px;
            gap: 20px;
            min-height: 200px;
        }

        .bmkg-logo-container, .jatim-logo-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            text-align: center;
            width: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .bmkg-logo-container {
            align-self: flex-start;
        }

        .jatim-logo-container {
            align-self: flex-start;
        }

        .bmkg-logo-container:hover, .jatim-logo-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 50px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }

        .bmkg-logo, .jatim-logo {
            max-height: 80px;
            width: auto;
            margin-bottom: 15px;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.1));
        }

        .bmkg-text, .jatim-text {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            line-height: 1.4;
        }

        .logo-connector {
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8), transparent);
            max-width: 200px;
            margin-top: 100px;
        }

        /* Container untuk logo kanan dan user info */
        .right-side-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            width: 300px;
        }

        /* User info and logout */
        .user-info {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 25px;
            border-radius: 50px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 100%;
            justify-content: center;
        }

        .username {
            font-weight: 600;
            color: #2d3748;
            font-size: 1rem;
        }

        .logout-btn {
            background: linear-gradient(135deg, #f56565, #c53030);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(245, 101, 101, 0.3);
        }

        /* Main Title */
        .main-title {
            text-align: center;
            color: white;
            margin: 40px 0;
            padding: 30px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .main-title h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .main-title p {
            font-size: 1.5rem;
            opacity: 0.95;
            font-weight: 300;
        }

        .main-title .subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
            font-style: italic;
        }

        /* CONTROL PANEL STYLING */
        .control-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .control-title {
            color: #2d3748;
            margin-bottom: 25px;
            text-align: center;
            font-size: 2rem;
            font-weight: 600;
            position: relative;
        }

        .control-title::after {
            content: '';
            display: block;
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            margin: 15px auto 0;
            border-radius: 2px;
        }

        /* Relay Status Indicator */
        .relay-status-indicator {
            text-align: center;
            margin: 20px 0;
            padding: 25px;
            border-radius: 20px;
            font-size: 1.6rem;
            font-weight: 600;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .relay-on-indicator {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #22543d;
            border: 2px solid #48bb78;
        }

        .relay-off-indicator {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #742a2a;
            border: 2px solid #f56565;
        }

        .relay-status-detail {
            font-size: 1.1rem;
            margin-top: 12px;
            font-weight: normal;
            opacity: 0.9;
            padding: 10px;
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
        }

        /* Mode Indicator */
        .mode-indicator {
            text-align: center;
            margin: 15px 0;
            padding: 15px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1rem;
        }

        .mode-auto {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #22543d;
        }

        .mode-manual {
            background: linear-gradient(135deg, #bee3f8, #90cdf4);
            color: #2c5282;
        }

        /* Mode Selector */
        .mode-selector {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 25px 0;
            flex-wrap: wrap;
        }

        .mode-btn {
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .mode-btn.auto {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .mode-btn.manual {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
        }

        .mode-btn.active {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .mode-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        .mode-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Status Info */
        .status-info {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 1.1rem;
        }

        .status-auto {
            background: linear-gradient(135deg, #f0fff4, #e6fffa);
            color: #22543d;
            border-left: 5px solid #48bb78;
        }

        .status-manual {
            background: linear-gradient(135deg, #ebf8ff, #e6f6ff);
            color: #2c5282;
            border-left: 5px solid #4299e1;
        }

        /* Manual Control */
        .relay-control {
            text-align: center;
            margin-top: 25px;
            padding: 25px;
            background: rgba(255,255,255,0.5);
            border-radius: 20px;
            display: none;
        }

        .relay-control.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .relay-control h3 {
            margin-bottom: 20px;
            color: #2d3748;
            font-size: 1.3rem;
        }

        .control-instruction {
            margin: 20px 0;
            padding: 15px;
            background: #edf2f7;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #4a5568;
            border-left: 4px solid #4299e1;
        }

        .relay-btn {
            padding: 18px 45px;
            margin: 10px;
            border: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .relay-on {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .relay-off {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            color: white;
        }

        .relay-btn:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        .relay-btn:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .relay-btn div {
            font-size: 0.9rem;
            margin-top: 5px;
            opacity: 0.9;
        }

        /* DASHBOARD CARDS */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 50px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 15px;
        }

        .card-header i {
            font-size: 2.5rem;
            margin-right: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-header h2 {
            font-size: 1.4rem;
            color: #2d3748;
            font-weight: 600;
        }

        .value {
            font-size: 3rem;
            font-weight: 700;
            text-align: center;
            margin: 20px 0;
            color: #2d3748;
            line-height: 1.2;
        }

        .status {
            text-align: center;
            padding: 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 15px;
        }

        .safe {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #22543d;
        }

        .warning {
            background: linear-gradient(135deg, #feebc8, #fbd38d);
            color: #744210;
        }

        .danger {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #742a2a;
        }

        /* CHARTS */
        .charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 40px;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .chart-title {
            text-align: center;
            margin-bottom: 20px;
            color: #2d3748;
            font-size: 1.3rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 15px;
        }

        .chart-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 2px;
        }

        .chart-wrapper {
            width: 100%;
            height: 300px;
            position: relative;
        }

        /* DOWNLOAD SECTION */
        .download-section-bottom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 35px;
            margin-top: 40px;
            margin-bottom: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .download-title {
            color: #2d3748;
            margin-bottom: 30px;
            font-size: 2rem;
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .download-title i {
            font-size: 2.2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .download-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            max-width: 800px;
            margin: 0 auto;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            color: #667eea;
            font-size: 1.2rem;
        }

        .date-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .date-input-wrapper i {
            position: absolute;
            left: 15px;
            color: #667eea;
            font-size: 1.3rem;
            pointer-events: none;
            z-index: 1;
        }

        .date-input-wrapper input {
            padding: 14px 14px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Poppins', sans-serif;
        }

        .date-input-wrapper input:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .date-input-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .btn-download-single {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 16px 30px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-download-single:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-download-single i {
            font-size: 1.5rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .refresh-container-bottom {
            text-align: center;
            margin-top: 25px;
        }

        .btn-refresh-bottom {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 10px 20px rgba(72, 187, 120, 0.3);
        }

        .btn-refresh-bottom:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 30px rgba(72, 187, 120, 0.4);
        }

        .btn-refresh-bottom i {
            font-size: 1.2rem;
        }

        /* Flatpickr Customization - UBAH WARNA BACKGROUND BULAN AGAR TIDAK PUTIH */
        .flatpickr-calendar {
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%) !important;
        }

        .flatpickr-months {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            border-radius: 20px 20px 0 0;
            padding: 10px;
        }

        .flatpickr-month {
            color: white !important;
            fill: white !important;
        }

        .flatpickr-month .flatpickr-current-month {
            color: white !important;
        }

        .flatpickr-month .flatpickr-current-month .numInputWrapper,
        .flatpickr-month .flatpickr-current-month .flatpickr-monthDropdown-months {
            color: white !important;
        }

        /* UBAH WARNA BACKGROUND BAGIAN BULAN (DAY CONTAINER) */
        .flatpickr-weekdays {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe) !important;
            padding: 10px 0;
            border-radius: 0;
        }

        .flatpickr-weekday {
            color: #4a5568 !important;
            font-weight: 600 !important;
        }

        /* UBAH WARNA BACKGROUND HARI-HARI DALAM KALENDER */
        .flatpickr-days {
            background: linear-gradient(135deg, #ffffff, #f8fafc) !important;
        }

        .flatpickr-day {
            background: transparent !important;
            color: #2d3748 !important;
            transition: all 0.3s ease;
        }

        .flatpickr-day:hover {
            background: #e9d8fd !important;
            color: #553c9a !important;
        }

        .flatpickr-day.selected {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            border-color: #667eea !important;
            color: white !important;
        }

        .flatpickr-day.today {
            border-color: #667eea !important;
            background: #ebf4ff !important;
            color: #667eea !important;
        }

        .flatpickr-day.today.selected {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            color: white !important;
        }

        .flatpickr-day.prevMonthDay,
        .flatpickr-day.nextMonthDay {
            color: #cbd5e0 !important;
            opacity: 0.7;
        }

        /* UBAH WARNA BACKGROUND DROPDOWN BULAN DAN TAHUN */
        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: rgba(255, 255, 255, 0.2) !important;
            border-radius: 10px !important;
            padding: 5px !important;
        }

        .flatpickr-current-month .flatpickr-monthDropdown-months option {
            color: #2d3748 !important;
            background: white !important;
        }

        .flatpickr-current-month .numInputWrapper {
            background: rgba(255, 255, 255, 0.2) !important;
            border-radius: 10px !important;
        }

        /* UBAH WARNA TOMBOL NAVIGASI */
        .flatpickr-prev-month,
        .flatpickr-next-month {
            color: white !important;
            fill: white !important;
        }

        .flatpickr-prev-month:hover,
        .flatpickr-next-month:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            border-radius: 50% !important;
        }

        /* Loading State */
        .loading {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .main-title h1 {
                font-size: 2.8rem;
            }
            
            .main-title p {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 768px) {
            .top-logos {
                flex-direction: column;
                align-items: center;
            }
            
            .logo-connector {
                display: none;
            }
            
            .bmkg-logo-container, .jatim-logo-container {
                width: 100%;
                max-width: 350px;
            }
            
            .main-title h1 {
                font-size: 2.2rem;
            }
            
            .main-title p {
                font-size: 1.1rem;
            }
            
            .charts {
                grid-template-columns: 1fr;
            }
            
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .mode-selector {
                flex-direction: column;
                align-items: center;
            }
            
            .mode-btn {
                width: 100%;
                justify-content: center;
            }
            
            .relay-btn {
                width: 100%;
                margin: 10px 0;
            }
            
            .download-info-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                text-align: center;
            }
            
            .card-header i {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .value {
                font-size: 2.5rem;
            }
            
            .relay-status-indicator {
                font-size: 1.3rem;
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .main-title h1 {
                font-size: 1.8rem;
            }
            
            .main-title p {
                font-size: 1rem;
            }
            
            .control-panel {
                padding: 20px;
            }
            
            .value {
                font-size: 2rem;
            }
            
            .bmkg-logo, .jatim-logo {
                max-height: 60px;
            }
            
            .user-info {
                flex-direction: column;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER DENGAN LOGO PROFESIONAL -->
        <div class="header-container" data-aos="fade-down">
            <!-- Baris Logo -->
            <div class="top-logos">
                <!-- Logo BMKG Kiri -->
                <div class="bmkg-logo-container" data-aos="fade-right" data-aos-delay="100">
                    <img src="logo-bmkg.png" alt="Logo BMKG" class="bmkg-logo"
                        onerror="this.src='https://www.bmkg.go.id/asset/img/logo-bmkg.png'; this.alt='Logo BMKG'">
                    <div class="bmkg-text">
                        BADAN METEOROLOGI<br>KLIMATOLOGI & GEOFISIKA
                    </div>
                </div>
                
                <!-- Garis penghubung -->
                <div class="logo-connector"></div>
                
                <!-- Container untuk Jawa Timur + Logout -->
                <div class="right-side-container">
                    <!-- Logo Jawa Timur Kanan -->
                    <div class="jatim-logo-container" data-aos="fade-left" data-aos-delay="100">
                        <img src="logo-jatim.png" alt="Logo Jawa Timur" class="jatim-logo"
                            onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/4/4c/Lambang_Jawa_Timur.svg/1200px-Lambang_Jawa_Timur.svg.png'; this.alt='Logo Jawa Timur'">
                        <div class="jatim-text">
                            PROVINSI JAWA TIMUR
                        </div>
                    </div>
                    
                    <!-- LOGOUT DI BAWAH LOGO JAWA TIMUR -->
                    <div class="user-info" data-aos="fade-up" data-aos-delay="200">
                        <span class="username">
                            <i class="fas fa-user-circle" style="color: #667eea;"></i>
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        </span>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>   
            
            <!-- Judul Utama -->
            <div class="main-title" data-aos="zoom-in" data-aos-delay="300">
                <h1>SISTEM MONITORING RUANG SERVER</h1>
                <p>Stasiun Klimatologi Kelas II Jawa Timur</p>
                <div class="subtitle">
                    <i class="fas fa-server"></i> Real-time Monitoring System
                </div>
            </div>
        </div>

        <!-- Panel Kontrol Mode dan Relay -->
        <div class="control-panel" data-aos="fade-up" data-aos-delay="400">
            <h2 class="control-title">
                <i class="fas fa-sliders-h" style="margin-right: 10px;"></i>
                KONTROL SISTEM MONITORING
            </h2>
            
            <!-- Status Relay -->
            <div class="relay-status-indicator <?php echo $current_relay === 'ON' ? 'relay-on-indicator' : 'relay-off-indicator'; ?>" data-aos="zoom-in">
                <?php if ($current_relay === 'ON'): ?>
                    <i class="fas fa-bolt" style="margin-right: 10px;"></i>
                    STATUS RELAY: <span style="font-size: 2rem; font-weight: 700;">ON</span>
                    <i class="fas fa-check-circle" style="margin-left: 10px;"></i>
                <?php else: ?>
                    <i class="fas fa-power-off" style="margin-right: 10px;"></i>
                    STATUS RELAY: <span style="font-size: 2rem; font-weight: 700;">OFF</span>
                    <i class="fas fa-times-circle" style="margin-left: 10px;"></i>
                <?php endif; ?>
                <div class="relay-status-detail">
                    <?php echo $relay_status_description; ?>
                </div>
            </div>

            <!-- Mode Indicator -->
            <div class="mode-indicator <?php echo $current_mode === 'auto' ? 'mode-auto' : 'mode-manual'; ?>" data-aos="fade-up">
                <?php if ($current_mode === 'auto'): ?>
                    <i class="fas fa-robot"></i> SISTEM DALAM MODE OTOMATIS
                <?php else: ?>
                    <i class="fas fa-gamepad"></i> SISTEM DALAM MODE MANUAL
                <?php endif; ?>
            </div>

            <!-- Pemilih Mode -->
            <form method="POST" id="modeForm">
                <div class="mode-selector">
                    <button type="button" class="mode-btn auto <?php echo $current_mode === 'auto' ? 'active' : ''; ?>" 
                            onclick="changeMode('auto')" id="autoBtn">
                        <i class="fas fa-robot"></i> MODE OTOMATIS
                    </button>
                    <button type="button" class="mode-btn manual <?php echo $current_mode === 'manual' ? 'active' : ''; ?>" 
                            onclick="changeMode('manual')" id="manualBtn">
                        <i class="fas fa-gamepad"></i> MODE MANUAL
                    </button>
                    <input type="hidden" name="mode" id="modeInput" value="<?php echo $current_mode; ?>">
                </div>
            </form>

            <!-- Status Mode -->
            <div class="status-info <?php echo $current_mode === 'auto' ? 'status-auto' : 'status-manual'; ?>" data-aos="fade-up">
                <?php
                if ($current_mode === 'auto') {
                    echo '<i class="fas fa-lock"></i> SISTEM OTOMATIS - Relay akan ON/OFF otomatis berdasarkan kondisi sensor';
                } else {
                    echo '<i class="fas fa-hand-pointer"></i> SISTEM MANUAL - Anda dapat mengontrol relay secara manual';
                }
                ?>
            </div>

            <!-- Kontrol Relay Manual -->
            <form method="POST" id="relayForm">
                <div class="relay-control <?php echo $current_mode === 'manual' ? 'active' : ''; ?>" id="manualControl">
                    <h3>
                        <i class="fas fa-hand-pointer" style="color: #4299e1;"></i>
                        KONTROL RELAY MANUAL
                    </h3>
                    
                    <!-- Instruksi Kontrol -->
                    <div class="control-instruction">
                        <i class="fas fa-info-circle" style="color: #4299e1;"></i>
                        Pilih aksi untuk mengontrol aliran listrik ke perangkat jaringan
                    </div>
                    
                    <button type="button" class="relay-btn relay-on" onclick="controlRelay('ON')" 
                        <?php echo $current_relay === 'ON' ? 'disabled' : ''; ?> id="relayOnBtn">
                        <i class="fas fa-power-off"></i> AKTIFKAN RELAY
                        <div style="font-size: 0.9rem; margin-top: 5px; font-weight: normal;">
                            Menyalakan arus listrik ke perangkat
                        </div>
                    </button>
                    <button type="button" class="relay-btn relay-off" onclick="controlRelay('OFF')" 
                        <?php echo $current_relay === 'OFF' ? 'disabled' : ''; ?> id="relayOffBtn">  
                        <i class="fas fa-ban"></i> NON-AKTIFKAN RELAY
                        <div style="font-size: 0.9rem; margin-top: 5px; font-weight: normal;">
                            Memutus arus listrik dari perangkat
                        </div>
                    </button>
                    <input type="hidden" name="relay" id="relayInput" value="<?php echo $current_relay; ?>">
                </div>
            </form>
        </div>

        <!-- Dashboard Sensor -->
        <div class="dashboard">
            <!-- Card Suhu -->
            <div class="card" data-aos="flip-left" data-aos-delay="100">
                <div class="card-header">
                    <i class="fas fa-thermometer-half"></i>
                    <h2>Suhu</h2>
                </div>
                <div class="value"><?php echo isset($latest_data['temperature']) ? number_format($latest_data['temperature'], 1) : 'N/A'; ?> °C</div>
                <div class="status <?php 
                    if (isset($latest_data['temperature'])) {
                        echo $latest_data['temperature'] > 27 ? 'danger' : 'safe';
                    } else {
                        echo 'warning';
                    }
                ?>">
                    <?php 
                    if (isset($latest_data['temperature'])) {
                        echo $latest_data['temperature'] > 27 ? '<i class="fas fa-exclamation-triangle"></i> SUHU TINGGI' : '<i class="fas fa-check-circle"></i> SUHU NORMAL';
                    } else {
                        echo '<i class="fas fa-question-circle"></i> DATA TIDAK TERSEDIA';
                    }
                    ?>
                </div>
            </div>

            <!-- Card Kelembapan -->
            <div class="card" data-aos="flip-left" data-aos-delay="200">
                <div class="card-header">
                    <i class="fas fa-tint"></i>
                    <h2>Kelembapan</h2>
                </div>
                <div class="value"><?php echo isset($latest_data['humidity']) ? number_format($latest_data['humidity'], 1) : 'N/A'; ?> %</div>
                <div class="status <?php 
                    if (isset($latest_data['humidity'])) {
                        echo $latest_data['humidity'] > 60 ? 'danger' : 'safe';
                    } else {
                        echo 'warning';
                    }
                ?>">
                    <?php 
                    if (isset($latest_data['humidity'])) {
                        echo $latest_data['humidity'] > 60 ? '<i class="fas fa-exclamation-triangle"></i> KELEMBABAN TINGGI' : '<i class="fas fa-check-circle"></i> KELEMBABAN NORMAL';
                    } else {
                        echo '<i class="fas fa-question-circle"></i> DATA TIDAK TERSEDIA';
                    }
                    ?>
                </div>
            </div>

            <!-- Card Api -->
            <div class="card" data-aos="flip-left" data-aos-delay="300">
                <div class="card-header">
                    <i class="fas fa-fire"></i>
                    <h2>Deteksi Api</h2>
                </div>
                <div class="value"><?php echo isset($latest_data['flame']) ? ($latest_data['flame'] == 1 ? 'TERDETEKSI' : 'AMAN') : 'N/A'; ?></div>
                <div class="status <?php 
                    if (isset($latest_data['flame'])) {
                        echo $latest_data['flame'] == 1 ? 'danger' : 'safe';
                    } else {
                        echo 'warning';
                    }
                ?>">
                    <?php 
                    if (isset($latest_data['flame'])) {
                        echo $latest_data['flame'] == 1 ? '<i class="fas fa-exclamation-triangle"></i> API TERDETEKSI' : '<i class="fas fa-check-circle"></i> TIDAK ADA API';
                    } else {
                        echo '<i class="fas fa-question-circle"></i> DATA TIDAK TERSEDIA';
                    }
                    ?>
                </div>
            </div>

            <!-- Card Tegangan -->
            <div class="card" data-aos="flip-left" data-aos-delay="400">
                <div class="card-header">
                    <i class="fas fa-bolt"></i>
                    <h2>Tegangan</h2>
                </div>
                <div class="value"><?php echo isset($latest_data['voltage']) ? number_format($latest_data['voltage'], 1) : 'N/A'; ?> V</div>
                <div class="status <?php 
                    if (isset($latest_data['voltage'])) {
                        echo $latest_data['voltage'] > 240 ? 'danger' : 'safe';
                    } else {
                        echo 'warning';
                    }
                ?>">
                    <?php 
                    if (isset($latest_data['voltage'])) {
                        echo $latest_data['voltage'] > 240 ? '<i class="fas fa-exclamation-triangle"></i> TEGANGAN TINGGI' : '<i class="fas fa-check-circle"></i> TEGANGAN NORMAL';
                    } else {
                        echo '<i class="fas fa-question-circle"></i> DATA TIDAK TERSEDIA';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Grafik -->
        <div class="charts">
            <div class="chart-container" data-aos="fade-right" data-aos-delay="100">
                <div class="chart-title">
                    <i class="fas fa-chart-line" style="margin-right: 8px; color: #667eea;"></i>
                    Grafik Suhu & Kelembapan
                </div>
                <div class="chart-wrapper">
                    <canvas id="tempHumidityChart"></canvas>
                </div>
            </div>
            <div class="chart-container" data-aos="fade-left" data-aos-delay="100">
                <div class="chart-title">
                    <i class="fas fa-chart-line" style="margin-right: 8px; color: #667eea;"></i>
                    Grafik Tegangan
                </div>
                <div class="chart-wrapper">
                    <canvas id="voltageChart"></canvas>
                </div>
            </div>
        </div>

        <!-- SECTION DOWNLOAD DATA HISTORIS DENGAN KALENDER -->
        <div class="download-section-bottom" data-aos="zoom-in-up" data-aos-delay="200">
            <h2 class="download-title">
                <i class="fas fa-download"></i> DOWNLOAD DATA HISTORIS
                <i class="fas fa-history"></i>
            </h2>
            
            <form method="GET" action="download.php" class="download-form">
                <div class="download-info-grid">
                    <!-- Dari Tanggal dengan Kalender -->
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt"></i> Dari Tanggal:
                        </div>
                        <div class="date-input-wrapper">
                            <i class="fas fa-calendar-day"></i>
                            <input type="text" name="start_date" id="start_date" class="datepicker" 
                                   placeholder="Pilih tanggal mulai">
                        </div>
                    </div>
                    
                    <!-- Sampai Tanggal dengan Kalender -->
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt"></i> Sampai Tanggal:
                        </div>
                        <div class="date-input-wrapper">
                            <i class="fas fa-calendar-check"></i>
                            <input type="text" name="end_date" id="end_date" class="datepicker" 
                                   placeholder="Pilih tanggal akhir">
                        </div>
                    </div>
                </div>
                
                <!-- Tombol Download -->
                <div style="grid-column: span 2; margin-top: 20px; text-align: center;">
                    <button type="submit" class="btn-download-single">
                        <i class="fas fa-download"></i> DOWNLOAD DATA
                        <i class="fas fa-arrow-down"></i>
                    </button>
                </div>
            </form>
            
            <!-- Tombol Refresh Data dengan Icon -->
            <div class="refresh-container-bottom">
                <button class="btn-refresh-bottom" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                    <i class="fas fa-bolt"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Data dari PHP untuk grafik
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        // Siapkan data untuk grafik
        const labels = chartData.map(item => {
            const date = new Date(item.timestamp);
            return date.toLocaleTimeString('id-ID', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        });
        
        const temperatures = chartData.map(item => parseFloat(item.temperature) || 0);
        const humidities = chartData.map(item => parseFloat(item.humidity) || 0);
        const voltages = chartData.map(item => parseFloat(item.voltage) || 0);

        // Grafik Suhu & Kelembapan
        const tempHumidityCtx = document.getElementById('tempHumidityChart').getContext('2d');
        const tempHumidityChart = new Chart(tempHumidityCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Suhu (°C)',
                        data: temperatures,
                        borderColor: '#f56565',
                        backgroundColor: 'rgba(245, 101, 101, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        yAxisID: 'y',
                        pointBackgroundColor: '#f56565',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 8
                    },
                    {
                        label: 'Kelembapan (%)',
                        data: humidities,
                        borderColor: '#4299e1',
                        backgroundColor: 'rgba(66, 153, 225, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        yAxisID: 'y1',
                        pointBackgroundColor: '#4299e1',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                family: 'Poppins',
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Suhu (°C)',
                            font: {
                                family: 'Poppins',
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Kelembapan (%)',
                            font: {
                                family: 'Poppins',
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    }
                }
            }
        });

        // Grafik Tegangan
        const voltageCtx = document.getElementById('voltageChart').getContext('2d');
        const voltageChart = new Chart(voltageCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Tegangan (V)',
                    data: voltages,
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    pointBackgroundColor: '#48bb78',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                family: 'Poppins',
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Tegangan (V)',
                            font: {
                                family: 'Poppins',
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    }
                }
            }
        });

        // Inisialisasi Flatpickr untuk date picker dengan background yang sudah diubah di CSS
        const commonConfig = {
            dateFormat: "m/d/Y",
            locale: "id",
            allowInput: true,
            showMonths: 1,
            weekNumbers: true,
            monthSelectorType: "dropdown",
            yearSelectorType: "dropdown",
            prevArrow: "<i class='fas fa-chevron-left'></i>",
            nextArrow: "<i class='fas fa-chevron-right'></i>",
        };
        
        // Inisialisasi untuk start_date
        flatpickr("#start_date", {
            ...commonConfig,
            defaultDate: "04/01/2026",
            onChange: function(selectedDates, dateStr, instance) {
                console.log("Start date dipilih: " + dateStr);
            }
        });

        // Inisialisasi untuk end_date
        flatpickr("#end_date", {
            ...commonConfig,
            defaultDate: "05/20/2026",
            onChange: function(selectedDates, dateStr, instance) {
                console.log("End date dipilih: " + dateStr);
            }
        });

        // Fungsi untuk mengubah mode
        function changeMode(mode) {
            // Update UI terlebih dahulu
            document.getElementById('autoBtn').classList.remove('active');
            document.getElementById('manualBtn').classList.remove('active');
            document.getElementById(mode + 'Btn').classList.add('active');
            
            // Tampilkan loading state
            document.getElementById('autoBtn').disabled = true;
            document.getElementById('manualBtn').disabled = true;
            document.getElementById('autoBtn').classList.add('loading');
            document.getElementById('manualBtn').classList.add('loading');

            // Submit form
            document.getElementById('modeInput').value = mode;
            const formData = new FormData(document.getElementById('modeForm'));
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // Refresh halaman setelah berhasil
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    throw new Error('Network response was not ok');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengubah mode');
                // Reset UI jika error
                document.getElementById('autoBtn').disabled = false;
                document.getElementById('manualBtn').disabled = false;
                document.getElementById('autoBtn').classList.remove('loading');
                document.getElementById('manualBtn').classList.remove('loading');
            });
        }

        // Fungsi untuk mengontrol relay
        function controlRelay(action) {
            let actionText = '';
            let detailText = '';
            
            if (action === 'ON') {
                actionText = 'mengaktifkan';
                detailText = 'Arus listrik akan dialirkan ke perangkat jaringan';
            } else {
                actionText = 'menon-aktifkan';
                detailText = 'Arus listrik ke perangkat jaringan akan diputus';
            }
            
            if (!confirm(`Apakah Anda yakin ingin ${actionText} relay?\n\n${detailText}`)) {
                return;
            }

            // Update UI terlebih dahulu
            document.getElementById('relayOnBtn').disabled = true;
            document.getElementById('relayOffBtn').disabled = true;
            document.getElementById('relayOnBtn').classList.add('loading');
            document.getElementById('relayOffBtn').classList.add('loading');

            // Submit form
            document.getElementById('relayInput').value = action;
            const formData = new FormData(document.getElementById('relayForm'));
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // Refresh halaman setelah berhasil
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    throw new Error('Network response was not ok');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengontrol relay');
                // Reset UI jika error
                document.getElementById('relayOnBtn').disabled = false;
                document.getElementById('relayOffBtn').disabled = false;
                document.getElementById('relayOnBtn').classList.remove('loading');
                document.getElementById('relayOffBtn').classList.remove('loading');
            });
        }

        // Fungsi refresh data
        function refreshData() {
            // Tambah animasi loading
            const refreshBtn = document.querySelector('.btn-refresh-bottom');
            refreshBtn.classList.add('loading');
            
            setTimeout(() => {
                location.reload();
            }, 500);
        }

        // Auto refresh setiap 30 detik
        setInterval(() => {
            const refreshBtn = document.querySelector('.btn-refresh-bottom');
            refreshBtn.classList.add('loading');
            setTimeout(() => {
                location.reload();
            }, 500);
        }, 30000);
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>