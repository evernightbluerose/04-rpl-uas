<?php
/**
 * kelas dasar untuk semua controller
 *
 * menyediakan dependency injection koneksi database dan helper bersama
 *
 * @package sistemkehadiran\controllers
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/guard.php';

class BaseController
{
    /**
     * @var mysqli koneksi database
     */
    protected $conn;

    /**
     * konstruktor controller
     *
     * @param mysqli|null $conn koneksi database
     */
    public function __construct($conn = null)
    {
        $this->conn = $conn;
    }

    /**
     * helper untuk memvalidasi request ajax
     *
     * @return bool true jika request ajax, false jika tidak
     */
    protected function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }

    /**
     * mengirim respon json dan menghentikan eksekusi
     *
     * @param array $data data respon
     * @return void
     */
    protected function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        if ($this->conn) {
            $this->conn->close();
        }
        exit;
    }
}
