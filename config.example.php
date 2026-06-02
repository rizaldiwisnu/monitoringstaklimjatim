<?php
session_start();

// Konfigurasi database
$host = "localhost";
$username = "your_database_username";
$password = "your_database_password";
$database = "monitoringskjatim";

// Konfigurasi sistem
$system_name = "monitoring";
$system_version = "1.0";
$copyright_year = date("Y");

// Fungsi koneksi database
function getDatabaseConnection() {
    global $host, $username, $password, $database;
    $conn = mysqli_connect($host, $username, $password, $database);
    if (!$conn) {
        die("Koneksi database gagal: " . mysqli_connect_error());
    }
    return $conn;
}

// Fungsi untuk cek login
function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

// Fungsi untuk require login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Fungsi untuk redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Fungsi untuk validasi input
function sanitizeInput($input) {
    $conn = getDatabaseConnection();
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

// Fungsi untuk mendapatkan user info
function getUserInfo() {
    if (isset($_SESSION['user_id'])) {
        $conn = getDatabaseConnection();
        $user_id = $_SESSION['user_id'];
        $query = "SELECT * FROM users WHERE id = '$user_id' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
    }
    return null;
}

// Fungsi untuk cek role user
function hasRole($required_role) {
    $user = getUserInfo();
    if ($user && isset($user['role'])) {
        return $user['role'] === $required_role;
    }
    return false;
}

// Auto logout setelah 1 jam inactive
function checkSessionTimeout() {
    $timeout = 3600; // 1 jam dalam detik

    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > $timeout) {

        // Session expired
        session_unset();
        session_destroy();
        redirect('login.php?session=expired');
    }

    $_SESSION['last_activity'] = time();
}

// Jalankan session timeout check
checkSessionTimeout();

// Debug mode (set false untuk production)
$debug_mode = false;

if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>
