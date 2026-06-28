/**
 * naskah javascript alur keamanan absensi berjenjang
 * menangani verifikasi qr, selfie, lokasi gps, dan biometrik webauthn
 *
 * @package sistemkehadiran
 */

let wizardState = {
    action: null,
    qr_token: null,
    selfie_base64: null,
    latitude: null,
    longitude: null,
    webauthn_data: null
};

let selfieStream = null;
let html5Qrcode = null;

// reset data alur ke default
function resetWizardState() {
    wizardState = {
        action: null,
        qr_token: null,
        selfie_base64: null,
        latitude: null,
        longitude: null,
        webauthn_data: null
    };
}

// mulai alur verifikasi absensi
function startSecurityWizard(action) {
    resetWizardState();
    wizardState.action = action;

    // deteksi apakah pengguna mengakses dari perangkat seluler
    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    if (!isMobile) {
        // periksa apakah diperbolehkan absen dari pc/laptop
        if (window.APP_SETTINGS.allow_pc_attendance === false || window.APP_SETTINGS.allow_pc_attendance === "0") {
            if (typeof window.showToast === 'function') {
                window.showToast('Akses Ditolak', 'Presensi hanya diperbolehkan melalui perangkat Mobile (Smartphone).', 'danger');
            } else {
                alert('Presensi hanya diperbolehkan melalui perangkat Mobile (Smartphone).');
            }
            return;
        }
    }

    document.getElementById('modal-security-wizard').style.display = 'flex';

    // ambil lokasi gps di latar belakang jika pengaturan aktif
    if (window.APP_SETTINGS.location_logging) {
        prefetchLocation();
    }

    if (window.APP_SETTINGS.qr_login) {
        if (isMobile) {
            showStep('step-qr');
            startQRScanner();
        } else {
            // periksa izin pemindaian qr dari pc
            if (window.APP_SETTINGS.allow_pc_qr_scan === true || window.APP_SETTINGS.allow_pc_qr_scan === "1") {
                showStep('step-qr');
                startQRScanner();
            } else {
                nextAfterQR();
            }
        }
    } else {
        nextAfterQR();
    }
}

// tampilkan salah satu langkah penyaringan verifikasi
function showStep(stepId) {
    var steps = ['step-qr', 'step-selfie', 'step-location', 'step-webauthn', 'step-submitting'];
    for (var i = 0; i < steps.length; i++) {
        var el = document.getElementById(steps[i]);
        if (el) el.style.display = (steps[i] === stepId) ? 'block' : 'none';
    }
}

// batalkan alur verifikasi dan matikan semua kamera aktif
function cancelWizard() {
    if (html5Qrcode) {
        try { html5Qrcode.stop(); } catch(e) {}
        html5Qrcode = null;
    }
    stopSelfieCamera();
    var qrReader = document.getElementById('qr-reader');
    if (qrReader) qrReader.innerHTML = '';
    document.getElementById('modal-security-wizard').style.display = 'none';
}

// langkah 1: inisialisasi pemindai qr code dengan pemilihan kamera terbaik
var availableCameras = [];

function startQRScanner() {
    var qrReader = document.getElementById('qr-reader');
    if (qrReader) qrReader.innerHTML = '';

    html5Qrcode = new Html5Qrcode("qr-reader");

    // deteksi daftar kamera pada perangkat
    Html5Qrcode.getCameras().then(function(cameras) {
        if (!cameras || cameras.length === 0) {
            alert("Tidak ada kamera yang terdeteksi.");
            cancelWizard();
            return;
        }

        availableCameras = cameras;

        // tampilkan opsi kamera jika perangkat memiliki lebih dari satu kamera
        var select = document.getElementById('qr-camera-select');
        if (select) {
            select.innerHTML = '';
            for (var i = 0; i < cameras.length; i++) {
                var opt = document.createElement('option');
                opt.value = cameras[i].id;
                opt.textContent = cameras[i].label || ('Kamera ' + (i + 1));
                select.appendChild(opt);
            }
            select.style.display = cameras.length > 1 ? 'block' : 'none';
        }

        // prioritaskan pemilihan kamera belakang secara otomatis
        var bestCamera = cameras[0];
        for (var i = 0; i < cameras.length; i++) {
            var label = (cameras[i].label || '').toLowerCase();
            if (label.indexOf('back') !== -1 || label.indexOf('rear') !== -1 || 
                label.indexOf('belakang') !== -1 || label.indexOf('environment') !== -1) {
                bestCamera = cameras[i];
                break;
            }
        }
        if (cameras.length > 1 && bestCamera === cameras[0]) {
            var firstLabel = (cameras[0].label || '').toLowerCase();
            if (firstLabel.indexOf('front') !== -1 || firstLabel.indexOf('depan') !== -1 || 
                firstLabel.indexOf('user') !== -1) {
                bestCamera = cameras[cameras.length - 1];
            }
        }

        if (select) select.value = bestCamera.id;

        startQRWithCamera(bestCamera.id);

    }).catch(function(err) {
        console.error("Camera enumeration failed:", err);
        startQRWithFacingMode();
    });
}

