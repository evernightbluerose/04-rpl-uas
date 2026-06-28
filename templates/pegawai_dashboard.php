<?php
/**
 * template: templates/pegawai_dashboard.php
 * dipanggil oleh pegawai.php
 * variabel: $cek_presensi (array|null), $riwayat (mysqli_result)
 *
 * @package sistemkehadiran\templates
 */
?>
<div class="container">

    <!-- salam pembuka dengan nama pengguna -->
    <div class="page-welcome fade-in text-center">
        <h2>Selamat Datang, <?= e($_SESSION['nama']) ?> 👋</h2>
        <p><?= date('l, d F Y') ?></p>
    </div>

    <!-- kartu presensi -->
    <div class="attendance-card fade-in fade-in-delay-1 mb-4">

        <div class="time-display" id="time-display"><?= date('H:i') ?></div>
        <div class="date-display"><?= date('d F Y - l') ?></div>
        
        <div class="schedule-info text-muted mt-2 mb-3" style="font-size: 0.9rem; padding: 0.5rem; background: rgba(0,0,0,0.05); border-radius: 8px;">
            <div><i class="bi bi-box-arrow-in-right"></i> Jam Masuk: s/d <strong><?= e($settings['jam_masuk_batas'] ?? '07:00') ?></strong> WIB</div>
            <div><i class="bi bi-box-arrow-left"></i> Mulai Pulang: <strong><?= e($settings['jam_pulang_mulai'] ?? '16:00') ?></strong> WIB</div>
        </div>

        <?php 
            $active_sec = [];
            if (!empty($settings['qr_login'])) $active_sec[] = '<i class="bi bi-qr-code-scan"></i> QR';
            if (!empty($settings['selfie_validation'])) $active_sec[] = '<i class="bi bi-camera"></i> Selfie';
            if (!empty($settings['location_logging'])) $active_sec[] = '<i class="bi bi-geo-alt"></i> GPS';
            if (!empty($settings['webauthn'])) $active_sec[] = '<i class="bi bi-fingerprint"></i> Biometrik';
            $sec_html = implode(' &bull; ', $active_sec);
            $skip_pulang = isset($settings['skip_keamanan_pulang']) ? (int)$settings['skip_keamanan_pulang'] : 1;
        ?>

        <?php if (!$cek_presensi): ?>
            <!-- kondisi pegawai belum absen sama sekali hari ini -->
            <div class="status-indicator pending">
                <i class="bi bi-hourglass-split"></i> Belum absen hari ini
            </div>
            <button type="button" class="btn btn-success btn-lg w-100 mb-2" id="btn-absen-masuk" onclick="startSecurityWizard('absen_masuk')">
                <i class="bi bi-box-arrow-in-right"></i> Absen Masuk
            </button>
            <?php if (!empty($sec_html)): ?>
            <div class="text-center text-muted" style="font-size:0.8rem;">
                <i class="bi bi-shield-lock text-success me-1"></i> Keamanan aktif: <?= $sec_html ?>
            </div>
            <?php endif; ?>

        <?php elseif ($cek_presensi['jam_pulang'] === null): ?>
            <!-- kondisi sudah absen masuk belum absen pulang -->
            <div class="status-indicator checked-in">
                <i class="bi bi-check-lg"></i>
                Masuk pukul <?= e($cek_presensi['jam_masuk']) ?>
                <?php if ($cek_presensi['status'] === 'Terlambat'): ?>
                    - <span style="color: var(--danger);">Terlambat</span>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-warning btn-lg w-100 mb-2" id="btn-absen-pulang" onclick="<?= $skip_pulang ? 'absenPulangLangsung()' : "startSecurityWizard('absen_pulang')" ?>">
                <i class="bi bi-box-arrow-right"></i> Absen Pulang
            </button>
            <div class="text-center text-muted" style="font-size:0.8rem;">
                <?php if ($skip_pulang): ?>
                    <i class="bi bi-unlock text-warning me-1"></i> Keamanan absen pulang dilewati
                <?php elseif (!empty($sec_html)): ?>
                    <i class="bi bi-shield-lock text-success me-1"></i> Keamanan absen pulang: <?= $sec_html ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- kondisi presensi hari ini sudah lengkap -->
            <div class="status-indicator completed">
                <i class="bi bi-patch-check-fill"></i> Presensi hari ini selesai
            </div>
            <div class="d-flex justify-content-center gap-4 mt-2"
                 style="color: var(--text-secondary); font-size: 0.9rem;">
                <div>
                    <i class="bi bi-arrow-right-circle" style="color: var(--success);"></i>
                    Masuk: <strong><?= e($cek_presensi['jam_masuk']) ?></strong>
                </div>
                <div>
                    <i class="bi bi-arrow-left-circle" style="color: var(--warning);"></i>
                    Pulang: <strong><?= e($cek_presensi['jam_pulang']) ?></strong>
                </div>
            </div>

        <?php endif; ?>

        <!-- tautan bantuan kendala absensi -->
        <div class="text-center mt-3">
            <a href="#" onclick="document.getElementById('modal-troubleshoot').style.display='flex'; return false;" class="text-decoration-none text-muted" style="font-size:0.85rem;">
                <i class="bi bi-question-circle"></i> Tidak bisa absen? Klik di sini.
            </a>
        </div>

    </div>

    <!-- modal troubleshooting -->
    <div id="modal-troubleshoot" class="modal-backdrop" style="display:none; align-items:center; justify-content:center; z-index:9999;">
        <div class="modal-box" style="text-align:left; max-width:500px; width:100%; padding:2rem;">
            <h4 class="mb-3"><i class="bi bi-tools"></i> Panduan Kendala Absensi</h4>
            
            <div class="mb-3">
                <strong><i class="bi bi-phone"></i> Perangkat Hilang / Ganti Baru</strong>
                <p class="text-muted text-sm mb-0">Jika Anda menggunakan perangkat baru, sistem akan menolak sidik jari/kredensial lama Anda. Silakan lapor ke Admin di kantor untuk <strong>Reset WebAuthn</strong>.</p>
            </div>
            
            <div class="mb-3">
                <strong><i class="bi bi-geo-alt-fill"></i> Lokasi Tidak Terdeteksi</strong>
                <p class="text-muted text-sm mb-0">Pastikan fitur GPS / Lokasi diaktifkan di pengaturan HP Anda dan berikan izin lokasi pada browser.</p>
            </div>
            
            <div class="mb-4">
                <strong><i class="bi bi-camera"></i> Kamera / QR Gagal</strong>
                <p class="text-muted text-sm mb-0">Jika layar kamera hitam, pastikan izin kamera tidak diblokir oleh browser (cek ikon gembok di sebelah URL situs).</p>
            </div>

            <button class="btn btn-secondary w-100" onclick="document.getElementById('modal-troubleshoot').style.display='none'">Tutup Panduan</button>
        </div>
    </div>

    <!-- riwayat kehadiran dengan switch list / calendar -->
    <?php if (!empty($rows_for_list)): ?>
    <div class="card card-elevated fade-in fade-in-delay-2" id="card-riwayat">
        <div class="card-body p-4">

            <div class="section-header mb-3">
                <h3 class="section-title mb-0">
                    <i class="bi bi-clock-history"></i> Riwayat Kehadiran
                </h3>
                <!-- tombol switch view -->
                <div class="d-flex gap-1" role="group" aria-label="Pilih tampilan">
                    <button id="btn-view-list" class="btn btn-sm btn-primary" onclick="switchView('list')" title="Tampilan Daftar">
                        <i class="bi bi-list-ul"></i> Daftar
                    </button>
                    <button id="btn-view-cal" class="btn btn-sm btn-outline-primary" onclick="switchView('calendar')" title="Tampilan Kalender">
                        <i class="bi bi-calendar3"></i> Kalender
                    </button>
                </div>
            </div>

            <!-- list view -->
            <div id="view-list">
                <div class="table-container">
                    <table class="table table-hover mb-0" id="table-riwayat-pegawai">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Masuk</th>
                                <th>Pulang</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows_for_list as $r): ?>
                            <tr>
                                <td class="text-nowrap"><?= e($r['tanggal']) ?></td>
                                <td class="fw-semibold"><?= e($r['jam_masuk'] ?: '-') ?></td>
                                <td><?= e($r['jam_pulang'] ?: '-') ?></td>
                                <td>
                                    <?php $cls_masuk = $r['status'] === 'Terlambat' ? 'bg-danger' : 'bg-success'; ?>
                                    <span class="badge <?= $cls_masuk ?> mb-1">M: <?= e($r['status']) ?></span>
                                    <?php if ($r['status_pulang']): ?>
                                        <?php $cls_pulang = match($r['status_pulang']) {
                                            'Lebih Dulu' => 'bg-warning text-dark',
                                            'Otomatis'   => 'bg-secondary',
                                            default      => 'bg-success'
                                        }; ?>
                                        <br><span class="badge <?= $cls_pulang ?>">P: <?= e($r['status_pulang']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- calendar view -->
            <div id="view-calendar" style="display:none;">
                <!-- navigasi bulan kalender -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <button class="btn btn-ghost btn-sm" onclick="changeCalMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                    <span id="cal-month-label" class="fw-semibold" style="font-size:1.05rem;"></span>
                    <button class="btn btn-ghost btn-sm" onclick="changeCalMonth(1)"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div id="cal-pegawai" style="display:grid; grid-template-columns:repeat(7,1fr); gap:4px;"></div>
                <!-- legenda -->
                <div class="d-flex gap-3 mt-3 flex-wrap" style="font-size:0.8rem;">
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--bs-success);margin-right:4px;"></span>Tepat Waktu</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--bs-danger);margin-right:4px;"></span>Terlambat</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--bs-secondary);margin-right:4px;"></span>Tidak Hadir</span>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>


