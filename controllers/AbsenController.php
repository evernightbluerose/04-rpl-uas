<?php
/**
 * kelas pengendali proses presensi dan api terkait
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class AbsenController extends BaseController
{
    /**
     * mengekspor data presensi ke format csv
     *
     * @return void
     */
    public function exportLaporan(): void
    {
        require_login($this->conn);
        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['Admin', 'Kades'])) {
            http_response_code(403);
            die('akses ditolak.');
        }

        // ambil filter tanggal
        $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
        $end_date   = isset($_GET['end_date'])   ? trim($_GET['end_date']) : '';

        // validasi format tanggal yyyy-mm-dd
        if (!empty($start_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            $start_date = '';
        }
        if (!empty($end_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $end_date = '';
        }

        // buat query sql dinamis
        $sql = "
            SELECT p.nip, p.nama, j.nama_jabatan, pr.tanggal, pr.jam_masuk, pr.jam_pulang, pr.status, pr.status_pulang, pr.metode_absen, pr.lat, pr.lng 
            FROM presensi pr
            JOIN pegawai p ON pr.id_pegawai = p.id_pegawai
            LEFT JOIN jabatan j ON p.id_jabatan = j.id_jabatan
        ";

        $params = [];
        $types = '';

        if (!empty($start_date) && !empty($end_date)) {
            $sql .= " WHERE pr.tanggal BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= 'ss';
        } elseif (!empty($start_date)) {
            $sql .= " WHERE pr.tanggal >= ?";
            $params[] = $start_date;
            $types .= 's';
        } elseif (!empty($end_date)) {
            $sql .= " WHERE pr.tanggal <= ?";
            $params[] = $end_date;
            $types .= 's';
        }

        $sql .= " ORDER BY pr.tanggal DESC, pr.jam_masuk DESC";

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // set header unduhan untuk file csv
        $filename = "Laporan_Presensi";
        if (!empty($start_date)) {
            $filename .= "_" . $start_date;
        }
        if (!empty($end_date)) {
            $filename .= "_ke_" . $end_date;
        }
        $filename .= ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // buka output stream
        $output = fopen('php://output', 'w');

        // tulis utf-8 bom untuk kompatibilitas excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // tulis baris header kolom
        fputcsv($output, [
            'No',
            'NIP',
            'Nama Pegawai',
            'Jabatan',
            'Tanggal',
            'Jam Masuk',
            'Jam Pulang',
            'Status Masuk',
            'Status Pulang',
            'Metode Absen',
            'Latitude',
            'Longitude'
        ]);

        $no = 1;
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $no++,
                $row['nip'],
                $row['nama'],
                $row['nama_jabatan'] ?? '-',
                $row['tanggal'],
                $row['jam_masuk'] ?? '-',
                $row['jam_pulang'] ?? '-',
                $row['status'] ?? '-',
                $row['status_pulang'] ?? '-',
                $row['metode_absen'] ?? 'Manual',
                $row['lat'] ?? '-',
                $row['lng'] ?? '-'
            ]);
        }

        fclose($output);
        $stmt->close();
        $this->conn->close();
        exit;
    }

    /**
     * memproses data presensi secure dengan security wizard
     *
     * @return void
     */
    public function prosesAbsenSecure(): void
    {
        require_login($this->conn);
        $id_user = (int)$_SESSION['id_user'];
        $id_pegawai = (int)$_SESSION['id_pegawai'];

        // handler untuk get check_webauthn
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_webauthn') {
            $stmt = $this->conn->prepare("SELECT credential_id FROM webauthn_credentials WHERE id_user = ?");
            $stmt->bind_param("i", $id_user);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $this->jsonResponse([
                    'has_credential' => true,
                    'credential_id' => $row['credential_id'],
                    'user_id' => $id_user
                ]);
            } else {
                $stmt_u = $this->conn->prepare("SELECT username, role FROM users WHERE id_user = ?");
                $stmt_u->bind_param("i", $id_user);
                $stmt_u->execute();
                $u = $stmt_u->get_result()->fetch_assoc();
                
                $this->jsonResponse([
                    'has_credential' => false,
                    'user_id' => $id_user,
                    'username' => $u['username'],
                    'nama' => $_SESSION['nama']
                ]);
            }
            $stmt->close();
            exit;
        }

        // handler untuk reserve_qr (dipanggil seketika saat HP selesai memindai QR)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'reserve_qr') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['qr_token'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Token tidak valid']);
            }
            
            // cek apakah qr code masih fresh (is_used = 0)
            $stmt = $this->conn->prepare("SELECT id_token FROM qr_tokens WHERE token = ? AND is_used = 0");
            $stmt->bind_param("s", $input['qr_token']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                // tandai QR sebagai sedang di-claim / di-reserve (is_used = 2)
                $this->conn->query("UPDATE qr_tokens SET is_used = 2 WHERE id_token = " . $row['id_token']);
                // trigger update data agar Layar Kios seketika me-refresh QR baru
                trigger_data_update($this->conn);
                $this->jsonResponse(['success' => true]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'QR Code kedaluwarsa atau sudah dipindai orang lain.']);
            }
            $stmt->close();
            exit;
        }

        // handler untuk post absen
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->jsonResponse(['success' => false, 'error' => 'Invalid JSON']);
            }

            // validasi csrf
            if (!verify_csrf($input['csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'CSRF Token tidak valid']);
            }

            // ambil pengaturan aplikasi
            $settings = [];
            $res_settings = $this->conn->query("SELECT setting_key, setting_value FROM app_settings");
            if ($res_settings) {
                while ($row = $res_settings->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }

            // deteksi mobile vs pc via user-agent di server-side
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $is_mobile = (bool)preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $user_agent);

            if (!$is_mobile) {
                if (isset($settings['allow_pc_attendance']) && ($settings['allow_pc_attendance'] === '0' || $settings['allow_pc_attendance'] === 0 || $settings['allow_pc_attendance'] === 'false')) {
                    $this->jsonResponse(['success' => false, 'error' => 'Presensi hanya diperbolehkan melalui perangkat Mobile (Smartphone).']);
                }
            }

            $metode_absen = [];

            $skip_pulang = isset($settings['skip_keamanan_pulang']) ? (int)$settings['skip_keamanan_pulang'] : 1;
            $apply_security = ($input['action'] === 'absen_masuk') || ($input['action'] === 'absen_pulang' && !$skip_pulang);

            // 1. validasi qr (diwajibkan untuk mobile atau pc jika diizinkan)
            $requires_qr = $settings['qr_login'] && ($is_mobile || (!empty($settings['allow_pc_qr_scan']) && $settings['allow_pc_qr_scan'] != '0' && $settings['allow_pc_qr_scan'] != 'false'));
            if ($requires_qr && $apply_security) {
                if (empty($input['qr_token'])) {
                    $this->jsonResponse(['success' => false, 'error' => 'QR Code wajib di-scan untuk absen.']);
                }
                // izinkan is_used = 0 (belum di-reserve) ATAU is_used = 2 (sudah di-reserve oleh orang ini)
                $stmt_qr = $this->conn->prepare("SELECT id_token, created_at FROM qr_tokens WHERE token = ? AND is_used IN (0, 2)");
                $stmt_qr->bind_param("s", $input['qr_token']);
                $stmt_qr->execute();
                $qr_res = $stmt_qr->get_result();
                if ($qr_row = $qr_res->fetch_assoc()) {
                    // cek kadaluarsa sesuai durasi konfigurasi manual (qr_refresh_duration)
                    $qr_duration = isset($settings['qr_refresh_duration']) ? (int)$settings['qr_refresh_duration'] : 30;
                    $created_time = strtotime($qr_row['created_at']);
                    if (time() - $created_time > ($qr_duration + 5)) {
                        $this->jsonResponse(['success' => false, 'error' => 'Sesi QR Code kedaluwarsa. Silakan scan ulang.']);
                    }
                    // tandai token sudah digunakan
                    $this->conn->query("UPDATE qr_tokens SET is_used = 1 WHERE id_token = " . $qr_row['id_token']);
                    // bersihkan otomatis riwayat token agar maksimal 100 saja
                    $this->conn->query("
                        DELETE FROM qr_tokens 
                        WHERE is_used = 1 
                          AND id_token NOT IN (
                            SELECT id_token FROM (
                              SELECT id_token FROM qr_tokens 
                              WHERE is_used = 1 
                              ORDER BY created_at DESC 
                              LIMIT 100
                            ) as temp
                          )
                    ");
                    $metode_absen[] = 'QR';
                } else {
                    $this->jsonResponse(['success' => false, 'error' => 'QR Code tidak valid atau sudah digunakan.']);
                }
                $stmt_qr->close();
            }

            // 2. verifikasi webauthn
            if ($settings['webauthn'] && $apply_security) {
                if (empty($input['webauthn_data'])) {
                    $this->jsonResponse(['success' => false, 'error' => 'Autentikasi Biometrik/FIDO2 wajib dilakukan untuk absen.']);
                }
                $wa = $input['webauthn_data'];
                if ($wa['type'] === 'register') {
                    $stmt_wa = $this->conn->prepare("INSERT INTO webauthn_credentials (id_user, credential_id) VALUES (?, ?)");
                    $stmt_wa->bind_param("is", $id_user, $wa['id']);
                    $stmt_wa->execute();
                    $metode_absen[] = 'WebAuthn(Reg)';
                    $stmt_wa->close();
                } elseif ($wa['type'] === 'login') {
                    $stmt_wa = $this->conn->prepare("SELECT credential_id FROM webauthn_credentials WHERE id_user = ? AND credential_id = ?");
                    $stmt_wa->bind_param("is", $id_user, $wa['id']);
                    $stmt_wa->execute();
                    if ($stmt_wa->get_result()->num_rows === 0) {
                        $this->jsonResponse(['success' => false, 'error' => 'Autentikasi Biometrik/FIDO2 Gagal. Kredensial tidak cocok.']);
                    }
                    $metode_absen[] = 'WebAuthn';
                    $stmt_wa->close();
                }
            }

            // 3. validasi foto selfie base64
            $foto_path = null;
            if ($settings['selfie_validation'] && $apply_security) {
                if (empty($input['selfie_base64'])) {
                    $this->jsonResponse(['success' => false, 'error' => 'Foto selfie wajib disertakan untuk absen.']);
                }
                $foto_path = $input['selfie_base64'];
                $metode_absen[] = 'Selfie';
            }

            // 4. pencatatan koordinat lokasi gps
            $lat = null;
            $lng = null;
            if ($settings['location_logging']) {
                $lat = $input['lat'] ?? null;
                $lng = $input['lng'] ?? null;
                if ($lat && $lng) $metode_absen[] = 'GPS';
            }

            if (empty($metode_absen)) $metode_absen[] = 'Manual';
            $metode_str = implode(' + ', $metode_absen);

            // proses penulisan ke database presensi
            $action = $input['action'];
            $tgl_hari_ini = date('Y-m-d');
            $jam_sekarang = date('H:i:s');

            // auto-checkout untuk hari-hari sebelumnya yang belum ditutup
            $default_pulang = !empty($settings['jam_pulang_mulai']) ? $settings['jam_pulang_mulai'] . ':00' : '16:00:00';
            $stmt_auto = $this->conn->prepare("UPDATE presensi SET jam_pulang = ?, status_pulang = 'Otomatis' WHERE jam_pulang IS NULL AND tanggal < ?");
            $stmt_auto->bind_param('ss', $default_pulang, $tgl_hari_ini);
            $stmt_auto->execute();
            $stmt_auto->close();

            $stmt_cek = $this->conn->prepare('SELECT * FROM presensi WHERE id_pegawai = ? AND tanggal = ?');
            $stmt_cek->bind_param('is', $id_pegawai, $tgl_hari_ini);
            $stmt_cek->execute();
            $cek_presensi = $stmt_cek->get_result()->fetch_assoc();
            $stmt_cek->close();

            if ($action === 'absen_masuk' && !$cek_presensi) {
                $batas_masuk = !empty($settings['jam_masuk_batas']) ? $settings['jam_masuk_batas'] . ':00' : '07:00:00';
                $status = ($jam_sekarang > $batas_masuk) ? 'Terlambat' : 'Tepat Waktu';
                $stmt_in = $this->conn->prepare('INSERT INTO presensi (id_pegawai, tanggal, jam_masuk, status, foto_selfie, lat, lng, metode_absen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt_in->bind_param('isssssss', $id_pegawai, $tgl_hari_ini, $jam_sekarang, $status, $foto_path, $lat, $lng, $metode_str);
                $stmt_in->execute();
                $stmt_in->close();
                trigger_data_update($this->conn);
                $this->jsonResponse(['success' => true]);
            } 
            elseif ($action === 'absen_pulang' && $cek_presensi && $cek_presensi['jam_pulang'] === null) {
                $batas_pulang = !empty($settings['jam_pulang_mulai']) ? $settings['jam_pulang_mulai'] . ':00' : '16:00:00';
                $status_pulang = ($jam_sekarang >= $batas_pulang) ? 'Tepat Waktu' : 'Lebih Dulu';
                
                $stmt_out = $this->conn->prepare('UPDATE presensi SET jam_pulang = ?, status_pulang = ?, foto_selfie = COALESCE(foto_selfie, ?), lat = COALESCE(lat, ?), lng = COALESCE(lng, ?), metode_absen = CONCAT(metode_absen, " | ", ?) WHERE id_pegawai = ? AND tanggal = ?');
                $stmt_out->bind_param('ssssssis', $jam_sekarang, $status_pulang, $foto_path, $lat, $lng, $metode_str, $id_pegawai, $tgl_hari_ini);
                $stmt_out->execute();
                $stmt_out->close();
                trigger_data_update($this->conn);
                $this->jsonResponse(['success' => true]);
            }

            $this->jsonResponse(['success' => false, 'error' => 'Aksi absen tidak valid atau sudah dilakukan.']);
        }
    }

    /**
     * memproses request get untuk menghasilkan token baru
     *
     * @return void
     */
    public function qrGenerator(): void
    {
        require_login($this->conn);
        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['Admin', 'Kades'])) {
            http_response_code(403);
            $this->jsonResponse(['error' => 'Forbidden']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // generate token acak 64 karakter
            $token = bin2hex(random_bytes(32));
            $now = date('Y-m-d H:i:s');
            
            // hapus token lama yang sudah lebih dari 1 jam untuk mencegah penumpukan
            $this->conn->query("DELETE FROM qr_tokens WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            
            // simpan token baru
            $stmt = $this->conn->prepare("INSERT INTO qr_tokens (token, created_at, is_used) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $token, $now);
            
            if ($stmt->execute()) {
                // bersihkan otomatis riwayat token agar maksimal 100 saja
                $this->conn->query("
                    DELETE FROM qr_tokens 
                    WHERE is_used = 1 
                      AND id_token NOT IN (
                        SELECT id_token FROM (
                          SELECT id_token FROM qr_tokens 
                          WHERE is_used = 1 
                          ORDER BY created_at DESC 
                          LIMIT 100
                        ) as temp
                      )
                ");
                $stmt->close();
                $this->jsonResponse(['success' => true, 'token' => $token]);
            } else {
                $stmt->close();
                http_response_code(500);
                $this->jsonResponse(['error' => 'Gagal membuat token']);
            }
        }
    }

    /**
     * endpoint untuk mengecek timestamp pembaruan data terakhir
     *
     * @return void
     */
    public function apiSync(): void
    {
        init_session();
        header('Content-Type: application/json');

        if (!isset($_SESSION['id_user'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized']);
        }
        
        // lepaskan lock session agar operasi crud dari client yang sama tidak tertahan
        session_write_close();

        $stmt = $this->conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'last_data_update'");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $last_update = (int)$row['setting_value'];
        } else {
            // jika belum ada buat dengan timestamp sekarang
            $last_update = time();
            $this->conn->query("INSERT INTO app_settings (setting_key, setting_value) VALUES ('last_data_update', $last_update)");
        }
        $stmt->close();

        $this->jsonResponse([
            'success' => true,
            'last_update' => $last_update
        ]);
    }

    /**
     * tampilan absensi mandiri standalone fullscreen
     *
     * @return void
     */
    public function standaloneView(): void
    {
        require_login($this->conn);
        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['Admin', 'Kades'])) {
            header('Location: login.php');
            exit;
        }

        $page_title = 'Layar Absensi Standalone - Sistem Kehadiran';

        // ambil pengaturan refresh duration
        $settings = [];
        $res_settings = $this->conn->query("SELECT setting_key, setting_value FROM app_settings");
        if ($res_settings) {
            while ($row = $res_settings->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        require_once __DIR__ . '/../templates/absen_standalone.php';
        $this->conn->close();
    }

    /**
     * api json untuk mengambil 5 data absensi terbaru hari ini
     *
     * @return void
     */
    public function apiLatestAbsen(): void
    {
        require_login($this->conn);
        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['Admin', 'Kades'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Forbidden']);
        }

        $tgl_hari_ini = date('Y-m-d');
        $stmt = $this->conn->prepare('
            SELECT p.nama, pr.jam_masuk, pr.jam_pulang, pr.status, pr.status_pulang, pr.metode_absen
            FROM presensi pr
            JOIN pegawai p ON pr.id_pegawai = p.id_pegawai
            WHERE pr.tanggal = ?
            ORDER BY pr.jam_masuk DESC, pr.id_presensi DESC
            LIMIT 5
        ');
        $stmt->bind_param('s', $tgl_hari_ini);
        $stmt->execute();
        $res = $stmt->get_result();
        $logs = [];
        while ($row = $res->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();

        $this->jsonResponse([
            'success' => true,
            'data' => $logs
        ]);
    }
}
