# StockMate Pro

Sistem Manajemen Inventori & Gudang untuk PT PSY BERKAH INDONESIA

## 📋 Deskripsi

StockMate Pro adalah aplikasi web berbasis PHP untuk mengelola inventory/gudang dengan fitur lengkap meliputi transaksi barang masuk/keluar, manajemen outlet, stock opname, dan sistem role-based access control (RBAC).

## ✨ Fitur Utama

### 1. Transaksi Gudang
- **Barang Masuk**: Kelola penerimaan barang dari supplier ke gudang
- **Barang Keluar**: Kelola pengiriman barang dari gudang ke outlet

### 2. Master Data
- **Master Barang**: Database barang dengan support barcode scanning
- **Supplier**: Manajemen data supplier
- **Outlet**: Manajemen data outlet/cabang
- **Departemen/Bagian**: Struktur organisasi

### 3. Manajemen Outlet
- **Terima Barang**: Penerimaan barang dari gudang ke outlet
- **Input Pemakaian**: Pencatatan pemakaian barang di outlet
- **Monitor Stok**: Real-time monitoring stok per outlet

### 4. Stock Opname
- **Input Stok Akhir**: Pencatatan stok harian
- **Sistem Approval**: Verifikasi selisih stok oleh manager
- **Tracking**: Monitoring stok awal vs akhir dengan selisih otomatis

### 5. Monitoring & Laporan
- **Dashboard**: Visualisasi data dengan chart interaktif
- **Laporan Stok**: Laporan stok gudang dan outlet
- **Laporan Transaksi**: Laporan barang masuk dan keluar
- **Analisis Selisih**: Analisis perbedaan stok
- **Alert Stok Minimum**: Notifikasi otomatis untuk stok rendah

### 6. Sistem RBAC (Role-Based Access Control)
- **Role Admin & User**: Pembagian hak akses
- **Menu Dinamis**: Hak akses menu per user/role
- **Manajemen User**: Kelola user dan permissions

## 🛠️ Teknologi

### Backend
- **PHP** (PDO untuk database)
- **MySQL/MariaDB**

### Frontend
- **Tailwind CSS** - Modern utility-first CSS framework
- **Alpine.js** - Lightweight JavaScript framework
- **ApexCharts** - Interactive charts
- **SweetAlert2** - Beautiful alerts
- **Font Awesome** - Icon library

### Keamanan
- Session-based authentication
- Password hashing (bcrypt)
- Prepared statements (SQL injection prevention)
- Input sanitization

## 📦 Instalasi

### Persyaratan
- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau MariaDB 10.3+
- Web server (Apache/Nginx)
- Extension PHP: PDO, pdo_mysql

### Langkah Instalasi

1. **Clone repository**
   ```bash
   git clone https://github.com/fthrzkamr/ims_project-work.git
   cd ims_project-work
   ```

2. **Setup Database**
   - Buat database baru dengan nama `stockmate_pro`
   - Import file SQL:
     ```bash
     mysql -u root -p stockmate_pro < sql/stockmate_pro.sql
     ```

3. **Konfigurasi Database**
   - Edit file `config/connection/connection.php`
   - Sesuaikan kredensial database:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'stockmate_pro');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

4. **Setup Web Server**
   - Arahkan document root ke folder project
   - Untuk XAMPP: letakkan di `C:\xampp\htdocs\project_work`
   - Pastikan `.htaccess` aktif (mod_rewrite enabled)

5. **Akses Aplikasi**
   - Buka browser: `http://localhost/project_work`
   - Login default:
     - Username: `admin`
     - Password: `admin123`

## 🗂️ Struktur Project

```
project_work/
├── assets/              # CSS & JavaScript files
│   ├── css/
│   └── js/
├── config/              # Configuration files
│   ├── connection/      # Database connection
│   ├── frame/           # Layout components (sidebar, content)
│   └── function/        # Helper functions & migrations
├── content/             # Content modules
│   ├── account/         # User account management
│   ├── administrator/   # User administration
│   ├── approvalso/      # Stock opname approval
│   ├── barang/          # Master barang
│   ├── barangkeluar/    # Outbound transactions
│   ├── barangmasuk/     # Inbound transactions
│   ├── hakakses/        # Access rights management
│   ├── home/            # Dashboard
│   ├── inventory/       # Inventory management
│   ├── laporan/         # Reports
│   ├── outlet/          # Outlet management
│   ├── pemakaian/       # Usage records
│   ├── scanner/         # Barcode scanner
│   ├── stockopname/     # Stock opname
│   ├── stokoutlet/      # Outlet stock
│   ├── supplier/        # Supplier management
│   └── terimabarang/    # Goods receipt
├── sql/                 # Database schema & migrations
├── .htaccess            # Apache rewrite rules
├── index.php            # Main entry point
├── signin.php           # Login page
├── signout.php          # Logout handler
└── sistem.php           # Main system file
```

## 🔐 Keamanan

- Password di-hash menggunakan bcrypt
- Semua query menggunakan prepared statements
- Session management dengan timeout
- Input sanitization dan validation
- CSRF protection (dalam development)

## 📱 Fitur Tambahan

### Barcode Scanner
- Support untuk scan barcode barang
- Integrasi dengan transaksi

### Notifikasi Real-time
- Alert stok minimum
- Notifikasi approval pending
- Badge counter untuk menu

### Export Laporan
- Export ke berbagai format (dalam development)
- Laporan custom per periode

## 🚀 Development

### Menambah Modul Baru
1. Buat folder di `content/nama_modul/`
2. Buat file `nama_modul.php` untuk list
3. Buat file `input.php` untuk form input
4. Buat file `edit.php` untuk form edit
5. Tambahkan menu di database (tabel `menu` & `sub_menu`)

### Database Migration
- File migration ada di `config/function/migration_rbac.php`
- Auto-sync permissions untuk admin

## 🤝 Kontribusi

Kontribusi sangat diterima! Silakan:
1. Fork repository
2. Buat branch fitur (`git checkout -b fitur-baru`)
3. Commit perubahan (`git commit -m 'Tambah fitur baru'`)
4. Push ke branch (`git push origin fitur-baru`)
5. Buat Pull Request

## 📝 Lisensi

Proprietary - PT PSY BERKAH INDONESIA

## 👥 Tim Pengembang

- **Developer**: fthrzkamr
- **Company**: PT PSY BERKAH INDONESIA

## 📞 Kontak & Dukungan

Untuk pertanyaan atau dukungan, silakan hubungi tim IT Support.

---

**StockMate Pro** © 2026 PT PSY BERKAH INDONESIA