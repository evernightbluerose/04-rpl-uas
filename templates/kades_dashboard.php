<?php
/**
 * template: templates/kades_dashboard.php
 * dipanggil oleh kades.php
 * variabel: $total_pegawai, $stats, $belum_hadir, $result
 *
 * @package sistemkehadiran\templates
 */
?>
<div class="container">

    <!-- judul halaman -->
    <div class="page-welcome fade-in">
        <h2><i class="bi bi-clipboard-data"></i> Laporan Kehadiran</h2>
        <p>Pantau riwayat presensi seluruh pegawai desa.</p>
    </div>

    <!-- statistik ringkasan hari ini -->
    <div class="row g-3 mb-4">

        <div class="col-md-3 col-6 fade-in fade-in-delay-1">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
                <div class="stat-info">
                    <h3><?= (int) $total_pegawai ?></h3>
                    <p>Total Pegawai</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 fade-in fade-in-delay-2">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-info">
                    <h3><?= (int) ($stats['total_tepat'] ?? 0) ?></h3>
                    <p>Tepat Waktu</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 fade-in fade-in-delay-3">
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="bi bi-clock-history"></i></div>
                <div class="stat-info">
                    <h3><?= (int) ($stats['total_terlambat'] ?? 0) ?></h3>
                    <p>Terlambat</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 fade-in fade-in-delay-4">
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-person-x-fill"></i></div>
                <div class="stat-info">
                    <h3><?= (int) $belum_hadir ?></h3>
                    <p>Belum Hadir</p>
                </div>
            </div>
        </div>

    </div>

    <!-- qr code absensi global -->
    <div class="row mb-4 fade-in fade-in-delay-4">
        <div class="col-md-12">
            <div class="card card-elevated h-100">
                <div class="card-body p-4 text-center">
                    <h3 class="section-title"><i class="bi bi-qr-code-scan"></i> QR Absensi Global</h3>
                    <p class="text-muted">Arahkan pegawai untuk scan QR ini saat masuk/pulang.</p>
                    
                    <div id="qr-container" style="background:#fff; padding:1rem; display:inline-block; border-radius:12px; margin:1rem 0;">
                        <div id="qrcode"></div>
                    </div>
                    
                    <div class="text-sm text-muted mb-2">
                        <i class="bi bi-arrow-repeat spin-icon"></i> Diperbarui otomatis setiap <?= $settings['qr_refresh_duration'] ?? 30 ?> detik
                    </div>
                    <button class="btn btn-sm btn-theme" onclick="if(typeof window.fetchNewQR==='function'){window.fetchNewQR();this.innerHTML='<i class=\'bi bi-check\'></i> Diperbarui!';var b=this;setTimeout(function(){b.innerHTML='<i class=\'bi bi-arrow-clockwise\'></i> Refresh Manual';},1500);}" style="font-size:0.85rem;">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Manual
                    </button>
                    <a href="absen_standalone.php" target="_blank" class="btn btn-sm btn-outline-primary ms-1" style="font-size:0.85rem;">
                        <i class="bi bi-box-arrow-up-right"></i> Layar Penuh
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- tabel riwayat presensi -->
    <div class="card card-elevated fade-in fade-in-delay-4" id="card-riwayat">
        <div class="card-body p-4">

            <div class="section-header mb-3">
                <div>
                    <h3 class="section-title mb-1">
                        <i class="bi bi-calendar-check-fill"></i> Riwayat Presensi
                    </h3>
                    <p class="text-muted text-sm mb-0">Riwayat presensi seluruh pegawai — maks 50 entri per halaman.</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <form method="GET" action="export_laporan.php" class="d-flex align-items-center gap-1 mb-0 no-ajax">
                        <input type="date" name="start_date" class="form-control form-control-sm" style="max-width:128px;" title="Dari">
                        <input type="date" name="end_date"   class="form-control form-control-sm" style="max-width:128px;" title="Sampai">
                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-spreadsheet-fill"></i> Export</button>
                    </form>
                    <div class="d-flex gap-1" role="group">
                        <button id="btn-log-list-k" class="btn btn-sm btn-primary" onclick="switchLogViewKades('list')">
                            <i class="bi bi-list-ul"></i> Daftar
                        </button>
                        <button id="btn-log-cal-k" class="btn btn-sm btn-outline-primary" onclick="switchLogViewKades('calendar')">
                            <i class="bi bi-calendar3"></i> Kalender
                        </button>
                    </div>
                </div>
            </div>

            <!-- list view -->
            <div id="log-view-list-k">
            <?php if ($result->num_rows > 0): ?>
            <div class="table-container">
                <table class="table table-hover mb-0" id="table-riwayat">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>Nama Pegawai</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Status</th>
                            <th>Metode &amp; Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
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

            <!-- paginasi riwayat presensi -->
            <?= render_pagination($total_presensi_pages, $page_presensi, ['page_pegawai' => $page_pegawai], 'page') ?>

            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>Belum ada data presensi yang tercatat.</p>
            </div>
            <?php endif; ?>
            </div><!-- /#log-view-list-k -->

            <!-- calendar view (dimuat saat pertama kali diklik) -->
            <div id="log-view-cal-k" style="display:none;">
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
            </div><!-- /#log-view-cal-k -->

        </div>
    </div>

    <!-- tabel daftar pegawai (read-only untuk kades) -->
    <div class="card card-elevated fade-in fade-in-delay-4 mt-4" id="card-daftar-pegawai">
        <div class="card-body p-4">

            <div class="section-header mb-3">
                <div>
                    <h3 class="section-title mb-1">
                        <i class="bi bi-people-fill"></i> Daftar Pegawai Desa
                    </h3>
                    <p class="text-muted text-sm mb-0">Data seluruh pegawai desa.</p>
                </div>
                <span class="badge bg-secondary">Maks 50 Entri per Halaman</span>
            </div>

            <!-- form pencarian -->
            <form method="GET" action="" class="mb-4 no-ajax">
                <div class="input-group">
                    <span class="input-group-text bg-elevated border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="cari" class="form-control border-start-0 ps-0"
                           placeholder="Cari nama atau NIP pegawai..."
                           value="<?= e($_GET['cari'] ?? '') ?>">
                    <button class="btn btn-primary px-4" type="submit">Cari</button>
                </div>
            </form>

            <?php if ($result_pegawai->num_rows > 0): ?>
            <div class="table-container">
                <table class="table table-hover mb-0" id="table-pegawai-kades">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>NIP</th>
                            <th>Nama Pegawai</th>
                            <th>Jabatan</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1 + $offset_pegawai; while ($row = $result_pegawai->fetch_assoc()): ?>
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
                                    <div class="fw-semibold"><?= e($row['nama']) ?></div>
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
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- paginasi daftar pegawai -->
            <?= render_pagination($total_pegawai_pages, $page_pegawai, ['page' => $page_presensi], 'page_pegawai') ?>

            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p>Belum ada data pegawai.</p>
            </div>
            <?php endif; ?>

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
// tampilkan foto selfie pada modal overlay tanpa membuka tab baru
function showSelfie(src) {
    document.getElementById('modal-selfie-img').src = src;
    document.getElementById('modal-selfie').style.display = 'flex';
}
// tutup modal saat klik area luar gambar
document.getElementById('modal-selfie').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

// switch antara tampilan daftar dan kalender pada log kehadiran kades
function switchLogViewKades(view) {
    var lv = document.getElementById('log-view-list-k');
    var cv = document.getElementById('log-view-cal-k');
    var bl = document.getElementById('btn-log-list-k');
    var bc = document.getElementById('btn-log-cal-k');
    if (!lv || !cv) return;
    if (view === 'calendar') {
        lv.style.display = 'none';
        cv.style.display = 'block';
        bl.className = 'btn btn-sm btn-outline-primary';
        bc.className = 'btn btn-sm btn-primary';
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