function startQRWithCamera(cameraId) {
    if (!html5Qrcode) html5Qrcode = new Html5Qrcode("qr-reader");

    html5Qrcode.start(
        cameraId,
        { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 },
        onQRSuccess,
        function() { }
    ).then(function() {
        forceQRVideoDisplay();
    }).catch(function(err) {
        console.error("Camera start failed for id " + cameraId + ":", err);
        startQRWithFacingMode();
    });
}

function startQRWithFacingMode() {
    if (!html5Qrcode) html5Qrcode = new Html5Qrcode("qr-reader");

    html5Qrcode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 },
        onQRSuccess,
        function() { }
    ).then(function() {
        forceQRVideoDisplay();
    }).catch(function(err) {
        console.error("Camera error:", err);
        alert("Gagal mengakses kamera. Pastikan izin kamera diberikan.");
        cancelWizard();
    });
}

// memaksa elemen video agar terlihat dengan rasio penuh di beberapa perangkat
function forceQRVideoDisplay() {
    setTimeout(function() {
        var container = document.getElementById('qr-reader');
        if (!container) return;
        var videos = container.querySelectorAll('video');
        for (var i = 0; i < videos.length; i++) {
            videos[i].style.display = 'block';
            videos[i].style.width = '100%';
            videos[i].style.height = 'auto';
            videos[i].style.objectFit = 'cover';
            videos[i].setAttribute('playsinline', '');
            videos[i].setAttribute('muted', '');
            videos[i].play().catch(function() {});
        }
    }, 300);
}

// dijalankan setelah kode qr berhasil dipindai
function onQRSuccess(decodedText) {
    var qrReader = document.getElementById('qr-reader');
    if (html5Qrcode) {
        html5Qrcode.stop().then(function() {
            html5Qrcode = null;
        }).catch(function(e) { console.error(e); });
    }
    
    if (qrReader) qrReader.innerHTML = '<div style="padding:2rem;color:var(--info);"><span class="spinner-border spinner-border-sm me-2"></span>Memverifikasi QR...</div>';
    
    // reserve QR code agar layar depan (kios) segera berganti ke QR baru
    fetch('proses_absen_secure.php?action=reserve_qr', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_token: decodedText })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            wizardState.qr_token = decodedText;
            if (qrReader) qrReader.innerHTML = '<div style="padding:2rem;color:var(--success);"><i class="bi bi-check-circle" style="font-size:2rem;"></i><p>QR Berhasil!</p></div>';
            var select = document.getElementById('qr-camera-select');
            if (select) select.style.display = 'none';
            nextAfterQR();
        } else {
            alert(data.error || 'QR Code tidak valid');
            if (qrReader) qrReader.innerHTML = '<div style="padding:2rem;color:var(--danger);"><i class="bi bi-x-circle" style="font-size:2rem;"></i><p>Gagal. Scan Ulang.</p></div>';
            setTimeout(() => { startQRScanner(); }, 1500);
        }
    })
    .catch(err => {
        console.error("Reserve QR Error:", err);
        alert('Gagal menghubungi server.');
        setTimeout(() => { startQRScanner(); }, 1500);
    });
}

