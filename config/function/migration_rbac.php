<?php
global $conn;

try {
    // 1. Buat tabel roles
    $conn->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id_role INT AUTO_INCREMENT PRIMARY KEY,
            nama_role VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB;
    ");

    // Tambahkan kolom is_admin jika belum ada
    $check_roles_admin = $conn->query("SHOW COLUMNS FROM roles LIKE 'is_admin'")->fetch();
    if (!$check_roles_admin) {
        $conn->exec("ALTER TABLE roles ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER nama_role");
    }

    // 2. Isi default roles
    $conn->exec("
        INSERT IGNORE INTO roles (id_role, nama_role, is_admin) VALUES
        (1, 'Super Admin', 1),
        (2, 'Admin Gudang', 0),
        (3, 'Stock Keeper', 0),
        (4, 'Staff', 0),
        (5, 'SPV / Management', 0);
    ");

    // Pastikan Super Admin terupdate jika kolom baru ditambahkan
    $conn->exec("UPDATE roles SET is_admin = 1 WHERE id_role = 1");

    // 3. Tambahkan kolom id_role ke tabel users jika belum ada
    $check_user_role = $conn->query("SHOW COLUMNS FROM users LIKE 'id_role'")->fetch();
    if (!$check_user_role) {
        $conn->exec("ALTER TABLE users ADD COLUMN id_role INT DEFAULT NULL AFTER password");
        
        // Map data lama: admin -> Super Admin (1), user -> Staff (4)
        $conn->exec("UPDATE users SET id_role = 1 WHERE role = 'admin'");
        $conn->exec("UPDATE users SET id_role = 4 WHERE role = 'user' OR role IS NULL");
        
        // Tambahkan Foreign Key
        $conn->exec("ALTER TABLE users ADD CONSTRAINT fk_user_role FOREIGN KEY (id_role) REFERENCES roles(id_role) ON DELETE SET NULL");
    }

    // 4. Ubah struktur role_menu agar mengacu ke id_role (bukan id_user)
    // Cek apakah role_menu masih mengacu ke id_user
    $check_rm_user = $conn->query("SHOW COLUMNS FROM role_menu LIKE 'id_user'")->fetch();
    if ($check_rm_user) {
        // Drop table lama dan buat baru dengan relasi id_role
        $conn->exec("DROP TABLE IF EXISTS role_menu");
        $conn->exec("
            CREATE TABLE role_menu (
                id_rm INT AUTO_INCREMENT PRIMARY KEY,
                id_role INT NOT NULL,
                id_smu INT NOT NULL,
                can_view TINYINT(1) DEFAULT 1,
                can_create TINYINT(1) DEFAULT 0,
                can_edit TINYINT(1) DEFAULT 0,
                can_delete TINYINT(1) DEFAULT 0,
                can_print TINYINT(1) DEFAULT 0,
                UNIQUE KEY unique_role_smu (id_role, id_smu),
                FOREIGN KEY (id_role) REFERENCES roles(id_role) ON DELETE CASCADE,
                FOREIGN KEY (id_smu) REFERENCES sub_menu(id_smu) ON DELETE CASCADE
            ) ENGINE=InnoDB;
        ");

        // Otomatis berikan hak akses penuh ke Super Admin (role 1) untuk semua menu
        $conn->exec("
            INSERT IGNORE INTO role_menu (id_role, id_smu, can_view, can_create, can_edit, can_delete, can_print)
            SELECT 1, id_smu, 1, 1, 1, 1, 1 FROM sub_menu;
        ");
    }

    // 4.b. Buat tabel user_menu untuk custom user overrides jika belum ada
    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_menu (
            id_um INT AUTO_INCREMENT PRIMARY KEY,
            id_user INT NOT NULL,
            id_smu INT NOT NULL,
            can_view TINYINT(1) DEFAULT 0,
            can_create TINYINT(1) DEFAULT 0,
            can_edit TINYINT(1) DEFAULT 0,
            can_delete TINYINT(1) DEFAULT 0,
            can_print TINYINT(1) DEFAULT 0,
            UNIQUE KEY unique_user_smu (id_user, id_smu),
            FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
            FOREIGN KEY (id_smu) REFERENCES sub_menu(id_smu) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // 4.c. Isi default permissions untuk roles lain (Admin Gudang, Stock Keeper, Staff, SPV) jika kosong
    $role_menu_count = $conn->query("SELECT COUNT(*) FROM role_menu WHERE id_role > 1")->fetchColumn();
    if ((int)$role_menu_count === 0) {
        $defaults = [
            2 => ['barangmasuk', 'barangmasuk/i', 'barangkeluar', 'barangkeluar/i', 'barang', 'supplier', 'supplier/i', 'outlet', 'outlet/i', 'scanner'],
            3 => ['terimabarang', 'terimabarang/i', 'pemakaian', 'pemakaian/i', 'stokoutlet'],
            4 => ['stockopname', 'stockopname/i'],
            5 => ['approvalso', 'laporan', 'laporanmasuk', 'laporankeluar', 'laporanselisih']
        ];
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT IGNORE INTO role_menu (id_role, id_smu, can_view, can_create, can_edit, can_delete, can_print)
            SELECT ?, id_smu, 1, 1, 1, 1, 1 FROM sub_menu WHERE url_smu = ?
        ");
        foreach ($defaults as $role_id => $urls) {
            foreach ($urls as $url) {
                $stmt->execute([$role_id, $url]);
            }
        }
        $conn->commit();
    }

    // 5. Pastikan default stasiun kerja / bagian terisi di database jika kosong
    $total_bagian = $conn->query("SELECT COUNT(*) FROM bagian")->fetchColumn();
    if ((int)$total_bagian === 0) {
        $conn->exec("
            INSERT INTO bagian (nama_bagian) VALUES
            ('Gudang Pusat'),
            ('Steamboat'),
            ('Yakiniku'),
            ('Barista'),
            ('Kitchen'),
            ('Kasir'),
            ('Server / Waiters')
        ");
    }

    // 6. Buat tabel audit_log jika belum ada
    $conn->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id_log      INT AUTO_INCREMENT PRIMARY KEY,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            id_user     INT,
            aksi        VARCHAR(50) NOT NULL COMMENT 'CREATE, UPDATE, DELETE',
            tabel       VARCHAR(50) NOT NULL,
            id_record   INT NOT NULL,
            deskripsi   TEXT NOT NULL COMMENT 'Menjelaskan detail aktivitas',
            FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL
        ) ENGINE=InnoDB;
    ");

    // 7. Tambahkan kolom no_so dan tipe_so ke tabel stock_opname jika belum ada
    $check_no_so = $conn->query("SHOW COLUMNS FROM stock_opname LIKE 'no_so'")->fetch();
    if (!$check_no_so) {
        $conn->exec("ALTER TABLE stock_opname ADD COLUMN no_so VARCHAR(50) AFTER id_so");
    }
    $check_tipe_so = $conn->query("SHOW COLUMNS FROM stock_opname LIKE 'tipe_so'")->fetch();
    if (!$check_tipe_so) {
        $conn->exec("ALTER TABLE stock_opname ADD COLUMN tipe_so ENUM('Gudang', 'Outlet') DEFAULT 'Outlet' AFTER no_so");
    }

    // 8. Hapus kolom stok dari tabel barang jika masih ada (karena stok bersifat transaksional)
    $check_stok = $conn->query("SHOW COLUMNS FROM barang LIKE 'stok'")->fetch();
    if ($check_stok) {
        $conn->exec("ALTER TABLE barang DROP COLUMN stok");
    }

    // 9. Hapus sub menu "Tambah ..." karena tidak diperlukan lagi (menggunakan tombol tambah di halaman daftar)
    $conn->exec("DELETE FROM sub_menu WHERE nama_smu LIKE 'Tambah %' OR nama_smu LIKE 'Input %' OR url_smu LIKE '%/i'");

    // 10. Buat tabel tipe_barang dan tambahkan ke master barang
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tipe_barang (
            id_tipe INT AUTO_INCREMENT PRIMARY KEY,
            nama_tipe VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB;
    ");
    $total_tipe = $conn->query("SELECT COUNT(*) FROM tipe_barang")->fetchColumn();
    if ((int)$total_tipe === 0) {
        $conn->exec("INSERT INTO tipe_barang (nama_tipe) VALUES ('Bahan Baku (Raw Material)'), ('Barang Jadi (Finished Good)'), ('Bahan Pembantu / Konsumabel'), ('Aset / Inventaris')");
    }
    $check_tipe = $conn->query("SHOW COLUMNS FROM barang LIKE 'id_tipe'")->fetch();
    if (!$check_tipe) {
        $conn->exec("ALTER TABLE barang ADD COLUMN id_tipe INT DEFAULT NULL AFTER kategori");
        $conn->exec("ALTER TABLE barang ADD CONSTRAINT fk_barang_tipe FOREIGN KEY (id_tipe) REFERENCES tipe_barang(id_tipe) ON DELETE SET NULL");
    }

    // 11. Tambahkan supplier_lainnya ke tabel barang_masuk
    $check_supp_lainnya = $conn->query("SHOW COLUMNS FROM barang_masuk LIKE 'supplier_lainnya'")->fetch();
    if (!$check_supp_lainnya) {
        $conn->exec("ALTER TABLE barang_masuk ADD COLUMN supplier_lainnya VARCHAR(150) DEFAULT NULL AFTER id_supplier");
    }

    // 12. Buat tabel Inventory dan integrasi menu
    $conn->exec("
        CREATE TABLE IF NOT EXISTS inventory (
            id_inventory INT AUTO_INCREMENT PRIMARY KEY,
            id_barang INT NOT NULL UNIQUE,
            stok INT DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // Hapus kolom lokasi_rak jika sudah telanjur terbuat dari versi sebelumnya
    $check_lokasi = $conn->query("SHOW COLUMNS FROM inventory LIKE 'lokasi_rak'")->fetch();
    if ($check_lokasi) {
        $conn->exec("ALTER TABLE inventory DROP COLUMN lokasi_rak");
    }

    // Sinkronisasi barang lama ke inventory
    $conn->exec("
        INSERT IGNORE INTO inventory (id_barang, stok)
        SELECT id_barang, 
        (COALESCE((SELECT SUM(qty) FROM barang_masuk WHERE id_barang = b.id_barang), 0) - 
         COALESCE((SELECT SUM(qty) FROM barang_keluar WHERE id_barang = b.id_barang), 0))
        FROM barang b
    ");

    // Buat Trigger agar inventory stok selalu update otomatis saat ada masuk/keluar
    $conn->exec("DROP TRIGGER IF EXISTS trg_barang_masuk");
    $conn->exec("
        CREATE TRIGGER trg_barang_masuk AFTER INSERT ON barang_masuk
        FOR EACH ROW
        UPDATE inventory SET stok = stok + NEW.qty WHERE id_barang = NEW.id_barang
    ");
    
    $conn->exec("DROP TRIGGER IF EXISTS trg_barang_keluar");
    $conn->exec("
        CREATE TRIGGER trg_barang_keluar AFTER INSERT ON barang_keluar
        FOR EACH ROW
        UPDATE inventory SET stok = stok - NEW.qty WHERE id_barang = NEW.id_barang
    ");

    // Daftarkan Menu Utama Inventory (di luar Master Barang)
    $check_menu_inv = $conn->query("SELECT id_menu FROM menu WHERE nama_menu = 'Inventory'")->fetchColumn();
    if (!$check_menu_inv) {
        // Masukkan ke kategori 'Transaksi Gudang' (id_kmu = 1) atau 'Master Data' (id_kmu = 2). Kita taruh di kmu=1.
        $conn->exec("INSERT INTO menu (id_kmu, id_icon, nama_menu, urutan_menu) VALUES (2, 2, 'Inventory', 4)");
        $check_menu_inv = $conn->lastInsertId();
    }

    // Daftarkan Sub Menu
    $check_inv_smu = $conn->query("SELECT id_smu FROM sub_menu WHERE url_smu = 'inventory'")->fetchColumn();
    if (!$check_inv_smu) {
        $conn->exec("INSERT INTO sub_menu (id_menu, nama_smu, url_smu, urutan_smu) VALUES ($check_menu_inv, 'Daftar Inventory', 'inventory', 1)");
    } else {
        // Update menu parent jika sebelumnya ada di master barang (id_menu = 3)
        $conn->exec("UPDATE sub_menu SET id_menu = $check_menu_inv, nama_smu = 'Daftar Inventory' WHERE url_smu = 'inventory'");
    }

    // 13. Kembalikan Menu Pemakaian (Jika sempat terhapus)
    $check_menu_pemakaian = $conn->query("SELECT id_menu FROM menu WHERE nama_menu = 'Pemakaian'")->fetchColumn();
    if (!$check_menu_pemakaian) {
        $conn->exec("INSERT INTO menu (id_kmu, id_icon, nama_menu, urutan_menu) VALUES (3, 14, 'Pemakaian', 2)");
        $check_menu_pemakaian = $conn->lastInsertId();
    }
    $check_smu_pemakaian = $conn->query("SELECT id_smu FROM sub_menu WHERE url_smu = 'pemakaian'")->fetchColumn();
    if (!$check_smu_pemakaian) {
        $conn->exec("INSERT INTO sub_menu (id_menu, nama_smu, url_smu, urutan_smu) VALUES ($check_menu_pemakaian, 'Input Pemakaian', 'pemakaian', 1)");
    }

    // 14. Tambah status pada barang_keluar untuk alur Terima Barang
    $check_status_bk = $conn->query("SHOW COLUMNS FROM barang_keluar LIKE 'status'")->fetch();
    if (!$check_status_bk) {
        $conn->exec("ALTER TABLE barang_keluar ADD COLUMN status ENUM('Pending', 'Diterima') DEFAULT 'Pending' AFTER id_user");
    }

    // 15. Trigger Terima Barang (mengubah status menjadi Diterima -> menambah stok_outlet)
    $conn->exec("DROP TRIGGER IF EXISTS trg_terima_barang");
    $conn->exec("
        CREATE TRIGGER trg_terima_barang AFTER UPDATE ON barang_keluar
        FOR EACH ROW
        BEGIN
            IF NEW.status = 'Diterima' AND OLD.status = 'Pending' THEN
                INSERT INTO stok_outlet (id_outlet, id_barang, stok) 
                VALUES (NEW.id_outlet, NEW.id_barang, NEW.qty)
                ON DUPLICATE KEY UPDATE stok = stok + NEW.qty;
            END IF;
        END;
    ");

    // 16. Trigger Pemakaian (Pemakaian -> mengurangi stok_outlet)
    $conn->exec("DROP TRIGGER IF EXISTS trg_pemakaian");
    $conn->exec("
        CREATE TRIGGER trg_pemakaian AFTER INSERT ON pemakaian
        FOR EACH ROW
        BEGIN
            UPDATE stok_outlet SET stok = stok - NEW.qty 
            WHERE id_outlet = NEW.id_outlet AND id_barang = NEW.id_barang;
        END;
    ");

} catch (Exception $e) {
    // Tulis error log jika gagal
    error_log('[RBAC Migration Failed] ' . $e->getMessage());
}
