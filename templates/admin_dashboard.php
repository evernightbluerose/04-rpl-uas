<?php
/**
 * template: templates/admin_dashboard.php
 * dipanggil oleh controllers/admincontroller.php
 * variabel: $pesan, $pesan_type, $total_pegawai, $total_hadir,
 *           $total_terlambat, $search, $result, $result_pres,
 *           $settings, $page_pegawai, $page_presensi, $offset_presensi,
 *           $total_pegawai_pages, $total_presensi_pages
 *
 * @package sistemkehadiran\templates
 */
?>
<div class="container">

    <!-- judul halaman -->
    <div class="page-welcome fade-in">
        <h2><i class="bi bi-speedometer2"></i> Dasbor Admin</h2>
        <p>Kelola data pegawai dan pantau kehadiran hari ini.</p>
    </div>

    <!-- flash message hasil operasi crud -->
    <?php if ($pesan): ?>
        <div class="alert alert-<?= $pesan_type ?> fade-in" role="alert">
            <?php
            $icon = match($pesan_type) {
                'success' => 'check-circle',
                'danger'  => 'exclamation-triangle',
                default   => 'info-circle',
            };
            ?>
            <i class="bi bi-<?= $icon ?>"></i> <?= $pesan ?>
        </div>
    <?php endif; ?>

    <!-- statistik ringkasan -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 fade-in fade-in-delay-1">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
                <div class="stat-info"><h3><?= (int)$total_pegawai ?></h3><p>Total Pegawai</p></div>
            </div>
        </div>
        <div class="col-md-4 fade-in fade-in-delay-2">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-info"><h3><?= (int)$total_hadir ?></h3><p>Hadir Hari Ini</p></div>
            </div>
        </div>
        <div class="col-md-4 fade-in fade-in-delay-3">
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-clock-history"></i></div>
                <div class="stat-info"><h3><?= (int)$total_terlambat ?></h3><p>Terlambat Hari Ini</p></div>
            </div>
        </div>
    </div>

    <!-- fitur keamanan presensi: qr code + pengaturan -->
    <form method="POST" class="no-ajax" id="form-settings" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_settings">
        
        <div class="row g-3 mb-4">
            <!-- qr code generator global + keamanan moduler -->
            <div class="col-md-6 fade-in fade-in-delay-3">
                <div class="card card-elevated h-100">
                    <div class="card-body p-4 text-center">
                        <h3 class="section-title"><i class="bi bi-qr-code-scan"></i> QR Absensi Global</h3>
                        <p class="text-muted">Arahkan pegawai untuk scan QR ini saat masuk/pulang.</p>
                        <div id="qr-container" style="background:#fff;padding:1rem;display:inline-block;border-radius:12px;margin:1rem 0;">
                            <div id="qrcode"></div>
                        </div>
                        <div class="text-sm text-muted mb-2">
                            <i class="bi bi-arrow-repeat spin-icon"></i> Diperbarui otomatis setiap <?= $settings['qr_refresh_duration'] ?? 30 ?> detik
                        </div>
                        <button type="button" class="btn btn-sm btn-theme" onclick="if(typeof window.fetchNewQR==='function'){window.fetchNewQR();this.innerHTML='<i class=\'bi bi-check\'></i> Diperbarui!';var b=this;setTimeout(function(){b.innerHTML='<i class=\'bi bi-arrow-clockwise\'></i> Refresh Manual';},1500);}" style="font-size:0.85rem;">
                            <i class="bi bi-arrow-clockwise"></i> Refresh Manual
                        </button>
                        <a href="absen_standalone.php" target="_blank" class="btn btn-sm btn-outline-primary ms-1" style="font-size:0.85rem;">
                            <i class="bi bi-box-arrow-up-right"></i> Layar Penuh
                        </a>
                        
                        <hr class="my-4">
                        
                        <div class="text-start">
                            <h3 class="section-title"><i class="bi bi-shield-lock"></i> Pengaturan Keamanan Moduler</h3>
                            <p class="text-muted mb-4">Hidupkan atau matikan lapisan keamanan absensi.</p>

                            <div class="form-check form-switch mb-3" style="font-size:1.1rem;">
                                <input class="form-check-input" type="checkbox" role="switch" id="setting_qr" name="qr_login"
                                       <?= !empty($settings['qr_login']) ? 'checked' : '' ?> onchange="togglePCSettings()">
                                <label class="form-check-label" for="setting_qr"><strong>QR Login</strong> (Kamera Belakang)</label>
                            </div>
                            <div class="form-check form-switch mb-3" style="font-size:1.1rem;">
                                <input class="form-check-input" type="checkbox" role="switch" id="setting_selfie" name="selfie_validation"
                                       <?= !empty($settings['selfie_validation']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="setting_selfie"><strong>Selfie Validation</strong> (Kamera Depan)</label>
                            </div>
                            <div class="form-check form-switch mb-3" style="font-size:1.1rem;">
                                <input class="form-check-input" type="checkbox" role="switch" id="setting_loc" name="location_logging"
                                       <?= !empty($settings['location_logging']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="setting_loc"><strong>Location Logging</strong> (GPS)</label>
                            </div>
                            <div class="form-check form-switch mb-3" style="font-size:1.1rem;">
                                <input class="form-check-input" type="checkbox" role="switch" id="setting_wa" name="webauthn"
                                       <?= !empty($settings['webauthn']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="setting_wa"><strong>WebAuthn FIDO2</strong> (Biometrik/Device Key)</label>
                            </div>
                            <div class="form-check form-switch mb-3" style="font-size:1.1rem;">
                                <input class="form-check-input" type="checkbox" role="switch" id="setting_allow_pc" name="allow_pc_attendance"
                                       <?= !isset($settings['allow_pc_attendance']) || !empty($settings['allow_pc_attendance']) ? 'checked' : '' ?>
                                       onchange="togglePCSettings()">
                                <label class="form-check-label" for="setting_allow_pc"><strong>Allow PC Attendance</strong> (Izinkan absen dari PC/Laptop)</label>
                            </div>
                            <div id="nested_pc_settings" style="margin-left:1.5rem;display:<?= (!isset($settings['allow_pc_attendance']) || !empty($settings['allow_pc_attendance'])) && !empty($settings['qr_login']) ? 'block' : 'none' ?>;">
                                <div class="form-check form-switch mb-3" style="font-size:1.1rem;">
                                    <input class="form-check-input" type="checkbox" role="switch" id="setting_allow_pc_qr" name="allow_pc_qr_scan"
                                           <?= !empty($settings['allow_pc_qr_scan']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="setting_allow_pc_qr"><strong>Enable PC QR Scanning</strong> (Izinkan scan QR dari PC/Laptop)</label>
                                </div>
                            </div>
                            
                            <div class="form-check form-switch mb-3" style="font-size:1.1rem;">
                                <input class="form-check-input" type="checkbox" role="switch" id="setting_skip_pulang" name="skip_keamanan_pulang"
                                       <?= !isset($settings['skip_keamanan_pulang']) || !empty($settings['skip_keamanan_pulang']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="setting_skip_pulang"><strong>Skip Keamanan Absen Pulang</strong> (Lewati verifikasi saat pulang)</label>
                            </div>

                            <div class="mb-2">
                                <label for="setting_qr_duration" class="form-label"><strong>Durasi Auto-Refresh QR Code (Detik)</strong></label>
                                <input type="number" class="form-control" id="setting_qr_duration" name="qr_refresh_duration"
                                       value="<?= $settings['qr_refresh_duration'] ?? 30 ?>" min="5" max="300">
                                <div class="form-text">Minimal 5 detik, maksimal 300 detik.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- pengaturan jadwal absensi dan tampilan -->
            <div class="col-md-6 fade-in fade-in-delay-4">
                <div class="card card-elevated h-100">
                    <div class="card-body p-4">
                        <h3 class="section-title"><i class="bi bi-clock-history"></i> Jadwal Absensi</h3>
                        <div class="mb-3">
                            <label for="setting_jam_masuk" class="form-label"><strong>Batas Jam Masuk (Tepat Waktu)</strong></label>
                            <input type="time" class="form-control" id="setting_jam_masuk" name="jam_masuk_batas"
                                   value="<?= e($settings['jam_masuk_batas'] ?? '07:00') ?>" required>
                            <div class="form-text">Presensi setelah jam ini akan dicatat sebagai "Terlambat".</div>
                        </div>
                        <div class="mb-4">
                            <label for="setting_jam_pulang" class="form-label"><strong>Mulai Jam Pulang</strong></label>
                            <input type="time" class="form-control" id="setting_jam_pulang" name="jam_pulang_mulai"
                                   value="<?= e($settings['jam_pulang_mulai'] ?? '16:00') ?>" required>
                            <div class="form-text">Acuan dimulainya jam pulang bagi pegawai.</div>
                        </div>

                        <hr>
                        <h4 class="mb-3 mt-4" style="font-size:1.2rem;"><i class="bi bi-palette"></i> Pengaturan Tampilan</h4>
                        <div class="mb-3">
                            <label for="setting_app_name" class="form-label"><strong>Nama Aplikasi</strong></label>
                            <input type="text" class="form-control" id="setting_app_name" name="app_name"
                                   value="<?= e($settings['app_name'] ?? 'Presensi Desa') ?>" placeholder="Contoh: Sistem Kehadiran" required>
                            <div class="form-text">Ditampilkan di navigasi atas dan halaman login.</div>
                        </div>
                        <div class="mb-3">
                            <label for="setting_app_footer_text" class="form-label"><strong>Teks Hak Cipta Footer</strong></label>
                            <input type="text" class="form-control" id="setting_app_footer_text" name="app_footer_text"
                                   value="<?= e($settings['app_footer_text'] ?? '&copy; ' . date('Y') . ' Sistem Kehadiran Desa') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="setting_app_footer_link" class="form-label"><strong>Domain / Teks Sub-Footer</strong></label>
                            <input type="text" class="form-control" id="setting_app_footer_link" name="app_footer_link"
                                   value="<?= e($settings['app_footer_link'] ?? 'rpl.iamsochronically.online') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="setting_max_attachment_size" class="form-label"><strong>Maksimal Ukuran Lampiran (MB)</strong></label>
                            <input type="number" class="form-control" id="setting_max_attachment_size" name="max_attachment_size"
                                   value="<?= e($settings['max_attachment_size'] ?? '2') ?>" min="1" max="50" required>
                            <div class="form-text">Maksimum ukuran berkas lampiran logbook dan pengajuan izin (dalam Megabyte).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label"><strong>Logo Aplikasi</strong></label>
                            <input type="file" class="form-control" accept="image/png, image/jpeg, image/webp" id="input_logo">
                            <input type="hidden" name="app_logo_base64" id="app_logo_base64">
                            <div class="form-text">Maksimal resolusi 128x128. Akan di-resize otomatis. Biarkan kosong jika tidak mengubah.</div>
                            <?php if(!empty($settings['app_logo'])): ?>
                                <div class="mt-2 text-center p-2" style="background:var(--bg-elevated); border:1px solid var(--border-color); border-radius:8px;">
                                    <img src="<?= e($settings['app_logo']) ?>" alt="Logo Saat Ini" style="max-height:48px; object-fit:contain;">
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-2">Simpan Pengaturan</button>
                    </div>
                </div>
            </div>
        </div>
    </form>


    <!-- tabel data pegawai -->
    <div class="card card-elevated fade-in fade-in-delay-4" id="card-pegawai">
        <div class="card-body p-4">

            <div class="section-header mb-3">
                <div>
                    <h3 class="section-title mb-1"><i class="bi bi-people-fill"></i> Data Pegawai</h3>
                    <p class="text-muted text-sm mb-0">Kelola seluruh data pegawai desa.</p>
                </div>
                <a href="tambah_pegawai.php" class="btn btn-success btn-sm" id="btn-tambah">
                    <i class="bi bi-plus-lg"></i> Tambah Pegawai
                </a>
            </div>

            <form method="GET" id="form-cari" role="search" class="no-ajax mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="cari" id="input-cari" class="form-control"
                           placeholder="Cari nama atau NIP pegawai..."
                           value="<?= e($search) ?>" maxlength="100" autocomplete="off">
                    <?php if ($search): ?>
                        <a href="admin.php" class="btn btn-outline-secondary" title="Hapus pencarian"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">Cari</button>
                </div>
            </form>

            <div id="dynamic-table-container">
            <?php if ($result->num_rows > 0): ?>
            <div class="table-container">
                <table class="table table-hover mb-0" id="table-pegawai">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>NIP</th>
                            <th>Nama Pegawai</th>
                            <th>Jabatan</th>
                            <th>Role</th>
                            <th style="width:190px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                            <td><code><?= e($row['nip']) ?></code></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($row['foto_profil'])): ?>
                                        <img src="<?= e($row['foto_profil']) ?>" alt="Foto" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color);">
                                    <?php else: ?>
                                        <div style="width:32px;height:32px;border-radius:50%;background:var(--bg-elevated);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;color:var(--text-secondary);">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="fw-semibold">
                                        <?= e($row['nama']) ?>
                                        <?php if (!empty($row['has_webauthn'])): ?>
                                            <span class="badge bg-success ms-1" style="font-size:0.65rem;" title="FIDO2/WebAuthn Aktif"><i class="bi bi-shield-check"></i> FIDO2</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted"><?= e($row['nama_jabatan'] ?? '-') ?></td>
                            <td>
                                <?php if (($row['role'] ?? '') === 'Admin'): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php elseif (($row['role'] ?? '') === 'Kades'): ?>
                                    <span class="badge bg-info">Kades</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pegawai</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="edit_pegawai.php?id=<?= (int)$row['id_pegawai'] ?>" class="btn btn-warning btn-sm" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                    <?php if (($row['role'] ?? '') !== 'Admin'): ?>
                                    <form method="POST" action="reset_password.php" class="d-inline"
                                          onsubmit="return confirm('Reset password <?= e($row['nama']) ?> ke 123456?')">
                                        <?= csrf_field() ?><input type="hidden" name="id_pegawai" value="<?= (int)$row['id_pegawai'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Reset password"><i class="bi bi-key"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (($row['role'] ?? '') !== 'Admin' && !empty($row['has_webauthn'])): ?>
                                    <form method="POST" action="reset_webauthn.php" class="d-inline"
                                          onsubmit="return confirm('Hapus WebAuthn <?= e($row['nama']) ?>?')">
                                        <?= csrf_field() ?><input type="hidden" name="id_pegawai" value="<?= (int)$row['id_pegawai'] ?>">
                                        <button type="submit" class="btn btn-dark btn-sm" title="Reset WebAuthn"><i class="bi bi-shield-slash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" action="hapus_pegawai.php" class="d-inline"
                                          onsubmit="return confirm('Hapus pegawai ini?')">
                                        <?= csrf_field() ?><input type="hidden" name="id_pegawai" value="<?= (int)$row['id_pegawai'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($total_pegawai_pages, $page_pegawai, ['search' => $search, 'page_presensi' => $page_presensi], 'page') ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-person-x"></i>
                <p><?= $search ? 'Tidak ada pegawai dengan kata kunci &ldquo;<strong>' . e($search) . '</strong>&rdquo;.' : 'Belum ada data pegawai. <a href="tambah_pegawai.php">Tambah sekarang</a>.' ?></p>
            </div>
            <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- log kehadiran pegawai dengan switch list / calendar -->
    <div class="card card-elevated fade-in fade-in-delay-4 mt-4" id="card-presensi">
        <div class="card-body p-4">

            <div class="section-header mb-3">
                <div>
                    <h3 class="section-title mb-1"><i class="bi bi-calendar-check-fill"></i> Log Kehadiran Pegawai</h3>
                    <p class="text-muted text-sm mb-0">Riwayat presensi seluruh pegawai — maks 50 entri per halaman.</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <form method="GET" action="export_laporan.php" class="d-flex align-items-center gap-1 mb-0 no-ajax">
                        <input type="date" name="start_date" class="form-control form-control-sm" style="max-width:128px;" title="Dari">
                        <input type="date" name="end_date"   class="form-control form-control-sm" style="max-width:128px;" title="Sampai">
                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-spreadsheet-fill"></i> Export</button>
                    </form>
                    <div class="d-flex gap-1" role="group">
                        <button id="btn-log-list" class="btn btn-sm btn-primary" onclick="switchLogView('list')" title="Tampilan Daftar">
                            <i class="bi bi-list-ul"></i> Daftar
                        </button>
                        <button id="btn-log-cal" class="btn btn-sm btn-outline-primary" onclick="switchLogView('calendar')" title="Tampilan Kalender">
                            <i class="bi bi-calendar3"></i> Kalender
                        </button>
                    </div>
                </div>
            </div>

            <!-- list view -->
            <div id="log-view-list">
            <?php if ($result_pres->num_rows > 0): ?>
            <div class="table-container">
                <table class="table table-hover mb-0" id="table-presensi-admin">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>Nama Pegawai</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Status</th>
                            <th>Metode &amp; Bukti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1 + $offset_presensi; while ($row = $result_pres->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                            <td class="fw-semibold"><?= e($row['nama']) ?></td>
                            <td class="text-nowrap"><?= e($row['tanggal']) ?></td>
                            <td class="text-nowrap fw-semibold"><?= e($row['jam_masuk'] ?: '-') ?></td>
                            <td class="text-nowrap">
                                <?php if ($row['jam_pulang']): ?>
                                    <?= e($row['jam_pulang']) ?>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Belum Pulang</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $cls = $row['status'] === 'Terlambat' ? 'bg-danger' : 'bg-success'; ?>
                                <span class="badge <?= $cls ?>"><?= e($row['status']) ?></span>
                                <?php if (!empty($row['status_pulang'])): ?>
                                    <span class="badge bg-secondary mt-1 d-block" style="font-size:0.65rem;">Pulang: <?= e($row['status_pulang']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-sm">
                                    <span class="text-muted"><?= e($row['metode_absen'] ?: 'Manual') ?></span>
                                    <div class="d-flex gap-1 mt-1 flex-wrap">
                                        <?php if ($row['foto_selfie']): ?>
                                            <button type="button" class="badge bg-secondary border-0" style="cursor:pointer;" title="Lihat Selfie"
                                                    onclick="showSelfie(this.dataset.src)" data-src="<?= e($row['foto_selfie']) ?>">
                                                <i class="bi bi-camera"></i> Selfie
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($row['lat'] && $row['lng']): ?>
                                            <a href="https://maps.google.com/?q=<?= e($row['lat']) ?>,<?= e($row['lng']) ?>"
                                               target="_blank" rel="noopener"
                                               class="badge bg-info text-decoration-none text-dark" title="Lihat GPS">
                                                <i class="bi bi-geo-alt"></i> GPS
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($total_presensi_pages, $page_presensi, ['search' => $search, 'page' => $page_pegawai], 'page_presensi') ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>Belum ada data kehadiran yang tercatat.</p>
            </div>
            <?php endif; ?>
            </div><!-- /#log-view-list -->

            <!-- calendar view (dimuat saat pertama kali diklik) -->
            <div id="log-view-cal" style="display:none;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <button class="btn btn-ghost btn-sm" onclick="calGlobalPrev()"><i class="bi bi-chevron-left"></i></button>
                    <span id="cal-global-label" class="fw-semibold" style="font-size:1rem;min-width:140px;text-align:center;"></span>
                    <button class="btn btn-ghost btn-sm" onclick="calGlobalNext()"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div id="cal-loading" style="display:none;align-items:center;justify-content:center;gap:0.5rem;padding:1.5rem;color:var(--text-secondary);">
                    <div class="spinner-border spinner-border-sm"></div> Memuat data kalender...
                </div>
                <div id="cal-global-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;"></div>
                <div class="d-flex gap-4 mt-3 flex-wrap" style="font-size:0.78rem;color:var(--text-secondary);">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:rgba(16,185,129,0.2);border:1px solid rgba(16,185,129,0.4);"></span> Ada kehadiran
                    </span>
                    <span style="display:flex;align-items:center;gap:6px;">
                        <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.35);"></span> Ada keterlambatan
                    </span>
                    <span style="display:flex;align-items:center;gap:6px;">
                        <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:rgba(148,163,184,0.06);border:1px solid var(--border-color);"></span> Tidak ada data
                    </span>
                </div>
            </div><!-- /#log-view-cal -->

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/partials/calendar_global.php'; ?>

<!-- modal preview selfie -->
<div id="modal-selfie" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.82);z-index:9999;align-items:center;justify-content:center;">
    <div style="position:relative;max-width:92vw;max-height:90vh;text-align:center;">
        <button onclick="document.getElementById('modal-selfie').style.display='none'"
                style="position:absolute;top:-2.2rem;right:0;background:none;border:none;color:#fff;font-size:1.6rem;cursor:pointer;">
            <i class="bi bi-x-circle-fill"></i>
        </button>
        <img id="modal-selfie-img" src="" alt="Selfie"
             style="max-width:100%;max-height:85vh;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,0.6);">
    </div>
</div>

<script>
// menampilkan foto selfie pada modal overlay
function showSelfie(src) {
    document.getElementById('modal-selfie-img').src = src;
    document.getElementById('modal-selfie').style.display = 'flex';
}
// tutup modal selfie saat klik di luar gambar
document.getElementById('modal-selfie').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

// toggle visibilitas pengaturan pc yang bersarang
function togglePCSettings() {
    var allowPC = document.getElementById('setting_allow_pc').checked;
    var qrLogin = document.getElementById('setting_qr').checked;
    var nested  = document.getElementById('nested_pc_settings');
    if (nested) nested.style.display = (allowPC && qrLogin) ? 'block' : 'none';
}

// switch antara tampilan daftar dan kalender pada log kehadiran
function switchLogView(view) {
    var lv = document.getElementById('log-view-list');
    var cv = document.getElementById('log-view-cal');
    var bl = document.getElementById('btn-log-list');
    var bc = document.getElementById('btn-log-cal');
    if (!lv || !cv) return;
    if (view === 'calendar') {
        lv.style.display = 'none';
        cv.style.display = 'block';
        bl.className = 'btn btn-sm btn-outline-primary';
        bc.className = 'btn btn-sm btn-primary';
        // fetch hanya saat pertama kali dibuka
        if (!cv.dataset.loaded) {
            fetchCalendarData(calGY, calGM, function() { renderCalGlobal(); });
            cv.dataset.loaded = '1';
        }
    } else {
        lv.style.display = 'block';
        cv.style.display = 'none';
        bl.className = 'btn btn-sm btn-primary';
        bc.className = 'btn btn-sm btn-outline-primary';
    }
}
</script>

<script>
// proses upload logo aplikasi secara live ke base64 (max resolusi 128x128)
document.addEventListener('DOMContentLoaded', function() {
    var inputLogo = document.getElementById('input_logo');
    if(inputLogo) {
        inputLogo.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if(!file) return;
            var reader = new FileReader();
            reader.onload = function(evt) {
                var img = new Image();
                img.onload = function() {
                    var max_size = 128;
                    var width = img.width;
                    var height = img.height;
                    if (width > height) {
                        if (width > max_size) {
                            height *= max_size / width;
                            width = max_size;
                        }
                    } else {
                        if (height > max_size) {
                            width *= max_size / height;
                            height = max_size;
                        }
                    }
                    var canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    document.getElementById('app_logo_base64').value = canvas.toDataURL('image/png');
                };
                img.src = evt.target.result;
            };
            reader.readAsDataURL(file);
        });
    }
});
</script>
