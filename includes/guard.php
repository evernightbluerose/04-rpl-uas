<?php
/**
 * middleware perlindungan halaman berdasarkan role dan validasi single-session
 *
 * menyediakan fungsi pengecekan sesi login, otorisasi role,
 * dan memastikan hanya satu sesi aktif per pengguna pada satu waktu
 *
 * @package sistemkehadiran
 */


/**
 * memverifikasi bahwa session_token di session php masih cocok dengan
 * session_token yang tersimpan di database
 *
 * jika tidak cocok (artinya ada login baru dari perangkat lain yang sudah
 * menimpa token ini), sesi ini langsung diinvalidate dan user diarahkan ke
 * halaman login dengan pesan pemberitahuan
 *
 * @param mysqli $conn koneksi database yang sudah aktif
 * @return void
 */
function verify_session_token(mysqli $conn): void
{
    // lewati pengecekan jika tidak ada data sesi (belum login)
    if (empty($_SESSION['id_user']) || empty($_SESSION['session_token'])) {
        return;
    }

    $id_user       = (int) $_SESSION['id_user'];
    $session_token = $_SESSION['session_token'];

    $stmt = $conn->prepare('SELECT session_token FROM users WHERE id_user = ? LIMIT 1');
    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        // pengguna tidak ditemukan di database — paksa logout
        _force_logout('Akun tidak ditemukan.');
    }

    $row = $result->fetch_assoc();

    // bandingkan token secara aman menggunakan hash_equals untuk mencegah timing attack
    if (!hash_equals((string)($row['session_token'] ?? ''), $session_token)) {
        // token tidak cocok — ada sesi baru yang sudah login dari perangkat lain
        _force_logout('Sesi Anda telah berakhir karena ada login baru dari perangkat lain.');
    }
}


/**
 * menginvalidate sesi php saat ini dan mengarahkan ke halaman login
 *
 * @param string $pesan pesan yang akan ditampilkan di halaman login
 * @return never
 */
function _force_logout(string $pesan): never
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: login.php?pesan=' . urlencode($pesan));
    exit;
}


/**
 * memastikan pengguna sudah login dan memiliki role yang sesuai
 * sekaligus memverifikasi single-session token
 * jika tidak memenuhi syarat, pengguna dialihkan ke halaman login
 *
 * @param string $required_role role yang dibutuhkan
 * @param mysqli|null $conn koneksi database untuk verifikasi token
 * @return void
 */
function require_role(string $required_role, ?mysqli $conn = null): void
{
    init_session();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: login.php');
        exit;
    }

    // verifikasi token hanya jika koneksi database tersedia
    if ($conn) {
        verify_session_token($conn);
    }
}


/**
 * memastikan pengguna sudah login (role apa pun diterima)
 * sekaligus memverifikasi single-session token
 * berguna untuk halaman yang bisa diakses oleh semua role
 *
 * @param mysqli|null $conn koneksi database untuk verifikasi token
 * @return void
 */
function require_login(?mysqli $conn = null): void
{
    init_session();
    if (!isset($_SESSION['role'])) {
        header('Location: login.php');
        exit;
    }

    // verifikasi token hanya jika koneksi database tersedia
    if ($conn) {
        verify_session_token($conn);
    }
}
