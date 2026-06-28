<?php
/**
 * partial view: script + popup untuk calendar toggle di log kehadiran
 *
 * tidak merender card sendiri - hanya menyediakan:
 *   - floating popup detail kehadiran saat klik tanggal
 *   - fungsi renderCalGlobal(), fetchCalendarData(), openCalPopup()
 *   - css animasi dan variabel berbasis tema
 *
 * digunakan oleh admin_dashboard.php dan kades_dashboard.php
 *
 * @package sistemkehadiran\templates\partials
 */
?>
<!-- floating popup detail kehadiran per tanggal -->
<div id="cal-popup" style="
    display:none;
    position:fixed;
    z-index:8000;
    min-width:300px;
    max-width:380px;
    width:90vw;
    background:var(--bg-elevated);
    color:var(--text-primary);
    border-radius:16px;
    box-shadow:var(--shadow-lg);
    border:1px solid var(--border-hover);
    overflow:hidden;
    animation:popupFadeIn .18s ease;
">
    <!-- header popup dengan warna aksen -->
    <div style="padding:1rem 1.2rem 0.8rem;background:var(--accent);color:#fff;" id="cal-popup-header">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div style="font-size:0.75rem;opacity:0.85;letter-spacing:0.04em;">KEHADIRAN</div>
                <div id="cal-popup-date" style="font-size:1.1rem;font-weight:700;margin-top:2px;"></div>
            </div>
            <button onclick="closeCalPopup()"
                    style="background:rgba(255,255,255,0.18);border:none;color:#fff;border-radius:50%;width:30px;height:30px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <!-- tiga angka ringkasan -->
        <div class="d-flex gap-4 mt-3 mb-1">
            <div>
                <div id="cal-popup-total" style="font-size:2rem;font-weight:800;line-height:1;">0</div>
                <div style="font-size:0.7rem;opacity:0.8;margin-top:2px;">Hadir</div>
            </div>
            <div>
                <div id="cal-popup-tepat" style="font-size:2rem;font-weight:800;line-height:1;color:#6ee7b7;">0</div>
                <div style="font-size:0.7rem;opacity:0.8;margin-top:2px;">Tepat Waktu</div>
            </div>
            <div>
                <div id="cal-popup-terlambat" style="font-size:2rem;font-weight:800;line-height:1;color:#fca5a5;">0</div>
                <div style="font-size:0.7rem;opacity:0.8;margin-top:2px;">Terlambat</div>
            </div>
        </div>
    </div>
    <!-- daftar pegawai yang hadir (scrollable) -->
    <div id="cal-popup-list" style="max-height:220px;overflow-y:auto;padding:0.5rem 1rem 0.8rem;border-top:1px solid var(--border-color);">
        <p class="text-muted text-sm mb-0 pt-1">Tidak ada data kehadiran.</p>
    </div>
</div>

<style>
@keyframes popupFadeIn {
    from { opacity:0; transform:scale(0.94) translateY(6px); }
    to   { opacity:1; transform:scale(1)    translateY(0);   }
}
/* sel hari kalender */
.cal-day-cell {
    border-radius: 10px;
    padding: 8px 4px;
    text-align: center;
    cursor: pointer;
    transition: transform .12s ease, box-shadow .12s ease;
    position: relative;
    user-select: none;
}
.cal-day-cell:hover {
    transform: scale(1.08);
    z-index: 2;
    box-shadow: var(--shadow-md);
}
/* header hari (min sen sel …) */
.cal-day-header {
    text-align: center;
    padding: 6px 2px;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-secondary);
    letter-spacing: 0.05em;
}
/* scrollbar popup list */
#cal-popup-list::-webkit-scrollbar { width: 4px; }
#cal-popup-list::-webkit-scrollbar-track { background: transparent; }
#cal-popup-list::-webkit-scrollbar-thumb { background: var(--border-hover); border-radius: 4px; }
</style>

<script>
// ===== kalender kehadiran global (admin / kades) =====

var calGY = (new Date()).getFullYear();
var calGM = (new Date()).getMonth() + 1;
var calGData = {};

var BULAN_ID = ['Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];
var HARI_ID  = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

// fetch json dari api_calendar.php lalu render grid
function fetchCalendarData(year, month, cb) {
    var loading = document.getElementById('cal-loading');
    if (loading) loading.style.display = 'flex';
    fetch('api_calendar.php?year=' + year + '&month=' + month, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            calGData = data || {};
            if (loading) loading.style.display = 'none';
            if (cb) cb();
        })
        .catch(function() {
            calGData = {};
            if (loading) loading.style.display = 'none';
            if (cb) cb();
        });
}