// mengganti kamera pemindai qr aktif
function switchQRCamera(cameraId) {
    if (!html5Qrcode) return;
    html5Qrcode.stop().then(function() {
        html5Qrcode = null;
        var qrReader = document.getElementById('qr-reader');
        if (qrReader) qrReader.innerHTML = '';
        html5Qrcode = new Html5Qrcode("qr-reader");
        startQRWithCamera(cameraId);
    }).catch(function(err) {
        console.error("Switch camera error:", err);
    });
}

// absen pulang langsung tanpa melewati wizard
function absenPulangLangsung() {
    if (!confirm("Anda yakin ingin absen pulang sekarang?")) return;

    var btn = document.getElementById('btn-absen-pulang');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...';
    }

    var payload = {
        action: 'absen_pulang',
        csrf_token: window.CSRF_TOKEN,
        qr_token: null,
        selfie_base64: null,
        lat: null,
        lng: null,
        webauthn_data: null
    };

    fetch('proses_absen_secure.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.href = 'pegawai.php?pesan=Absen+Pulang+berhasil!&tipe=success';
        } else {
            alert("Error: " + (data.error || 'Gagal absen pulang'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-box-arrow-right"></i> Absen Pulang';
            }
        }
    })
    .catch(function(err) {
        console.error(err);
        alert("Terjadi kesalahan jaringan.");
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-box-arrow-right"></i> Absen Pulang';
        }
    });
}

function nextAfterQR() {
    if (window.APP_SETTINGS.selfie_validation) {
        showStep('step-selfie');
        startSelfieCamera();
    } else {
        nextAfterSelfie();
    }
}

// langkah 2: pengambilan foto selfie verifikasi wajah
function startSelfieCamera() {
    var video = document.getElementById('selfie-video');
    video.setAttribute('muted', '');
    video.setAttribute('playsinline', '');
    video.setAttribute('autoplay', '');

    var constraints = [
        { video: { facingMode: "user", width: { ideal: 640 }, height: { ideal: 480 } } },
        { video: { facingMode: "user" } },
        { video: true }
    ];

    tryGetUserMedia(video, constraints, 0);
}

function tryGetUserMedia(video, constraintsList, index) {
    if (index >= constraintsList.length) {
        alert("Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.");
        cancelWizard();
        return;
    }

    navigator.mediaDevices.getUserMedia(constraintsList[index])
        .then(function(stream) {
            selfieStream = stream;
            video.srcObject = stream;
            video.onloadedmetadata = function() {
                video.play().catch(function(e) { console.warn("Video play warning:", e); });
            };
        })
        .catch(function(err) {
            console.warn("Camera attempt " + (index + 1) + " failed:", err.name);
            tryGetUserMedia(video, constraintsList, index + 1);
        });
}

function stopSelfieCamera() {
    if (selfieStream) {
        selfieStream.getTracks().forEach(function(track) { track.stop(); });
        selfieStream = null;
    }
}

function takeSelfie() {
    var video = document.getElementById('selfie-video');
    var canvas = document.getElementById('selfie-canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    wizardState.selfie_base64 = canvas.toDataURL('image/jpeg', 0.7);
    stopSelfieCamera();
    nextAfterSelfie();
}

function nextAfterSelfie() {
    if (window.APP_SETTINGS.location_logging) {
        if (wizardState.latitude && wizardState.longitude) {
            nextAfterLocation();
        } else {
            showStep('step-location');
            getLocation();
        }
    } else {
        nextAfterLocation();
    }
}

// langkah 3: penentuan posisi koordinat gps (geolocation)
function prefetchLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                wizardState.latitude = position.coords.latitude;
                wizardState.longitude = position.coords.longitude;
            },
            function() { },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
        );
    }
}

