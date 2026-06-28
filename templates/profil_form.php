<?php
/**
 * template: templates/profil_form.php
 * tampilan form pengaturan profil dan pembaruan password
 *
 * @package sistemkehadiran\templates
 */
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<style>
    .profile-pic-wrapper {
        text-align: center;
        margin-bottom: 2rem;
    }
    .profile-pic {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--accent);
        cursor: pointer;
        transition: opacity 0.3s;
    }
    .profile-pic:hover {
        opacity: 0.8;
    }
    #imageToCrop {
        max-width: 100%;
        display: block;
    }
</style>

<div class="container">
    <div class="page-welcome fade-in">
        <h2><i class="bi bi-person-circle"></i> Pengaturan Profil</h2>
        <p>Perbarui informasi identitas, kata sandi, dan foto profil Anda.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card card-elevated fade-in fade-in-delay-1">
                <div class="card-body p-4">
                    
                    <?php if ($pesan): ?>
                        <div class="alert alert-<?= $pesan_type ?> fade-in" role="alert">
                            <i class="bi bi-<?= $pesan_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                            <?= $pesan ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="form-profil" class="no-ajax">
                        <?= csrf_field() ?>
                        <input type="hidden" name="foto_profil_base64" id="foto_profil_base64">

                        <div class="profile-pic-wrapper">
                            <label for="fotoInput" title="Klik untuk mengganti foto profil">
                                <?php
                                $img_src = ''; // fallback visual jika foto kosong
                                if (!empty($profil['foto_profil'])) {
                                    $img_src = $profil['foto_profil'];
                                }
                                ?>
                                <img src="<?= e($img_src) ?>" class="profile-pic" id="profilePicPreview" alt="Foto Profil" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($profil['nama']) ?>&background=random'">
                                <div class="text-sm mt-2 text-muted"><i class="bi bi-camera"></i> Ubah Foto</div>
                            </label>
                            <input type="file" id="fotoInput" accept="image/png, image/jpeg, image/webp" style="display:none;">
                        </div>

                        <h4 class="mb-3">Informasi Publik</h4>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">NIP</label>
                            <input type="text" class="form-control" value="<?= e($profil['nip']) ?>" disabled readonly>
                            <div class="form-text">NIP tidak dapat diubah secara mandiri.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Username</label>
                            <input type="text" class="form-control" value="<?= e($profil['username']) ?>" disabled readonly>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Nama Tampil <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" value="<?= e($profil['nama']) ?>" required>
                        </div>

                        <hr>
                        <h4 class="mb-3 mt-4">Ubah Kata Sandi</h4>
                        <p class="text-muted text-sm mb-3">Biarkan kosong jika tidak ingin mengubah kata sandi.</p>

                        <div class="mb-3">
                            <label class="form-label">Kata Sandi Saat Ini</label>
                            <input type="password" name="password_lama" class="form-control" placeholder="••••••••">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kata Sandi Baru</label>
                            <input type="password" name="password_baru" class="form-control" placeholder="••••••••">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" name="konfirmasi_password" class="form-control" placeholder="••••••••">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- modal cropper -->
<div class="modal fade" id="cropModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card">
      <div class="modal-header">
        <h5 class="modal-title">Sesuaikan Foto Profil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div style="max-height: 400px; overflow: hidden; margin-bottom: 1rem;">
            <img id="imageToCrop" src="" alt="Picture">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-theme" id="btnCrop"><i class="bi bi-crop"></i> Terapkan</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    let fotoInput = document.getElementById('fotoInput');
    let imageToCrop = document.getElementById('imageToCrop');
    let btnCrop = document.getElementById('btnCrop');
    let profilePicPreview = document.getElementById('profilePicPreview');
    let hiddenBase64 = document.getElementById('foto_profil_base64');
    let cropper;
    
    // inisialisasi modal bootstrap
    let cropModalElement = document.getElementById('cropModal');
    let cropModal;
    if (typeof bootstrap !== 'undefined') {
        cropModal = new bootstrap.Modal(cropModalElement);
    }

    fotoInput.addEventListener('change', function(e) {
        let files = e.target.files;
        if (files && files.length > 0) {
            let file = files[0];
            
            // validasi jenis file gambar
            if (!file.type.match('image.*')) {
                alert("Harap pilih file gambar (JPG/PNG/WebP).");
                return;
            }
            
            let reader = new FileReader();
            reader.onload = function(event) {
                imageToCrop.src = event.target.result;
                if (cropModal) {
                    cropModal.show();
                } else {
                    cropModalElement.style.display = 'block';
                }
            };
            reader.readAsDataURL(file);
        }
    });

    cropModalElement.addEventListener('shown.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
        }
        cropper = new Cropper(imageToCrop, {
            aspectRatio: 1, // rasio aspek persegi 1:1
            viewMode: 1,
            autoCropArea: 1,
        });
    });

    cropModalElement.addEventListener('hidden.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        fotoInput.value = ""; // reset nilai agar file yang sama bisa dipilih kembali
    });

    btnCrop.addEventListener('click', function() {
        if (!cropper) return;
        
        // mengambil data hasil potong dengan ukuran 300x300 piksel
        let canvas = cropper.getCroppedCanvas({
            width: 300,
            height: 300,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        
        let base64Image = canvas.toDataURL('image/jpeg', 0.85); // kompres jpeg dengan kualitas 85%
        
        // set preview gambar
        profilePicPreview.src = base64Image;
        // set input hidden base64
        hiddenBase64.value = base64Image;
        
        if (cropModal) {
            cropModal.hide();
        } else {
            cropModalElement.style.display = 'none';
        }
    });
});
</script>
