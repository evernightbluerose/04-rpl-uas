-- ============================================================
-- database: catatan_kehadiran
-- sistem presensi kehadiran pegawai desa
-- ============================================================
--
-- deskripsi skema
-- ===============
-- database ini terdiri dari 11 tabel yang saling berelasi:
--
--   jabatan ──< pegawai ──< presensi
--                  │
--   users ─────────┘──< logbook ──< logbook_attachments
--      │           └──< pengajuan_izin ──< izin_attachments
--      │
--      └──< webauthn_credentials
--
--  * app_settings dan qr_tokens berdiri sendiri untuk manajemen konfigurasi dan token dinamis.
--
-- 1. users                : kredensial login (username, password, role, token sesi).
-- 2. jabatan              : data jabatan/tunjangan pegawai.
-- 3. pegawai              : data identitas pegawai (nip, nama, jabatan, foto profil).
--                           semua pengguna (admin, kades, pegawai) memiliki
--                           record di tabel ini.
-- 4. presensi             : catatan kehadiran masuk & pulang harian per pegawai beserta bukti.
-- 5. logbook              : catatan kegiatan harian (sprint 2).
-- 6. pengajuan_izin       : pengajuan cuti/sakit/izin dinas (sprint 2).
-- 7. app_settings         : konfigurasi moduler, jam operasional, nama, logo, dan batasan ukuran berkas (sprint 2).
-- 8. qr_tokens            : sinkronisasi token presensi dinamis (sprint 2).
-- 9. webauthn_credentials : data kredensial login biometrik (fido2) per user (sprint 2).
-- 10. logbook_attachments : berkas lampiran pendukung untuk logbook kegiatan (sprint 2).
-- 11. izin_attachments    : berkas lampiran pendukung untuk pengajuan izin (sprint 2).
--
-- relasi antar tabel utama
-- ========================
--
--  ┌──────────────────┐                                                ┌──────────────────────┐
--  │      users       │                                                │       presensi       │
--  ├──────────────────┤       ┌──────────────┐       ┌───────────────┐  ├──────────────────────┤
--  │ id_user     (pk) │◄──┐   │   jabatan    │       │    pegawai    │  │ id_presensi     (pk) │
--  │ username (uq)    │   │   ├──────────────┤       ├───────────────┤  │ id_pegawai      (fk) │
--  │ password         │   │   │ id_jabatan(pk)│◄──┐  │ id_pegawai(pk)│◄─┤ tanggal              │
--  │ role             │   │   │ nama_jabatan │   │  │ nip           │  │ jam_masuk            │
--  │ session_token    │   │   └──────────────┘   │  │ nama          │  │ jam_pulang           │
--  └────────┬─────────┘   │        1──n          │  │ id_jabatan(fk)├──┘ status               │
--           │             │                      └──┤ id_user   (fk)│    │ status_pulang        │
--           │ 1──1        └─────────────────────────┤ foto_profil   │    │ foto_selfie          │
--           │                                       └───────┬───────┘    │ lat                  │
--           │ n                                             │ 1          │ lng                  │
--  ┌────────┴─────────┐                                     │            │ metode_absen         │
--  │webauthn_cred     │                          ┌──────────┼──────────┐ └──────────────────────┘
--  ├──────────────────┤                          │ n        │        n │
--  │ id_user     (pk) │                    ┌─────┴─────┐   ┌┴──────────────┐
--  │ credential_id    │                    │  logbook  │   │ pengajuan_izin │
--  └──────────────────┘                    ├───────────┤   ├────────────────┤
--                                          │id_logbook │   │ id_izin   (pk) │
--                                          │id_pegawai │   │ id_pegawai(fk) │
--                                          │tanggal    │   │ jenis_izin     │
--                                          │uraian_keg │   │ tanggal_mulai  │
--                                          └─────┬─────┘   │ tanggal_selesai│
--                                                │         │ keterangan     │
--                                                │ 1       │ status_valid   │
--                                                ▼         └───────┬────────┘
--                                          ┌─────┴─────────────┐   │
--                                          │logbook_attachments│   │ 1
--                                          ├───────────────────┤   ▼
--                                          │id_attachment (pk) │ ┌─┴───────────────┐
--                                          │id_logbook    (fk) │ │izin_attachments │
--                                          │file_name          │ ├─────────────────┤
--                                          │file_type          │ │id_attachment(pk)│
--                                          │file_data          │ │id_izin      (fk)│
--                                          └───────────────────┘ │file_name        │
--                                                                │file_type        │
--                                                                │file_data        │
--                                                                └─────────────────┘
--
-- penjelasan kolom penting
-- ========================
--
-- nip (nomor induk pegawai):
--   - terletak di tabel `pegawai`, kolom `nip` (varchar 20).
--   - merupakan identitas resmi pegawai desa (bukan primary key).
--   - primary key tabel pegawai adalah `id_pegawai` (auto-increment).
--   - nip diinput oleh admin saat menambahkan data pegawai baru.
--
-- jabatan:
--   - tabel `jabatan` menyimpan daftar jabatan (kepala desa, sekretaris, dll).
--   - kolom `id_jabatan` di tabel `pegawai` adalah fk ke tabel ini.
--
-- role (peran pengguna):
--   - disimpan di tabel `users`, kolom `role` (enum).
--   - ada 3 role: 'admin', 'kades', 'pegawai'.
--
-- password:
--   - di-hash menggunakan sha-256 (sebelumnya md5) demi keamanan.
--   - disimpan di tabel `users`, kolom `password` (varchar 255).
--
-- session_token:
--   - digunakan untuk menampung token sesi aktif guna mendeteksi login ganda (single session).
--
-- foto_profil & foto_selfie:
--   - disimpan langsung dalam tipe longtext menggunakan format data-uri / base64.
--
-- koordinat (lat & lng):
--   - mencatat posisi geografis pegawai saat melakukan presensi (jika gps diaktifkan).
--
-- status presensi & pulang:
--   - status: 'tepat waktu' / 'terlambat'
--   - status_pulang: 'tepat waktu' / 'lebih dulu' / 'otomatis'
--
-- on delete cascade:
--   - jika user dihapus → pegawai terkait ikut terhapus.
--   - jika pegawai dihapus → semua presensi, logbook, dan pengajuan_izin terkait ikut terhapus.
--   - jika jabatan dihapus → pegawai terkait di-set null.
--
-- ============================================================

