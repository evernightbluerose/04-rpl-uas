<?php
/**
 * konfigurasi database - sistem kehadiran desa
 * menggunakan mysqli dengan charset utf8mb4
 *
 * @package sistemkehadiran
 */

// konfigurasi timezone
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// konfigurasi database - sesuai database.sql
$host = "localhost";
$user = "root";
$pass = "";
$db   = "catatan_kehadiran";

// buat koneksi dengan error reporting dan charset yang aman
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
    // perlindungan dari sql injection pada level koneksi
    $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
} catch (mysqli_sql_exception $e) {
    // jangan tampilkan detail error ke pengguna di produksi
    error_log("koneksi database gagal: " . $e->getMessage());
    http_response_code(503);
    die("layanan tidak tersedia. silakan coba beberapa saat lagi.");
}

/**
 * memperbarui timestamp global setiap ada perubahan data crud
 *
 * @param mysqli $conn objek koneksi database
 * @return void
 */
function trigger_data_update($conn) {
    $conn->query("UPDATE app_settings SET setting_value = UNIX_TIMESTAMP() WHERE setting_key = 'last_data_update'");
}

// muat pengaturan global aplikasi untuk digunakan di header, footer, dsb
$global_settings = [];
if (isset($conn)) {
    $res = $conn->query("SELECT setting_key, setting_value FROM app_settings");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $global_settings[$r['setting_key']] = $r['setting_value'];
        }
    }
}