function getLocation() {
    if (!navigator.geolocation) {
        nextAfterLocation();
        return;
    }

    var resolved = false;

    // coba akurasi tinggi (gps)
    navigator.geolocation.getCurrentPosition(
        function(position) {
            if (resolved) return;
            resolved = true;
            wizardState.latitude = position.coords.latitude;
            wizardState.longitude = position.coords.longitude;
            nextAfterLocation();
        },
        function() {
            if (resolved) return;
            // coba akurasi rendah (berbasis jaringan)
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    if (resolved) return;
                    resolved = true;
                    wizardState.latitude = position.coords.latitude;
                    wizardState.longitude = position.coords.longitude;
                    nextAfterLocation();
                },
                function() {
                    if (resolved) return;
                    resolved = true;
                    console.warn("Location unavailable, continuing without GPS");
                    nextAfterLocation();
                },
                { enableHighAccuracy: false, timeout: 8000, maximumAge: 120000 }
            );
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
    );

    // pembatas waktu keamanan pelokalan 12 detik
    setTimeout(function() {
        if (!resolved) {
            resolved = true;
            console.warn("Location timeout, continuing without GPS");
            nextAfterLocation();
        }
    }, 12000);
}

function nextAfterLocation() {
    if (window.APP_SETTINGS.webauthn) {
        showStep('step-webauthn');
    } else {
        submitAttendance();
    }
}

// langkah 4: verifikasi biometrik/fido2 webauthn
async function triggerWebAuthn() {
    try {
        var checkRes = await fetch('proses_absen_secure.php?action=check_webauthn', {
            credentials: 'same-origin'
        });
        var checkData = await checkRes.json();

        if (!checkData.has_credential) {
            var publicKeyCredentialCreationOptions = {
                challenge: new Uint8Array(32), // Dummy challenge for demo
                rp: { name: "Sistem Presensi Desa", id: window.location.hostname },
                user: {
                    id: Uint8Array.from(checkData.user_id.toString(), function(c) { return c.charCodeAt(0); }),
                    name: checkData.username,
                    displayName: checkData.nama
                },
                pubKeyCredParams: [{alg: -7, type: "public-key"}, {alg: -257, type: "public-key"}],
                authenticatorSelection: { userVerification: "discouraged" },
                timeout: 60000,
                attestation: "none"
            };
            var credential = await navigator.credentials.create({ publicKey: publicKeyCredentialCreationOptions });
            wizardState.webauthn_data = {
                type: 'register',
                id: credential.id
            };
            submitAttendance();
        } else {
            // Helper function to decode base64url to Uint8Array
            function base64urlToUint8Array(base64url) {
                var padding = '='.repeat((4 - base64url.length % 4) % 4);
                var base64 = (base64url + padding).replace(/\-/g, '+').replace(/_/g, '/');
                var rawData = window.atob(base64);
                var outputArray = new Uint8Array(rawData.length);
                for (var i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }

            var publicKeyCredentialRequestOptions = {
                challenge: new Uint8Array(32), // Dummy challenge for demo
                allowCredentials: [{
                    id: base64urlToUint8Array(checkData.credential_id),
                    type: 'public-key'
                }],
                userVerification: "discouraged",
                timeout: 60000
            };
            var assertion = await navigator.credentials.get({ publicKey: publicKeyCredentialRequestOptions });
            wizardState.webauthn_data = {
                type: 'login',
                id: assertion.id
            };
            submitAttendance();
        }
    } catch (err) {
        console.error("WebAuthn Error:", err);
        alert("Autentikasi Biometrik/FIDO2 dibatalkan atau gagal.");
        cancelWizard();
    }
}

// langkah 5: pengiriman data hasil verifikasi akhir ke server
function submitAttendance() {
    showStep('step-submitting');

    var payload = {
        action: wizardState.action,
        csrf_token: window.CSRF_TOKEN,
        qr_token: wizardState.qr_token,
        selfie_base64: wizardState.selfie_base64,
        lat: wizardState.latitude,
        lng: wizardState.longitude,
        webauthn_data: wizardState.webauthn_data
    };

    fetch('proses_absen_secure.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.href = 'pegawai.php?pesan=Absen+berhasil!&tipe=success';
        } else {
            alert("Error: " + (data.error || 'Gagal memproses absensi'));
            cancelWizard();
        }
    })
    .catch(function(err) {
        console.error(err);
        alert("Terjadi kesalahan jaringan.");
        cancelWizard();
    });
}
