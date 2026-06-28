<?php
/**
 * utilitas otentikasi, csrf, dan sanitasi output
 *
 * menyediakan fungsi keamanan dasar di seluruh aplikasi
 *
 * @package sistemkehadiran
 */


/**
 * memulai session php dengan konfigurasi keamanan ketat
 *
 * @return void
 */
function init_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // pastikan cookie session hanya bisa diakses via http
        ini_set('session.cookie_httponly', '1');
        // cegah pengiriman cookie lintas situs
        ini_set('session.cookie_samesite', 'Strict');
        // tolak session id yang tidak dikenal oleh server
        ini_set('session.use_strict_mode', '1');
        // hanya gunakan cookie, bukan url parameter
        ini_set('session.use_only_cookies', '1');

        // perpanjang masa hidup sesi agar login persist selamanya (~10 tahun)
        // gc_maxlifetime: mencegah garbage collector menghapus data sesi di server
        ini_set('session.gc_maxlifetime', '315360000');
        // cookie_lifetime: membuat cookie bertahan di browser meskipun ditutup
        ini_set('session.cookie_lifetime', '315360000');

        session_start();
    }
}


/**
 * mengambil token csrf dari session, atau membuat yang baru jika belum ada
 *
 * @return string token csrf format hex 64 karakter
 */
function csrf_token(): string
{
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes menghasilkan data acak yang aman secara kriptografi
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


/**
 * menghasilkan hidden input html berisi token csrf
 *
 * @return string tag input html
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}


/**
 * memvalidasi token csrf yang dikirim lewat form
 *
 * @param string $token token yang diterima
 * @return bool true jika valid, false jika tidak
 */
function verify_csrf(string $token): bool
{
    init_session();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    // perbandingan string yang aman dari timing attack
    return hash_equals($_SESSION['csrf_token'], $token);
}


/**
 * sanitasi nilai string untuk output html untuk mencegah xss
 *
 * @param string $value nilai yang akan di-escape
 * @return string nilai yang aman untuk ditampilkan
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


/**
 * merender navigasi paginasi dengan bootstrap 5
 *
 * @param int $total_pages total halaman
 * @param int $current_page halaman aktif
 * @param array $url_params parameter url yang ingin dipertahankan
 * @param string $page_key kunci parameter halaman
 * @return string html paginasi
 */
function render_pagination(int $total_pages, int $current_page, array $url_params = [], string $page_key = 'page'): string
{
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation" class="mt-3"><ul class="pagination justify-content-center">';
    
    // tombol previous
    $prev_page = max(1, $current_page - 1);
    $params = array_merge($url_params, [$page_key => $prev_page]);
    $query = http_build_query($params);
    $disabled = ($current_page == 1) ? 'disabled' : '';
    $html .= '<li class="page-item ' . $disabled . '"><a class="page-link" href="?' . $query . '">&laquo;</a></li>';
    
    // angka halaman
    $visible_pages = [];
    if ($total_pages <= 7) {
        for ($i = 1; $i <= $total_pages; $i++) $visible_pages[] = $i;
    } else {
        if ($current_page <= 4) {
            $visible_pages = [1, 2, 3, 4, 5, '...', $total_pages];
        } elseif ($current_page >= $total_pages - 3) {
            $visible_pages = [1, '...', $total_pages - 4, $total_pages - 3, $total_pages - 2, $total_pages - 1, $total_pages];
        } else {
            $visible_pages = [1, '...', $current_page - 1, $current_page, $current_page + 1, '...', $total_pages];
        }
    }

    foreach ($visible_pages as $i) {
        if ($i === '...') {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        } else {
            $params = array_merge($url_params, [$page_key => $i]);
            $query = http_build_query($params);
            $active = ($current_page == $i) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="?' . $query . '">' . $i . '</a></li>';
        }
    }
    
    // tombol next
    $next_page = min($total_pages, $current_page + 1);
    $params = array_merge($url_params, [$page_key => $next_page]);
    $query = http_build_query($params);
    $disabled = ($current_page == $total_pages) ? 'disabled' : '';
    $html .= '<li class="page-item ' . $disabled . '"><a class="page-link" href="?' . $query . '">&raquo;</a></li>';
    
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * meresize dan mengonversi gambar base64 ke jpeg base64 data-uri
 *
 * @param string $base64_string data-uri base64 gambar
 * @param int $max_width lebar maksimum hasil
 * @param int $max_height tinggi maksimum hasil
 * @param int $quality kualitas kompresi jpeg (0-100)
 * @return string data-uri jpeg base64 hasil resize
 */
function resize_image_base64(string $base64_string, int $max_width = 300, int $max_height = 300, int $quality = 75): string
{
    if (empty($base64_string)) {
        return '';
    }

    // ambil tipe dan data dari string base64
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64_string, $matches)) {
        return $base64_string;
    }

    // jika ekstensi GD tidak aktif di server, kembalikan gambar asli tanpa diproses
    if (!function_exists('imagecreatefromstring')) {
        return $base64_string;
    }

    $data = substr($base64_string, strpos($base64_string, ',') + 1);
    $data = base64_decode(str_replace(' ', '+', $data));
    if ($data === false) {
        return $base64_string;
    }

    // buat gambar dari string binary data
    $src_img = @imagecreatefromstring($data);
    if (!$src_img) {
        return $base64_string;
    }

    $width = imagesx($src_img);
    $height = imagesy($src_img);

    // hitung rasio aspek untuk mempertahankan proporsi
    $ratio = min($max_width / $width, $max_height / $height);
    if ($ratio < 1) {
        $new_width = (int)($width * $ratio);
        $new_height = (int)($height * $ratio);
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    // buat canvas baru
    $dst_img = imagecreatetruecolor($new_width, $new_height);
    imagealphablending($dst_img, false);
    imagesavealpha($dst_img, true);

    // salin dan ubah ukuran gambar
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // simpan ke output buffering sebagai jpeg
    ob_start();
    imagejpeg($dst_img, null, $quality);
    $jpeg_data = ob_get_clean();

    // bersihkan memori
    imagedestroy($src_img);
    imagedestroy($dst_img);

    return 'data:image/jpeg;base64,' . base64_encode($jpeg_data);
}

