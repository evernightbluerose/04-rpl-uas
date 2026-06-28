<?php
/**
 * kelas pengendali untuk mengunduh lampiran
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class AttachmentController extends BaseController
{
    /**
     * menangani aksi pengunduhan berkas lampiran
     *
     * @return void
     */
    public function download(): void
    {
        require_login($this->conn);

        $type = $_GET['type'] ?? '';
        $id = (int)($_GET['id'] ?? 0);

        $res = null;
        if ($type === 'logbook') {
            $stmt = $this->conn->prepare("SELECT file_name, file_type, file_data FROM logbook_attachments WHERE id_attachment = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } elseif ($type === 'izin') {
            $stmt = $this->conn->prepare("SELECT file_name, file_type, file_data FROM izin_attachments WHERE id_attachment = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if ($res) {
            $data = $res['file_data'];
            // format: data:image/png;base64,...
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $data, $matches)) {
                $content_type = $matches[1];
                $base64_data = $matches[2];
                $binary_data = base64_decode($base64_data);
                
                header('Content-Type: ' . $content_type);
                header('Content-Disposition: attachment; filename="' . $res['file_name'] . '"');
                header('Content-Length: ' . strlen($binary_data));
                echo $binary_data;
                $this->conn->close();
                exit;
            }
        }

        header("HTTP/1.0 404 Not Found");
        echo "Berkas tidak ditemukan.";
        $this->conn->close();
        exit;
    }
}
