<?php
/**
 * template tampilan pengajuan izin dan cuti pegawai
 *
 * @package sistemkehadiran
 */
?>

<div class="container">

    <!-- judul halaman -->
    <div class="page-welcome fade-in">
        <h2><i class="bi bi-calendar2-check"></i> Pengajuan Izin</h2>
        <p>
            <?php if ($user_role === 'Pegawai'): ?>
                Ajukan cuti, sakit, atau izin dinas Anda di sini.
            <?php else: ?>
                Validasi pengajuan izin dari pegawai.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success fade-in"><i class="bi bi-check-circle"></i> <?= e($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger fade-in"><i class="bi bi-exclamation-triangle"></i> <?= e($error_msg) ?></div>
    <?php endif; ?>

    <!-- card utama -->
    <div class="card card-elevated fade-in fade-in-delay-1">
        <div class="card-body p-4">

            <!-- header filter dan tombol tambah -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="bi bi-file-earmark-text"></i> Daftar Pengajuan
                </h3>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <?php if ($user_role !== 'Pegawai'): ?>
                        <form method="GET" class="d-flex gap-2 align-items-center no-ajax">
                            <select name="status" class="form-control" style="max-width:180px;">
                                <option value="">Semua Status</option>
                                <option value="Pending" <?= ($_GET['status']??'')==='Pending'?'selected':'' ?>>Pending</option>
                                <option value="Disetujui" <?= ($_GET['status']??'')==='Disetujui'?'selected':'' ?>>Disetujui</option>
                                <option value="Ditolak" <?= ($_GET['status']??'')==='Ditolak'?'selected':'' ?>>Ditolak</option>
                            </select>
                            <button type="submit" class="btn btn-theme"><i class="bi bi-funnel"></i> Filter</button>
                            <?php if (!empty($_GET['status'])): ?>
                                <a href="izin.php" class="btn btn-ghost"><i class="bi bi-x-circle"></i></a>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-success" onclick="document.getElementById('modal-add-izin').style.display='flex'">
                        <i class="bi bi-plus-lg"></i> Buat Pengajuan
                    </button>
                </div>
            </div>

            <!-- tabel pengajuan izin -->
            <div id="dynamic-table-container">
            <div class="table-container">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <?php if ($user_role !== 'Pegawai'): ?>
                                <th>Nama Pegawai</th>
                            <?php endif; ?>
                            <th>Jenis Izin</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th style="width:100px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($izins)): ?>
                            <tr>
                                <td colspan="<?= $user_role === 'Pegawai' ? 6 : 7 ?>" class="text-center text-muted" style="padding:2rem;">
                                    <i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>
                                    Belum ada pengajuan izin.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($izins as $izin): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <?php if ($user_role !== 'Pegawai'): ?>
                                    <td>
                                        <strong><?= e($izin['nama']) ?></strong>
                                        <div class="text-sm text-muted"><?= e($izin['nama_jabatan'] ?? '-') ?></div>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php
                                    $icon = 'bi-file-earmark-text';
                                    if ($izin['jenis_izin'] === 'Sakit') $icon = 'bi-heart-pulse';
                                    if ($izin['jenis_izin'] === 'Cuti') $icon = 'bi-calendar-event';
                                    if ($izin['jenis_izin'] === 'Izin Dinas') $icon = 'bi-briefcase';
                                    ?>
                                    <i class="bi <?= $icon ?>" style="color:var(--accent);"></i> <?= e($izin['jenis_izin']) ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <?= date('d M Y', strtotime($izin['tanggal_mulai'])) ?>
                                    <?php if ($izin['tanggal_mulai'] !== $izin['tanggal_selesai']): ?>
                                        <br><small class="text-muted">s/d <?= date('d M Y', strtotime($izin['tanggal_selesai'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width:250px; white-space:pre-wrap; font-size:0.9rem;"><?= e($izin['keterangan']) ?>
                                    <?php if (!empty($attachments_map[$izin['id_izin']])): ?>
                                        <div class="mt-2 pt-2 border-top" style="border-top: 1px dashed var(--border-color) !important;">
                                            <small class="text-muted d-block mb-1" style="font-size: 0.8rem;"><i class="bi bi-paperclip"></i> Lampiran:</small>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($attachments_map[$izin['id_izin']] as $att): ?>
                                                    <a href="download_attachment.php?type=izin&id=<?= $att['id_attachment'] ?>" 
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
                                    <?php
                                    $badge_class = 'bg-warning text-dark';
                                    if ($izin['status_validasi'] === 'Disetujui') $badge_class = 'bg-success';
                                    if ($izin['status_validasi'] === 'Ditolak') $badge_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= e($izin['status_validasi']) ?></span>
                                </td>
                                <td>
                                    <?php if ((isset($izin['id_user']) && $izin['id_user'] == $user_id) && $izin['status_validasi'] === 'Pending'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Batalkan pengajuan ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_izin" value="<?= $izin['id_izin'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Batalkan">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($user_role !== 'Pegawai' && $izin['status_validasi'] === 'Pending'): ?>
                                        <div class="d-flex gap-1">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Setujui pengajuan ini?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="validate">
                                                <input type="hidden" name="id_izin" value="<?= $izin['id_izin'] ?>">
                                                <input type="hidden" name="status_validasi" value="Disetujui">
                                                <button type="submit" class="btn btn-sm btn-success" title="Setujui">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Tolak pengajuan ini?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="validate">
                                                <input type="hidden" name="id_izin" value="<?= $izin['id_izin'] ?>">
                                                <input type="hidden" name="status_validasi" value="Ditolak">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Tolak">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="bi bi-dash"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($total_pages, $page, ['status' => $filter_status ?? '']) ?>
            </div>

        </div>
    </div>

</div>

<!-- modal tambah pengajuan izin -->
<div id="modal-add-izin" class="modal-backdrop" style="display:none;">
    <div class="modal-box" style="text-align:left;">
        <h3 style="margin-bottom:1rem;"><i class="bi bi-send-plus"></i> Buat Pengajuan Izin</h3>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">Jenis Izin</label>
                <select name="jenis_izin" class="form-control" required>
                    <option value="">-- Pilih Jenis --</option>
                    <option value="Sakit">Sakit</option>
                    <option value="Cuti">Cuti</option>
                    <option value="Izin Dinas">Izin Dinas</option>
                </select>
            </div>

            <div class="row g-2">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" class="form-control" required>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Keterangan / Alasan</label>
                <textarea name="keterangan" class="form-control" rows="3" required placeholder="Tuliskan keterangan detail..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Lampiran Berkas Pendukung (Bisa Pilih Banyak)</label>
                <input type="file" name="attachments[]" class="form-control" multiple>
                <div class="form-text">Maksimal ukuran per file: <?= e($max_size_mb) ?> MB.</div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1.5rem;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-add-izin').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</div>
