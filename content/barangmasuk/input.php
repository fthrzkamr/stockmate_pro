<?php
if (!canDo('barangmasuk', 'create')) {
    echo "<div class='p-4 text-red-650 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk menginput barang masuk.</div>";
    exit;
}

global $conn, $sistem;

// Ambil daftar Supplier
try {
    $suppliers = $conn->query("SELECT id_supplier, nama_supplier FROM supplier WHERE is_active=1 ORDER BY nama_supplier ASC")->fetchAll();
} catch (Exception $e) {
    $suppliers = [];
}

// Ambil daftar Barang untuk dropdown manual
try {
    $barangList = $conn->query("SELECT id_barang, nama_barang, barcode, satuan FROM barang WHERE is_active=1 ORDER BY nama_barang ASC")->fetchAll();
} catch (Exception $e) {
    $barangList = [];
}

// Handler AJAX Lookup Barcode
if (isset($_GET['ajax_lookup_barcode'])) {
    header('Content-Type: application/json');
    $barcode = trim($_GET['ajax_lookup_barcode']);
    try {
        $st = $conn->prepare("
            SELECT id_barang, nama_barang, barcode, satuan,
                   (COALESCE((SELECT SUM(qty) FROM barang_masuk WHERE id_barang = b.id_barang), 0) - 
                    COALESCE((SELECT SUM(qty) FROM barang_keluar WHERE id_barang = b.id_barang), 0)) as stok
            FROM barang b 
            WHERE b.barcode = ? AND b.is_active = 1
        ");
        $st->execute([$barcode]);
        $res = $st->fetch();
        if ($res) {
            echo json_encode(['status' => 'success', 'data' => $res]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Barang dengan barcode tersebut tidak ditemukan atau tidak aktif.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => 'Database error.']);
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal     = $_POST['tanggal'] ?? date('Y-m-d');
    $id_supplier = $_POST['id_supplier'] ?? '';
    $supplier_lainnya = null;
    
    // Jika pilih lainnya
    if ($id_supplier === 'lainnya') {
        $supplier_lainnya = trim($_POST['supplier_lainnya'] ?? '');
        $id_supplier = null;
    } else {
        $id_supplier = (int)$id_supplier ?: null;
    }

    $id_barang   = (int)($_POST['id_barang'] ?? 0);
    $qty         = (int)($_POST['qty'] ?? 0);
    $keterangan  = trim($_POST['keterangan'] ?? '');
    $id_user     = $_SESSION['sess_mngid'] ?? null;

    if (!$id_barang) {
        $error = 'Silakan pilih barang yang masuk.';
    } elseif ($qty <= 0) {
        $error = 'Jumlah masuk (qty) harus lebih besar dari 0.';
    } elseif ($id_supplier === null && empty($supplier_lainnya) && $_POST['id_supplier'] === 'lainnya') {
        $error = 'Silakan masukkan nama supplier lainnya.';
    } else {
        $conn->beginTransaction();
        try {
            // 1. Simpan transaksi barang masuk
            $ins = $conn->prepare("
                INSERT INTO barang_masuk (tanggal, id_supplier, supplier_lainnya, id_barang, qty, keterangan, id_user)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$tanggal, $id_supplier, $supplier_lainnya, $id_barang, $qty, $keterangan, $id_user]);
            $new_id = $conn->lastInsertId();

            // 2. Ambil nama barang & nama supplier untuk audit log
            $stBarang = $conn->prepare("SELECT nama_barang, barcode FROM barang WHERE id_barang = ?");
            $stBarang->execute([$id_barang]);
            $barang = $stBarang->fetch();

            $nama_supplier = 'Tanpa Supplier';
            if ($id_supplier) {
                $stSup = $conn->prepare("SELECT nama_supplier FROM supplier WHERE id_supplier = ?");
                $stSup->execute([$id_supplier]);
                $nama_supplier = $stSup->fetchColumn() ?: 'Tanpa Supplier';
            } elseif ($supplier_lainnya) {
                $nama_supplier = $supplier_lainnya . ' (Luar Master)';
            }

            // 3. Catat Audit Log
            writeAuditLog(
                'CREATE', 
                'barang_masuk', 
                $new_id, 
                "Penerimaan barang masuk: {$barang['nama_barang']} (Qty: $qty, Supplier: $nama_supplier)"
            );

            $conn->commit();
            $_SESSION['flash_success'] = "Penerimaan barang <b>{$barang['nama_barang']}</b> sebanyak <b>$qty</b> berhasil diinput.";
            echo "<script>window.location.href='$sistem/barangmasuk';</script>";
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>

<div class="fade-up max-w-2xl mx-auto space-y-5">
    
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/barangmasuk" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Input Barang Masuk</h1>
            <p class="text-slate-500 text-sm">Tambah stok barang ke Gudang Pusat dari supplier.</p>
        </div>
    </div>

    <!-- Alert Error -->
    <?php if ($error): ?>
    <div class="flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-triangle-exclamation text-rose-500 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <form method="POST" action="" class="divide-y divide-slate-100">
            
            <div class="p-6 space-y-4">
                
                <!-- Scanner Section -->
                <div class="bg-sky-50 border border-sky-100 rounded-xl p-4 flex flex-col items-center justify-center gap-3">
                    <div class="text-center">
                        <p class="text-sm font-bold text-sky-800">Gunakan Barcode Scanner</p>
                        <p class="text-xs text-sky-600/80 mt-0.5">Tunjukkan barcode produk ke kamera laptop/HP untuk memindai barang masuk.</p>
                    </div>
                    <button type="button" onclick="startScanner()" 
                            class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-all">
                        <i class="fa-solid fa-camera"></i> Mulai Scan Kamera
                    </button>
                    
                    <!-- Scanner Box Overlay/Container -->
                    <div id="scannerBox" class="hidden w-full max-w-md bg-black rounded-xl overflow-hidden relative border border-slate-700 mt-2">
                        <div id="interactiveReader" class="w-full"></div>
                        <div class="absolute bottom-4 left-0 right-0 flex justify-center">
                            <button type="button" onclick="stopScanner()" 
                                    class="bg-red-650 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                                Tutup Kamera
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tanggal Penerimaan -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tanggal Masuk</label>
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
                </div>

                <!-- Supplier Utama -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Supplier Pengirim</label>
                    <select name="id_supplier" id="selectSupplier" onchange="toggleSupplierLainnya(this.value)" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                        <option value="">— Pembelian Tanpa Supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id_supplier'] ?>" <?= (($_POST['id_supplier'] ?? '') == $s['id_supplier']) ? 'selected' : '' ?>>
                            <?= sanitize($s['nama_supplier']) ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="lainnya" <?= (($_POST['id_supplier'] ?? '') === 'lainnya') ? 'selected' : '' ?>>Lainnya (Ketik Manual)</option>
                    </select>
                </div>

                <!-- Input Supplier Lainnya (Hidden secara default) -->
                <div id="divSupplierLainnya" class="<?= (($_POST['id_supplier'] ?? '') === 'lainnya') ? '' : 'hidden' ?>">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nama Supplier Lainnya <span class="text-rose-500">*</span></label>
                    <input type="text" name="supplier_lainnya" id="inputSupplierLainnya" value="<?= htmlspecialchars($_POST['supplier_lainnya'] ?? '') ?>" placeholder="Masukkan nama supplier (di luar sistem)..."
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-sky-50">
                </div>

                <!-- Pencarian/Pilihan Barang -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Nama Barang / Produk</label>
                    
                    <!-- Dropdown Pilihan Barang -->
                    <select name="id_barang" id="selectBarang" onchange="updateProductInfo(this.value)" required
                            class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                        <option value="">— Pilih Barang —</option>
                        <?php foreach ($barangList as $b): ?>
                        <option value="<?= $b['id_barang'] ?>" data-satuan="<?= sanitize($b['satuan'] ?: 'Pcs') ?>" data-barcode="<?= sanitize($b['barcode']) ?>" <?= (($_POST['id_barang'] ?? '') == $b['id_barang']) ? 'selected' : '' ?>>
                            <?= sanitize($b['nama_barang']) ?> <?= $b['barcode'] ? "[{$b['barcode']}]" : "" ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Product Info Card (Muncul saat barang dipilih) -->
                    <div id="productInfoCard" class="hidden bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center justify-between text-xs transition-all">
                        <div>
                            <p class="font-bold text-slate-700" id="infoNamaBarang">—</p>
                            <p class="text-slate-400 mt-0.5" id="infoBarcodeBarang">Barcode: —</p>
                        </div>
                        <div class="text-right">
                            <p class="text-slate-400">Stok Saat Ini</p>
                            <p class="font-bold text-slate-700 text-sm" id="infoStokBarang">0 Pcs</p>
                        </div>
                    </div>
                </div>

                <!-- Kuantitas (Qty) -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Jumlah Masuk (Quantity)</label>
                    <div class="relative rounded-xl shadow-sm">
                        <input type="number" name="qty" id="inputQty" min="1" value="<?= htmlspecialchars($_POST['qty'] ?? '') ?>" required placeholder="Masukkan jumlah barang..."
                               class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                            <span class="text-slate-400 text-sm font-medium" id="lblSatuan">Pcs</span>
                        </div>
                    </div>
                </div>

                <!-- Keterangan -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Keterangan / Catatan</label>
                    <textarea name="keterangan" rows="3" placeholder="Masukkan nomor nota/invoice, catatan pengiriman, dll (opsional)..."
                              class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all"><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                </div>

            </div>

            <!-- Footer Buttons -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                <a href="<?= $sistem ?>/barangmasuk"
                   class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">
                    Batal
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Transaksi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- html5-qrcode Library -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
let html5QrcodeScanner = null;

function toggleSupplierLainnya(val) {
    const div = document.getElementById('divSupplierLainnya');
    const input = document.getElementById('inputSupplierLainnya');
    if (val === 'lainnya') {
        div.classList.remove('hidden');
        input.setAttribute('required', 'required');
        input.focus();
    } else {
        div.classList.add('hidden');
        input.removeAttribute('required');
        input.value = '';
    }
}

function updateProductInfo(idBarang) {
    const card = document.getElementById('productInfoCard');
    const select = document.getElementById('selectBarang');
    const option = select.options[select.selectedIndex];
    
    if (!idBarang) {
        card.classList.add('hidden');
        document.getElementById('lblSatuan').innerText = 'Pcs';
        return;
    }
    
    // Set satuan label
    const satuan = option.dataset.satuan || 'Pcs';
    document.getElementById('lblSatuan').innerText = satuan;

    // Ambil data detail via AJAX
    fetch(`<?= $sistem ?>/barangmasuk/i?ajax_lookup_barcode=${option.dataset.barcode || ''}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                document.getElementById('infoNamaBarang').innerText = res.data.nama_barang;
                document.getElementById('infoBarcodeBarang').innerText = 'Barcode: ' + (res.data.barcode || '—');
                document.getElementById('infoStokBarang').innerText = res.data.stok + ' ' + res.data.satuan;
                card.classList.remove('hidden');
            } else {
                // Fallback jika item tidak berbarcode tapi ada ID
                document.getElementById('infoNamaBarang').innerText = option.text;
                document.getElementById('infoBarcodeBarang').innerText = 'Barcode: ' + (option.dataset.barcode || '—');
                document.getElementById('infoStokBarang').innerText = '—';
                card.classList.remove('hidden');
            }
        }).catch(() => {
            // Fallback sederhana
            document.getElementById('infoNamaBarang').innerText = option.text;
            document.getElementById('infoBarcodeBarang').innerText = 'Barcode: ' + (option.dataset.barcode || '—');
            document.getElementById('infoStokBarang').innerText = '—';
            card.classList.remove('hidden');
        });
}

function startScanner() {
    const scannerBox = document.getElementById('scannerBox');
    scannerBox.classList.remove('hidden');
    
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
        qrbox: { width: 300, height: 120 }, // Dimensi tipis lebar khusus barcode 1D
        aspectRatio: 1.777778
    };
    
    html5QrcodeScanner.start(
        { facingMode: "environment" }, 
        config,
        (decodedText, decodedResult) => {
            stopScanner();
            
            // Cari barang berdasarkan barcode hasil scan menggunakan AJAX
            Swal.fire({ title: 'Mencari Produk...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch(`<?= $sistem ?>/barangmasuk/i?ajax_lookup_barcode=${decodedText}`)
                .then(r => r.json())
                .then(res => {
                    Swal.close();
                    if (res.status === 'success') {
                        // Pilih barang di dropdown
                        const select = document.getElementById('selectBarang');
                        select.value = res.data.id_barang;
                        updateProductInfo(res.data.id_barang);
                        
                        // Autofokus ke input qty
                        document.getElementById('inputQty').focus();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Produk Terdeteksi!',
                            text: res.data.nama_barang,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Tidak Ditemukan',
                            text: 'Barang dengan barcode ' + decodedText + ' belum terdaftar di sistem.'
                        });
                    }
                })
                .catch(() => {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'Gagal menghubungi server.' });
                });
        },
        (errorMessage) => {
            // Abaikan error per frame agar lancar
        }
    ).catch(err => {
        let errorMsg = 'Izin kamera ditolak atau kamera tidak ditemukan.';
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
</script>