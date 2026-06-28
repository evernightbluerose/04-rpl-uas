<?php
/**
 * kelas pengendali pengaturan profil dan pembaruan password
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/BaseController.php';

class ProfilController extends BaseController
{
    /**
     * menangani pembaruan data profil dan password
     *
     * @return void
     */
    public function index(): void
    {
        require_login($this->conn);

        $pesan = '';
        $pesan_type = 'info';

        $id_user = (int) $_SESSION['id_user'];

        // ambil data pengguna saat ini
        $stmt = $this->conn->prepare("
            SELECT p.nama, p.nip, p.foto_profil, u.username 
            FROM pegawai p 
            JOIN users u ON p.id_user = u.id_user 
            WHERE u.id_user = ?
        ");
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $profil = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                $pesan = 'Sesi tidak valid atau telah kadaluarsa.';
                $pesan_type = 'danger';
            } else {
                $nama_baru = trim($_POST['nama'] ?? '');
                $password_lama = $_POST['password_lama'] ?? '';
                $password_baru = $_POST['password_baru'] ?? '';
                $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';
                $foto_base64 = $_POST['foto_profil_base64'] ?? '';

                if (empty($nama_baru)) {
                    $pesan = 'Nama tidak boleh kosong.';
                    $pesan_type = 'danger';
                } else {
                    $this->conn->begin_transaction();
                    try {
                        $path_foto_baru = $profil['foto_profil'];
                        
                        if (!empty($foto_base64)) {
                            if (preg_match('/^data:image\/(\w+);base64,/', $foto_base64, $type)) {
                                $foto_data = substr($foto_base64, strpos($foto_base64, ',') + 1);
                                $foto_data = str_replace(' ', '+', $foto_data);
                                $type = strtolower($type[1]);
                                
                                if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                                    throw new Exception("Tipe file gambar tidak valid.");
                                }
                                
                                if (base64_decode($foto_data) === false) {
                                    throw new Exception("Gagal mendekode gambar.");
                                }
                                
                                // ubah ukuran dan kompres gambar sebelum disimpan ke database
                                $path_foto_baru = resize_image_base64($foto_base64);
                            } else {
                                throw new Exception("Format gambar base64 tidak valid.");
                            }
                        }

                        $stmt_upd = $this->conn->prepare("UPDATE pegawai SET nama = ?, foto_profil = ? WHERE id_user = ?");
                        $stmt_upd->bind_param("ssi", $nama_baru, $path_foto_baru, $id_user);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                        
                        $_SESSION['nama'] = $nama_baru;
                        $_SESSION['foto_profil'] = $path_foto_baru;
                        $profil['nama'] = $nama_baru;
                        $profil['foto_profil'] = $path_foto_baru;

                        if (!empty($password_lama) || !empty($password_baru) || !empty($konfirmasi_password)) {
                            $stmt_pass = $this->conn->prepare("SELECT password FROM users WHERE id_user = ?");
                            $stmt_pass->bind_param("i", $id_user);
                            $stmt_pass->execute();
                            $db_pass = $stmt_pass->get_result()->fetch_assoc()['password'];
                            $stmt_pass->close();

                            if (hash('sha256', $password_lama) !== $db_pass) {
                                throw new Exception("Kata sandi saat ini tidak sesuai.");
                            }
                            if ($password_baru !== $konfirmasi_password) {
                                throw new Exception("Konfirmasi kata sandi baru tidak cocok.");
                            }
                            if (strlen($password_baru) < 6) {
                                throw new Exception("Kata sandi baru minimal 6 karakter.");
                            }

                            $new_hash = hash('sha256', $password_baru);
                            $stmt_pw = $this->conn->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                            $stmt_pw->bind_param("si", $new_hash, $id_user);
                            $stmt_pw->execute();
                            $stmt_pw->close();
                            
                            $pesan = 'Profil, foto, dan kata sandi berhasil diperbarui!';
                            $pesan_type = 'success';
                        } else {
                            $pesan = 'Profil dan foto berhasil diperbarui!';
                            $pesan_type = 'success';
                        }

                        trigger_data_update($this->conn);
                        $this->conn->commit();
                    } catch (\Throwable $e) {
                        try { $this->conn->rollback(); } catch (\Throwable $er) {}
                        $pesan = 'Gagal: ' . $e->getMessage();
                        $pesan_type = 'danger';
                    }
                }
            }
        }

        $page_title = 'Pengaturan Profil - DB Dashboard';
        $current_page = 'profil.php';

        require_once __DIR__ . '/../includes/header.php';
        require_once __DIR__ . '/../templates/profil_form.php';
        
        $this->conn->close();
        require_once __DIR__ . '/../includes/footer.php';
    }
}