-- schema will be executed inside the connected database.

-- ============================================================
-- tabel 1: users
-- menyimpan kredensial login pengguna sistem.
-- setiap pengguna memiliki satu role (admin/kades/pegawai).
-- referensi: pdf halaman 19 - data dictionary tabel users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id_user       INT AUTO_INCREMENT PRIMARY KEY,   -- pk, auto-increment
    username      VARCHAR(50) UNIQUE NOT NULL,       -- username unik untuk login
    password      VARCHAR(255) NOT NULL,             -- password (hash sha-256)
    role          ENUM('Admin', 'Kades', 'Pegawai') NOT NULL,  -- hak akses
    session_token VARCHAR(64) DEFAULT NULL           -- token sesi aktif untuk single-session enforcement
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 2: jabatan
-- menyimpan daftar jabatan/tunjangan pegawai desa.
-- referensi: pdf halaman 18 - entitas #3 (jabatan)
-- ============================================================
CREATE TABLE IF NOT EXISTS jabatan (
    id_jabatan   INT AUTO_INCREMENT PRIMARY KEY,  -- PK, auto-increment
    nama_jabatan VARCHAR(100) NOT NULL             -- Nama jabatan
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 3: pegawai
-- menyimpan data identitas pegawai desa.
-- kolom `nip` berisi nomor induk pegawai (identitas resmi).
-- kolom `id_jabatan` menghubungkan pegawai ke jabatannya.
-- kolom `id_user` menghubungkan pegawai ke akun login-nya.
-- semua role (admin, kades, pegawai) memiliki record di sini.
-- referensi: pdf halaman 20 - data dictionary tabel pegawai
-- ============================================================
CREATE TABLE IF NOT EXISTS pegawai (
    id_pegawai INT AUTO_INCREMENT PRIMARY KEY,  -- PK, auto-increment
    nip        VARCHAR(20) NOT NULL,            -- Nomor Induk Pegawai
    nama       VARCHAR(100) NOT NULL,           -- Nama lengkap pegawai
    id_jabatan INT DEFAULT NULL,                -- FK ke tabel jabatan
    id_user    INT,                             -- FK ke tabel users
    foto_profil LONGTEXT DEFAULT NULL,      -- data-uri foto profil base64
    FOREIGN KEY (id_jabatan) REFERENCES jabatan(id_jabatan) ON DELETE SET NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 4: presensi
-- menyimpan catatan kehadiran harian setiap pegawai.
-- satu pegawai memiliki maksimal satu record per tanggal.
-- referensi: pdf halaman 21 - data dictionary tabel presensi
-- ============================================================
CREATE TABLE IF NOT EXISTS presensi (
    id_presensi INT AUTO_INCREMENT PRIMARY KEY, -- PK, auto-increment
    id_pegawai  INT NOT NULL,                   -- FK ke tabel pegawai
    tanggal     DATE NOT NULL,                  -- Tanggal presensi
    jam_masuk   TIME DEFAULT NULL,              -- Waktu absen masuk
    jam_pulang  TIME DEFAULT NULL,              -- Waktu absen pulang
    status      VARCHAR(20) DEFAULT 'Hadir',    -- 'Tepat Waktu' / 'Terlambat'
    status_pulang VARCHAR(20) DEFAULT NULL,     -- 'Tepat Waktu' / 'Lebih Dulu' / 'Otomatis'
    foto_selfie LONGTEXT DEFAULT NULL,      -- data-uri foto selfie base64
    lat         DECIMAL(10,8) DEFAULT NULL,     -- latitude
    lng         DECIMAL(11,8) DEFAULT NULL,     -- Longitude
    metode_absen VARCHAR(50) DEFAULT NULL,      -- 'QR', 'WebAuthn', dll
    FOREIGN KEY (id_pegawai) REFERENCES pegawai(id_pegawai) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 5: logbook (sprint 2 - struktur disiapkan)
-- catatan kegiatan harian pegawai.
-- referensi: pdf halaman 22 - data dictionary tabel logbook
-- ============================================================
CREATE TABLE IF NOT EXISTS logbook (
    id_logbook      INT AUTO_INCREMENT PRIMARY KEY, -- PK, auto-increment
    id_pegawai      INT NOT NULL,                   -- FK ke tabel pegawai
    tanggal         DATE NOT NULL,                  -- Tanggal kegiatan
    uraian_kegiatan TEXT NOT NULL,                   -- Deskripsi pekerjaan
    FOREIGN KEY (id_pegawai) REFERENCES pegawai(id_pegawai) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 6: pengajuan_izin (sprint 2 - struktur disiapkan)
-- data pengajuan cuti/sakit/izin dinas pegawai.
-- referensi: pdf halaman 23 - data dictionary tabel pengajuan izin
-- ============================================================
CREATE TABLE IF NOT EXISTS pengajuan_izin (
    id_izin          INT AUTO_INCREMENT PRIMARY KEY,                  -- PK
    id_pegawai       INT NOT NULL,                                    -- FK
    jenis_izin       ENUM('Sakit', 'Cuti', 'Izin Dinas') NOT NULL,   -- Jenis
    tanggal_mulai    DATE NOT NULL,                                   -- Mulai
    tanggal_selesai  DATE NOT NULL,                                   -- Selesai
    keterangan       TEXT,                                            -- Alasan
    status_validasi  ENUM('Pending', 'Disetujui', 'Ditolak')         -- Status
                     NOT NULL DEFAULT 'Pending',
    FOREIGN KEY (id_pegawai) REFERENCES pegawai(id_pegawai) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 7: app_settings (sprint 2 security)
-- pengaturan global keamanan presensi
-- setting_key yang ada: qr_login, selfie_validation, location_logging, webauthn, qr_refresh_duration, last_data_update, jam_masuk_batas, jam_pulang_mulai, allow_pc_attendance, allow_pc_qr_scan
-- ============================================================
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value LONGTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('qr_login', '1'),
('selfie_validation', '1'),
('location_logging', '1'),
('webauthn', '0'),
('qr_refresh_duration', '30'),
('last_data_update', '0'),
('jam_masuk_batas', '07:00'),
('jam_pulang_mulai', '16:00'),
('allow_pc_attendance', '1'),
('allow_pc_qr_scan', '0'),
('app_name', 'Presensi Desa'),
('app_footer_text', '&copy; 2026 Sistem Kehadiran Desa'),
('app_footer_link', 'rpl.iamsochronically.online'),
('max_attachment_size', '2'),
('skip_keamanan_pulang', '1'),
('app_logo', '');

-- ============================================================
-- tabel 8: qr_tokens (sprint 2 security)
-- token qr global dinamis
-- ============================================================
CREATE TABLE IF NOT EXISTS qr_tokens (
    id_token INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) UNIQUE NOT NULL,
    created_at DATETIME NOT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 9: webauthn_credentials (sprint 2 security)
-- kredensial unik device per user
-- ============================================================
CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id_user INT PRIMARY KEY,
    credential_id TEXT NOT NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 10: logbook_attachments (sprint 2 attachment)
-- lampiran berkas pendukung logbook kegiatan
-- ============================================================
CREATE TABLE IF NOT EXISTS logbook_attachments (
    id_attachment INT AUTO_INCREMENT PRIMARY KEY,
    id_logbook    INT NOT NULL,
    file_name     VARCHAR(255) NOT NULL,
    file_type     VARCHAR(100) NOT NULL,
    file_data     LONGTEXT NOT NULL,
    FOREIGN KEY (id_logbook) REFERENCES logbook(id_logbook) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- tabel 11: izin_attachments (sprint 2 attachment)
-- lampiran berkas pendukung pengajuan izin
-- ============================================================
CREATE TABLE IF NOT EXISTS izin_attachments (
    id_attachment INT AUTO_INCREMENT PRIMARY KEY,
    id_izin       INT NOT NULL,
    file_name     VARCHAR(255) NOT NULL,
    file_type     VARCHAR(100) NOT NULL,
    file_data     LONGTEXT NOT NULL,
    FOREIGN KEY (id_izin) REFERENCES pengajuan_izin(id_izin) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- data jabatan
-- daftar jabatan struktural pemerintah desa.
-- setiap jabatan bisa dimiliki oleh lebih dari satu pegawai.
-- kolom id_jabatan digunakan sebagai fk di tabel pegawai.
-- ============================================================
-- id_jabatan | nama_jabatan       | keterangan
-- -----------|--------------------|------------------------------------------
-- 1          | kepala desa        | pemimpin tertinggi pemerintahan desa
-- 2          | sekretaris desa    | koordinator administrasi & tata usaha desa
-- 3          | kaur umum          | kepala urusan umum & pelayanan
-- 4          | kaur keuangan      | kepala urusan keuangan & anggaran desa
-- 5          | kasi pemerintahan  | kepala seksi urusan pemerintahan & hukum
-- 6          | kasi kesejahteraan | kepala seksi urusan sosial & kesejahteraan
-- 7          | staf               | staf pelaksana umum (default untuk pegawai biasa)
-- ============================================================
INSERT INTO jabatan (nama_jabatan) VALUES
('Kepala Desa'),        -- id_jabatan = 1 | Pemimpin tertinggi desa
('Sekretaris Desa'),    -- id_jabatan = 2 | Koordinator administrasi desa
('Kaur Umum'),          -- id_jabatan = 3 | Kepala Urusan Umum
('Kaur Keuangan'),      -- id_jabatan = 4 | Kepala Urusan Keuangan
('Kasi Pemerintahan'),  -- id_jabatan = 5 | Kepala Seksi Pemerintahan
('Kasi Kesejahteraan'), -- id_jabatan = 6 | Kepala Seksi Kesejahteraan
('Staf');               -- id_jabatan = 7 | Staf pelaksana umum

-- ============================================================
-- data pengguna
-- ============================================================
-- id_user | username  | role     | password
-- --------|-----------|----------|---------------------------
-- 1       | admin     | admin    | admin
-- 2       | kades     | kades    | kades
-- 3       | pegawai1  | pegawai  | pegawai1
-- 4       | pegawai2  | pegawai  | pegawai2
-- ============================================================
INSERT INTO users (username, password, role) VALUES
('admin',    SHA2('admin', 256), 'Admin'),
('kades',    SHA2('kades', 256), 'Kades'),
('pegawai1', SHA2('pegawai1', 256), 'Pegawai'),
('pegawai2', SHA2('pegawai2', 256), 'Pegawai');

-- ============================================================
-- data pegawai
-- semua user (admin, kades, pegawai) memiliki record pegawai.
-- kolom id_jabatan mengacu ke tabel jabatan.
-- ============================================================
-- id_pegawai | nip        | nama           | id_jabatan | id_user
-- -----------|------------|----------------|------------|--------
-- 1          | 198503001  | Admin          | 3 (kaur)   | 1
-- 2          | 197801001  | Kades          | 1 (kades)  | 2
-- 3          | 198001001  | Pegawai Satu   | 7 (staf)   | 3
-- 4          | 198001002  | Pegawai Dua    | 7 (staf)   | 4
-- ============================================================
INSERT INTO pegawai (nip, nama, id_jabatan, id_user) VALUES
('198503001', 'Admin',         3, 1),
('197801001', 'Kades',         1, 2),
('198001001', 'Pegawai Satu',  7, 3),
('198001002', 'Pegawai Dua',   7, 4);

-- ============================================================
-- data dummy presensi (untuk testing)
-- beberapa hari kehadiran pegawai sebagai contoh.
-- ============================================================
INSERT INTO presensi (id_pegawai, tanggal, jam_masuk, jam_pulang, status) VALUES
-- 5 mei 2026
(3, '2026-05-05', '06:48:00', '16:02:00', 'Tepat Waktu'),
(4, '2026-05-05', '06:55:00', '16:10:00', 'Tepat Waktu'),
-- 6 mei 2026
(3, '2026-05-06', '07:15:00', '16:30:00', 'Terlambat'),
(4, '2026-05-06', '06:45:00', '16:00:00', 'Tepat Waktu'),
-- 7 mei 2026
(3, '2026-05-07', '06:50:00', '16:00:00', 'Tepat Waktu'),
(4, '2026-05-07', '06:40:00', NULL,       'Tepat Waktu');