// render grid ke dalam container target
function renderCalGlobal(containerId, labelId) {
    containerId = containerId || 'cal-global-grid';
    labelId     = labelId     || 'cal-global-label';

    var label = document.getElementById(labelId);
    if (label) label.textContent = BULAN_ID[calGM - 1] + ' ' + calGY;

    var grid = document.getElementById(containerId);
    if (!grid) return;
    grid.innerHTML = '';

    // header nama hari
    HARI_ID.forEach(function(h) {
        var el = document.createElement('div');
        el.className = 'cal-day-header';
        el.textContent = h;
        grid.appendChild(el);
    });

    var firstDay    = new Date(calGY, calGM - 1, 1).getDay();
    var daysInMonth = new Date(calGY, calGM, 0).getDate();
    var d = new Date();
    var todayStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

    // padding kosong sebelum hari pertama
    for (var i = 0; i < firstDay; i++) {
        grid.appendChild(document.createElement('div'));
    }

    for (var d = 1; d <= daysInMonth; d++) {
        var tgl  = calGY + '-' + String(calGM).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        var info = calGData[tgl];

        var cell = document.createElement('div');
        cell.className = 'cal-day-cell';

        var dayNum = document.createElement('div');
        dayNum.textContent = d;
        dayNum.style.cssText = 'font-size:0.88rem;font-weight:700;color:var(--text-primary);margin-bottom:3px;';

        if (info && info.total > 0) {
            var hasTerlambat = info.terlambat > 0;
            cell.style.background = hasTerlambat
                ? 'rgba(239,68,68,0.13)'
                : 'rgba(16,185,129,0.12)';
            cell.style.border = hasTerlambat
                ? '1px solid rgba(239,68,68,0.3)'
                : '1px solid rgba(16,185,129,0.28)';

            var badge = document.createElement('div');
            badge.textContent = info.total + ' hadir';
            badge.style.cssText = 'font-size:0.62rem;font-weight:600;color:' +
                (hasTerlambat ? 'var(--danger)' : 'var(--success)') + ';line-height:1;';

            cell.appendChild(dayNum);
            cell.appendChild(badge);
        } else {
            cell.style.background = 'rgba(148,163,184,0.06)';
            cell.style.border     = '1px solid var(--border-color)';
            cell.appendChild(dayNum);
        }

        // highlight hari ini
        if (tgl === todayStr) {
            cell.style.outline = '2px solid var(--accent)';
            cell.style.outlineOffset = '1px';
        }

        // klik buka popup
        (function(t) {
            cell.addEventListener('click', function(e) { openCalPopup(t, e); });
        })(tgl);

        grid.appendChild(cell);
    }
}

// buka popup detail di posisi dekat kursor
function openCalPopup(tgl, evt) {
    var popup   = document.getElementById('cal-popup');
    var info    = calGData[tgl] || { total: 0, terlambat: 0, list: [] };
    var tepat   = (info.total || 0) - (info.terlambat || 0);

    // isi header
    var parts = tgl.split('-');
    document.getElementById('cal-popup-date').textContent =
        parseInt(parts[2]) + ' ' + BULAN_ID[parseInt(parts[1]) - 1] + ' ' + parts[0];
    document.getElementById('cal-popup-total').textContent     = info.total || 0;
    document.getElementById('cal-popup-tepat').textContent     = tepat;
    document.getElementById('cal-popup-terlambat').textContent = info.terlambat || 0;

    // isi daftar
    var listEl = document.getElementById('cal-popup-list');
    if (info.list && info.list.length > 0) {
        var html = '';
        info.list.forEach(function(p, idx) {
            var stColor = p.status === 'Terlambat' ? 'var(--danger)' : 'var(--success)';
            html += '<div style="display:flex;align-items:center;justify-content:space-between;' +
                    'padding:0.4rem 0;' + (idx > 0 ? 'border-top:1px solid var(--border-color);' : '') + '">' +
                    '<div><div style="font-weight:600;font-size:0.88rem;color:var(--text-primary);">' + escHtml(p.nama) + '</div>' +
                    '<div style="font-size:0.73rem;color:var(--text-secondary);">' +
                    (p.jam_masuk || '-') + ' &rarr; ' + (p.jam_pulang || 'Belum pulang') + '</div></div>' +
                    '<span style="font-size:0.7rem;font-weight:600;color:' + stColor + ';white-space:nowrap;">' + escHtml(p.status) + '</span>' +
                    '</div>';
        });
        listEl.innerHTML = html;
    } else {
        listEl.innerHTML = '<p style="color:var(--text-secondary);font-size:0.85rem;margin:0;padding-top:0.5rem;">Tidak ada data kehadiran untuk tanggal ini.</p>';
    }

    // posisi popup dekat kursor, cegah keluar viewport
    popup.style.display = 'block';
    var vw = window.innerWidth, vh = window.innerHeight;
    var pw = Math.min(380, vw * 0.9), ph = popup.offsetHeight || 340;
    var x = evt.clientX + 14, y = evt.clientY + 14;
    if (x + pw > vw - 8) x = evt.clientX - pw - 14;
    if (y + ph > vh - 8) y = evt.clientY - ph - 14;
    if (x < 8) x = 8;
    if (y < 8) y = 8;
    popup.style.left = x + 'px';
    popup.style.top  = y + 'px';
}

function closeCalPopup() {
    var p = document.getElementById('cal-popup');
    if (p) p.style.display = 'none';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// tutup popup saat klik luar
document.addEventListener('click', function(e) {
    var popup = document.getElementById('cal-popup');
    if (popup && popup.style.display !== 'none' &&
        !popup.contains(e.target) && !e.target.closest('.cal-day-cell')) {
        popup.style.display = 'none';
    }
});

// ganti bulan
function calGlobalPrev() {
    calGM--; if (calGM < 1)  { calGM = 12; calGY--; }
    closeCalPopup();
    fetchCalendarData(calGY, calGM, function() { renderCalGlobal(); });
}
function calGlobalNext() {
    calGM++; if (calGM > 12) { calGM = 1;  calGY++; }
    closeCalPopup();
    fetchCalendarData(calGY, calGM, function() { renderCalGlobal(); });
}
</script>
