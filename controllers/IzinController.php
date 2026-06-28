<?php
/**
 * kelas pengendali pengajuan izin dan cuti pegawai
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class IzinController extends BaseController
{
    /**
     * menangani request pengajuan izin baik get maupun post
     *
     * @return void
     */
    public function handle(): void
    {
        require_login($this->conn);

        $page_title = 'Pengajuan Izin - DB Dashboard';
        $current_page = 'izin.php';

        $user_role = $_SESSION['role'] ?? '';
        $user_id = $_SESSION['id_user'] ?? 0;

        $settings = [];
        $res_settings = $this->conn->query("SELECT setting_key, setting_value FROM app_settings");
        if ($res_settings) {
            while ($row = $res_settings->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        $max_size_mb = (int)($settings['max_attachment_size'] ?? 2);
        $max_size_bytes = $max_size_mb * 1024 * 1024;

        $success_msg = '';
        $error_msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? '')) {
            $action = $_POST['action'] ?? '';

            // aksi tambah pengajuan oleh pegawai sendiri
            if ($action === 'add') {
                $jenis = $_POST['jenis_izin'] ?? '';
                $tgl_mulai = $_POST['tanggal_mulai'] ?? '';
                $tgl_selesai = $_POST['tanggal_selesai'] ?? '';
                $keterangan = trim($_POST['keterangan'] ?? '');

                // ambil data pegawai berdasarkan id_user
                $stmt = $this->conn->prepare('SELECT id_pegawai FROM pegawai WHERE id_user = ?');
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $pegawai = $res->fetch_assoc();
                $stmt->close();

                if ($pegawai && in_array($jenis, ['Sakit', 'Cuti', 'Izin Dinas']) && !empty($tgl_mulai) && !empty($tgl_selesai)) {
                    $id_pegawai = $pegawai['id_pegawai'];
                    $status = 'Pending';
                    
                    // validasi lampiran berkas
                    $attachments = [];
                    if (!empty($_FILES['attachments']['name'][0])) {
                        $files = $_FILES['attachments'];
                        for ($i = 0; $i < count($files['name']); $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                                continue;
                            }
                            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                                $error_msg = 'Terjadi kesalahan saat mengunggah berkas.';
                                break;
                            }
                            if ($files['size'][$i] > $max_size_bytes) {
                                $error_msg = 'Ukuran berkas melebihi batas maksimum ' . $max_size_mb . ' MB.';
                                break;
                            }
                            $file_name = basename($files['name'][$i]);
                            $file_type = $files['type'][$i];
                            $file_data = base64_encode(file_get_contents($files['tmp_name'][$i]));
                            $attachments[] = [
                                'name' => $file_name,
                                'type' => $file_type,
                                'data' => 'data:' . $file_type . ';base64,' . $file_data
                            ];
                        }
                    }

                    if (empty($error_msg)) {
                        $this->conn->begin_transaction();
                        try {
                            $stmt2 = $this->conn->prepare('INSERT INTO pengajuan_izin (id_pegawai, jenis_izin, tanggal_mulai, tanggal_selesai, keterangan, status_validasi) VALUES (?, ?, ?, ?, ?, ?)');
                            $stmt2->bind_param('isssss', $id_pegawai, $jenis, $tgl_mulai, $tgl_selesai, $keterangan, $status);
                            $stmt2->execute();
                            $id_izin = $stmt2->insert_id;
                            $stmt2->close();
                            
                            // simpan lampiran berkas
                            if (!empty($attachments)) {
                                $stmt_att = $this->conn->prepare('INSERT INTO izin_attachments (id_izin, file_name, file_type, file_data) VALUES (?, ?, ?, ?)');
                                foreach ($attachments as $att) {
                                    $stmt_att->bind_param('isss', $id_izin, $att['name'], $att['type'], $att['data']);
                                    $stmt_att->execute();
                                }
                                $stmt_att->close();
                            }
                            
                            trigger_data_update($this->conn);
                            $this->conn->commit();
                            $success_msg = 'Pengajuan izin berhasil dikirim dan menunggu validasi.';
                        } catch (\Throwable $e) {
                            try { $this->conn->rollback(); } catch (\Throwable $er) {}
                            $error_msg = 'Terjadi kesalahan saat mengajukan izin.';
                        }
                    }
                } else {
                    $error_msg = 'Mohon lengkapi semua data dengan benar.';
                }
            } 
            // aksi pembatalan pengajuan izin
            elseif ($action === 'delete') {
                $id_izin = (int)($_POST['id_izin'] ?? 0);
                // hanya izinkan menghapus pengajuan yang berstatus pending
                $stmt = $this->conn->prepare('SELECT p.id_izin FROM pengajuan_izin p JOIN pegawai peg ON p.id_pegawai = peg.id_pegawai WHERE p.id_izin = ? AND peg.id_user = ? AND p.status_validasi = "Pending" FOR UPDATE');
                $stmt->bind_param('ii', $id_izin, $user_id);
                $this->conn->begin_transaction();
                $stmt->execute();
                $res = $stmt->get_result();
                $stmt->close();

                if ($res->num_rows > 0) {
                    $del = $this->conn->prepare('DELETE FROM pengajuan_izin WHERE id_izin = ?');
                    $del->bind_param('i', $id_izin);
                    $del->execute();
                    $del->close();
                    trigger_data_update($this->conn);
                    $this->conn->commit();
                    $success_msg = 'Pengajuan izin berhasil dibatalkan/dihapus.';
                } else {
                    try { $this->conn->rollback(); } catch (Exception $er) {}
                    $error_msg = 'Tidak dapat menghapus pengajuan ini (mungkin sudah divalidasi).';
                }
            }
            // aksi validasi izin oleh admin atau kades
            elseif ($user_role !== 'Pegawai' && $action === 'validate') {
                $id_izin = (int)($_POST['id_izin'] ?? 0);
                $status_baru = $_POST['status_validasi'] ?? '';
                
                if (in_array($status_baru, ['Disetujui', 'Ditolak'])) {
                    $this->conn->begin_transaction();
                    $update = $this->conn->prepare('UPDATE pengajuan_izin SET status_validasi = ? WHERE id_izin = ? AND status_validasi = "Pending"');
                    $update->bind_param('si', $status_baru, $id_izin);
                    $update->execute();
                    $affected = $update->affected_rows;
                    $update->close();

                    if ($affected > 0) {
                        trigger_data_update($this->conn);
                        $this->conn->commit();
                        $success_msg = "Pengajuan izin berhasil $status_baru.";
                    } else {
                        try { $this->conn->rollback(); } catch (Exception $er) {}
                        $error_msg = "Gagal memvalidasi pengajuan. Mungkin status sudah berubah.";
                    }
                }
            }

            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => !empty($success_msg),
                    'message' => $success_msg ?: $error_msg
                ]);
            }
        }

        // setup paginasi maksimal 50 data per halaman
        $limit = 50;
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;

        $izins = [];
        if ($user_role === 'Pegawai') {
            // ambil data total pengajuan mandiri untuk paginasi
            $stmt_c = $this->conn->prepare('
                SELECT COUNT(*) AS total 
                FROM pengajuan_izin i 
                JOIN pegawai p ON i.id_pegawai = p.id_pegawai 
                WHERE p.id_user = ?
            ');
            $stmt_c->bind_param('i', $user_id);
            $stmt_c->execute();
            $total_rows = $stmt_c->get_result()->fetch_assoc()['total'];
            $stmt_c->close();
            
            $total_pages = ceil($total_rows / $limit);
            if ($page > $total_pages && $total_pages > 0) {
                $page = $total_pages;
                $offset = ($page - 1) * $limit;
            }

            $stmt = $this->conn->prepare('
                SELECT i.*, p.nama, p.nip, j.nama_jabatan 
                FROM pengajuan_izin i 
                JOIN pegawai p ON i.id_pegawai = p.id_pegawai 
                LEFT JOIN jabatan j ON p.id_jabatan = j.id_jabatan
                WHERE p.id_user = ? 
                ORDER BY i.id_izin DESC
                LIMIT ? OFFSET ?
            ');
            $stmt->bind_param('iii', $user_id, $limit, $offset);
            $stmt->execute();
            $izins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            // kades atau admin melihat semua pengajuan
            $filter_status = $_GET['status'] ?? '';
            $query_c = '
                SELECT COUNT(*) AS total 
                FROM pengajuan_izin i 
                JOIN pegawai p ON i.id_pegawai = p.id_pegawai
            ';
            if (!empty($filter_status) && in_array($filter_status, ['Pending', 'Disetujui', 'Ditolak'])) {
                $query_c .= ' WHERE i.status_validasi = ? ';
            }
            $stmt_c = $this->conn->prepare($query_c);
            if (!empty($filter_status) && in_array($filter_status, ['Pending', 'Disetujui', 'Ditolak'])) {
                $stmt_c->bind_param('s', $filter_status);
            }
            $stmt_c->execute();
            $total_rows = $stmt_c->get_result()->fetch_assoc()['total'];
            $stmt_c->close();
            
            $total_pages = ceil($total_rows / $limit);
            if ($page > $total_pages && $total_pages > 0) {
                $page = $total_pages;
                $offset = ($page - 1) * $limit;
            }

            $query = '
                SELECT i.*, p.nama, p.nip, j.nama_jabatan, p.id_user 
                FROM pengajuan_izin i 
                JOIN pegawai p ON i.id_pegawai = p.id_pegawai 
                LEFT JOIN jabatan j ON p.id_jabatan = j.id_jabatan
            ';
            if (!empty($filter_status) && in_array($filter_status, ['Pending', 'Disetujui', 'Ditolak'])) {
                $query .= ' WHERE i.status_validasi = ? ';
            }
            $query .= ' ORDER BY FIELD(i.status_validasi, "Pending", "Disetujui", "Ditolak"), i.id_izin DESC LIMIT ? OFFSET ?';
            
            $stmt = $this->conn->prepare($query);
            if (!empty($filter_status) && in_array($filter_status, ['Pending', 'Disetujui', 'Ditolak'])) {
                $stmt->bind_param('sii', $filter_status, $limit, $offset);
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }
            $stmt->execute();
            $izins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // ambil lampiran berkas untuk izin yang ditampilkan
        $attachments_map = [];
        if (!empty($izins)) {
            $izin_ids = array_column($izins, 'id_izin');
            if (!empty($izin_ids)) {
                $ids_placeholder = implode(',', array_fill(0, count($izin_ids), '?'));
                $stmt_att = $this->conn->prepare("SELECT id_attachment, id_izin, file_name, file_type FROM izin_attachments WHERE id_izin IN ($ids_placeholder)");
                if ($stmt_att) {
                    $types = str_repeat('i', count($izin_ids));
                    $stmt_att->bind_param($types, ...$izin_ids);
                    $stmt_att->execute();
                    $res_att = $stmt_att->get_result();
                    while ($row_att = $res_att->fetch_assoc()) {
                        $attachments_map[$row_att['id_izin']][] = $row_att;
                    }
                    $stmt_att->close();
                }
            }
        }

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/izin_view.php';
        
        $this->conn->close();
        require_once __DIR__ . '/../includes/footer.php';
    }
}
