<?php
/**
 * router untuk php built-in server agar mereplikasi perilaku .htaccess
 *
 * digunakan saat menjalankan: php -S localhost:8000 router.php
 * meneruskan semua request non-file ke index.php untuk diproses front controller
 *
 * @package sistemkehadiran
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// blokir akses ke folder sensitif (sama seperti .htaccess)
$blocked_prefixes = ['/config/', '/includes/', '/controllers/', '/templates/', '/assets/css/'];
foreach ($blocked_prefixes as $prefix) {
    if (strpos($uri, $prefix) === 0) {
        http_response_code(403);
        echo '403 - Forbidden';
        return true;
    }
}

// sajikan file statis yang benar-benar ada di disk (css, js, dll.)
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // biarkan built-in server menangani file statis
}

// teruskan semua request lain ke front controller
require __DIR__ . '/index.php';
