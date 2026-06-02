<?php
// download.php - File untuk download data historis dengan filter tanggal
$host = "localhost";
$username = "root";
$password = "";
$database = "monitoringskjatim";

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Ambil parameter dari URL (GET)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : 'server';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Validasi tanggal
if (empty($start_date) || empty($end_date)) {
    die("Tanggal mulai dan tanggal akhir harus diisi!");
}

// Konversi format tanggal dari mm/dd/yyyy ke yyyy-mm-dd untuk database
$start_date_obj = DateTime::createFromFormat('m/d/Y', $start_date);
$end_date_obj = DateTime::createFromFormat('m/d/Y', $end_date);

if (!$start_date_obj || !$end_date_obj) {
    die("Format tanggal salah! Gunakan format mm/dd/yyyy");
}

$start_date_db = $start_date_obj->format('Y-m-d');
$end_date_db = $end_date_obj->format('Y-m-d');

// Tambahkan waktu agar mencakup seluruh hari
$start_datetime = $start_date_db . ' 00:00:00';
$end_datetime = $end_date_db . ' 23:59:59';

// Query dengan filter tanggal
$query = "SELECT * FROM sensor_data 
          WHERE timestamp BETWEEN '$start_datetime' AND '$end_datetime' 
          ORDER BY timestamp DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error query: " . mysqli_error($conn));
}

// Cek apakah ada data
if (mysqli_num_rows($result) == 0) {
    // Jika tidak ada data, tetap buat file CSV dengan pesan
    $no_data = true;
}

// Header untuk file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=monitoring_data_' . $start_date_db . '_to_' . $end_date_db . '.csv');

// Output file CSV
$output = fopen('php://output', 'w');

// Header CSV
fputcsv($output, array(
    'No',
    'ID', 
    'Client ID', 
    'Suhu (°C)', 
    'Kelembapan (%)', 
    'Api', 
    'Tegangan (V)', 
    'Status Relay', 
    'Mode Sistem', 
    'State Relay',
    'Timestamp'
));

// Jika tidak ada data
if (isset($no_data) && $no_data) {
    fputcsv($output, array(
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA',
        'TIDAK ADA DATA'
    ));
} else {
    // Data rows
    $no = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        // Format data untuk CSV
        $csv_row = array(
            $no++,
            $row['id'],
            $row['client_id'],
            $row['temperature'],
            $row['humidity'],
            $row['flame'] == 1 ? 'TERDETEKSI' : 'AMAN',
            $row['voltage'],
            $row['relay'],
            $row['system_mode'],
            $row['relay_state'],
            $row['timestamp']
        );
        fputcsv($output, $csv_row);
    }
}

fclose($output);
mysqli_close($conn);
exit;
?>