<!-- modal security wizard -->
<div id="modal-security-wizard" class="modal-backdrop" style="display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="modal-box" style="text-align:center; max-width:400px; width:100%;">
        <h3 style="margin-bottom:1rem;" id="wizard-title">Verifikasi Keamanan</h3>
        
        <!-- step 1: scan qr -->
        <div id="step-qr" style="display:none;">
            <p class="text-muted text-sm">Arahkan kamera ke QR Code di Dasbor Kades/Admin</p>
            <div id="qr-reader" style="width:100%; margin:0 auto 0.5rem; border-radius:8px; overflow:hidden;"></div>
            <select id="qr-camera-select" class="form-control mb-2" style="font-size:0.85rem; display:none;" onchange="switchQRCamera(this.value)">
            </select>
            <button class="btn btn-ghost" onclick="cancelWizard()">Batal</button>
        </div>

        <!-- step 2: capture selfie -->
        <div id="step-selfie" style="display:none;">
            <p class="text-muted text-sm">Ambil foto selfie untuk verifikasi wajah</p>
            <video id="selfie-video" autoplay playsinline style="width:100%; border-radius:8px; margin-bottom:1rem; background:#000;"></video>
            <canvas id="selfie-canvas" style="display:none;"></canvas>
            <div>
                <button class="btn btn-primary w-100 mb-2" id="btn-take-selfie" onclick="takeSelfie()">
                    <i class="bi bi-camera"></i> Ambil Foto
                </button>
                <button class="btn btn-ghost w-100" onclick="cancelWizard()">Batal</button>
            </div>
        </div>

        <!-- step 3: track location gps -->
        <div id="step-location" style="display:none;">
            <p class="text-muted text-sm">Mendapatkan titik koordinat lokasi Anda...</p>
            <div class="spinner-border text-primary my-3" role="status"></div>
        </div>

        <!-- step 4: webauthn biometric fido2 -->
        <div id="step-webauthn" style="display:none;">
            <i class="bi bi-fingerprint" style="font-size:3rem; color:var(--primary);"></i>
            <p class="text-muted text-sm mt-3">Gunakan sidik jari atau PIN perangkat Anda</p>
            <button class="btn btn-primary w-100 mt-2" onclick="triggerWebAuthn()">
                Lanjutkan
            </button>
        </div>

        <!-- step 5: loading submit absensi -->
        <div id="step-submitting" style="display:none;">
            <p class="text-muted text-sm">Memverifikasi data absensi...</p>
            <div class="spinner-border text-success my-3" role="status"></div>
        </div>
        
    </div>
