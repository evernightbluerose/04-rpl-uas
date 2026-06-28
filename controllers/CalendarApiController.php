<?php
/**
 * endpoint api untuk data kalender kehadiran
 *
 * mengembalikan json berisi per-tanggal: jumlah hadir, terlambat,
 * dan daftar nama pegawai yang sudah absen pada tanggal tersebut
 * digunakan oleh calendar view di dasbor admin dan kades
 *
 * @package sistemkehadiran
 */

require_once __DIR__ . '/../includes/auth.php';

init_session();

// hanya admin dan kades yang boleh mengakses endpoint ini
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['Admin', 'Kades'], true)) {
    http_response_code(403);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'akses ditolak']);
    exit;
}

// buka koneksi sendiri agar tidak bergantung pada $conn dari scope luar
require_once __DIR__ . '/../config/database.php';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// parameter: year dan month untuk menentukan range kalender yang di-fetch
$year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

// validasi range
if ($month < 1 || $month > 12) $month = (int) date('n');
if ($year < 2020 || $year > 2100) $year = (int) date('Y');

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date   = date('Y-m-t', strtotime($start_date));

// ambil semua presensi dalam bulan tersebut beserta nama pegawai
// DATE() cast memastikan kompatibel meski kolom bertipe DATETIME
$stmt = $conn->prepare(
    'SELECT DATE(pr.tanggal) AS tanggal, pr.status, pr.jam_masuk, pr.jam_pulang, pg.nama
     FROM   presensi pr
     JOIN   pegawai  pg ON pr.id_pegawai = pg.id_pegawai
     WHERE  DATE(pr.tanggal) BETWEEN ? AND ?
     ORDER  BY pr.tanggal ASC, pr.jam_masuk ASC'
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// susun response: key = tanggal (y-m-d), value = { total, terlambat, list[] }
$data = [];
while ($row = $result->fetch_assoc()) {
    $tgl = $row['tanggal'];
    if (!isset($data[$tgl])) {
        $data[$tgl] = ['total' => 0, 'terlambat' => 0, 'list' => []];
    }
    $data[$tgl]['total']++;
    if ($row['status'] === 'Terlambat') {
        $data[$tgl]['terlambat']++;
    }
    $data[$tgl]['list'][] = [
        'nama'       => $row['nama'],
        'jam_masuk'  => $row['jam_masuk'],
        'jam_pulang' => $row['jam_pulang'],
        'status'     => $row['status'],
    ];
}

$stmt->close();
$conn->close();

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
