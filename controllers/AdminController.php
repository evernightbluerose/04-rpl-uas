<?php
/**
 * kelas pengendali dasbor admin dan manajemen pegawai
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class AdminController extends BaseController
{
    /**
     * menampilkan dasbor utama admin dan menangani pembaruan pengaturan aplikasi
     *
     * @return void
     */
    public function index(): void
    {
        require_role('Admin', $this->conn);

        // ambil flash message jika ada
        $pesan      = isset($_GET['pesan']) ? e($_GET['pesan']) : '';
        $pesan_type = isset($_GET['tipe'])  ? $_GET['tipe']     : 'info';

        $allowed_types = ['danger', 'success', 'info', 'warning'];
        if (!in_array($pesan_type, $allowed_types)) {
            $pesan_type = 'info';
        }

        // pencarian pegawai
        $search      = isset($_GET['cari']) ? trim($_GET['cari']) : '';
        $search_safe = '%' . $search . '%';

        // statistik kehadiran hari ini
        $tgl_hari_ini = date('Y-m-d');

        $stmt_c = $this->conn->prepare('SELECT COUNT(*) AS t FROM pegawai');
        $stmt_c->execute();
        $total_pegawai = $stmt_c->get_result()->fetch_assoc()['t'];
        $stmt_c->close();

        $stmt_h = $this->conn->prepare(
            "SELECT COUNT(*) AS t FROM presensi WHERE tanggal = ? AND status = 'Tepat Waktu'"
        );
        $stmt_h->bind_param('s', $tgl_hari_ini);
        $stmt_h->execute();
        $total_hadir = $stmt_h->get_result()->fetch_assoc()['t'];
        $stmt_h->close();

        $stmt_t = $this->conn->prepare(
            "SELECT COUNT(*) AS t FROM presensi WHERE tanggal = ? AND status = 'Terlambat'"
        );
        $stmt_t->bind_param('s', $tgl_hari_ini);
        $stmt_t->execute();
        $total_terlambat = $stmt_t->get_result()->fetch_assoc()['t'];
        $stmt_t->close();

        // daftar pegawai dengan paginasi maksimal 50 data per halaman
        $stmt_count_peg = $this->conn->prepare(
            'SELECT COUNT(*) AS total FROM pegawai p WHERE p.nama LIKE ?'
        );
        $stmt_count_peg->bind_param('s', $search_safe);
        $stmt_count_peg->execute();
        $total_pegawai_rows = $stmt_count_peg->get_result()->fetch_assoc()['total'];
        $stmt_count_peg->close();

        $limit = 50;
        $page_pegawai = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page_pegawai < 1) $page_pegawai = 1;
        $offset_pegawai = ($page_pegawai - 1) * $limit;
        $total_pegawai_pages = ceil($total_pegawai_rows / $limit);
        if ($page_pegawai > $total_pegawai_pages && $total_pegawai_pages > 0) {
            $page_pegawai = $total_pegawai_pages;
            $offset_pegawai = ($page_pegawai - 1) * $limit;
        }

        $stmt = $this->conn->prepare(
            'SELECT p.id_pegawai, p.nip, p.nama, p.foto_profil, j.nama_jabatan, u.role, u.id_user, (wc.id_user IS NOT NULL) AS has_webauthn
             FROM   pegawai p
             LEFT JOIN jabatan j ON p.id_jabatan = j.id_jabatan
             LEFT JOIN users u ON p.id_user = u.id_user
             LEFT JOIN webauthn_credentials wc ON u.id_user = wc.id_user
             WHERE  p.nama LIKE ?
             ORDER BY p.nama ASC
             LIMIT ? OFFSET ?'
        );
        $stmt->bind_param('sii', $search_safe, $limit, $offset_pegawai);
        $stmt->execute();
        $result = $stmt->get_result();

        // daftar presensi pegawai dengan paginasi maksimal 50 data per halaman
        $stmt_count_pres = $this->conn->prepare('SELECT COUNT(*) AS total FROM presensi');
        $stmt_count_pres->execute();
        $total_presensi_rows = $stmt_count_pres->get_result()->fetch_assoc()['total'];
        $stmt_count_pres->close();

        $page_presensi = isset($_GET['page_presensi']) ? (int) $_GET['page_presensi'] : 1;
        if ($page_presensi < 1) $page_presensi = 1;
        $offset_presensi = ($page_presensi - 1) * $limit;
        $total_presensi_pages = ceil($total_presensi_rows / $limit);
        if ($page_presensi > $total_presensi_pages && $total_presensi_pages > 0) {
            $page_presensi = $total_presensi_pages;
            $offset_presensi = ($page_presensi - 1) * $limit;
        }

        $stmt_pres = $this->conn->prepare(
            'SELECT p.nama, pr.tanggal, pr.jam_masuk, pr.jam_pulang, pr.status, pr.foto_selfie, pr.lat, pr.lng, pr.metode_absen
             FROM   presensi pr
             JOIN   pegawai  p ON pr.id_pegawai = p.id_pegawai
             ORDER BY pr.tanggal DESC, pr.jam_masuk DESC
             LIMIT ? OFFSET ?'
        );
        $stmt_pres->bind_param('ii', $limit, $offset_presensi);
        $stmt_pres->execute();
        $result_pres = $stmt_pres->get_result();

        // memproses perubahan pengaturan aplikasi
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings' && verify_csrf($_POST['csrf_token'] ?? '')) {
            $qr = isset($_POST['qr_login']) ? 1 : 0;
            $wa = isset($_POST['webauthn']) ? 1 : 0;
            $loc = isset($_POST['location_logging']) ? 1 : 0;
            $selfie = isset($_POST['selfie_validation']) ? 1 : 0;
            $qr_duration = isset($_POST['qr_refresh_duration']) ? (int)$_POST['qr_refresh_duration'] : 30;
            if ($qr_duration < 5) $qr_duration = 5;
            if ($qr_duration > 300) $qr_duration = 300;

            $jam_masuk_batas = $_POST['jam_masuk_batas'] ?? '07:00';
            $jam_pulang_mulai = $_POST['jam_pulang_mulai'] ?? '16:00';
            
            $allow_pc = isset($_POST['allow_pc_attendance']) ? 1 : 0;
            $allow_pc_qr = isset($_POST['allow_pc_qr_scan']) ? 1 : 0;
            $skip_pulang = isset($_POST['skip_keamanan_pulang']) ? 1 : 0;

            $app_name = $_POST['app_name'] ?? 'Sistem Kehadiran Desa';
            $app_footer_text = $_POST['app_footer_text'] ?? ('&copy; ' . date('Y') . ' Sistem Kehadiran Desa');
            $app_footer_link = $_POST['app_footer_link'] ?? 'rpl.iamsochronically.online';
            $max_attachment_size = $_POST['max_attachment_size'] ?? '2';

            $app_logo_base64 = $_POST['app_logo_base64'] ?? '';

            $stmt_set = $this->conn->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = ?");
            $settings_to_update = [
                'qr_login' => $qr,
                'webauthn' => $wa,
                'location_logging' => $loc,
                'selfie_validation' => $selfie,
                'qr_refresh_duration' => $qr_duration,
                'jam_masuk_batas' => $jam_masuk_batas,
                'jam_pulang_mulai' => $jam_pulang_mulai,
                'allow_pc_attendance' => $allow_pc,
                'allow_pc_qr_scan' => $allow_pc_qr,
                'skip_keamanan_pulang' => $skip_pulang,
                'app_name' => $app_name,
                'app_footer_text' => $app_footer_text,
                'app_footer_link' => $app_footer_link,
                'max_attachment_size' => $max_attachment_size
            ];
            
            if (!empty($app_logo_base64)) {
                $settings_to_update['app_logo'] = $app_logo_base64;
            }
            foreach ($settings_to_update as $key => $val) {
                $stmt_set->bind_param("ss", $val, $key);
                $stmt_set->execute();
            }
            $stmt_set->close();
            
            $stmt_insert = $this->conn->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
            $settings_to_update = [
                'qr_refresh_duration' => $qr_duration,
                'jam_masuk_batas' => $jam_masuk_batas,
                'jam_pulang_mulai' => $jam_pulang_mulai,
                'allow_pc_attendance' => $allow_pc,
                'allow_pc_qr_scan' => $allow_pc_qr,
                'app_name' => $app_name,
                'app_footer_text' => $app_footer_text,
                'app_footer_link' => $app_footer_link,
                'max_attachment_size' => $max_attachment_size
            ];
            
            if (!empty($app_logo_base64)) {
                $settings_to_update['app_logo'] = $app_logo_base64;
            }
            foreach ($settings_to_update as $key => $val) {
                $stmt_insert->bind_param("ss", $key, $val);
                $stmt_insert->execute();
            }
            $stmt_insert->close();
            
            header('Location: admin.php?pesan=' . urlencode('Pengaturan keamanan & waktu diperbarui.') . '&tipe=success');
            exit;
        }

        $settings = [];
        $res_settings = $this->conn->query("SELECT setting_key, setting_value FROM app_settings");
        if ($res_settings) {
            while ($row = $res_settings->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/admin_dashboard.php';

        $stmt->close();
        if (isset($stmt_pres)) {
            $stmt_pres->close();
        }
        $this->conn->close();

        require_once __DIR__ . '/../includes/footer.php';
    }

    /**
     * menambahkan pegawai baru beserta akun penggunanya
     *
     * @return void
     */
    public function tambahPegawai(): void
    {
        require_role('Admin', $this->conn);

        $error            = '';
        $nip              = '';
        $nama             = '';
        $username         = '';
        $id_jabatan_input = '';
        $role             = 'Pegawai';

        // ambil daftar jabatan untuk dropdown pilihan
        $jabatan_list = [];
        $stmt_jab = $this->conn->prepare('SELECT id_jabatan, nama_jabatan FROM jabatan ORDER BY nama_jabatan ASC');
        $stmt_jab->execute();
        $res_jab = $stmt_jab->get_result();
        while ($j = $res_jab->fetch_assoc()) {
            $jabatan_list[] = $j;
        }
        $stmt_jab->close();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf_token'] ?? '';
            if (!verify_csrf($csrf)) {
                $error = 'Sesi tidak valid. Silakan coba lagi.';
            } else {
                $nip              = trim($_POST['nip']        ?? '');
                $nama             = trim($_POST['nama']       ?? '');
                $username         = trim($_POST['username']   ?? '');
                $password         = $_POST['password']        ?? '';
                $id_jabatan_input = $_POST['id_jabatan']      ?? '';
                $role             = trim($_POST['role']        ?? 'Pegawai');

                if (empty($nip) || empty($nama) || empty($username) || empty($password)) {
                    $error = 'Semua field wajib diisi.';
                } elseif (!in_array($role, ['Pegawai', 'Admin', 'Kades'])) {
                    $error = 'Role tidak valid.';
                } elseif (!preg_match('/^[0-9]+$/', $nip)) {
                    $error = 'NIP hanya boleh berisi angka.';
                } elseif (strlen($nip) > 20) {
                    $error = 'NIP maksimal 20 karakter.';
                } elseif (strlen($nama) > 100) {
                    $error = 'Nama maksimal 100 karakter.';
                } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    $error = 'Username hanya boleh huruf, angka, dan underscore.';
                } elseif (strlen($username) > 50) {
                    $error = 'Username maksimal 50 karakter.';
                } elseif (strlen($password) < 4) {
                    $error = 'Password minimal 4 karakter.';
                } else {
                    $check = $this->conn->prepare('SELECT id_user FROM users WHERE username = ?');
                    $check->bind_param('s', $username);
                    $check->execute();
                    if ($check->get_result()->num_rows > 0) {
                        $error = 'Username sudah digunakan.';
                    }
                    $check->close();

                    if (empty($error)) {
                        $this->conn->begin_transaction();
                        try {
                            $pass_hash = hash('sha256', $password);

                            $stmt_user = $this->conn->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
                            $stmt_user->bind_param('sss', $username, $pass_hash, $role);
                            if (!$stmt_user->execute()) {
                                throw new Exception('Gagal membuat akun pengguna.');
                            }
                            $id_user = $this->conn->insert_id;
                            $stmt_user->close();

                            $id_jab = !empty($id_jabatan_input) ? (int)$id_jabatan_input : null;
                            $stmt_peg = $this->conn->prepare('INSERT INTO pegawai (nip, nama, id_jabatan, id_user) VALUES (?, ?, ?, ?)');
                            $stmt_peg->bind_param('ssii', $nip, $nama, $id_jab, $id_user);
                            if (!$stmt_peg->execute()) {
                                throw new Exception('Gagal menyimpan data pegawai.');
                            }
                            $stmt_peg->close();

                            trigger_data_update($this->conn);
                            $this->conn->commit();
                            $pesan = "Pegawai \"$nama\" berhasil ditambahkan.";

                            if ($this->isAjax()) {
                                $this->jsonResponse(['success' => true, 'message' => $pesan]);
                            }

                            header('Location: admin.php?pesan=' . urlencode($pesan) . '&tipe=success');
                            exit;
                        } catch (\Throwable $e) {
                            try { $this->conn->rollback(); } catch (\Throwable $er) {}
                            $error = $e->getMessage();
                        }
                    }
                }
            }

            if ($this->isAjax() && !empty($error)) {
                $this->jsonResponse(['success' => false, 'message' => $error]);
            }
        }

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/tambah_pegawai_form.php';
        
        $this->conn->close();
        require_once __DIR__ . '/../includes/footer.php';
    }

    /**
     * memperbarui data pegawai beserta rolenya
     *
     * @return void
     */
    public function editPegawai(): void
    {
        require_role('Admin', $this->conn);

        $error = '';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        $jabatan_list = [];
        $stmt_jab = $this->conn->prepare('SELECT id_jabatan, nama_jabatan FROM jabatan ORDER BY nama_jabatan ASC');
        $stmt_jab->execute();
        $res_jab = $stmt_jab->get_result();
        while ($j = $res_jab->fetch_assoc()) {
            $jabatan_list[] = $j;
        }
        $stmt_jab->close();

        $stmt = $this->conn->prepare('
            SELECT p.id_pegawai, p.nip, p.nama, p.id_jabatan, u.role, u.id_user 
            FROM pegawai p 
            LEFT JOIN users u ON p.id_user = u.id_user 
            WHERE p.id_pegawai = ?
        ');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $pegawai = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pegawai) {
            header('Location: admin.php?pesan=' . urlencode('Pegawai tidak ditemukan.') . '&tipe=danger');
            exit;
        }

        $nip              = $pegawai['nip'];
        $nama             = $pegawai['nama'];
        $id_jabatan_input = $pegawai['id_jabatan'] ?? '';
        $role             = $pegawai['role'] ?? 'Pegawai';
        $id_user          = (int) ($pegawai['id_user'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf_token'] ?? '';
            $id   = (int) ($_POST['id_pegawai'] ?? 0);

            if (!verify_csrf($csrf)) {
                $error = 'Sesi tidak valid. Silakan coba lagi.';
            } else {
                $nip              = trim($_POST['nip']        ?? '');
                $nama             = trim($_POST['nama']       ?? '');
                $id_jabatan_input = $_POST['id_jabatan']      ?? '';
                $role             = trim($_POST['role']       ?? 'Pegawai');

                if (empty($nip) || empty($nama)) {
                    $error = 'NIP dan Nama wajib diisi.';
                } elseif (!in_array($role, ['Pegawai', 'Admin', 'Kades'])) {
                    $error = 'Role tidak valid.';
                } elseif (!preg_match('/^[0-9]+$/', $nip)) {
                    $error = 'NIP hanya boleh berisi angka.';
                } elseif (strlen($nip) > 20 || strlen($nama) > 100) {
                    $error = 'Input melebihi batas karakter.';
                } else {
                    $id_jab = !empty($id_jabatan_input) ? (int)$id_jabatan_input : null;
                    $this->conn->begin_transaction();
                    try {
                        $stmt_upd = $this->conn->prepare('UPDATE pegawai SET nip = ?, nama = ?, id_jabatan = ? WHERE id_pegawai = ?');
                        $stmt_upd->bind_param('ssii', $nip, $nama, $id_jab, $id);
                        if (!$stmt_upd->execute()) {
                            throw new Exception('Gagal memperbarui data pegawai.');
                        }
                        $stmt_upd->close();

                        if ($id_user > 0) {
                            $stmt_usr = $this->conn->prepare('UPDATE users SET role = ? WHERE id_user = ?');
                            $stmt_usr->bind_param('si', $role, $id_user);
                            if (!$stmt_usr->execute()) {
                                throw new Exception('Gagal memperbarui role pengguna.');
                            }
                            $stmt_usr->close();
                        }

                        trigger_data_update($this->conn);
                        $this->conn->commit();
                        $pesan = "Data pegawai \"$nama\" berhasil diperbarui.";

                        if ($this->isAjax()) {
                            $this->jsonResponse(['success' => true, 'message' => $pesan]);
                        }

                        header('Location: admin.php?pesan=' . urlencode($pesan) . '&tipe=success');
                        exit;
                    } catch (\Throwable $e) {
                        try { $this->conn->rollback(); } catch (\Throwable $er) {}
                        $error = $e->getMessage();
                    }
                }
            }

            if ($this->isAjax() && !empty($error)) {
                $this->jsonResponse(['success' => false, 'message' => $error]);
            }
        }

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/edit_pegawai_form.php';
        
        $this->conn->close();
        require_once __DIR__ . '/../includes/footer.php';
    }

    /**
     * menghapus data pegawai beserta akun penggunanya
     *
     * @return void
     */
    public function hapusPegawai(): void
    {
        require_role('Admin', $this->conn);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($csrf)) {
            $pesan = urlencode('Sesi tidak valid. Silakan coba lagi.');
            header('Location: admin.php?pesan=' . $pesan . '&tipe=danger');
            exit;
        }

        $id_pegawai = (int) ($_POST['id_pegawai'] ?? 0);
        if ($id_pegawai <= 0) {
            header('Location: admin.php?pesan=' . urlencode('ID pegawai tidak valid.') . '&tipe=danger');
            exit;
        }

        $stmt = $this->conn->prepare('SELECT id_user, nama FROM pegawai WHERE id_pegawai = ?');
        $stmt->bind_param('i', $id_pegawai);
        $stmt->execute();
        $peg = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$peg) {
            header('Location: admin.php?pesan=' . urlencode('Pegawai tidak ditemukan.') . '&tipe=danger');
            exit;
        }

        $nama = $peg['nama'];

        if ($peg['id_user']) {
            $stmt_del = $this->conn->prepare('DELETE FROM users WHERE id_user = ?');
            $stmt_del->bind_param('i', $peg['id_user']);
            $stmt_del->execute();
            $stmt_del->close();
        } else {
            $stmt_del = $this->conn->prepare('DELETE FROM pegawai WHERE id_pegawai = ?');
            $stmt_del->bind_param('i', $id_pegawai);
            $stmt_del->execute();
            $stmt_del->close();
        }

        trigger_data_update($this->conn);
        
        $pesan = "Pegawai \"$nama\" berhasil dihapus.";
        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => $pesan]);
        }

        $this->conn->close();
        header('Location: admin.php?pesan=' . urlencode($pesan) . '&tipe=success');
        exit;
    }

    /**
     * mereset password pegawai ke nilai default 123456
     *
     * @return void
     */
    public function resetPassword(): void
    {
        require_role('Admin', $this->conn);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($csrf)) {
            $pesan = urlencode('Sesi tidak valid. Silakan coba lagi.');
            header('Location: admin.php?pesan=' . $pesan . '&tipe=danger');
            exit;
        }

        $id_pegawai = (int) ($_POST['id_pegawai'] ?? 0);
        if ($id_pegawai <= 0) {
            header('Location: admin.php?pesan=' . urlencode('ID pegawai tidak valid.') . '&tipe=danger');
            exit;
        }

        $stmt = $this->conn->prepare('SELECT p.id_user, p.nama, u.role FROM pegawai p LEFT JOIN users u ON p.id_user = u.id_user WHERE p.id_pegawai = ?');
        $stmt->bind_param('i', $id_pegawai);
        $stmt->execute();
        $peg = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$peg || !$peg['id_user']) {
            header('Location: admin.php?pesan=' . urlencode('Data pegawai atau akun tidak ditemukan.') . '&tipe=danger');
            exit;
        }

        if ($peg['role'] === 'Admin') {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Tidak dapat mereset password sesama Admin.']);
            }
            header('Location: admin.php?pesan=' . urlencode('Tidak dapat mereset password sesama Admin.') . '&tipe=danger');
            exit;
        }

        $nama = $peg['nama'];
        $default_pass = '123456';
        $pass_hash = hash('sha256', $default_pass);

        $stmt_up = $this->conn->prepare('UPDATE users SET password = ? WHERE id_user = ?');
        $stmt_up->bind_param('si', $pass_hash, $peg['id_user']);
        $stmt_up->execute();
        $stmt_up->close();

        trigger_data_update($this->conn);
        
        $pesan = "Password \"$nama\" berhasil direset menjadi '123456'.";
        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => $pesan]);
        }

        $this->conn->close();
        header('Location: admin.php?pesan=' . urlencode($pesan) . '&tipe=success');
        exit;
    }

    /**
     * mereset kredensial webauthn fido2 milik pegawai
     *
     * @return void
     */
    public function resetWebauthn(): void
    {
        require_role('Admin', $this->conn);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($csrf)) {
            $pesan = urlencode('Sesi tidak valid. Silakan coba lagi.');
            header('Location: admin.php?pesan=' . $pesan . '&tipe=danger');
            exit;
        }

        $id_pegawai = (int) ($_POST['id_pegawai'] ?? 0);
        if ($id_pegawai <= 0) {
            header('Location: admin.php?pesan=' . urlencode('ID pegawai tidak valid.') . '&tipe=danger');
            exit;
        }

        $stmt = $this->conn->prepare('SELECT p.id_user, p.nama, u.role FROM pegawai p LEFT JOIN users u ON p.id_user = u.id_user WHERE p.id_pegawai = ?');
        $stmt->bind_param('i', $id_pegawai);
        $stmt->execute();
        $peg = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$peg || !$peg['id_user']) {
            header('Location: admin.php?pesan=' . urlencode('Data pegawai atau akun tidak ditemukan.') . '&tipe=danger');
            exit;
        }

        $id_user = (int) $peg['id_user'];
        $nama = $peg['nama'];

        if ($peg['role'] === 'Admin') {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Tidak dapat mereset WebAuthn sesama Admin.']);
            }
            header('Location: admin.php?pesan=' . urlencode('Tidak dapat mereset WebAuthn sesama Admin.') . '&tipe=danger');
            exit;
        }

        $stmt_del = $this->conn->prepare('DELETE FROM webauthn_credentials WHERE id_user = ?');
        $stmt_del->bind_param('i', $id_user);
        $stmt_del->execute();
        $stmt_del->close();

        trigger_data_update($this->conn);
        
        $pesan = "Kredensial WebAuthn milik \"$nama\" berhasil direset.";
        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => $pesan]);
        }

        $this->conn->close();
        header('Location: admin.php?pesan=' . urlencode($pesan) . '&tipe=success');
        exit;
    }
}