</div>

<!-- passing data konfigurasi keamanan ke javascript -->
<script>
    window.APP_SETTINGS = {
        qr_login: <?= !empty($settings['qr_login']) ? 'true' : 'false' ?>,
        selfie_validation: <?= !empty($settings['selfie_validation']) ? 'true' : 'false' ?>,
        location_logging: <?= !empty($settings['location_logging']) ? 'true' : 'false' ?>,
        webauthn: <?= !empty($settings['webauthn']) ? 'true' : 'false' ?>,
        qr_refresh_duration: <?= isset($settings['qr_refresh_duration']) ? (int)$settings['qr_refresh_duration'] : 30 ?>,
        allow_pc_attendance: <?= !isset($settings['allow_pc_attendance']) || !empty($settings['allow_pc_attendance']) ? 'true' : 'false' ?>,
        allow_pc_qr_scan: <?= !empty($settings['allow_pc_qr_scan']) ? 'true' : 'false' ?>
    };
    window.CSRF_TOKEN = "<?= csrf_token() ?>";

    // data riwayat kehadiran untuk calendar view (dirender dari php)
    var PEGAWAI_CAL_DATA = <?= $calendar_json ?? '{}' ?>;
</script>

<!-- memuat pustaka qr scanner dan naskah alur presensi -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script src="assets/js/attendance_wizard.js?v=<?= time() ?>"></script>

<script>
// ===== calendar view pegawai =====
var calYear, calMonth;

