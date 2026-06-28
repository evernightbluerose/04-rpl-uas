<?php
/**
 * kelas pengendali dasbor kepala desa
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class KadesController extends BaseController
{
    /**
     * menampilkan dasbor kepala desa beserta statistik kehadiran harian dan daftar pegawai
     *
     * @return void
     */
    public function index(): void
    {
        require_role('Kades', $this->conn);

        $tgl_hari_ini = date('Y-m-d');

        // query menggunakan case when untuk menghitung per kategori status
        $stmt_total = $this->conn->prepare(
            'SELECT
                 COUNT(DISTINCT pr.id_pegawai) AS total_hadir,
                 SUM(CASE WHEN pr.status = "Terlambat"   THEN 1 ELSE 0 END) AS total_terlambat,
                 SUM(CASE WHEN pr.status = "Tepat Waktu" THEN 1 ELSE 0 END) AS total_tepat
             FROM presensi pr
             WHERE pr.tanggal = ?'
        );
        $stmt_total->bind_param('s', $tgl_hari_ini);
        $stmt_total->execute();
        $stats = $stmt_total->get_result()->fetch_assoc();
        $stmt_total->close();

        // hitung total pegawai untuk menghitung yang belum hadir
        $stmt_peg = $this->conn->prepare('SELECT COUNT(*) AS total FROM pegawai');
        $stmt_peg->execute();
        $total_pegawai = $stmt_peg->get_result()->fetch_assoc()['total'];
        $stmt_peg->close();

        // pegawai belum hadir = total pegawai dikurangi yang sudah presensi
        $belum_hadir = $total_pegawai - ($stats['total_hadir'] ?? 0);

        // setup paginasi riwayat presensi maksimal 50 data per halaman
        $stmt_count = $this->conn->prepare('SELECT COUNT(*) AS total FROM presensi');
        $stmt_count->execute();
        $total_presensi_rows = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        $limit = 50;
        $page_presensi = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page_presensi < 1) $page_presensi = 1;
        $offset_presensi = ($page_presensi - 1) * $limit;
        $total_presensi_pages = ceil($total_presensi_rows / $limit);
        if ($page_presensi > $total_presensi_pages && $total_presensi_pages > 0) {
            $page_presensi = $total_presensi_pages;
            $offset_presensi = ($page_presensi - 1) * $limit;
        }

        $stmt = $this->conn->prepare(
            'SELECT p.nama, pr.tanggal, pr.jam_masuk, pr.jam_pulang, pr.status, pr.foto_selfie, pr.lat, pr.lng, pr.metode_absen
             FROM   presensi pr
             JOIN   pegawai  p ON pr.id_pegawai = p.id_pegawai
             ORDER BY pr.tanggal DESC, pr.jam_masuk DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bind_param('ii', $limit, $offset_presensi);
        $stmt->execute();
        $result = $stmt->get_result();

        $search = $_GET['cari'] ?? '';
        $search_safe = "%{$search}%";

        // setup paginasi daftar pegawai maksimal 50 data per halaman
        $stmt_count_peg = $this->conn->prepare('SELECT COUNT(*) AS total FROM pegawai WHERE nama LIKE ? OR nip LIKE ?');
        $stmt_count_peg->bind_param('ss', $search_safe, $search_safe);
        $stmt_count_peg->execute();
        $total_pegawai_rows = $stmt_count_peg->get_result()->fetch_assoc()['total'];
        $stmt_count_peg->close();

        $page_pegawai = isset($_GET['page_pegawai']) ? (int) $_GET['page_pegawai'] : 1;
        if ($page_pegawai < 1) $page_pegawai = 1;
        $offset_pegawai = ($page_pegawai - 1) * $limit;
        $total_pegawai_pages = ceil($total_pegawai_rows / $limit);
        if ($page_pegawai > $total_pegawai_pages && $total_pegawai_pages > 0) {
            $page_pegawai = $total_pegawai_pages;
            $offset_pegawai = ($page_pegawai - 1) * $limit;
        }

        $stmt_pegawai = $this->conn->prepare(
            'SELECT p.id_pegawai, p.nip, p.nama, p.foto_profil, j.nama_jabatan, u.role
             FROM   pegawai p
             LEFT JOIN jabatan j ON p.id_jabatan = j.id_jabatan
             LEFT JOIN users u ON p.id_user = u.id_user
             WHERE  p.nama LIKE ? OR p.nip LIKE ?
             ORDER BY p.nama ASC
             LIMIT ? OFFSET ?'
        );
        $stmt_pegawai->bind_param('ssii', $search_safe, $search_safe, $limit, $offset_pegawai);
        $stmt_pegawai->execute();
        $result_pegawai = $stmt_pegawai->get_result();

        // mengambil pengaturan global aplikasi
        $settings = [];
        $res_settings = $this->conn->query("SELECT setting_key, setting_value FROM app_settings");
        if ($res_settings) {
            while ($row = $res_settings->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/kades_dashboard.php';

        $stmt->close();
        $stmt_pegawai->close();
        $this->conn->close();

        require_once __DIR__ . '/../includes/footer.php';
    }
}
