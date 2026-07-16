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

// Ambil daftar Barang untuk JSON/JS
try {
    $barangList = $conn->query("SELECT id_barang, nama_barang, barcode, satuan FROM barang WHERE is_active=1 ORDER BY nama_barang ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $barangList = [];
}

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal          = $_POST['tanggal'] ?? date('Y-m-d');
    $id_supplier      = $_POST['id_supplier'] ?? '';
    $supplier_lainnya = null;
    
    if ($id_supplier === 'lainnya') {
        $supplier_lainnya = trim($_POST['supplier_lainnya'] ?? '');
        $id_supplier = null;
    } else {
        $id_supplier = (int)$id_supplier ?: null;
    }

    $keterangan = trim($_POST['keterangan'] ?? '');
    $id_user    = $_SESSION['sess_mngid'] ?? null;
    
    $items_id  = $_POST['item_id_barang'] ?? [];
    $items_qty = $_POST['item_qty'] ?? [];

    if (empty($items_id)) {
        $error = "Tambahkan minimal 1 barang ke daftar barang masuk!";
    } elseif ($id_supplier === null && empty($supplier_lainnya) && $_POST['id_supplier'] === 'lainnya') {
        $error = "Silakan masukkan nama supplier lainnya.";
    } else {
        // Validasi qty
        foreach ($items_id as $idx => $id_barang) {
            $qty = (int)($items_qty[$idx] ?? 0);
            if ($qty <= 0) { $errors[] = "Qty untuk item #".($idx+1)." tidak valid."; }
        }

        if (empty($errors)) {
            $conn->beginTransaction();
            $kode_transaksi = 'TRX-IN-' . time() . rand(10, 99);
            
            try {
                $ins = $conn->prepare("
                    INSERT INTO barang_masuk (kode_transaksi, tanggal, id_supplier, supplier_lainnya, id_barang, qty, keterangan, id_user)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stBarang = $conn->prepare("SELECT nama_barang FROM barang WHERE id_barang = ?");
                
                $nama_supplier = 'Tanpa Supplier';
                if ($id_supplier) {
                    $stSup = $conn->prepare("SELECT nama_supplier FROM supplier WHERE id_supplier = ?");
                    $stSup->execute([$id_supplier]);
                    $nama_supplier = $stSup->fetchColumn() ?: 'Tanpa Supplier';
                } elseif ($supplier_lainnya) {
                    $nama_supplier = $supplier_lainnya . ' (Luar Master)';
                }

                foreach ($items_id as $idx => $id_barang) {
                    $id_barang = (int)$id_barang;
                    $qty       = (int)($items_qty[$idx] ?? 0);
                    if ($qty <= 0) continue;
                    
                    $ins->execute([$kode_transaksi, $tanggal, $id_supplier, $supplier_lainnya, $id_barang, $qty, $keterangan, $id_user]);
                    $new_id = $conn->lastInsertId();
                    
                    $stBarang->execute([$id_barang]);
                    $nama_barang = $stBarang->fetchColumn() ?: 'Unknown';
                    
                    writeAuditLog(
                        'CREATE', 
                        'barang_masuk', 
                        $new_id, 
                        "Penerimaan barang: $nama_barang (Qty: $qty, Supplier: $nama_supplier)"
                    );
                }

                $conn->commit();
                $_SESSION['flash_success'] = "Penerimaan <b>" . count($items_id) . " jenis barang</b> berhasil diinput ke sistem.";
                echo "<script>window.location.href='$sistem/barangmasuk';</script>";
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="fade-up max-w-3xl mx-auto space-y-5">
    
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/barangmasuk" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Input Barang Masuk</h1>
            <p class="text-slate-500 text-sm">Tambah stok banyak barang sekaligus ke Gudang Pusat.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-triangle-exclamation text-rose-500 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm space-y-1">
        <p class="font-bold flex items-center gap-2"><i class="fa-solid fa-triangle-exclamation"></i> Ditemukan kesalahan:</p>
        <?php foreach ($errors as $e): ?>
            <p class="pl-4">• <?= $e ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="formMasuk" onsubmit="return validateForm()">
        
        <!-- Section 1: Info Penerimaan -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-4">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <p class="text-sm font-semibold text-slate-700">
                    <i class="fa-solid fa-file-invoice text-indigo-500 mr-2"></i>Informasi Penerimaan
                </p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tanggal Masuk</label>
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-slate-50">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Supplier Pengirim</label>
                    <select name="id_supplier" id="selectSupplier" onchange="toggleSupplierLainnya(this.value)" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-slate-50">
                        <option value="">— Pembelian Tanpa Supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id_supplier'] ?>" <?= (($_POST['id_supplier'] ?? '') == $s['id_supplier']) ? 'selected' : '' ?>>
                            <?= sanitize($s['nama_supplier']) ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="lainnya" <?= (($_POST['id_supplier'] ?? '') === 'lainnya') ? 'selected' : '' ?>>Lainnya (Ketik Manual)</option>
                    </select>
                </div>

                <div id="divSupplierLainnya" class="sm:col-span-2 <?= (($_POST['id_supplier'] ?? '') === 'lainnya') ? '' : 'hidden' ?>">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nama Supplier Lainnya <span class="text-rose-500">*</span></label>
                    <input type="text" name="supplier_lainnya" id="inputSupplierLainnya" value="<?= htmlspecialchars($_POST['supplier_lainnya'] ?? '') ?>" placeholder="Masukkan nama supplier (di luar sistem)..."
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-sky-50">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Keterangan / Catatan <span class="text-slate-400 font-normal">(Opsional)</span></label>
                    <textarea name="keterangan" rows="2" placeholder="Masukkan nomor nota/invoice, catatan pengiriman, dll..."
                              class="w-full px-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all"><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Section 2: Tambah Barang -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-4">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <p class="text-sm font-semibold text-slate-700">
                    <i class="fa-solid fa-boxes-stacked text-sky-500 mr-2"></i>Pilih Barang
                </p>
            </div>
            <div class="p-6 space-y-4">
                
                <div class="bg-sky-50 border border-sky-100 rounded-xl p-4 flex flex-col items-center gap-3">
                    <div class="text-center">
                        <p class="text-sm font-bold text-sky-800">Gunakan Barcode Scanner</p>
                        <p class="text-xs text-sky-600/80 mt-0.5">Scan barcode produk untuk menambah langsung ke daftar penerimaan.</p>
                    </div>
                    <button type="button" onclick="startScanner()" 
                            class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-all">
                        <i class="fa-solid fa-camera"></i> Mulai Scan Kamera
                    </button>
                    
                    <div id="scannerBox" class="hidden w-full max-w-md bg-black rounded-xl overflow-hidden flex-col mt-2">
                        <div id="interactiveReader" class="w-full"></div>
                        <div class="p-3 bg-slate-800 flex justify-center">
                            <button type="button" onclick="stopScanner()" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                                <i class="fa-solid fa-circle-xmark"></i> Tutup Kamera
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <select id="selectBarangAdd" class="flex-1 px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white select2-barang">
                        <option value="">-- Ketik Nama Barang atau Pilih --</option>
                        <?php foreach ($barangList as $b): ?>
                        <option value="<?= $b['id_barang'] ?>" data-satuan="<?= sanitize($b['satuan'] ?: 'Pcs') ?>" data-barcode="<?= sanitize($b['barcode']) ?>" data-nama="<?= sanitize($b['nama_barang']) ?>">
                            <?= sanitize($b['nama_barang']) ?> <?= $b['barcode'] ? "[{$b['barcode']}]" : "" ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="addItemToCart()"
                            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-xl transition-all flex items-center gap-2 flex-shrink-0">
                        <i class="fa-solid fa-plus"></i> Tambah
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 3: Daftar Keranjang -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-4">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-700">
                    <i class="fa-solid fa-list-check text-emerald-500 mr-2"></i>Daftar Barang Masuk
                </p>
                <span id="cartBadge" class="text-xs bg-slate-200 text-slate-600 font-bold px-2.5 py-0.5 rounded-full">0 item</span>
            </div>

            <div id="cartEmpty" class="px-6 py-10 text-center text-slate-400">
                <i class="fa-regular fa-folder-open text-4xl mb-3 block text-slate-300"></i>
                <p class="text-sm">Belum ada barang di daftar. Scan barcode atau tambah manual di atas.</p>
            </div>

            <div id="cartContainer" class="hidden overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Nama Barang</th>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider w-40 text-center">Jumlah Masuk</th>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider w-16 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <!-- JS renders rows -->
                    </tbody>
                </table>
            </div>

            <div id="hiddenInputs"></div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3">
            <a href="<?= $sistem ?>/barangmasuk" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">Batal</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Transaksi
            </button>
        </div>
    </form>
</div>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let html5QrcodeScanner = null;
let cart = [];

$(document).ready(function() {
    $('.select2-barang').select2({ width: '100%', placeholder: '-- Ketik Nama Barang --' });
});

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

// ─── CART LOGIC ──────────────────────────────────────────────────────────────

function addItemToCart(idBarang = null) {
    const select = document.getElementById('selectBarangAdd');
    const id = idBarang || select.value;
    if (!id) return;

    const opt = select.querySelector(`option[value="${id}"]`);
    if (!opt) return;

    if (cart.find(c => c.id == id)) {
        const qtyEl = document.getElementById('qty_' + id);
        if (qtyEl) { qtyEl.focus(); qtyEl.select(); }
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Barang sudah ada di daftar!', showConfirmButton: false, timer: 1500 });
        return;
    }

    cart.push({
        id:      id,
        nama:    opt.dataset.nama || opt.text,
        barcode: opt.dataset.barcode || '—',
        satuan:  opt.dataset.satuan || 'Pcs',
        qty:     1
    });

    // Disable option
    $('#selectBarangAdd option[value="' + id + '"]').prop('disabled', true);
    $('#selectBarangAdd').select2({ width: '100%', placeholder: '-- Ketik Nama Barang --' });

    renderCart();
    $('#selectBarangAdd').val('').trigger('change');

    setTimeout(() => {
        const qtyEl = document.getElementById('qty_' + id);
        if (qtyEl) { qtyEl.focus(); qtyEl.select(); }
    }, 100);
}

function removeFromCart(id) {
    cart = cart.filter(c => c.id != id);
    
    // Re-enable option
    $('#selectBarangAdd option[value="' + id + '"]').prop('disabled', false);
    $('#selectBarangAdd').select2({ width: '100%', placeholder: '-- Ketik Nama Barang --' });
    
    renderCart();
}

function updateQty(id, val) {
    const item = cart.find(c => c.id == id);
    if (item) item.qty = parseInt(val) || 1;
}

function renderCart() {
    const tbody       = document.getElementById('cartBody');
    const empty       = document.getElementById('cartEmpty');
    const container   = document.getElementById('cartContainer');
    const badge       = document.getElementById('cartBadge');
    const hiddenDiv   = document.getElementById('hiddenInputs');

    badge.textContent = cart.length + ' item';
    hiddenDiv.innerHTML = '';

    if (cart.length === 0) {
        empty.classList.remove('hidden');
        container.classList.add('hidden');
        return;
    }

    empty.classList.add('hidden');
    container.classList.remove('hidden');
    tbody.innerHTML = '';

    cart.forEach(item => {
        const row = document.createElement('tr');
        row.className = 'border-b border-slate-100 hover:bg-slate-50/50';
        row.innerHTML = `
            <td class="px-4 py-3">
                <p class="font-semibold text-slate-800 text-sm">${item.nama}</p>
                <p class="text-xs text-slate-400 mt-0.5">Barcode: ${item.barcode}</p>
            </td>
            <td class="px-4 py-3">
                <div class="relative flex items-center justify-center">
                    <input type="number" id="qty_${item.id}" value="${item.qty}" min="1"
                           onchange="updateQty('${item.id}', this.value)"
                           oninput="updateQty('${item.id}', this.value)"
                           class="w-24 text-center px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all font-semibold">
                    <span class="ml-1.5 text-xs text-slate-400">${item.satuan}</span>
                </div>
            </td>
            <td class="px-4 py-3 text-center">
                <button type="button" onclick="removeFromCart('${item.id}')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-400 hover:text-red-600 transition-all mx-auto">
                    <i class="fa-solid fa-trash text-xs"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);

        hiddenDiv.innerHTML += `<input type="hidden" name="item_id_barang[]" value="${item.id}">`;
        hiddenDiv.innerHTML += `<input type="hidden" id="hidden_qty_${item.id}" name="item_qty[]" value="${item.qty}">`;
    });
}

function validateForm() {
    cart.forEach(item => {
        const qtyEl = document.getElementById('qty_' + item.id);
        const hiddenEl = document.getElementById('hidden_qty_' + item.id);
        if (qtyEl && hiddenEl) {
            hiddenEl.value = qtyEl.value;
        }
    });

    if (cart.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Daftar Kosong', text: 'Tambahkan minimal 1 barang ke keranjang!' });
        return false;
    }
    return true;
}

// ─── SCANNER LOGIC ───────────────────────────────────────────────────────────

function startScanner() {
    const scannerBox = document.getElementById('scannerBox');
    scannerBox.classList.remove('hidden');
    scannerBox.classList.add('flex');

    if (!html5QrcodeScanner) {
        html5QrcodeScanner = new Html5Qrcode("interactiveReader");
    }

    html5QrcodeScanner.start(
        { facingMode: "environment" },
        { fps: 24, qrbox: { width: 300, height: 120 }, aspectRatio: 1.777778 },
        (decodedText) => {
            stopScanner();
            Swal.fire({ title: 'Mencari Produk...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            setTimeout(() => {
                let found = null;
                document.querySelectorAll('#selectBarangAdd option').forEach(opt => {
                    if (opt.dataset.barcode == decodedText) found = opt;
                });

                Swal.close();
                if (found) {
                    addItemToCart(found.value);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Ditambahkan: ' + found.dataset.nama, showConfirmButton: false, timer: 2000 });
                } else {
                    Swal.fire({ icon: 'error', title: 'Tidak Ditemukan', text: 'Barcode ' + decodedText + ' belum terdaftar.' });
                }
            }, 400);
        },
        () => {}
    ).catch(err => {
        Swal.fire({ icon: 'error', title: 'Kamera Gagal', html: 'Izin kamera ditolak atau perangkat tidak didukung.' });
        scannerBox.classList.add('hidden');
        scannerBox.classList.remove('flex');
    });
}

function stopScanner() {
    if (html5QrcodeScanner && html5QrcodeScanner.isScanning) {
        html5QrcodeScanner.stop().then(() => {
            document.getElementById('scannerBox').classList.add('hidden');
            document.getElementById('scannerBox').classList.remove('flex');
        }).catch(() => {
            document.getElementById('scannerBox').classList.add('hidden');
            document.getElementById('scannerBox').classList.remove('flex');
        });
    } else {
        document.getElementById('scannerBox').classList.add('hidden');
        document.getElementById('scannerBox').classList.remove('flex');
    }
}
</script>