// inisialisasi kalender ke bulan saat ini
(function () {
    var now = new Date();
    calYear  = now.getFullYear();
    calMonth = now.getMonth() + 1;
})();

// nama bulan dalam bahasa indonesia
var BULAN_ID = ['Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];
var HARI_ID  = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

// render sel kalender sesuai bulan & tahun aktif
function renderCalPegawai() {
    var label = document.getElementById('cal-month-label');
    if (label) label.textContent = BULAN_ID[calMonth - 1] + ' ' + calYear;

    var container = document.getElementById('cal-pegawai');
    if (!container) return;
    container.innerHTML = '';

    // header hari
    HARI_ID.forEach(function(h) {
        var el = document.createElement('div');
        el.textContent = h;
        el.style.cssText = 'text-align:center;font-size:0.75rem;font-weight:600;color:var(--text-secondary);padding:4px 0;';
        container.appendChild(el);
    });

    // hari pertama & jumlah hari dalam bulan
    var firstDay = new Date(calYear, calMonth - 1, 1).getDay(); // 0=minggu
    var daysInMonth = new Date(calYear, calMonth, 0).getDate();
    var todayStr = new Date().toISOString().slice(0, 10);

    // padding kosong sebelum hari pertama
    for (var i = 0; i < firstDay; i++) {
        container.appendChild(document.createElement('div'));
    }

    for (var d = 1; d <= daysInMonth; d++) {
        var tgl = calYear + '-' + String(calMonth).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        var data = PEGAWAI_CAL_DATA[tgl];

        var cell = document.createElement('div');
        cell.style.cssText = 'border-radius:8px;padding:6px 2px;text-align:center;cursor:default;transition:transform .15s;';
        cell.title = tgl;

        var dayNum = document.createElement('div');
        dayNum.textContent = d;
        dayNum.style.cssText = 'font-size:0.85rem;font-weight:600;';

        var dot = document.createElement('div');
        dot.style.cssText = 'height:6px;width:6px;border-radius:50%;margin:3px auto 0;';

        if (tgl === todayStr) {
            cell.style.border = '2px solid var(--primary)';
        }

        if (data) {
            var bg = data.status === 'Terlambat' ? 'rgba(220,53,69,0.15)' : 'rgba(25,135,84,0.13)';
            var dotColor = data.status === 'Terlambat' ? 'var(--bs-danger)' : 'var(--bs-success)';
            cell.style.background = bg;
            dot.style.background = dotColor;

            // tooltip info
            var info = data.jam_masuk ? 'Masuk: ' + data.jam_masuk : '';
            if (data.jam_pulang) info += '\nPulang: ' + data.jam_pulang;
            cell.title = tgl + (info ? '\n' + info : '');
        } else {
            // hari kerja (senin-jumat) yang sudah lewat dan tidak ada data = tidak hadir
            var dayOfWeek = new Date(calYear, calMonth - 1, d).getDay();
            var isPast = tgl < todayStr;
            if (isPast && dayOfWeek !== 0 && dayOfWeek !== 6) {
                cell.style.background = 'rgba(108,117,125,0.1)';
                dot.style.background = 'transparent';
            }
        }

        cell.appendChild(dayNum);
        cell.appendChild(dot);
        container.appendChild(cell);
    }
}

// ganti bulan kalender pegawai
function changeCalMonth(dir) {
    calMonth += dir;
    if (calMonth > 12) { calMonth = 1;  calYear++; }
    if (calMonth < 1)  { calMonth = 12; calYear--; }
    renderCalPegawai();
}

// switch antara list view dan calendar view
function switchView(view) {
    var listEl  = document.getElementById('view-list');
    var calEl   = document.getElementById('view-calendar');
    var btnList = document.getElementById('btn-view-list');
    var btnCal  = document.getElementById('btn-view-cal');
    if (!listEl || !calEl) return;

    if (view === 'calendar') {
        listEl.style.display = 'none';
        calEl.style.display  = 'block';
        btnList.className = 'btn btn-sm btn-outline-primary';
        btnCal.className  = 'btn btn-sm btn-primary';
        renderCalPegawai();
    } else {
        listEl.style.display = 'block';
        calEl.style.display  = 'none';
        btnList.className = 'btn btn-sm btn-primary';
        btnCal.className  = 'btn btn-sm btn-outline-primary';
    }
}
</script>

<!-- jam realtime inline yang aman dari pembatasan proxy cdn -->
<script>
(function () {
    var el = document.getElementById('time-display');
    if (!el) return;
    function tick() {
        var d = new Date();
        var h = String(d.getHours()).padStart(2, '0');
        var m = String(d.getMinutes()).padStart(2, '0');
        el.textContent = h + ':' + m;
    }
    setInterval(tick, 30000);
})();
</script>
