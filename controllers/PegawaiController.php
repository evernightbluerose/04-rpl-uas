<?php
/**
 * kelas pengendali proses presensi pegawai harian
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class PegawaiController extends BaseController
{
    /**
     * menampilkan dasbor utama pegawai beserta riwayat presensi terbaru
     *
     * @return void
     */
    public function index(): void
    {
        require_role('Pegawai', $this->conn);

        $id_pegawai   = (int) $_SESSION['id_pegawai'];
        $tgl_hari_ini = date('Y-m-d');
        $jam_sekarang = date('H:i:s');

        // cek status presensi hari ini
        $stmt_cek = $this->conn->prepare(
            'SELECT * FROM presensi WHERE id_pegawai = ? AND tanggal = ?'
        );
        $stmt_cek->bind_param('is', $id_pegawai, $tgl_hari_ini);
        $stmt_cek->execute();
        $cek_presensi = $stmt_cek->get_result()->fetch_assoc();
        $stmt_cek->close();

        // proses form absen fallback post
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf_token'] ?? '';
            if (!verify_csrf($csrf)) {
                header('Location: pegawai.php');
                exit;
            }

            if (isset($_POST['absen_masuk']) && !$cek_presensi) {
                // aturan bisnis: masuk setelah 07:00 dianggap terlambat
                $status = ($jam_sekarang > '07:00:00') ? 'Terlambat' : 'Tepat Waktu';

                $stmt_in = $this->conn->prepare(
                    'INSERT INTO presensi (id_pegawai, tanggal, jam_masuk, status, metode_absen) VALUES (?, ?, ?, ?, "Manual")'
                );
                $stmt_in->bind_param('isss', $id_pegawai, $tgl_hari_ini, $jam_sekarang, $status);
                $stmt_in->execute();
                $stmt_in->close();
                trigger_data_update($this->conn);

                header('Location: pegawai.php');
                exit;
            }

            if (isset($_POST['absen_pulang']) && $cek_presensi && $cek_presensi['jam_pulang'] === null) {
                $status_pulang = ($jam_sekarang >= '16:00:00') ? 'Tepat Waktu' : 'Lebih Dulu';
                $stmt_out = $this->conn->prepare(
                    'UPDATE presensi SET jam_pulang = ?, status_pulang = ?, metode_absen = CONCAT(metode_absen, " | Manual") WHERE id_pegawai = ? AND tanggal = ?'
                );
                $stmt_out->bind_param('ssis', $jam_sekarang, $status_pulang, $id_pegawai, $tgl_hari_ini);
                $stmt_out->execute();
                $stmt_out->close();
                trigger_data_update($this->conn);

                header('Location: pegawai.php');
                exit;
            }
        }

        // mengambil data riwayat presensi 90 hari terakhir untuk list + calendar view
        $stmt_riwayat = $this->conn->prepare(
            'SELECT tanggal, jam_masuk, jam_pulang, status, status_pulang, metode_absen
             FROM   presensi
             WHERE  id_pegawai = ?
             ORDER BY tanggal DESC
             LIMIT 90'
        );
        $stmt_riwayat->bind_param('i', $id_pegawai);
        $stmt_riwayat->execute();
        $riwayat = $stmt_riwayat->get_result();

        // buat array json untuk calendar view — indexed by tanggal (y-m-d)
        $calendar_data = [];
        $rows_for_list = [];
        while ($row = $riwayat->fetch_assoc()) {
            $calendar_data[$row['tanggal']] = [
                'status'       => $row['status'],
                'jam_masuk'    => $row['jam_masuk'],
                'jam_pulang'   => $row['jam_pulang'],
                'status_pulang'=> $row['status_pulang'],
            ];
            $rows_for_list[] = $row;
        }
        $calendar_json = json_encode($calendar_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // mengambil pengaturan keamanan aktif
        $settings = [];
        $res_settings = $this->conn->query("SELECT setting_key, setting_value FROM app_settings");
        if ($res_settings) {
            while ($row = $res_settings->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/pegawai_dashboard.php';

        $stmt_riwayat->close();
        $this->conn->close();

        require_once __DIR__ . '/../includes/footer.php';
    }
}
