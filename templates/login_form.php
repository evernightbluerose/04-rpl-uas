<?php
/**
 * template: templates/login_form.php
 * dipanggil oleh login.php
 * variabel yang tersedia: $pesan, $pesan_type
 *
 * @package sistemkehadiran\templates
 */

// bawa $global_settings dari scope global agar bisa diakses
// saat file ini di-require dari dalam method controller (class scope)
global $global_settings;
?>

<!-- wrapper memusatkan kartu login secara vertikal & horizontal -->
<div class="login-wrapper">
<div class="login-card fade-in">

    <!-- bagian atas: ikon, judul, subjudul -->
    <div class="login-header">
        <div class="login-icon">
            <?php if (!empty($global_settings['app_logo'])): ?>
                <img src="<?= e($global_settings['app_logo']) ?>" alt="Logo" style="width:48px; height:48px; object-fit:contain;">
            <?php else: ?>
                <i class="bi bi-building-check"></i>
            <?php endif; ?>
        </div>
        <h1><?= e($global_settings['app_name'] ?? 'Presensi Desa') ?></h1>
        <p>Masuk untuk mengakses dashboard</p>
    </div>

    <!-- notifikasi error/sukses dari proses_login.php -->
    <?php if ($pesan): ?>
        <div class="alert alert-<?= $pesan_type ?> fade-in" role="alert" id="alert-login">
            <i class="bi bi-<?= $pesan_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= $pesan ?>
        </div>
    <?php endif; ?>

    <?php if ($session_conflict && !empty($conflict_token)): ?>
    <!-- panel peringatan: sesi aktif terdeteksi di perangkat lain -->
    <div class="alert alert-warning fade-in mb-4" role="alert" style="border-left: 4px solid #f59e0b;">
        <div class="d-flex align-items-start gap-2 mb-2">
            <i class="bi bi-shield-exclamation" style="font-size:1.4rem; color:#f59e0b; flex-shrink:0; margin-top:2px;"></i>
            <div>
                <strong>Sesi Aktif Terdeteksi</strong>
                <p class="mb-0 mt-1" style="font-size:0.9rem;">
                    Akun ini sedang aktif di perangkat lain. Melanjutkan login akan
                    <strong>menginvalidasi sesi tersebut</strong> secara otomatis.
                </p>
            </div>
        </div>
        <form method="POST" action="proses_login.php" class="no-ajax mt-3" id="form-force-login" autocomplete="off">
            <?= csrf_field() ?>
            <!-- username dan password diambil dari session pending, tidak perlu diisi ulang -->
            <input type="hidden" name="username"       value="<?= e($_SESSION['pending_username'] ?? '') ?>">
            <input type="hidden" name="password"       value="">
            <input type="hidden" name="force_login"    value="1">
            <input type="hidden" name="conflict_token" value="<?= e($conflict_token) ?>">
            <!-- password diisi via js dari field utama di bawah atau dari input tersembunyi ini -->
            <div class="mb-3">
                <label class="form-label" style="font-size:0.85rem;">Konfirmasi Password</label>
                <div class="input-icon-wrapper">
                    <input type="password" name="password_confirm_force" id="input-force-password"
                           class="form-control" placeholder="Masukkan password untuk konfirmasi"
                           required maxlength="255">
                    <i class="bi bi-lock input-icon"></i>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="login.php" class="btn btn-ghost flex-fill">Batal</a>
                <button type="submit" class="btn btn-warning flex-fill" id="btn-force-login"
                        onclick="syncForcePassword()">
                    <i class="bi bi-box-arrow-in-right"></i> Lanjutkan & Hapus Sesi Lama
                </button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <!-- form login dikirim ke proses_login.php via post -->
    <form action="proses_login.php" method="POST"
          class="login-form no-ajax" id="form-login" autocomplete="off">

        <!-- token csrf tersembunyi wajib ada di setiap form post -->
        <?= csrf_field() ?>

        <!-- field username -->
        <div class="form-group">
            <label for="input-username" class="form-label">Username</label>
            <div class="input-icon-wrapper">
                <input
                    type="text"
                    name="username"
                    id="input-username"
                    class="form-control"
                    placeholder="Masukkan username"
                    required
                    maxlength="50"
                    pattern="[a-zA-Z0-9_]+"
                    title="Hanya huruf, angka, dan underscore"
                    autofocus
                >
                <i class="bi bi-person input-icon"></i>
            </div>
        </div>

        <!-- field password -->
        <div class="form-group">
            <label for="input-password" class="form-label">Password</label>
            <div class="input-icon-wrapper">
                <input
                    type="password"
                    name="password"
                    id="input-password"
                    class="form-control"
                    placeholder="Masukkan password"
                    required
                    maxlength="255"
                >
                <i class="bi bi-lock input-icon"></i>
            </div>
        </div>

        <!-- tombol submit -->
        <button type="submit" class="btn btn-primary btn-login" id="btn-login">
            <i class="bi bi-box-arrow-in-right"></i> Masuk
        </button>

    </form>
    <?php endif; ?>

    <!-- tautan bantuan -->
    <div class="login-help text-center mt-3" style="font-size: 0.9rem;">
        <a href="#" onclick="showHelpModal(); return false;" class="text-decoration-none">Lupa sandi?</a> | 
        <a href="#" onclick="showHelpModal(); return false;" class="text-decoration-none">Belum punya akun?</a>
    </div>

</div>
</div>


<!-- modal bantuan login -->
<div id="modal-help-login" class="modal-backdrop" style="display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="modal-box" style="text-align:center; max-width:400px; width:100%; padding: 2rem;">
        <i class="bi bi-info-circle" style="font-size:3rem; color:var(--primary);"></i>
        <h3 class="mt-3 mb-2">Pusat Bantuan</h3>
        <p class="text-muted text-sm mb-4">
            Untuk menjaga keamanan sistem, pembuatan akun baru atau pengaturan ulang kata sandi (reset password) hanya dapat dilakukan secara langsung oleh <strong>Admin Desa</strong> di kantor.
        </p>
        <p class="text-muted text-sm mb-4">
            Silakan temui petugas tata usaha di jam kerja dengan membawa kartu identitas Anda.
        </p>
        <button class="btn btn-primary w-100" onclick="document.getElementById('modal-help-login').style.display='none'">Mengerti</button>
    </div>
</div>

<script>
function showHelpModal() {
    document.getElementById('modal-help-login').style.display = 'flex';
}

// menyinkronkan nilai dari input konfirmasi force-login ke field password hidden
// agar password yang dikirim ke server adalah nilai dari input konfirmasi
function syncForcePassword() {
    var forcePass = document.getElementById('input-force-password');
    var form      = document.getElementById('form-force-login');
    if (!forcePass || !form) return;
    var hiddenPass = form.querySelector('input[name="password"]');
    if (hiddenPass) {
        hiddenPass.value = forcePass.value;
    }
}
</script>
