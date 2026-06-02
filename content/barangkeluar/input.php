<?php
if (!canDo('barangkeluar', 'input')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk menambah barang keluar.</div>";
    exit;
}

global $conn, $sistem;

// Ambil daftar outlet
$outlets = $conn->query("SELECT * FROM outlet ORDER BY nama_outlet ASC")->fetchAll();
// Ambil daftar barang yang stoknya lebih dari 0
$barangs = $conn->query("
    SELECT b.*, t.nama_tipe, i.stok 
    FROM barang b 
    JOIN inventory i ON b.id_barang = i.id_barang
    LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe 
    WHERE i.stok > 0
    ORDER BY b.nama_barang ASC
")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
    $id_outlet = (int)($_POST['id_outlet'] ?? 0);
    $id_barang = (int)($_POST['id_barang'] ?? 0);
    $qty       = (int)($_POST['qty'] ?? 0);
    $keterangan= trim($_POST['keterangan'] ?? '');
    $id_user   = $_SESSION['user_id'] ?? null;

    if (!$id_outlet || !$id_barang || $qty <= 0) {
        $error = "Pilih Outlet, pilih Barang, dan isi Jumlah (Qty) dengan benar!";
    } else {
        // Cek apakah stok cukup
        $cekStok = $conn->prepare("SELECT stok, nama_barang FROM inventory i JOIN barang b ON i.id_barang=b.id_barang WHERE i.id_barang = ?");
        $cekStok->execute([$id_barang]);
        $stokAktual = $cekStok->fetch();

        if (!$stokAktual || $stokAktual['stok'] < $qty) {
            $error = "Stok " . sanitize($stokAktual['nama_barang'] ?? 'Barang') . " tidak mencukupi! Sisa stok sistem: " . ($stokAktual['stok'] ?? 0);
        } else {
            try {
                // Insert ke barang_keluar dengan status Pending
                $stmt = $conn->prepare("
                    INSERT INTO barang_keluar (tanggal, id_outlet, id_barang, qty, keterangan, id_user, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $stmt->execute([$tanggal, $id_outlet, $id_barang, $qty, $keterangan ?: null, $id_user]);
                $new_id = (int)$conn->lastInsertId();

                writeAuditLog('CREATE', 'barang_keluar', $new_id, "Mengirim " . $qty . " item ke Outlet ID: $id_outlet");
                
                $_SESSION['flash_success'] = "Barang berhasil dikirim dan sedang <b>Menunggu Diterima</b> oleh Outlet.";
                echo "<script>window.location.href='$sistem/barangkeluar';</script>";
                exit;
            } catch (Exception $e) {
                $error = "Terjadi kesalahan sistem: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="fade-up max-w-xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/barangkeluar" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Distribusi Barang</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kirim barang dari gudang pusat ke outlet.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm shadow-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0 text-red-500"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <p class="text-sm font-semibold text-slate-700">
                <i class="fa-solid fa-truck-fast text-indigo-500 mr-2"></i>Detail Pengiriman
            </p>
        </div>
        
        <div class="p-6 space-y-5">
            <!-- Banner Scanner (Sama seperti Barang Masuk) -->
            <div class="bg-sky-50 border border-sky-100 rounded-xl p-4 flex flex-col items-center justify-center gap-3">
                <div class="text-center">
                    <p class="text-sm font-bold text-sky-800">Gunakan Barcode Scanner</p>
                    <p class="text-xs text-sky-600/80 mt-0.5">Tunjukkan barcode produk ke kamera HP untuk memindai barang keluar.</p>
                </div>
                <button type="button" onclick="startScanner()" 
                        class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-all">
                    <i class="fa-solid fa-camera"></i> Mulai Scan Kamera
                </button>
                
                <!-- Scanner Box Inline Container -->
                <div id="scannerBox" class="hidden w-full max-w-md bg-black rounded-xl overflow-hidden relative border border-slate-700 mt-2">
                    <div id="interactiveReader" class="w-full"></div>
                    <div class="absolute bottom-4 left-0 right-0 flex justify-center">
                        <button type="button" onclick="stopScanner()" 
                                class="bg-red-500 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                            Tutup Kamera
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tanggal Keluar</label>
                <input type="date" name="tanggal" required value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-slate-50">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Pilih Outlet Tujuan</label>
                <select name="id_outlet" required class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-slate-50">
                    <option value="">-- Pilih Outlet --</option>
                    <?php foreach ($outlets as $o): ?>
                        <option value="<?= $o['id_outlet'] ?>" <?= (isset($_POST['id_outlet']) && $_POST['id_outlet']==$o['id_outlet']) ? 'selected' : '' ?>>
                            <?= sanitize($o['nama_outlet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Nama Barang / Produk</label>
                <select name="id_barang" id="id_barang" onchange="updateProductInfo(this.value)" required class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all select2-init bg-white">
                    <option value="">-- Ketik Nama atau Scan Barcode --</option>
                    <?php foreach ($barangs as $b): ?>
                        <option value="<?= $b['id_barang'] ?>" data-barcode="<?= sanitize($b['barcode']) ?>" data-stok="<?= $b['stok'] ?>" data-satuan="<?= sanitize($b['satuan']) ?>" data-nama="<?= sanitize($b['nama_barang']) ?>" <?= (isset($_POST['id_barang']) && $_POST['id_barang']==$b['id_barang']) ? 'selected' : '' ?>>
                            <?= sanitize($b['nama_barang']) ?> <?= $b['barcode'] ? "[{$b['barcode']}]" : "" ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Product Info Card -->
                <div id="productInfoCard" class="hidden bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex items-center justify-between text-xs transition-all">
                    <div>
                        <p class="font-bold text-slate-700" id="infoNamaBarang">—</p>
                        <p class="text-slate-400 mt-0.5" id="infoBarcodeBarang">Barcode: —</p>
                    </div>
                    <div class="text-right">
                        <p class="text-slate-400">Stok Sistem</p>
                        <p class="font-bold text-indigo-600 text-sm" id="infoStokBarang">0 Pcs</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Jumlah (Qty)</label>
                <div class="relative">
                    <input type="number" name="qty" id="qty" required min="1" value="<?= htmlspecialchars($_POST['qty'] ?? '') ?>"
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all">
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400 uppercase" id="satuan-label">
                        Pcs
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Keterangan / Catatan</label>
                <textarea name="keterangan" rows="2" placeholder="Catatan pengiriman..."
                          class="w-full px-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all"><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <a href="<?= $sistem ?>/barangkeluar" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">Batal</a>
            <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-paper-plane text-xs"></i> Kirim ke Outlet
            </button>
        </div>
    </form>
</div>



<!-- Library Select2 untuk Pencarian Dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Library HTML5 QR Code -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let html5QrcodeScanner = null;

$(document).ready(function() {
    $('.select2-init').select2({
        width: '100%'
    });

    $('#id_barang').on('change', function() {
        let sel = $(this).find(':selected');
        let satuan = sel.data('satuan');
        let maxStok = sel.data('stok');
        
        if (satuan) {
            $('#satuan-label').text(satuan);
        }
        if (maxStok !== undefined) {
            $('#qty').attr('max', maxStok);
        }
    });
});

// Fitur Scanner Kamera Inline
function startScanner() {
    $('#scannerBox').removeClass('hidden').addClass('block');
    
    html5QrcodeScanner = new Html5QrcodeScanner("interactiveReader", { 
        fps: 10, 
        qrbox: {width: 250, height: 150},
        supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
    }, false);

    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
}

function stopScanner() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().catch(error => {
            console.error("Failed to clear scanner. ", error);
        });
    }
    $('#scannerBox').addClass('hidden').removeClass('block');
}

function onScanSuccess(decodedText, decodedResult) {
    stopScanner();
    
    Swal.fire({ title: 'Mencari Produk...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    setTimeout(() => {
        let found = false;
        let nama_barang = '';
        $('#id_barang option').each(function() {
            if ($(this).data('barcode') == decodedText) {
                $('#id_barang').val($(this).val()).trigger('change');
                nama_barang = $(this).data('nama') || $(this).text();
                found = true;
                return false;
            }
        });

        if (found) {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: 'Produk Terdeteksi!',
                text: nama_barang,
                timer: 1500,
                showConfirmButton: false
            });
            setTimeout(() => { $('#qty').focus(); }, 300);
        } else {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Tidak Ditemukan',
                text: 'Barang dengan barcode ' + decodedText + ' belum terdaftar atau stok kosong!'
            });
        }
    }, 400); // Simulasi delay loading pencarian AJAX
}

function onScanFailure(error) {
    // Abaikan error background
}

function updateProductInfo(val) {
    let sel = $('#id_barang').find(':selected');
    let satuan = sel.data('satuan');
    let maxStok = sel.data('stok');
    let nama = sel.data('nama');
    let barcode = sel.data('barcode');

    if (val) {
        $('#productInfoCard').removeClass('hidden').addClass('flex');
        $('#infoNamaBarang').text(nama);
        $('#infoBarcodeBarang').text("Barcode: " + (barcode ? barcode : "Tanpa Barcode"));
        $('#infoStokBarang').text(maxStok + " " + satuan);
    } else {
        $('#productInfoCard').addClass('hidden').removeClass('flex');
    }
}
</script>