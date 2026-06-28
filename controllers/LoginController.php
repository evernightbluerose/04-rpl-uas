<?php
/**
 * kelas pengendali proses login, autentikasi, dan logout
 *
 * mengelola alur login multi-langkah termasuk single-session enforcement
 * dan konfirmasi pengguna sebelum menginvalidate sesi aktif lain
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class LoginController extends BaseController
{
    /**
     * menampilkan halaman login
     *
     * @return void
     */
    public function login(): void
    {
        init_session();
        if (isset($_SESSION['role'])) {
            $role_lower = strtolower($_SESSION['role']);
            header('Location: ' . $role_lower . '.php');
            exit;
        }

        $page_title = 'Login - Sistem Kehadiran Desa';
        $show_nav   = false;

        $pesan            = isset($_GET['pesan'])    ? e($_GET['pesan'])    : '';
        $pesan_type       = isset($_GET['tipe'])     ? e($_GET['tipe'])     : 'danger'; // default ke danger agar text error berwarna merah
        $session_conflict = isset($_GET['conflict']) && $_GET['conflict'] === '1';

        // ambil token konfirmasi sementara dari session untuk validasi di form konfirmasi
        $conflict_token = $_SESSION['conflict_token'] ?? '';

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/login_form.php';

        if ($this->conn) {
            $this->conn->close();
        }
        require_once __DIR__ . '/../includes/footer.php';
    }

    /**
     * memproses verifikasi kredensial login
     *
     * alur single-session:
     * 1. validasi kredensial
     * 2. cek apakah ada session_token aktif di database (sesi lain sedang berjalan)
     * 3. jika ada, simpan info ke session sementara dan redirect ke halaman konfirmasi
     * 4. jika dikonfirmasi (force_login=1), hapus token lama dan buat sesi baru
     *
     * @return void
     */
    public function prosesLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: login.php');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($csrf)) {
            $pesan = urlencode('Sesi tidak valid. Silakan coba lagi.');
            header('Location: login.php?pesan=' . $pesan);
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password']      ?? '';

        // pastikan kedua field tidak kosong
        if (empty($username) || empty($password)) {
            header('Location: login.php?pesan=' . urlencode('Username dan password wajib diisi.'));
            exit;
        }

        // username hanya boleh berisi huruf, angka, dan underscore
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            header('Location: login.php?pesan=' . urlencode('Format username tidak valid.'));
            exit;
        }

        // batasi panjang input untuk mencegah payload terlalu besar
        if (strlen($username) > 50 || strlen($password) > 255) {
            header('Location: login.php?pesan=' . urlencode('Input melebihi batas karakter.'));
            exit;
        }

        $password_hash = hash('sha256', $password);

        // gabungkan ke tabel pegawai untuk mengambil informasi nama dan foto
        $stmt = $this->conn->prepare(
            'SELECT u.id_user, u.role, u.session_token, p.id_pegawai, p.nama, p.foto_profil
             FROM   users u
             LEFT JOIN pegawai p ON u.id_user = p.id_user
             WHERE  u.username = ? AND u.password = ?'
        );
        $stmt->bind_param('ss', $username, $password_hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            $this->conn->close();
            header('Location: login.php?pesan=' . urlencode('Username atau password salah.'));
            exit;
        }

        $data = $result->fetch_assoc();
        $stmt->close();

        // periksa apakah ada sesi aktif lain (session_token tidak null di database)
        $has_conflict   = !empty($data['session_token']);
        $force_login    = isset($_POST['force_login']) && $_POST['force_login'] === '1';
        $conflict_token = $_POST['conflict_token'] ?? '';

        if ($has_conflict && !$force_login) {
            // simpan info login sementara ke session dengan token konfirmasi sekali pakai
            $new_conflict_token = bin2hex(random_bytes(16));
            $_SESSION['conflict_token']    = $new_conflict_token;
            $_SESSION['pending_username']  = $username;
            $_SESSION['pending_pass_hash'] = $password_hash;

            $this->conn->close();

            // redirect ke halaman login dengan flag conflict untuk menampilkan notifikasi
            header('Location: login.php?conflict=1');
            exit;
        }

        // jika konfirmasi diterima, validasi token konfirmasi agar tidak bisa di-replay
        if ($force_login) {
            $saved_token    = $_SESSION['conflict_token']    ?? '';
            $saved_username = $_SESSION['pending_username']  ?? '';
            $saved_hash     = $_SESSION['pending_pass_hash'] ?? '';

            if (
                !hash_equals($saved_token, $conflict_token) ||
                $saved_username !== $username ||
                $saved_hash     !== $password_hash
            ) {
                header('Location: login.php?pesan=' . urlencode('Konfirmasi tidak valid. Silakan login ulang.'));
                exit;
            }

            // bersihkan data sementara dari session
            unset($_SESSION['conflict_token'], $_SESSION['pending_username'], $_SESSION['pending_pass_hash']);
        }

        // buat session token baru — ini akan menginvalidate sesi lama secara otomatis
        $new_session_token = bin2hex(random_bytes(32));

        // simpan token baru ke database (menimpa token lama jika ada)
        $stmt_tok = $this->conn->prepare('UPDATE users SET session_token = ? WHERE id_user = ?');
        $stmt_tok->bind_param('si', $new_session_token, $data['id_user']);
        $stmt_tok->execute();
        $stmt_tok->close();

        // regenerasi session id untuk mencegah session fixation attack
        session_regenerate_id(true);

        // simpan data pengguna dan token ke session php
        $_SESSION['id_user']       = (int) $data['id_user'];
        $_SESSION['role']          = $data['role'];
        $_SESSION['nama']          = $data['nama'] ?? 'Administrator';
        $_SESSION['foto_profil']   = $data['foto_profil'] ?? null;
        $_SESSION['session_token'] = $new_session_token;

        // simpan id_pegawai khusus jika ada relasinya
        if (!empty($data['id_pegawai'])) {
            $_SESSION['id_pegawai'] = (int) $data['id_pegawai'];
        }

        // hapus token csrf lama dan buat yang baru setelah login berhasil
        unset($_SESSION['csrf_token']);

        // arahkan ke dashboard sesuai role masing-masing
        $valid_roles = ['Admin', 'Kades', 'Pegawai'];
        $role_lower  = in_array($data['role'], $valid_roles) ? strtolower($data['role']) : 'login';

        $this->conn->close();
        header('Location: ' . $role_lower . '.php');
        exit;
    }

    /**
     * memproses logout pengguna dan menghapus session_token dari database
     *
     * @return void
     */
    public function logout(): void
    {
        init_session();

        // hapus session_token dari database agar sesi benar-benar tidak valid
        if (!empty($_SESSION['id_user']) && $this->conn) {
            $stmt = $this->conn->prepare('UPDATE users SET session_token = NULL WHERE id_user = ?');
            $stmt->bind_param('i', $_SESSION['id_user']);
            $stmt->execute();
            $stmt->close();
        }

        // hapus semua variabel sesi
        $_SESSION = array();

        // hapus cookie session di browser
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        if ($this->conn) {
            $this->conn->close();
        }

        // alihkan ke halaman login
        header('Location: login.php');
        exit;
    }
}
