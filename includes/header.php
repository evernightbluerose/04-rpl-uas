<?php
/**
 * komponen header bersama semua halaman
 *
 * bertanggung jawab untuk:
 * - mengirim http security headers
 * - merender tag head html beserta meta tags
 * - me-inject css secara inline agar tidak diblokir proxy
 * - merender navbar jika user sudah login
 *
 * @package sistemkehadiran
 */

// bawa $global_settings dari scope global agar bisa diakses
// saat file ini di-require dari dalam method controller (class scope)
global $global_settings;

// mulai session jika belum aktif
init_session();

// security headers untuk mencegah mime sniffing dan clickjacking
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// melarang crawler mengindeks halaman ini via http header
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');

// nilai default jika halaman tidak mendefinisikan variabel ini
$page_title = $page_title ?? 'sistem kehadiran desa';
$page_role  = $page_role  ?? '';
$show_nav   = $show_nav   ?? true;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="sistem presensi kehadiran pegawai desa">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($page_title) ?></title>

    <!-- bootstrap css & icons dari cdn yang dipercaya -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- google fonts utama aplikasi -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- deteksi tema gelap/terang dijalankan sebelum render warna halaman -->
    <script>
    (function () {
        var html  = document.documentElement;
        var saved = localStorage.getItem('theme');
        var theme = 'dark';
        if (saved === 'light' || saved === 'dark') {
            theme = saved;
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            theme = 'light';
        }
        html.setAttribute('data-theme', theme);
        html.setAttribute('data-bs-theme', theme);
    })();

    // fungsi toggle tema dengan transisi smooth
    function toggleTheme() {
        var html    = document.documentElement;
        var current = html.getAttribute('data-theme') || 'dark';
        var next    = current === 'dark' ? 'light' : 'dark';

        // aktifkan transisi smooth sementara pada seluruh elemen
        html.classList.add('theme-transitioning');

        html.setAttribute('data-theme', next);
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);

        // perbarui ikon matahari/bulan
        var icon = document.getElementById('theme-icon');
        if (icon) icon.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';

        // hapus kelas transisi setelah animasi selesai agar tidak mengganggu interaksi normal
        setTimeout(function() {
            html.classList.remove('theme-transitioning');
        }, 500);
    }

    // sinkronkan ikon setelah dom selesai dimuat
    document.addEventListener('DOMContentLoaded', function () {
        var icon  = document.getElementById('theme-icon');
        var theme = document.documentElement.getAttribute('data-theme') || 'dark';
        if (icon) icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    });
    </script>

    <!-- css inline untuk kompatibilitas proxy -->
    <style>
<?php readfile(__DIR__ . '/../assets/css/style.css'); ?>
    </style>
</head>
<body>

<?php if ($show_nav && isset($_SESSION['role'])): ?>
    <!-- navbar utama hanya tampil saat sudah login -->
    <nav class="navbar navbar-expand-lg sticky-top" id="main-navbar">
        <div class="container">

            <!-- brand / logo -->
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <?php if (!empty($global_settings['app_logo'])): ?>
                    <img src="<?= e($global_settings['app_logo']) ?>" alt="Logo" style="width:28px; height:28px; object-fit:contain;">
                <?php else: ?>
                    <i class="bi bi-building-check"></i>
                <?php endif; ?>
                <span><?= e($global_settings['app_name'] ?? 'Presensi Desa') ?></span>
            </a>

            <!-- tombol burger untuk layar kecil -->
            <button class="navbar-toggler" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- menu utama navbar -->
            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav ms-auto align-items-center gap-2">

                    <!-- tautan utama -->
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page??'') === 'dashboard' ? 'active' : '' ?>" href="<?= $_SESSION['role'] === 'Admin' ? 'admin.php' : ($_SESSION['role'] === 'Kades' ? 'kades.php' : 'pegawai.php') ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page??'') === 'logbook.php' ? 'active' : '' ?>" href="logbook.php">
                            <i class="bi bi-journal-text"></i> Logbook
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page??'') === 'izin.php' ? 'active' : '' ?>" href="izin.php">
                            <i class="bi bi-calendar2-check"></i> Izin & Cuti
                        </a>
                    </li>

                    <!-- info pengguna login -->
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page??'') === 'profil.php' ? 'active' : '' ?> d-flex align-items-center gap-2" href="profil.php" title="Pengaturan Profil">
                            <?php if (!empty($_SESSION['foto_profil'])): ?>
                                <img src="<?= e($_SESSION['foto_profil']) ?>" alt="Foto" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nama']) ?>&background=random'">
                            <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                            <span><?= e($_SESSION['nama'] ?? 'Pengguna') ?></span>
                            <span class="role-badge"><?= e($_SESSION['role']) ?></span>
                        </a>
                    </li>

                    <!-- tombol toggle tema gelap/terang -->
                    <li class="nav-item">
                        <button class="btn btn-theme-toggle" onclick="toggleTheme()"
                                id="btn-theme-toggle" title="Ganti Tema" aria-label="Ganti tema">
                            <i id="theme-icon" class="bi bi-sun-fill"></i>
                        </button>
                    </li>

                    <!-- tombol keluar -->
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-logout" id="btn-logout">
                            <i class="bi bi-box-arrow-right"></i> Keluar
                        </a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>

<?php else: ?>
    <!-- tombol tema mengambang untuk halaman login tanpa navbar -->
    <button class="theme-toggle-float" onclick="toggleTheme()"
            id="btn-theme-toggle-float" title="Ganti Tema" aria-label="Ganti tema">
        <i id="theme-icon" class="bi bi-sun-fill"></i>
    </button>
<?php endif; ?>

<!-- area konten utama ditutup di footer.php -->
<main class="main-content">
