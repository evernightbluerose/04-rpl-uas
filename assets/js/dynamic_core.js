/**
 * naskah javascript inti untuk dynamic ui dan anti-race condition
 * menangani form ajax dan auto-refresh polling data tabel
 *
 * @package sistemkehadiran
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. pencegatan form ajax untuk crud dinamis dan anti-race condition
    var forms = document.querySelectorAll('form:not(.no-ajax)');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            
            // kunci tombol kirim untuk mencegah klik ganda
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
            }

            try {
                var formData = new FormData(form);
                
                // ambil url aksi menggunakan getattribute untuk menghindari bentrokan elemen input
                var targetUrl = form.getAttribute('action');
                if (!targetUrl || targetUrl === '' || targetUrl === 'null') {
                    targetUrl = window.location.pathname + window.location.search;
                }
                
                var method = form.getAttribute('method') || 'POST';
                
                var response = await fetch(targetUrl, {
                    method: method,
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                // periksa tipe konten respon dari server
                var contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    var result = await response.json();
                    
                    if (result.success) {
                        var actionInput = form.querySelector('input[name="action"]');
                        if (actionInput && actionInput.value === 'add') {
                            form.reset();
                            // sembunyikan modal jika terdeteksi
                            var modal = form.closest('.modal-backdrop');
                            if (modal) modal.style.display = 'none';
                        }
                        showToast('Sukses', result.message || 'Berhasil!', 'success');
                        
                        // segarkan tabel data secara paksa
                        checkDataUpdate(true);
                    } else {
                        showToast('Gagal', result.message || 'Terjadi kesalahan', 'danger');
                    }
                } else {
                    // muat ulang penuh jika server mengembalikan dokumen html
                    window.location.reload();
                    return;
                }
            } catch (error) {
                console.error('AJAX Form Error:', error);
                showToast('Error', 'Gagal menghubungi server.', 'danger');
            } finally {
                // kembalikan status tombol kirim ke semula
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        });
    });

    // 2. pembaruan tabel otomatis secara periodik (auto-refresh polling)
    var lastKnownTimestamp = 0;
    var isFetching = false;
    
    var containerIds = ['dynamic-table-container', 'log-view-list', 'log-view-list-k'];
    var hasAnyContainer = false;
    
    containerIds.forEach(function(id) {
        if (document.getElementById(id)) hasAnyContainer = true;
    });

    async function checkDataUpdate(forceReload) {
        if (isFetching || !hasAnyContainer) return;
        isFetching = true;

        try {
            var res = await fetch('api_sync.php', { credentials: 'same-origin' });
            var data = await res.json();
            
            if (data.success) {
                if (lastKnownTimestamp === 0 && !forceReload) {
                    lastKnownTimestamp = data.last_update;
                } 
                else if (data.last_update > lastKnownTimestamp || forceReload) {
                    lastKnownTimestamp = data.last_update;
                    
                    var htmlRes = await fetch(window.location.href, { credentials: 'same-origin' });
                    var htmlText = await htmlRes.text();
                    
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(htmlText, 'text/html');
                    
                    containerIds.forEach(function(id) {
                        var targetNode = document.getElementById(id);
                        var sourceNode = doc.getElementById(id);
                        if (targetNode && sourceNode) {
                            targetNode.innerHTML = sourceNode.innerHTML;
                        }
                    });
                    
                    // perbarui qr code jika fungsi global tersedia
                    if (typeof window.fetchNewQR === 'function') {
                        window.fetchNewQR();
                    }
                }
            }
        } catch (err) {
            console.error('Sync error:', err);
        } finally {
            isFetching = false;
        }
    }
    
    // ekspos fungsi periksa pembaruan secara global
    window.checkDataUpdate = checkDataUpdate;

    // aktifkan interval polling jika elemen tabel dinamis ditemukan
    if (hasAnyContainer) {
        setInterval(function() { checkDataUpdate(false); }, 5000);
        checkDataUpdate(false);
    }
    
    // penampil notifikasi roti panggang (toast) berbasis bootstrap
    function showToast(title, message, type) {
        var toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }
        
        var toastId = 'toast-' + Date.now();
        var toastHTML = 
            '<div id="' + toastId + '" class="toast align-items-center text-bg-' + type + ' border-0" role="alert" aria-live="assertive" aria-atomic="true">' +
            '  <div class="d-flex">' +
            '    <div class="toast-body"><strong>' + title + ':</strong> ' + message + '</div>' +
            '    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '  </div>' +
            '</div>';
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        var toastEl = document.getElementById(toastId);
        var toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
        
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }
    
    // ekspos fungsi notifikasi secara global
    window.showToast = showToast;

});
