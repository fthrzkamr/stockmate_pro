<?php
if (!canDo('barang', 'edit')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk mengubah data barang.</div>";
    exit;
}

global $conn, $sistem;

$id_barang = (int)($_GET['id'] ?? $_GET['keycode'] ?? 0);
if (!$id_barang) {
    echo "<script>window.location.href='$sistem/barang';</script>";
    exit;
}

// Ambil data barang
try {
    $st = $conn->prepare("SELECT * FROM barang WHERE id_barang = ?");
    $st->execute([$id_barang]);
    $barang = $st->fetch();
} catch (Exception $e) {
    $barang = null;
}

if (!$barang) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Barang tidak ditemukan.</div>";
    exit;
}

// Ambil daftar supplier
try {
    $suppliers = $conn->query("SELECT * FROM supplier WHERE is_active=1 ORDER BY nama_supplier ASC")->fetchAll();
} catch (Exception $e) {
    $suppliers = [];
}

// Ambil daftar kategori barang dan tipe barang
try {
    $kategori_list = $conn->query("SELECT * FROM kategori_barang ORDER BY id_kategori ASC")->fetchAll(PDO::FETCH_ASSOC);
    $tipe_barang_list = $conn->query("SELECT * FROM tipe_barang ORDER BY id_tipe ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kategori_list = [];
    $tipe_barang_list = [];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode     = trim($_POST['barcode'] ?? '');
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $id_kat      = (int)($_POST['kategori'] ?? 0);
    $kategori    = 'Lainnya';
    foreach ($kategori_list as $k) {
        if ($k['id_kategori'] == $id_kat) {
            $kategori = $k['nama_kategori'];
            break;
        }
    }
    $satuan      = trim($_POST['satuan'] ?? '');
    $min_stok    = (int)($_POST['min_stok'] ?? 0);
    $id_supplier = (int)($_POST['id_supplier'] ?? 0) ?: null;
    $id_tipe     = (int)($_POST['id_tipe'] ?? 0) ?: null;
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if (!$nama_barang) {
        $error = 'Nama Barang wajib diisi.';
    } else {
        try {
            // Cek jika barcode diisi, apakah sudah digunakan oleh barang lain
            $duplikat = false;
            if ($barcode) {
                $cek = $conn->prepare("SELECT nama_barang FROM barang WHERE barcode = ? AND id_barang != ?");
                $cek->execute([$barcode, $id_barang]);
                $exBarang = $cek->fetchColumn();
                if ($exBarang) {
                    $duplikat = true;
                    $error = "Barcode <b>$barcode</b> sudah digunakan oleh barang: <b>$exBarang</b>";
                }
            }

            if (!$duplikat) {
                $stmt = $conn->prepare("
                    UPDATE barang 
                    SET barcode = ?, nama_barang = ?, kategori = ?, satuan = ?, min_stok = ?, id_supplier = ?, id_tipe = ?, is_active = ?
                    WHERE id_barang = ?
                ");
                $stmt->execute([
                    $barcode ?: null,
                    $nama_barang,
                    $kategori ?: 'Lainnya',
                    $satuan ?: 'Pcs',
                    $min_stok,
                    $id_supplier,
                    $id_tipe,
                    $is_active,
                    $id_barang
                ]);
                
                writeAuditLog('UPDATE', 'barang', $id_barang, "Memperbarui barang: $nama_barang (Barcode: " . ($barcode ?: '-') . ", Status: " . ($is_active ? 'Aktif' : 'Nonaktif') . ")");
                
                $_SESSION['flash_success'] = "Barang <b>$nama_barang</b> berhasil diperbarui.";
                echo "<script>window.location.href='$sistem/barang';</script>";
                exit;
            }
        } catch (Exception $e) {
            $error = 'Gagal memperbarui data: ' . $e->getMessage();
        }
    }
}
?>

<!-- Load HTML5-QRCode Library untuk mobile scanning -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<div class="fade-up max-w-2xl mx-auto space-y-5">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/barang" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Barang</h1>
            <p class="text-slate-500 text-sm">Perbarui detail item <span class="font-semibold text-sky-600"><?= sanitize($barang['nama_barang']) ?></span>.</p>
        </div>
    </div>

    <!-- Alert Error -->
    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm shadow-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0 text-red-500"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <p class="text-sm font-semibold text-slate-700">
                <i class="fa-solid fa-box-open text-sky-500 mr-2"></i>Detail Barang
            </p>
            <span class="text-xs text-slate-400 font-medium">ID: #<?= $barang['id_barang'] ?></span>
        </div>
        
        <div class="p-6 space-y-5">

            <!-- Barcode & Scanner -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Barcode / Kode EAN</label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-barcode absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="barcode" id="barcodeInput" value="<?= htmlspecialchars($_POST['barcode'] ?? $barang['barcode']) ?>"
                               placeholder="Scan barcode kemasan atau isi manual..."
                               class="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
                    </div>
                    <button type="button" onclick="startScanner()"
                            class="inline-flex items-center gap-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors">
                        <i class="fa-solid fa-qrcode"></i> Scan Kamera
                    </button>
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Gunakan pemindai kamera untuk mengganti atau memindai barcode fisik baru.</p>
            </div>

            <!-- Kamera Scanner Box (Tersembunyi) -->
            <div id="scannerBox" class="hidden bg-slate-50 border border-slate-200 rounded-2xl p-4 text-center space-y-3 relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider"><i class="fa-solid fa-camera mr-1"></i> Scanner Aktif</span>
                    <button type="button" onclick="stopScanner()" class="text-xs text-red-500 hover:text-red-700 font-semibold">Tutup Kamera</button>
                </div>
                <div id="interactiveReader" class="w-full max-w-sm mx-auto overflow-hidden rounded-xl border border-slate-200 shadow-inner"></div>
                <p class="text-[10px] text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i>Posisikan barcode produk tepat di tengah kotak pemindai.</p>
            </div>

            <!-- Nama Barang -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nama Barang <span class="text-red-500">*</span></label>
                <input type="text" name="nama_barang" value="<?= htmlspecialchars($_POST['nama_barang'] ?? $barang['nama_barang']) ?>" required
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>

            <!-- Kategori & Tipe Barang -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Kategori <span class="text-red-500">*</span></label>
                    <select name="kategori" id="editKategori" onchange="updateEditTipe()" required class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                        <option value="">— Pilih Kategori —</option>
                        <?php 
                        $curKat = $_POST['kategori'] ?? $barang['kategori'];
                        foreach ($kategori_list as $k): 
                            $sel = ($curKat == $k['nama_kategori'] || $curKat == $k['id_kategori']) ? 'selected' : '';
                        ?>
                        <option value="<?= $k['id_kategori'] ?>" <?= $sel ?>><?= sanitize($k['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tipe Barang <span class="text-red-500">*</span></label>
                    <select name="id_tipe" id="editTipe" required class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                        <option value="">— Pilih Tipe Barang —</option>
                    </select>
                </div>
            </div>

            <!-- Satuan -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Satuan</label>
                <select name="satuan" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                    <option value="Pcs" <?= (($_POST['satuan'] ?? $barang['satuan']) == 'Pcs') ? 'selected' : '' ?>>Pcs (Pcs / Biji)</option>
                    <option value="Botol" <?= (($_POST['satuan'] ?? $barang['satuan']) == 'Botol') ? 'selected' : '' ?>>Botol</option>
                    <option value="Pack" <?= (($_POST['satuan'] ?? $barang['satuan']) == 'Pack') ? 'selected' : '' ?>>Pack</option>
                    <option value="Pouch" <?= (($_POST['satuan'] ?? $barang['satuan']) == 'Pouch') ? 'selected' : '' ?>>Pouch</option>
                    <option value="Karton" <?= (($_POST['satuan'] ?? $barang['satuan']) == 'Karton') ? 'selected' : '' ?>>Karton (Dus)</option>
                    <option value="Liter" <?= (($_POST['satuan'] ?? $barang['satuan']) == 'Liter') ? 'selected' : '' ?>>Liter</option>
                    <option value="Kg" <?= (($_POST['satuan'] ?? $barang['satuan']) == 'Kg') ? 'selected' : '' ?>>Kilogram (Kg)</option>
                </select>
            </div>

            <!-- Batas Minimum Stok -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Batas Minimum Stok (Alert)</label>
                <input type="number" name="min_stok" min="0" value="<?= htmlspecialchars($_POST['min_stok'] ?? $barang['min_stok']) ?>"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>

            <!-- Supplier -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Supplier Utama</label>
                <select name="id_supplier" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                    <option value="">— Tidak Ada Supplier —</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id_supplier'] ?>" <?= (($_POST['id_supplier'] ?? $barang['id_supplier']) == $s['id_supplier']) ? 'selected' : '' ?>>
                        <?= sanitize($s['nama_supplier']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Aktif (Soft Delete Toggle) -->
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-700">Status Aktif Barang</p>
                    <p class="text-[10px] text-slate-400">Nonaktifkan barang untuk menyembunyikannya dari form transaksi.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" <?= ($_POST['is_active'] ?? $barang['is_active']) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                </label>
            </div>

        </div>

        <!-- Footer Buttons -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <a href="<?= $sistem ?>/barang"
               class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Perbarui Barang
            </button>
        </div>
    </form>
</div>

<script>
let html5QrcodeScanner = null;

function startScanner() {
    const scannerBox = document.getElementById('scannerBox');
    scannerBox.classList.remove('hidden');
    
    // Inisialisasi html5-qrcode dengan format decoder dibatasi agar pemindaian barcode 1D sangat cepat
    if (!html5QrcodeScanner) {
        html5QrcodeScanner = new Html5Qrcode("interactiveReader", {
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.QR_CODE
            ]
        });
    }
    
    const config = { 
        fps: 24, 
        qrbox: { width: 300, height: 120 }, // Kotak lebar dan tipis khusus untuk barcode produk rumah tangga
        aspectRatio: 1.777778
    };
    
    html5QrcodeScanner.start(
        { facingMode: "environment" }, 
        config,
        (decodedText, decodedResult) => {
            document.getElementById('barcodeInput').value = decodedText;
            stopScanner();
            
            Swal.fire({
                icon: 'success',
                title: 'Barcode Terbaca!',
                text: decodedText,
                timer: 1500,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        },
        (errorMessage) => {
            // Abaikan kegagalan baca per frame agar performa lancar
        }
    ).catch(err => {
        let errorMsg = 'Izin kamera ditolak atau kamera tidak ditemukan.';
        
        // Pengecekan HTTPS / Secure Context
        if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
            errorMsg = 'Kamera tidak dapat diakses karena browser memblokir koneksi HTTP tidak aman (Non-HTTPS). Silakan akses melalui HTTPS atau gunakan localhost.';
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Kamera Gagal',
            html: errorMsg
        });
        scannerBox.classList.add('hidden');
    });
}

function stopScanner() {
    if (html5QrcodeScanner && html5QrcodeScanner.isScanning) {
        html5QrcodeScanner.stop().then(() => {
            document.getElementById('scannerBox').classList.add('hidden');
        }).catch(() => {
            document.getElementById('scannerBox').classList.add('hidden');
        });
    } else {
        document.getElementById('scannerBox').classList.add('hidden');
    }
}

const allTipe = <?= json_encode($tipe_barang_list) ?>;
const selectedTipeId = <?= json_encode($_POST['id_tipe'] ?? $barang['id_tipe']) ?>;

function updateEditTipe() {
    const katSelect = document.getElementById('editKategori');
    const tipeSelect = document.getElementById('editTipe');
    const idKat = katSelect.value;
    
    tipeSelect.innerHTML = '<option value="">— Pilih Tipe Barang —</option>';
    if (!idKat) return;

    const filtered = allTipe.filter(t => t.id_kategori == idKat);
    filtered.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id_tipe;
        opt.textContent = t.nama_tipe;
        if (t.id_tipe == selectedTipeId) {
            opt.selected = true;
        }
        tipeSelect.appendChild(opt);
    });

    if (!tipeSelect.value && filtered.length > 0) {
        tipeSelect.value = filtered[0].id_tipe;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateEditTipe();
});
</script>