<?php
if (!canDo('barangkeluar', 'input')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk menambah barang keluar.</div>";
    exit;
}

global $conn, $sistem;

// Ambil daftar outlet
$outlets = $conn->query("SELECT * FROM outlet WHERE is_active = 1 ORDER BY nama_outlet ASC")->fetchAll();

// Ambil daftar barang yang stoknya lebih dari 0 (JSON untuk JS)
$barangs = $conn->query("
    SELECT b.id_barang, b.nama_barang, b.barcode, b.satuan, i.stok 
    FROM barang b 
    JOIN inventory i ON b.id_barang = i.id_barang
    WHERE i.stok > 0
    ORDER BY b.nama_barang ASC
")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tanggal dikunci hari ini
    $tanggal    = date('Y-m-d');
    $id_outlet  = (int)($_POST['id_outlet'] ?? 0);
    $keterangan = null; // Opsi keterangan dihapus
    $id_user    = $_SESSION['user_id'] ?? null;

    $items_id  = $_POST['item_id_barang'] ?? [];
    $items_qty = $_POST['item_qty'] ?? [];

    if (!$id_outlet) {
        $error = "Pilih Outlet Tujuan terlebih dahulu!";
    } elseif (empty($items_id)) {
        $error = "Tambahkan minimal 1 barang ke daftar pengiriman!";
    } else {
        // Validasi semua item
        foreach ($items_id as $idx => $id_barang) {
            $id_barang = (int)$id_barang;
            $qty       = (int)($items_qty[$idx] ?? 0);
            if ($qty <= 0) { $errors[] = "Qty untuk item #".($idx+1)." tidak valid."; continue; }

            $cekStok = $conn->prepare("SELECT stok, nama_barang FROM inventory i JOIN barang b ON i.id_barang=b.id_barang WHERE i.id_barang = ?");
            $cekStok->execute([$id_barang]);
            $stokAktual = $cekStok->fetch();

            if (!$stokAktual || $stokAktual['stok'] < $qty) {
                $errors[] = "Stok <b>" . sanitize($stokAktual['nama_barang'] ?? 'Barang') . "</b> tidak mencukupi! Stok tersedia: " . ($stokAktual['stok'] ?? 0);
            }
        }

        if (empty($errors)) {
            $kode_transaksi = 'TRX-OUT-' . time() . rand(10, 99);
            try {
                $stmt = $conn->prepare("
                    INSERT INTO barang_keluar (kode_transaksi, tanggal, id_outlet, id_barang, qty, keterangan, id_user, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
                ");
                foreach ($items_id as $idx => $id_barang) {
                    $id_barang = (int)$id_barang;
                    $qty       = (int)($items_qty[$idx] ?? 0);
                    if ($qty <= 0) continue;
                    $stmt->execute([$kode_transaksi, $tanggal, $id_outlet, $id_barang, $qty, $keterangan, $id_user]);
                    $new_id = (int)$conn->lastInsertId();
                    writeAuditLog('CREATE', 'barang_keluar', $new_id, "Mengirim $qty item ke Outlet ID: $id_outlet");
                }

                $_SESSION['flash_success'] = "Berhasil mengirim <b>" . count($items_id) . " jenis barang</b> dan sedang <b>Menunggu Diterima</b> oleh Outlet.";
                echo "<script>window.location.href='$sistem/barangkeluar';</script>";
                exit;
            } catch (Exception $e) {
                $error = "Terjadi kesalahan sistem: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="fade-up max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/barangkeluar" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Distribusi Barang</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kirim beberapa barang sekaligus dari gudang pusat ke outlet.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0 text-red-500"></i>
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

    <form method="POST" id="formKeluar" onsubmit="return validateForm()">
        <!-- Section 1: Info Pengiriman -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-4">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <p class="text-sm font-semibold text-slate-700">
                    <i class="fa-solid fa-truck-fast text-indigo-500 mr-2"></i>Informasi Pengiriman
                </p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <!-- Tanggal dikunci ke Hari Ini (Readonly) -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tanggal Keluar (Hari Ini)</label>
                    <input type="text" readonly value="<?= date('d M Y') ?>"
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-100 text-slate-600 font-semibold cursor-not-allowed outline-none">
                    <input type="hidden" name="tanggal" value="<?= date('Y-m-d') ?>">
                </div>

                <!-- Outlet Tujuan -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Outlet Tujuan <span class="text-red-500">*</span></label>
                    <select name="id_outlet" id="id_outlet" required onchange="updateOutletAlamat()" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-slate-50">
                        <option value="" data-alamat="">-- Pilih Outlet --</option>
                        <?php foreach ($outlets as $o): ?>
                            <option value="<?= $o['id_outlet'] ?>" 
                                    data-alamat="<?= htmlspecialchars($o['alamat'] ?: 'Alamat tidak tersedia') ?>"
                                    <?= (isset($_POST['id_outlet']) && $_POST['id_outlet']==$o['id_outlet']) ? 'selected' : '' ?>>
                                <?= sanitize($o['nama_outlet']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section 2: Tambah Barang -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-4">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <p class="text-sm font-semibold text-slate-700">
                    <i class="fa-solid fa-boxes-stacked text-sky-500 mr-2"></i>Tambah Barang ke Daftar
                </p>
            </div>
            <div class="p-6 space-y-4">
                <!-- Scanner Banner -->
                <div class="bg-sky-50 border border-sky-100 rounded-xl p-4 flex flex-col items-center gap-3">
                    <div class="text-center">
                        <p class="text-sm font-bold text-sky-800">Gunakan Barcode Scanner</p>
                        <p class="text-xs text-sky-600/80 mt-0.5">Scan barcode produk — barang otomatis masuk ke daftar.</p>
                    </div>
                    <button type="button" onclick="startScanner()"
                            class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-all">
                        <i class="fa-solid fa-camera"></i> Mulai Scan Kamera
                    </button>
                    <div id="scannerBox" class="hidden w-full max-w-md bg-black rounded-xl overflow-hidden flex-col">
                        <div id="interactiveReader" class="w-full"></div>
                        <div class="p-3 bg-slate-800 flex justify-center">
                            <button type="button" onclick="stopScanner()"
                                    class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                                <i class="fa-solid fa-circle-xmark"></i> Tutup Kamera
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Manual Add -->
                <div class="flex gap-2">
                    <select id="selectBarangAdd" class="flex-1 px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white select2-barang">
                        <option value="">-- Ketik Nama Barang atau Pilih --</option>
                        <?php foreach ($barangs as $b): ?>
                            <option value="<?= $b['id_barang'] ?>" data-barcode="<?= sanitize($b['barcode']) ?>" data-stok="<?= $b['stok'] ?>" data-satuan="<?= sanitize($b['satuan'] ?: 'Pcs') ?>" data-nama="<?= sanitize($b['nama_barang']) ?>">
                                <?= sanitize($b['nama_barang']) ?> <?= $b['barcode'] ? "[{$b['barcode']}]" : "" ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="addItemToCart()"
                            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-all flex items-center gap-2 flex-shrink-0">
                        <i class="fa-solid fa-plus"></i> Tambah
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 3: Daftar Barang (Cart) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-4">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-emerald-500"></i> Daftar Barang yang Akan Dikirim
                    </p>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-1">
                        <span class="inline-flex items-center gap-1 font-medium text-slate-600">
                            <i class="fa-regular fa-calendar text-indigo-500"></i> Tanggal: <b class="text-slate-800"><?= date('d M Y') ?></b>
                        </span>
                        <span class="text-slate-300">•</span>
                        <span class="inline-flex items-center gap-1 font-medium text-slate-600">
                            <i class="fa-solid fa-location-dot text-rose-500"></i> Alamat Outlet: <b id="displayAlamatOutlet" class="text-slate-800 italic">Pilih outlet terlebih dahulu</b>
                        </span>
                    </div>
                </div>
                <span id="cartBadge" class="text-xs bg-slate-200 text-slate-600 font-bold px-2.5 py-0.5 rounded-full flex-shrink-0">0 item</span>
            </div>

            <!-- Empty State -->
            <div id="cartEmpty" class="px-6 py-10 text-center text-slate-400">
                <i class="fa-regular fa-folder-open text-4xl mb-3 block text-slate-300"></i>
                <p class="text-sm">Belum ada barang. Scan barcode atau pilih dari dropdown di atas.</p>
            </div>

            <!-- Cart Table -->
            <div id="cartContainer" class="hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Nama Barang</th>
                            <th class="text-center px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider w-32">Stok Gudang</th>
                            <th class="text-center px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider w-36">Jumlah Kirim</th>
                            <th class="text-center px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider w-16"></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <!-- JS will populate this -->
                    </tbody>
                </table>
            </div>

            <!-- Hidden inputs will be appended here by JS -->
            <div id="hiddenInputs"></div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3">
            <a href="<?= $sistem ?>/barangkeluar" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">Batal</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-paper-plane text-xs"></i> Kirim ke Outlet
            </button>
        </div>
    </form>
</div>

<!-- Libraries -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let html5QrcodeScanner = null;
let cart = [];

$(document).ready(function() {
    $('.select2-barang').select2({ width: '100%', placeholder: '-- Ketik Nama Barang --' });
    updateOutletAlamat();
});

function updateOutletAlamat() {
    const select = document.getElementById('id_outlet');
    const display = document.getElementById('displayAlamatOutlet');
    if (!select || !display) return;

    const selectedOpt = select.options[select.selectedIndex];
    const alamat = selectedOpt ? selectedOpt.dataset.alamat : '';

    if (select.value && alamat) {
        display.textContent = alamat;
        display.classList.remove('italic', 'text-slate-400');
        display.classList.add('text-slate-800');
    } else {
        display.textContent = 'Pilih outlet terlebih dahulu';
        display.classList.add('italic');
        display.classList.remove('text-slate-800');
    }
}

// ─── CART LOGIC ──────────────────────────────────────────────────────────────

function addItemToCart(idBarang = null) {
    const select = document.getElementById('selectBarangAdd');
    const id = idBarang || select.value;
    if (!id) { return; }

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
        stok:    parseInt(opt.dataset.stok) || 0,
        satuan:  opt.dataset.satuan || 'Pcs',
        qty:     1
    });

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
    $('#selectBarangAdd option[value="' + id + '"]').prop('disabled', false);
    $('#selectBarangAdd').select2({ width: '100%', placeholder: '-- Ketik Nama Barang --' });
    renderCart();
}

function updateQty(id, val) {
    const item = cart.find(c => c.id == id);
    if (item) {
        item.qty = parseInt(val) || 1;
    }
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
        row.className = 'border-b border-slate-100 hover:bg-slate-50/50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-3">
                <p class="font-semibold text-slate-800 text-sm">${item.nama}</p>
                <p class="text-xs text-slate-400 mt-0.5">Barcode: ${item.barcode}</p>
            </td>
            <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center gap-1 text-sm font-bold ${item.stok > 0 ? 'text-emerald-600' : 'text-red-500'}">
                    ${item.stok} <span class="text-xs font-normal text-slate-400">${item.satuan}</span>
                </span>
            </td>
            <td class="px-4 py-3">
                <div class="relative flex items-center justify-center">
                    <input type="number" id="qty_${item.id}" value="${item.qty}" min="1" max="${item.stok}"
                           onchange="updateQty('${item.id}', this.value)"
                           oninput="updateQty('${item.id}', this.value)"
                           class="w-24 text-center px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all font-semibold">
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

        if (parseInt(qtyEl?.value || 0) > item.stok) {
            Swal.fire({
                icon: 'error',
                title: 'Stok Tidak Cukup!',
                html: `Qty <b>${item.nama}</b> melebihi stok gudang (${item.stok} ${item.satuan}).`
            });
            qtyEl.focus();
            return false;
        }
    });

    if (cart.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Daftar Kosong', text: 'Tambahkan minimal 1 barang!' });
        return false;
    }
    if (!document.getElementById('id_outlet').value) {
        Swal.fire({ icon: 'warning', title: 'Pilih Outlet', text: 'Pilih outlet tujuan pengiriman!' });
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
                    Swal.fire({ icon: 'error', title: 'Tidak Ditemukan', text: 'Barcode ' + decodedText + ' belum terdaftar atau stok kosong.' });
                }
            }, 400);
        },
        () => { /* abaikan error per-frame */ }
    ).catch(err => {
        let msg = 'Izin kamera ditolak atau kamera tidak ditemukan.';
        Swal.fire({ icon: 'error', title: 'Kamera Gagal', html: msg });
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