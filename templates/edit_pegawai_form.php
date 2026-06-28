<?php
/**
 * template: templates/edit_pegawai_form.php
 * dipanggil oleh edit_pegawai.php
 * variabel: $error, $id, $nip, $nama, $id_jabatan_input, $jabatan_list
 *
 * @package sistemkehadiran\templates
 */
?>
<div class="container" style="max-width: 600px;">

    <!-- judul halaman -->
    <div class="page-welcome fade-in">
        <h2><i class="bi bi-pencil-square"></i> Edit Pegawai</h2>
        <p>Perbarui data pegawai yang sudah terdaftar.</p>
    </div>

    <!-- error validasi dari server jika ada -->
    <?php if ($error): ?>
        <div class="alert alert-danger fade-in" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- kartu form -->
    <div class="card card-elevated fade-in fade-in-delay-1">
        <div class="card-body p-4">

            <form method="POST" id="form-edit-pegawai" class="no-ajax" autocomplete="off">

                <!-- token csrf tersembunyi wajib ada di setiap form post -->
                <?= csrf_field() ?>

                <!-- id pegawai tersembunyi dikirim untuk menentukan baris yang diedit -->
                <input type="hidden" name="id_pegawai" value="<?= (int) $id ?>">

                <!-- field nip hanya angka maks 20 karakter -->
                <div class="mb-3">
                    <label for="input-nip" class="form-label">NIP</label>
                    <input type="text" name="nip" id="input-nip"
                           class="form-control"
                           value="<?= e($nip) ?>"
                           required maxlength="20" pattern="[0-9]+">
                </div>

                <!-- field nama lengkap -->
                <div class="mb-3">
                    <label for="input-nama" class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama" id="input-nama"
                           class="form-control"
                           value="<?= e($nama) ?>"
                           required maxlength="100">
                </div>

                <!-- dropdown jabatan dipilih berdasarkan id_jabatan pegawai saat ini -->
                <div class="mb-3">
                    <label for="input-jabatan" class="form-label">Jabatan</label>
                    <select name="id_jabatan" id="input-jabatan" class="form-control">
                        <option value="">- Pilih Jabatan -</option>
                        <?php foreach ($jabatan_list as $jab): ?>
                        <option value="<?= (int) $jab['id_jabatan'] ?>"
                            <?= ($id_jabatan_input == $jab['id_jabatan']) ? 'selected' : '' ?>>
                            <?= e($jab['nama_jabatan']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- field role/tipe user -->
                <div class="mb-3">
                    <label for="input-role" class="form-label">Tipe User / Role</label>
                    <select name="role" id="input-role" class="form-control" required>
                        <option value="Pegawai" <?= ($role ?? 'Pegawai') === 'Pegawai' ? 'selected' : '' ?>>Pegawai Biasa</option>
                        <option value="Admin" <?= ($role ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Kades" <?= ($role ?? '') === 'Kades' ? 'selected' : '' ?>>Kades</option>
                    </select>
                </div>

                <!-- tombol aksi -->
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary" id="btn-simpan">
                        <i class="bi bi-check-lg"></i> Simpan Perubahan
                    </button>
                    <a href="admin.php" class="btn btn-outline-secondary" id="btn-batal">
                        <i class="bi bi-arrow-left"></i> Batal
                    </a>
                </div>

            </form>
        </div>
    </div>

</div>
