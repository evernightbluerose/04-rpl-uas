<?php
/**
 * kelas pengendali untuk mencatat logbook harian kegiatan pegawai
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class LogbookController extends BaseController
{
    /**
     * menangani request logbook harian baik get maupun post
     *
     * @return void
     */
    public function handle(): void
    {
        require_login($this->conn);

        $page_title = 'Logbook Kegiatan - DB Dashboard';
        $current_page = 'logbook.php';

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

        // memproses input form (semua role dapat mencatat kegiatannya sendiri)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? '')) {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'add') {
                $tanggal = trim($_POST['tanggal'] ?? '');
                $uraian = trim($_POST['uraian_kegiatan'] ?? '');
                
                // ambil id_pegawai berdasarkan id_user
                $stmt = $this->conn->prepare('SELECT id_pegawai FROM pegawai WHERE id_user = ?');
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $pegawai = $res->fetch_assoc();
                $stmt->close();
                
                if ($pegawai && !empty($tanggal) && !empty($uraian)) {
                    $id_pegawai = $pegawai['id_pegawai'];
                    
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
                            $stmt2 = $this->conn->prepare('INSERT INTO logbook (id_pegawai, tanggal, uraian_kegiatan) VALUES (?, ?, ?)');
                            $stmt2->bind_param('iss', $id_pegawai, $tanggal, $uraian);
                            $stmt2->execute();
                            $id_logbook = $stmt2->insert_id;
                            $stmt2->close();
                            
                            // simpan lampiran berkas
                            if (!empty($attachments)) {
                                $stmt_att = $this->conn->prepare('INSERT INTO logbook_attachments (id_logbook, file_name, file_type, file_data) VALUES (?, ?, ?, ?)');
                                foreach ($attachments as $att) {
                                    $stmt_att->bind_param('isss', $id_logbook, $att['name'], $att['type'], $att['data']);
                                    $stmt_att->execute();
                                }
                                $stmt_att->close();
                            }
                            
                            trigger_data_update($this->conn);
                            $this->conn->commit();
                            $success_msg = 'Kegiatan berhasil ditambahkan ke logbook.';
                        } catch (\Throwable $e) {
                            try { $this->conn->rollback(); } catch (\Throwable $er) {}
                            $error_msg = 'Gagal menambahkan kegiatan.';
                        }
                    }
                } else {
                    $error_msg = 'Mohon lengkapi semua data.';
                }
            } elseif ($action === 'delete') {
                $id_logbook = (int)($_POST['id_logbook'] ?? 0);
                // verifikasi kepemilikan data sebelum dihapus
                $stmt = $this->conn->prepare('SELECT l.id_logbook FROM logbook l JOIN pegawai p ON l.id_pegawai = p.id_pegawai WHERE l.id_logbook = ? AND p.id_user = ? FOR UPDATE');
                $stmt->bind_param('ii', $id_logbook, $user_id);
                $this->conn->begin_transaction();
                $stmt->execute();
                $res = $stmt->get_result();
                $stmt->close();

                if ($res->num_rows > 0) {
                    $del = $this->conn->prepare('DELETE FROM logbook WHERE id_logbook = ?');
                    $del->bind_param('i', $id_logbook);
                    $del->execute();
                    $del->close();
                    trigger_data_update($this->conn);
                    $this->conn->commit();
                    $success_msg = 'Kegiatan berhasil dihapus.';
                } else {
                    try { $this->conn->rollback(); } catch (Exception $er) {}
                    $error_msg = 'Gagal menghapus kegiatan.';
                }
            }

            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => !empty($success_msg),
                    'message' => $success_msg ?: $error_msg
                ]);
            }
        }

        // setup paginasi maksimal 50 entri per halaman
        $limit = 50;
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;

        $logs = [];
        if ($user_role === 'Pegawai') {
            // ambil jumlah data mandiri untuk paginasi
            $stmt_c = $this->conn->prepare('
                SELECT COUNT(*) AS total 
                FROM logbook l 
                JOIN pegawai p ON l.id_pegawai = p.id_pegawai 
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
                SELECT l.*, p.nama, p.nip, j.nama_jabatan 
                FROM logbook l 
                JOIN pegawai p ON l.id_pegawai = p.id_pegawai 
                LEFT JOIN jabatan j ON p.id_jabatan = j.id_jabatan
                WHERE p.id_user = ? 
                ORDER BY l.tanggal DESC, l.id_logbook DESC
                LIMIT ? OFFSET ?
            ');
            $stmt->bind_param('iii', $user_id, $limit, $offset);
            $stmt->execute();
            $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            // kades atau admin dapat melihat semua logbook kegiatan
            $filter_date = $_GET['date'] ?? '';
            $query_c = '
                SELECT COUNT(*) AS total 
                FROM logbook l 
                JOIN pegawai p ON l.id_pegawai = p.id_pegawai
            ';
            if (!empty($filter_date)) {
                $query_c .= ' WHERE l.tanggal = ? ';
            }
            $stmt_c = $this->conn->prepare($query_c);
            if (!empty($filter_date)) {
                $stmt_c->bind_param('s', $filter_date);
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
                SELECT l.*, p.nama, p.nip, j.nama_jabatan, p.id_user 
                FROM logbook l 
                JOIN pegawai p ON l.id_pegawai = p.id_pegawai 
                LEFT JOIN jabatan j ON p.id_jabatan = j.id_jabatan
            ';
            if (!empty($filter_date)) {
                $query .= ' WHERE l.tanggal = ? ';
            }
            $query .= ' ORDER BY l.tanggal DESC, l.id_logbook DESC LIMIT ? OFFSET ?';
            
            $stmt = $this->conn->prepare($query);
            if (!empty($filter_date)) {
                $stmt->bind_param('sii', $filter_date, $limit, $offset);
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }
            $stmt->execute();
            $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // ambil lampiran berkas untuk logbook yang ditampilkan
        $attachments_map = [];
        if (!empty($logs)) {
            $logbook_ids = array_column($logs, 'id_logbook');
            if (!empty($logbook_ids)) {
                $ids_placeholder = implode(',', array_fill(0, count($logbook_ids), '?'));
                $stmt_att = $this->conn->prepare("SELECT id_attachment, id_logbook, file_name, file_type FROM logbook_attachments WHERE id_logbook IN ($ids_placeholder)");
                if ($stmt_att) {
                    $types = str_repeat('i', count($logbook_ids));
                    $stmt_att->bind_param($types, ...$logbook_ids);
                    $stmt_att->execute();
                    $res_att = $stmt_att->get_result();
                    while ($row_att = $res_att->fetch_assoc()) {
                        $attachments_map[$row_att['id_logbook']][] = $row_att;
                    }
                    $stmt_att->close();
                }
            }
        }

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/logbook_view.php';
        
        $this->conn->close();
        require_once __DIR__ . '/../includes/footer.php';
    }
}
