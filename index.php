<?php
ini_set("display_errors", 1); error_reporting(E_ALL);
/**
 * front controller & router untuk sistem kehadiran desa
 *
 * mengkonsolidasikan seluruh entry point root menjadi satu pintu utama
 * memetakan url secara dinamis ke controller masing-masing
 *
 * @package sistemkehadiran
 */

// BLIND FIX: Tangkap semua Fatal Error agar tidak muncul layar blank
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        while (ob_get_level()) { ob_end_clean(); } // bersihkan buffer
        http_response_code(500);
        echo "<div style='font-family:sans-serif; padding:20px; border:2px solid red; background:#ffe6e6; margin:20px; border-radius:8px;'>";
        echo "<h3 style='color:red; margin-top:0;'>Terjadi Kesalahan Sistem (Fatal Error)</h3>";
        echo "<p>Sistem mendeteksi kegagalan kritis. Mohon tangkap layar pesan ini:</p>";
        echo "<code>" . htmlspecialchars($error['message']) . "</code><br><br>";
        echo "<small>File: " . htmlspecialchars($error['file']) . " (Baris " . $error['line'] . ")</small>";
        echo "</div>";
    }
});

// memuat konfigurasi database dan utilitas
require_once __DIR__ . '/config/database.php';

// mendeteksi route baik di root domain maupun subfolder
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (strpos($request_path, $base_path) === 0) {
    $request_path = substr($request_path, strlen($base_path));
}
$route = trim($request_path, '/');

// arahkan ke halaman login jika route kosong
if ($route === '' || $route === 'index.php') {
    $route = 'login.php';
}

// peta routing aplikasi
switch ($route) {
    case 'login.php':
    case 'login':
        require_once __DIR__ . '/controllers/LoginController.php';
        (new LoginController($conn))->login();
        break;

    case 'proses_login.php':
        require_once __DIR__ . '/controllers/LoginController.php';
        (new LoginController($conn))->prosesLogin();
        break;

    case 'logout.php':
    case 'logout':
        require_once __DIR__ . '/controllers/LoginController.php';
        (new LoginController($conn))->logout();
        break;

    case 'admin.php':
    case 'admin':
        require_once __DIR__ . '/controllers/AdminController.php';
        (new AdminController($conn))->index();
        break;

    case 'tambah_pegawai.php':
        require_once __DIR__ . '/controllers/AdminController.php';
        (new AdminController($conn))->tambahPegawai();
        break;

    case 'edit_pegawai.php':
        require_once __DIR__ . '/controllers/AdminController.php';
        (new AdminController($conn))->editPegawai();
        break;

    case 'hapus_pegawai.php':
        require_once __DIR__ . '/controllers/AdminController.php';
        (new AdminController($conn))->hapusPegawai();
        break;

    case 'reset_password.php':
        require_once __DIR__ . '/controllers/AdminController.php';
        (new AdminController($conn))->resetPassword();
        break;

    case 'reset_webauthn.php':
        require_once __DIR__ . '/controllers/AdminController.php';
        (new AdminController($conn))->resetWebauthn();
        break;

    case 'kades.php':
    case 'kades':
        require_once __DIR__ . '/controllers/KadesController.php';
        (new KadesController($conn))->index();
        break;

    case 'pegawai.php':
    case 'pegawai':
        require_once __DIR__ . '/controllers/PegawaiController.php';
        (new PegawaiController($conn))->index();
        break;

    case 'profil.php':
    case 'profil':
        require_once __DIR__ . '/controllers/ProfilController.php';
        (new ProfilController($conn))->index();
        break;

    case 'izin.php':
    case 'izin':
        require_once __DIR__ . '/controllers/IzinController.php';
        (new IzinController($conn))->handle();
        break;

    case 'logbook.php':
    case 'logbook':
        require_once __DIR__ . '/controllers/LogbookController.php';
        (new LogbookController($conn))->handle();
        break;

    case 'export_laporan.php':
        require_once __DIR__ . '/controllers/AbsenController.php';
        (new AbsenController($conn))->exportLaporan();
        break;

    case 'proses_absen_secure.php':
        require_once __DIR__ . '/controllers/AbsenController.php';
        (new AbsenController($conn))->prosesAbsenSecure();
        break;

    case 'qr_generator.php':
        require_once __DIR__ . '/controllers/AbsenController.php';
        (new AbsenController($conn))->qrGenerator();
        break;

    case 'api_sync.php':
        require_once __DIR__ . '/controllers/AbsenController.php';
        (new AbsenController($conn))->apiSync();
        break;

    case 'absen_standalone.php':
    case 'absen_standalone':
        require_once __DIR__ . '/controllers/AbsenController.php';
        (new AbsenController($conn))->standaloneView();
        break;

    case 'api_latest_absen.php':
    case 'api_latest_absen':
        require_once __DIR__ . '/controllers/AbsenController.php';
        (new AbsenController($conn))->apiLatestAbsen();
        break;

    case 'api_calendar.php':
    case 'api_calendar':
        require_once __DIR__ . '/controllers/CalendarApiController.php';
        break;

    case 'download_attachment.php':
    case 'download_attachment':
        require_once __DIR__ . '/controllers/AttachmentController.php';
        (new AttachmentController($conn))->download();
        break;

    default:
        http_response_code(404);
        echo "404 - Halaman Tidak Ditemukan";
        break;
}
