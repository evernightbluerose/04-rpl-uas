<?php
/**
 * template tampilan logbook kegiatan harian pegawai
 *
 * @package sistemkehadiran
 */
?>

<div class="container">

    <!-- judul halaman -->
    <div class="page-welcome fade-in">
        <h2><i class="bi bi-journal-text"></i> Logbook Kegiatan</h2>
        <p>
            <?php if ($user_role === 'Pegawai'): ?>
                Catat dan pantau aktivitas harian Anda.
            <?php else: ?>
                Pantau logbook kegiatan dari seluruh pegawai.
            <?php endif; ?>
        </p>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success fade-in"><i class="bi bi-check-circle"></i> <?= e($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger fade-in"><i class="bi bi-exclamation-triangle"></i> <?= e($error_msg) ?></div>
    <?php endif; ?>

    <!-- card utama -->
    <div class="card card-elevated fade-in fade-in-delay-1">
        <div class="card-body p-4">

            <!-- header filter dan tombol tambah -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="bi bi-list-check"></i> Daftar Kegiatan
                </h3>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <?php if ($user_role !== 'Pegawai'): ?>
                        <form method="GET" class="d-flex gap-2 align-items-center no-ajax">
                            <input type="date" name="date" class="form-control" value="<?= e($_GET['date'] ?? '') ?>" style="max-width:180px;">
                            <button type="submit" class="btn btn-theme"><i class="bi bi-funnel"></i> Filter</button>
                            <?php if (!empty($_GET['date'])): ?>
                                <a href="logbook.php" class="btn btn-ghost"><i class="bi bi-x-circle"></i></a>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-success" onclick="document.getElementById('modal-add-logbook').style.display='flex'">
                        <i class="bi bi-plus-lg"></i> Tambah Kegiatan
                    </button>
                </div>
            </div>

            <!-- tabel logbook -->
            <div id="dynamic-table-container">
            <div class="table-container">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <?php if ($user_role !== 'Pegawai'): ?>
                                <th>Nama Pegawai</th>
                                <th>Jabatan</th>
                            <?php endif; ?>
                            <th>Tanggal</th>
                            <th>Uraian Kegiatan</th>
                            <th style="width:80px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="<?= $user_role === 'Pegawai' ? 4 : 6 ?>" class="text-center text-muted" style="padding:2rem;">
                                    <i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>
                                    Belum ada catatan logbook.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <?php if ($user_role !== 'Pegawai'): ?>
                                    <td>
                                        <strong><?= e($log['nama']) ?></strong>
                                        <div class="text-sm text-muted">NIP: <?= e($log['nip']) ?></div>
                                    </td>
                                    <td><?= e($log['nama_jabatan'] ?? '-') ?></td>
                                <?php endif; ?>
                                <td style="white-space:nowrap;"><?= date('d M Y', strtotime($log['tanggal'])) ?></td>
                                <td style="max-width:300px; white-space:pre-wrap;"><?= e($log['uraian_kegiatan']) ?>
                                    <?php if (!empty($attachments_map[$log['id_logbook']])): ?>
                                        <div class="mt-2 pt-2 border-top" style="border-top: 1px dashed var(--border-color) !important;">
                                            <small class="text-muted d-block mb-1" style="font-size: 0.8rem;"><i class="bi bi-paperclip"></i> Lampiran:</small>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($attachments_map[$log['id_logbook']] as $att): ?>
                                                    <a href="download_attachment.php?type=logbook&id=<?= $att['id_attachment'] ?>" 
                                                       class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 py-1 px-2 no-ajax"
                                                       target="_blank" style="font-size: 0.75rem; border-radius: 4px;">
                                                        <i class="bi bi-file-earmark-arrow-down"></i> <?= e($att['file_name']) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user_role === 'Pegawai' || (isset($log['id_user']) && $log['id_user'] == $user_id)): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kegiatan ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_logbook" value="<?= $log['id_logbook'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($total_pages, $page, ['date' => $filter_date ?? '']) ?>
            </div>

        </div>
    </div>

</div>

<!-- modal tambah logbook -->
<div id="modal-add-logbook" class="modal-backdrop" style="display:none;">
    <div class="modal-box" style="text-align:left;">
        <h3 style="margin-bottom:1rem;"><i class="bi bi-journal-plus"></i> Tambah Kegiatan</h3>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Uraian Kegiatan</label>
                <textarea name="uraian_kegiatan" class="form-control" rows="4" required placeholder="Tuliskan kegiatan yang dilakukan hari ini..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Lampiran Berkas (Bisa Pilih Banyak)</label>
                <input type="file" name="attachments[]" class="form-control" multiple>
                <div class="form-text">Maksimal ukuran per file: <?= e($max_size_mb) ?> MB.</div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1.5rem;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-add-logbook').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>
