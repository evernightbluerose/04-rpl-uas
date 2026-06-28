<?php
/**
 * komponen footer bersama semua halaman
 *
 * menutup tag main dan merender footer serta memuat berkas js
 *
 * @package sistemkehadiran
 */

// bawa variabel global agar bisa diakses saat di-require dari dalam method controller
global $global_settings, $settings;
?>

</main><!-- /.main-content -->

<!-- footer halaman -->
<footer class="site-footer" id="site-footer">
    <div class="container">
        <div class="footer-content">

            <!-- nama aplikasi di kiri -->
            <div class="footer-brand">
                <i class="bi bi-building-check"></i>
                <span><?= e($global_settings['app_name'] ?? 'Sistem Presensi Desa') ?></span>
            </div>

            <!-- info hak cipta di kanan -->
            <div class="footer-info">
                <p><?= $global_settings['app_footer_text'] ?? ('&copy; ' . date('Y') . ' Sistem Kehadiran Desa') ?></p>
                <p class="footer-sub"><?= e($global_settings['app_footer_link'] ?? 'rpl.iamsochronically.online') ?></p>
            </div>

        </div>
    </div>
</footer>

<!-- bootstrap js dimuat di akhir body agar tidak memblokir render -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dynamic_core.js?v=<?= time() ?>"></script>

<!-- library qr code generator -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const qrContainer = document.getElementById('qrcode');
    if (qrContainer) {
        let qrcode = new QRCode(qrContainer, {
            width: 200,
            height: 200,
            colorDark : "#0f1726",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        window.fetchNewQR = function() {
            fetch('qr_generator.php', { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.token) {
                        qrcode.clear();
                        qrcode.makeCode(data.token);
                    }
                })
                .catch(err => console.error('error fetching qr:', err));
        };

        // ambil qr code segera lalu perbarui setiap beberapa detik
        var refreshDuration = <?= isset($settings['qr_refresh_duration']) ? (int)$settings['qr_refresh_duration'] : 30 ?> * 1000;
        window.fetchNewQR();
        setInterval(window.fetchNewQR, refreshDuration);
    }
});
</script>

</body>
</html>
