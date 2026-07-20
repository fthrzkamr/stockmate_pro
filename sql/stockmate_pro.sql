-- ============================================================
-- StockMate Pro — Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS stockmate_pro
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE stockmate_pro;

-- ── TABEL MASTER ──────────────────────────────────────────

-- Kategori Menu (grouping sidebar)
CREATE TABLE IF NOT EXISTS kategori_menu (
    id_kmu      INT AUTO_INCREMENT PRIMARY KEY,
    nama_kmu    VARCHAR(100) NOT NULL,
    urutan_kmu  INT DEFAULT 0
) ENGINE=InnoDB;

-- Icon (Font Awesome class)
CREATE TABLE IF NOT EXISTS icon (
    id_icon     INT AUTO_INCREMENT PRIMARY KEY,
    nama_icon   VARCHAR(100) NOT NULL COMMENT 'e.g. fa-warehouse'
) ENGINE=InnoDB;

-- Menu (parent menu)
CREATE TABLE IF NOT EXISTS menu (
    id_menu     INT AUTO_INCREMENT PRIMARY KEY,
    id_kmu      INT NOT NULL,
    id_icon     INT DEFAULT NULL,
    nama_menu   VARCHAR(100) NOT NULL,
    urutan_menu INT DEFAULT 0,
    FOREIGN KEY (id_kmu)  REFERENCES kategori_menu(id_kmu) ON DELETE CASCADE,
    FOREIGN KEY (id_icon) REFERENCES icon(id_icon) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Sub Menu (item yang di-klik, punya URL)
CREATE TABLE IF NOT EXISTS sub_menu (
    id_smu      INT AUTO_INCREMENT PRIMARY KEY,
    id_menu     INT NOT NULL,
    nama_smu    VARCHAR(100) NOT NULL,
    url_smu     VARCHAR(100) NOT NULL COMMENT 'clean URL, e.g. barangmasuk',
    urutan_smu  INT DEFAULT 0,
    FOREIGN KEY (id_menu) REFERENCES menu(id_menu) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Bagian (Departemen/Divisi)
CREATE TABLE IF NOT EXISTS bagian (
    id_bagian   INT AUTO_INCREMENT PRIMARY KEY,
    nama_bagian VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id_user     INT AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(100) NOT NULL,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','user') DEFAULT 'user' COMMENT 'admin = bisa kelola menu & user',
    outlet_id   INT DEFAULT NULL,
    id_bagian   INT DEFAULT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_bagian) REFERENCES bagian(id_bagian) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Role Menu (assign sub_menu ke user)
CREATE TABLE IF NOT EXISTS role_menu (
    id_rm       INT AUTO_INCREMENT PRIMARY KEY,
    id_user     INT NOT NULL,
    id_smu      INT NOT NULL,
    UNIQUE KEY unique_user_smu (id_user, id_smu),
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_smu)  REFERENCES sub_menu(id_smu) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── TABEL TRANSAKSI ───────────────────────────────────────

-- Supplier
CREATE TABLE IF NOT EXISTS supplier (
    id_supplier     INT AUTO_INCREMENT PRIMARY KEY,
    nama_supplier   VARCHAR(150) NOT NULL,
    alamat          TEXT,
    telepon         VARCHAR(20),
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Outlet
CREATE TABLE IF NOT EXISTS outlet (
    id_outlet   INT AUTO_INCREMENT PRIMARY KEY,
    nama_outlet VARCHAR(150) NOT NULL,
    alamat      TEXT,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Master Barang
CREATE TABLE IF NOT EXISTS barang (
    id_barang   INT AUTO_INCREMENT PRIMARY KEY,
    barcode     VARCHAR(100) UNIQUE,
    nama_barang VARCHAR(200) NOT NULL,
    kategori    VARCHAR(100),
    satuan      VARCHAR(50),
    min_stok    INT DEFAULT 0,
    id_supplier INT DEFAULT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Barang Masuk (dari Supplier ke Gudang)
CREATE TABLE IF NOT EXISTS barang_masuk (
    id_masuk    INT AUTO_INCREMENT PRIMARY KEY,
    tanggal     DATE NOT NULL,
    id_supplier INT,
    id_barang   INT NOT NULL,
    qty         INT NOT NULL,
    keterangan  TEXT,
    id_user     INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier) ON DELETE SET NULL,
    FOREIGN KEY (id_barang)   REFERENCES barang(id_barang) ON DELETE CASCADE,
    FOREIGN KEY (id_user)     REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Barang Keluar (dari Gudang ke Outlet)
CREATE TABLE IF NOT EXISTS barang_keluar (
    id_keluar   INT AUTO_INCREMENT PRIMARY KEY,
    tanggal     DATE NOT NULL,
    id_outlet   INT,
    id_barang   INT NOT NULL,
    qty         INT NOT NULL,
    keterangan  TEXT,
    id_user     INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_outlet)  REFERENCES outlet(id_outlet) ON DELETE SET NULL,
    FOREIGN KEY (id_barang)  REFERENCES barang(id_barang) ON DELETE CASCADE,
    FOREIGN KEY (id_user)    REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Stok Outlet (current stock per outlet per item)
CREATE TABLE IF NOT EXISTS stok_outlet (
    id_stok     INT AUTO_INCREMENT PRIMARY KEY,
    id_outlet   INT NOT NULL,
    id_barang   INT NOT NULL,
    stok        INT DEFAULT 0,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_outlet_barang (id_outlet, id_barang),
    FOREIGN KEY (id_outlet) REFERENCES outlet(id_outlet) ON DELETE CASCADE,
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pemakaian Barang Outlet
CREATE TABLE IF NOT EXISTS pemakaian (
    id_pemakaian INT AUTO_INCREMENT PRIMARY KEY,
    tanggal      DATE NOT NULL,
    id_outlet    INT,
    id_barang    INT NOT NULL,
    qty          INT NOT NULL,
    keterangan   TEXT,
    id_user      INT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_outlet) REFERENCES outlet(id_outlet) ON DELETE SET NULL,
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE CASCADE,
    FOREIGN KEY (id_user)   REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Stock Opname Harian
CREATE TABLE IF NOT EXISTS stock_opname (
    id_so            INT AUTO_INCREMENT PRIMARY KEY,
    tanggal          DATE NOT NULL,
    id_outlet        INT,
    id_barang        INT NOT NULL,
    id_user          INT,
    stok_awal        INT DEFAULT 0,
    stok_akhir       INT DEFAULT 0,
    selisih          INT GENERATED ALWAYS AS (stok_akhir - stok_awal) STORED,
    status_approval  ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    catatan_reject   TEXT,
    approved_by      INT DEFAULT NULL,
    approved_at      TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_outlet)   REFERENCES outlet(id_outlet) ON DELETE SET NULL,
    FOREIGN KEY (id_barang)   REFERENCES barang(id_barang) ON DELETE CASCADE,
    FOREIGN KEY (id_user)     REFERENCES users(id_user) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── SEED DATA ─────────────────────────────────────────────

-- Bagian
INSERT INTO bagian (nama_bagian) VALUES
('Gudang Pusat'),
('Outlet'),
('HRD'),
('Keuangan'),
('IT Support'),
('Manajemen');

-- Icons
INSERT INTO icon (nama_icon) VALUES
('fa-gauge-high'), ('fa-warehouse'), ('fa-truck-ramp-box'), ('fa-truck-fast'),
('fa-barcode'), ('fa-industry'), ('fa-store'), ('fa-clipboard-list'),
('fa-check-circle'), ('fa-chart-line'), ('fa-users-cog'), ('fa-key'),
('fa-box-open'), ('fa-minus-square'), ('fa-clock-rotate-left'),
('fa-triangle-exclamation'), ('fa-file-export'), ('fa-qrcode');

-- Kategori Menu
INSERT INTO kategori_menu (nama_kmu, urutan_kmu) VALUES
('Transaksi Gudang', 1),
('Master Data',      2),
('Outlet',           3),
('Stock Opname',     4),
('Monitoring',       5),
('Laporan',          6),
('Pengaturan',       7);

-- Menu
INSERT INTO menu (id_kmu, id_icon, nama_menu, urutan_menu) VALUES
(1, 3, 'Barang Masuk',    1),
(1, 4, 'Barang Keluar',   2),
(2, 5, 'Master Barang',   1),
(2, 6, 'Supplier',        2),
(2, 3, 'Outlet',          3),
(3, 13,'Terima Barang',   1),
(3, 14,'Pemakaian',       2),
(3, 7, 'Stok Outlet',     3),
(4, 8, 'Stock Opname',    1),
(5, 9, 'Approval SO',     1),
(5, 10,'Laporan',         2),
(5, 16,'Selisih Stok',    3),
(6, 17,'Cetak Laporan',   1),
(7, 11,'Administrator',   1),
(7, 12,'Hak Akses',       2),
(7, 2, 'Scanner',         3);

-- Sub Menu
INSERT INTO sub_menu (id_menu, nama_smu, url_smu, urutan_smu) VALUES
(1,  'Daftar Barang Masuk',  'barangmasuk',     1),
(1,  'Tambah Barang Masuk',  'barangmasuk/i',   2),
(2,  'Daftar Barang Keluar', 'barangkeluar',    1),
(2,  'Tambah Barang Keluar', 'barangkeluar/i',  2),
(3,  'Daftar Barang',        'barang',          1),
(3,  'Tambah Barang',        'barang/i',        2),
(4,  'Daftar Supplier',      'supplier',        1),
(4,  'Tambah Supplier',      'supplier/i',      2),
(5,  'Daftar Outlet',        'outlet',          1),
(5,  'Tambah Outlet',        'outlet/i',        2),
(6,  'Terima Barang',        'terimabarang',    1),
(6,  'Tambah Penerimaan',    'terimabarang/i',  2),
(7,  'Input Pemakaian',      'pemakaian',       1),
(7,  'Tambah Pemakaian',     'pemakaian/i',     2),
(8,  'Stok Outlet',          'stokoutlet',      1),
(9,  'Daftar Stock Opname',  'stockopname',     1),
(9,  'Input Stok Akhir',     'stockopname/i',   2),
(10, 'Approval Stock Opname','approvalso',      1),
(11, 'Laporan Stok',         'laporan',         1),
(11, 'Laporan Masuk',        'laporanmasuk',    2),
(11, 'Laporan Keluar',       'laporankeluar',   3),
(12, 'Selisih Stok',         'laporanselisih',  1),
(13, 'Cetak Laporan',        'laporan',         1),
(14, 'Data User',            'administrator',   1),
(14, 'Tambah User',          'administrator/i', 2),
(15, 'Hak Akses',            'hakakses',        1),
(16, 'Scanner Barcode',      'scanner',         1);

-- Default Admin User (password: admin123)
INSERT INTO users (nama, username, password, role, is_active) VALUES
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Assign semua menu ke admin
INSERT INTO role_menu (id_user, id_smu)
SELECT (SELECT id_user FROM users WHERE username = 'admin' LIMIT 1), id_smu FROM sub_menu;

-- Kategori Barang
CREATE TABLE IF NOT EXISTS kategori_barang (
    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

INSERT INTO kategori_barang (id_kategori, nama_kategori) VALUES
(1, '1'),
(2, '2'),
(3, '3');

-- Tipe Barang
CREATE TABLE IF NOT EXISTS tipe_barang (
    id_tipe INT AUTO_INCREMENT PRIMARY KEY,
    id_kategori INT DEFAULT NULL,
    nama_tipe VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_kategori) REFERENCES kategori_barang(id_kategori) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO tipe_barang (id_tipe, id_kategori, nama_tipe) VALUES
(1, 1, 'Bahan Baku Makanan'),
(2, 1, 'Bahan Minuman'),
(3, 2, 'Alat Rumah Tangga'),
(4, 2, 'Peralatan Makan'),
(5, 3, 'Kimia & Pembersih');
