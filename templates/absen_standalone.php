<?php
/**
 * template: templates/absen_standalone.php
 * tampilan layar qr dinamis untuk presensi
 *
 * @package sistemkehadiran\templates
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <!-- bootstrap css -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- google fonts utama aplikasi -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- deteksi tema gelap/terang -->
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

    // fungsi toggle tema
    function toggleTheme() {
        var html    = document.documentElement;
        var current = html.getAttribute('data-theme') || 'dark';
        var next    = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
        // perbarui ikon
        var icon = document.getElementById('theme-icon');
        if (icon) icon.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var icon  = document.getElementById('theme-icon');
        var theme = document.documentElement.getAttribute('data-theme') || 'dark';
        if (icon) icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    });
    </script>

    <!-- css inline untuk kompatibilitas proxy -->
    <style>
<?php readfile(__DIR__ . '/../assets/css/style.css'); ?>

        .main-container {
            flex: 1;
            padding: 2rem;
            display: flex;
            align-items: center;
        }

        .panel-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            height: 100%;
        }

        .clock-container {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: 2px;
            font-variant-numeric: tabular-nums;
            margin-bottom: 0.5rem;
        }

        #qrcode {
            background: #fff;
            padding: 1.5rem;
            display: inline-block;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        #qrcode:hover {
            transform: scale(1.02);
        }

        .spin-icon {
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        .guide-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }

        .guide-num {
            background-color: var(--accent-subtle);
            color: var(--accent);
            font-weight: 700;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
        }

        @media (max-width: 991.98px) {
            .main-container {
                padding: 1rem;
            }
            .panel-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- header / navbar pemerintah -->
    <nav class="navbar navbar-dark" id="main-navbar" style="padding: 0.75rem 1.5rem;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center text-decoration-none" href="#">
                <?php if(!empty($settings['app_logo'])): ?>
                    <img src="<?= e($settings['app_logo']) ?>" alt="Logo" class="me-2" style="height:32px; width:32px; object-fit:contain;">
                <?php else: ?>
                    <i class="bi bi-building-check me-2 fs-3"></i>
                <?php endif; ?>
                <div>
                    <span class="fw-bold d-block lh-1 text-uppercase"><?= e($settings['app_name'] ?? 'Presensi Desa') ?></span>
                    <small class="text-muted" style="font-size:0.75rem;">Sistem Kehadiran Perangkat Desa Resmi</small>
                </div>
            </a>
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-light btn-sm" onclick="window.close()"><i class="bi bi-x-circle me-1"></i> Tutup Layar</button>
            </div>
        </div>
    </nav>

    <!-- grid konten utama -->
    <div class="container-fluid main-container">
        <div class="row w-100 g-4">
            
            <!-- sisi kiri: waktu dan qr code -->
            <div class="col-lg-6 text-center">
                <div class="panel-card d-flex flex-column align-items-center justify-content-center py-5">
                    <div id="live-clock" class="clock-container">00:00:00</div>
                    <div id="live-date" class="date-container fw-semibold" style="color:var(--text-main); font-size: 1.35rem; margin-bottom: 1rem;">Memuat tanggal...</div>
                    
                    <!-- info batas waktu masuk/pulang -->
                    <div class="d-flex gap-4 justify-content-center my-3 p-3 w-100" style="max-width: 400px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: 12px;">
                        <div class="text-center">
                            <small class="text-muted d-block mb-1" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;"><i class="bi bi-box-arrow-in-right text-success"></i> Batas Masuk</small>
                            <span class="fw-bold text-success fs-5"><?= e(date('H:i', strtotime($settings['jam_masuk_batas'] ?? '07:30:00'))) ?> WIB</span>
                        </div>
                        <div style="border-left: 1px solid var(--border-color); height: 40px; align-self: center;"></div>
                        <div class="text-center">
                            <small class="text-muted d-block mb-1" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;"><i class="bi bi-box-arrow-right text-info"></i> Mulai Pulang</small>
                            <span class="fw-bold text-info fs-5"><?= e(date('H:i', strtotime($settings['jam_pulang_mulai'] ?? '16:00:00'))) ?> WIB</span>
                        </div>
                    </div>
                    
                    <div class="my-3">
                        <div id="qrcode"></div>
                    </div>
                    
                    <div class="refresh-text mt-3 text-muted small">
                        <i class="bi bi-arrow-repeat spin-icon text-info me-1"></i>
                        QR Code diperbarui otomatis setiap <span class="text-primary fw-bold"><?= (int)($settings['qr_refresh_duration'] ?? 30) ?></span> detik
                    </div>
                </div>
            </div>

            <!-- sisi kanan: presensi terbaru & panduan -->
            <div class="col-lg-6">
                <div class="row g-4 h-100">
                    
                    <!-- kanan atas: daftar log presensi terbaru -->
                    <div class="col-12 h-50">
                        <div class="panel-card d-flex flex-column">
                            <h4 class="mb-3 fw-bold d-flex align-items-center" style="color: var(--accent-green);">
                                <i class="bi bi-activity me-2"></i> Presensi Terbaru Hari Ini
                            </h4>
                            
                            <div class="flex-grow-1 overflow-auto">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Waktu</th>
                                            <th>Status</th>
                                            <th>Metode</th>
                                        </tr>
                                    </thead>
                                    <tbody id="latest-attendance-rows">
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Memuat data presensi terbaru...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- kanan bawah: panduan presensi cepat -->
                    <div class="col-12 h-50">
                        <div class="panel-card">
                            <h4 class="mb-4 fw-bold text-info d-flex align-items-center">
                                <i class="bi bi-info-circle me-2"></i> Panduan Presensi Cepat
                            </h4>
                            
                            <div class="guide-item">
                                <div class="guide-num">1</div>
                                <div class="guide-text">
                                    <strong>Buka HP Anda</strong><br>
                                    Silakan login ke dasbor pegawai melalui HP/Mobile Device.
                                </div>
                            </div>
                            
                            <div class="guide-item">
                                <div class="guide-num">2</div>
                                <div class="guide-text">
                                    <strong>Pilih Menu Absen</strong><br>
                                    Ketuk tombol <strong>Mulai Absen</strong> lalu selesaikan petunjuk verifikasi.
                                </div>
                            </div>
                            
                            <div class="guide-item">
                                <div class="guide-num">3</div>
                                <div class="guide-text">
                                    <strong>Pindai QR Code</strong><br>
                                    Arahkan kamera HP Anda ke QR code yang tertera pada layar ini. Sistem akan langsung mencatat kehadiran Anda.
                                </div>
                            </div>

                            <?php if (isset($settings['skip_keamanan_pulang']) && $settings['skip_keamanan_pulang'] == '0'): ?>
                            <div class="alert alert-warning mt-3 mb-0" style="font-size:0.85rem; padding:0.75rem;">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Perhatian:</strong> Proses <strong>Absen Pulang</strong> kini membutuhkan scan QR dan keamanan lainnya (sama seperti saat masuk).
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- tombol tema mengambang -->
    <button class="theme-toggle-float" onclick="toggleTheme()" title="Ganti Tema">
        <i id="theme-icon" class="bi bi-moon-fill"></i>
    </button>

    <!-- javascript dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. jam digital real-time
            function updateClock() {
                const clockEl = document.getElementById('live-clock');
                const dateEl = document.getElementById('live-date');
                if (!clockEl) return;
                
                const now = new Date();
                
                // format waktu hh:mm:ss
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                const ss = String(now.getSeconds()).padStart(2, '0');
                clockEl.textContent = `${hh}:${mm}:${ss}`;
                
                // format tanggal lokal indonesia
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                dateEl.textContent = now.toLocaleDateString('id-ID', options);
            }
            setInterval(updateClock, 1000);
            updateClock();

            // 2. pembuatan & pembaruan qr code dinamis
            const qrContainer = document.getElementById('qrcode');
            if (qrContainer) {
                let qrcode = new QRCode(qrContainer, {
                    width: 250,
                    height: 250,
                    colorDark : "#0b1329",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });

                function fetchNewQR() {
                    fetch('qr_generator.php', { credentials: 'same-origin' })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.token) {
                                qrcode.clear();
                                qrcode.makeCode(data.token);
                            }
                        })
                        .catch(err => console.error('Error fetching QR:', err));
                }

                const refreshDuration = <?= isset($settings['qr_refresh_duration']) ? (int)$settings['qr_refresh_duration'] : 30 ?> * 1000;
                fetchNewQR();
                setInterval(fetchNewQR, refreshDuration);
            }

            // 3. pembaruan log absensi terbaru via pooling
            const attendanceRows = document.getElementById('latest-attendance-rows');
            function fetchLatestAttendance() {
                fetch('api_latest_absen.php', { credentials: 'same-origin' })
                    .then(response => response.json())
                    .then(res => {
                        if (res.success && res.data) {
                            if (res.data.length === 0) {
                                attendanceRows.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4">Belum ada pegawai absen hari ini.</td></tr>`;
                                return;
                            }
                            
                            let html = '';
                            res.data.forEach(row => {
                                const statusCls = row.status === 'Tepat Waktu' ? 'bg-success' : 'bg-warning text-dark';
                                const jam = row.jam_pulang ? `${row.jam_masuk} - ${row.jam_pulang}` : row.jam_masuk;
                                html += `
                                    <tr>
                                        <td class="fw-bold text-primary">${escapeHTML(row.nama)}</td>
                                        <td class="font-monospace">${jam}</td>
                                        <td><span class="badge ${statusCls}">${escapeHTML(row.status)}</span></td>
                                        <td class="text-muted" style="font-size: 0.85rem;">${escapeHTML(row.metode_absen || 'Scan QR')}</td>
                                    </tr>
                                `;
                            });
                            attendanceRows.innerHTML = html;
                        }
                    })
                    .catch(err => console.error('Error fetching latest attendance:', err));
            }

            function escapeHTML(str) {
                if (!str) return '';
                return str.replace(/[&<>'"]/g, 
                    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
                );
            }

            // polling setiap 5 detik
            fetchLatestAttendance();
            setInterval(fetchLatestAttendance, 5000);
            
            // polling status pembaruan data real-time
            let lastUpdateVal = 0;
            function checkUpdates() {
                fetch('api_sync.php', { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success && d.last_update) {
                            if (lastUpdateVal !== 0 && d.last_update > lastUpdateVal) {
                                fetchLatestAttendance();
                                if (typeof fetchNewQR === 'function') {
                                    fetchNewQR();
                                }
                            }
                            lastUpdateVal = d.last_update;
                        }
                    })
                    .catch(err => {});
            }
            setInterval(checkUpdates, 2000);
        });
    </script>
</body>
</html>
