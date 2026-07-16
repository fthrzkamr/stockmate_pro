<?php
if (!canDo('pemakaian', 'create')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses.</div>";
    exit;
}

global $conn, $sistem;

// AJAX Handler untuk mengambil stok barang per outlet secara dinamis tanpa refresh page
if (isset($_GET['action']) && $_GET['action'] === 'get_stok') {
    ob_clean();
    header('Content-Type: application/json');
    $outlet_id = (int)($_GET['outlet_id'] ?? 0);
    $stokList = [];
    try {
        $st = $conn->prepare("
            SELECT so.id_barang, so.stok, b.nama_barang, b.barcode, b.satuan 
            FROM stok_outlet so
            JOIN barang b ON so.id_barang = b.id_barang
            WHERE so.id_outlet = ? AND so.stok > 0
            ORDER BY b.nama_barang ASC
        ");
        $st->execute([$outlet_id]);
        $stokList = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    echo json_encode($stokList);
    exit;
}

$my_outlet_id = $_SESSION['outlet_id'] ?? null;
$selected_outlet = $my_outlet_id ?: ($_GET['outlet_id'] ?? $_POST['outlet_id'] ?? null);

$error = '';
$success = '';

// Ambil list outlet jika user adalah admin
$adminOutlets = [];
if (!$my_outlet_id) {
    try {
        $adminOutlets = $conn->query("SELECT * FROM outlet ORDER BY nama_outlet ASC")->fetchAll();
    } catch (Exception $e) { }
}

// Ambil data stok di outlet yang terpilih
$stokList = [];
$stokMap = [];
if ($selected_outlet) {
    try {
        $st = $conn->prepare("
            SELECT so.id_barang, so.stok, b.nama_barang, b.barcode, b.satuan 
            FROM stok_outlet so
            JOIN barang b ON so.id_barang = b.id_barang
            WHERE so.id_outlet = ? AND so.stok > 0
            ORDER BY b.nama_barang ASC
        ");
        $st->execute([$selected_outlet]);
        $stokList = $st->fetchAll();
        
        foreach ($stokList as $s) {
            $stokMap[$s['id_barang']] = [
                'stok' => $s['stok'],
                'satuan' => $s['satuan'],
                'nama' => $s['nama_barang'],
                'barcode' => $s['barcode'] ?: '—'
            ];
        }
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $post_outlet_id = $my_outlet_id ?: (int)($_POST['outlet_id'] ?? 0);
    $items_id = $_POST['item_id_barang'] ?? [];
    $items_qty = $_POST['item_qty'] ?? [];

    if (!$post_outlet_id) {
        $error = "Pilih outlet terlebih dahulu.";
    } elseif (empty($items_id)) {
        $error = "Tambahkan minimal 1 barang ke daftar pemakaian.";
    } else {
        // Validasi stok untuk semua item
        $errors = [];
        foreach ($items_id as $idx => $id_barang) {
            $id_barang = (int)$id_barang;
            $qty       = (int)($items_qty[$idx] ?? 0);
            if ($qty <= 0) {
                $errors[] = "Qty untuk item ke-" . ($idx + 1) . " tidak valid.";
                continue;
            }

            $cekStok = $conn->prepare("SELECT so.stok, b.nama_barang FROM stok_outlet so JOIN barang b ON so.id_barang = b.id_barang WHERE so.id_outlet = ? AND so.id_barang = ?");
            $cekStok->execute([$post_outlet_id, $id_barang]);
            $stokData = $cekStok->fetch(PDO::FETCH_ASSOC);
            $sisaStok = $stokData ? (int)$stokData['stok'] : 0;
            $namaBarang = $stokData ? $stokData['nama_barang'] : 'Barang';

            if ($qty > $sisaStok) {
                $errors[] = "Stok untuk <b>" . sanitize($namaBarang) . "</b> tidak mencukupi! Sisa stok saat ini hanya $sisaStok.";
            }
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                $kode_transaksi = 'TRX-PEM-' . time() . rand(10, 99);
                $stmt = $conn->prepare("
                    INSERT INTO pemakaian (kode_transaksi, tanggal, id_outlet, id_barang, qty, keterangan, id_user)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($items_id as $idx => $id_barang) {
                    $id_barang = (int)$id_barang;
                    $qty       = (int)($items_qty[$idx] ?? 0);
                    if ($qty <= 0) continue;
                    
                    // Ambil sisa stok saat ini sebelum pemakaian untuk log
                    $cekStok = $conn->prepare("SELECT stok FROM stok_outlet WHERE id_outlet = ? AND id_barang = ?");
                    $cekStok->execute([$post_outlet_id, $id_barang]);
                    $sisaStok = (int)$cekStok->fetchColumn();

                    $stmt->execute([$kode_transaksi, $tanggal, $post_outlet_id, $id_barang, $qty, $keterangan ?: null, $_SESSION['user_id']]);
                    $id_pemakaian = $conn->lastInsertId();
                    writeAuditLog('CREATE', 'pemakaian', $id_pemakaian, "Pemakaian barang qty $qty. Sisa stok menjadi " . ($sisaStok - $qty));
                }
                $conn->commit();
                $_SESSION['flash_success'] = "Data pemakaian berhasil disimpan.";
                $success = true;
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}
?>

<div class="fade-up max-w-2xl mx-auto space-y-5">
    
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/pemakaian" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Input Pemakaian</h1>
            <p class="text-slate-500 text-sm">Catat pemakaian barang di outlet Anda.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateForm()" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 space-y-5">
            
            <!-- Tanggal -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tanggal <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-slate-50">
            </div>

            <!-- Pilih Outlet (Khusus Admin/SPV) -->
            <?php if (!$my_outlet_id && !empty($adminOutlets)): ?>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Pilih Outlet <span class="text-red-500">*</span></label>
                <select name="outlet_id" id="outlet_id_select" required onchange="changeOutlet(this.value)"
                        class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
                    <option value="">— Pilih Cabang / Outlet —</option>
                    <?php foreach ($adminOutlets as $o): ?>
                    <option value="<?= $o['id_outlet'] ?>" <?= ($selected_outlet == $o['id_outlet']) ? 'selected' : '' ?>>
                        <?= sanitize($o['nama_outlet']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-400 mt-1">Pilih outlet terlebih dahulu untuk memunculkan daftar stok barang.</p>
            </div>
            <?php endif; ?>

            <!-- Scan Barcode (Kamera & Manual) -->
            <div id="scanner_container" class="<?= $selected_outlet ? 'flex' : 'hidden' ?> bg-indigo-50 border border-indigo-100 rounded-xl p-4 flex flex-col items-center gap-3">
                <div class="text-center">
                    <p class="text-sm font-bold text-indigo-800">Scan Barcode Produk</p>
                    <p class="text-xs text-indigo-600/80 mt-0.5">Scan menggunakan alat scanner atau kamera HP.</p>
                </div>
                <div class="w-full relative max-w-md">
                    <i class="fa-solid fa-barcode absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="scan_barcode" placeholder="Scan barcode di sini..." autofocus
                           class="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
                </div>
                <button type="button" onclick="startScanner()"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-all">
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

            <!-- Pilih Barang & Qty -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Pilih Barang</label>
                    <select id="id_barang" onchange="updateMaxStok()" <?= !$selected_outlet ? 'disabled' : '' ?>
                            class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all <?= !$selected_outlet ? 'bg-slate-100 cursor-not-allowed' : 'bg-white' ?>">
                        <option value="">— Ketik atau Pilih Barang —</option>
                        <?php foreach ($stokList as $b): ?>
                        <option value="<?= $b['id_barang'] ?>" data-barcode="<?= sanitize($b['barcode']) ?>" <?= (($_POST['id_barang'] ?? '') == $b['id_barang']) ? 'selected' : '' ?>>
                            <?= sanitize($b['nama_barang']) ?> <?= $b['barcode'] ? '('.$b['barcode'].')' : '' ?> - Sisa: <?= number_format($b['stok']) ?> <?= $b['satuan'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-400 mt-1" id="pilih_barang_help">
                        <?= $selected_outlet ? 'Hanya barang yang memiliki stok di outlet terpilih yang akan tampil.' : 'Pilih outlet terlebih dahulu untuk menampilkan daftar barang.' ?>
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Qty</label>
                    <div class="relative flex items-center">
                        <input type="number" id="qty" min="1"
                               class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all">
                        <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                            <span class="text-sm font-semibold text-slate-400" id="satuan_label">Pcs</span>
                        </div>
                    </div>
                    <p class="text-xs font-bold text-emerald-600 mt-1" id="max_stok_info"></p>
                </div>
            </div>

            <!-- Tambah Button -->
            <div class="flex justify-end">
                <button type="button" onclick="addItemToCart()" id="btn_tambah_item" <?= !$selected_outlet ? 'disabled' : '' ?>
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white px-5 py-2.5 rounded-xl text-xs font-bold transition-all shadow-sm">
                    <i class="fa-solid fa-plus"></i> Tambah Ke Daftar
                </button>
            </div>

            <!-- Section 3: Daftar Pemakaian (Cart) -->
            <div class="bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200 bg-slate-100/60 flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <i class="fa-solid fa-list-check text-indigo-500 mr-2"></i>Daftar Pemakaian Barang
                    </p>
                    <span id="cartBadge" class="text-[10px] bg-indigo-100 text-indigo-700 font-bold px-2.5 py-0.5 rounded-full">0 item</span>
                </div>

                <!-- Empty State -->
                <div id="cartEmpty" class="p-6 text-center text-slate-400">
                    <i class="fa-regular fa-folder-open text-3xl mb-2 block text-slate-300"></i>
                    <p class="text-xs">Daftar barang masih kosong. Scan barcode atau pilih dari menu di atas.</p>
                </div>

                <!-- Cart Table -->
                <div id="cartContainer" class="hidden overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-100 border-b border-slate-200 text-slate-600">
                            <tr>
                                <th class="px-4 py-2.5 font-bold uppercase">Nama Barang</th>
                                <th class="px-4 py-2.5 font-bold uppercase text-center w-28">Stok Outlet</th>
                                <th class="px-4 py-2.5 font-bold uppercase text-center w-32">Qty Digunakan</th>
                                <th class="px-4 py-2.5 text-center w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody" class="divide-y divide-slate-100 bg-white">
                            <!-- JS will populate this -->
                        </tbody>
                    </table>
                </div>

                <!-- Hidden inputs will be appended here by JS -->
                <div id="hiddenInputs"></div>
            </div>

            <!-- Keterangan -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Keterangan / Catatan</label>
                <textarea name="keterangan" rows="3" placeholder="Contoh: Digunakan untuk acara..."
                          class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all"><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
            </div>

        </div>
        
        <!-- Footer -->
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3">
            <a href="<?= $sistem ?>/pemakaian" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Pemakaian
            </button>
        </div>
    </form>
</div>

<!-- Import Html5Qrcode Library -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let stokMap = <?= json_encode($stokMap) ?>;
let cart = [];

function changeOutlet(outletId) {
    const selectBarang = document.getElementById('id_barang');
    const scannerContainer = document.getElementById('scanner_container');
    const helpText = document.getElementById('pilih_barang_help');
    const btnTambah = document.getElementById('btn_tambah_item');
    
    // Reset dropdown barang
    selectBarang.innerHTML = '<option value="">— Ketik atau Pilih Barang —</option>';
    selectBarang.disabled = true;
    selectBarang.classList.add('bg-slate-100', 'cursor-not-allowed');
    selectBarang.classList.remove('bg-white');
    
    // Reset button
    if (btnTambah) {
        btnTambah.disabled = true;
    }
    
    // Hide scanner
    scannerContainer.classList.add('hidden');
    scannerContainer.classList.remove('flex');
    
    // Clear cart & max stock info
    cart = [];
    stokMap = {};
    updateMaxStok();
    renderCart();
    
    if (!outletId) {
        helpText.textContent = 'Pilih outlet terlebih dahulu untuk menampilkan daftar barang.';
        return;
    }
    
    helpText.textContent = 'Memuat stok barang...';
    
    fetch(`${usuper}/pemakaian/i?action=get_stok&outlet_id=${outletId}`)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                helpText.textContent = 'Tidak ada barang yang memiliki stok di outlet ini.';
                return;
            }
            
            // Populate barang dropdown & stokMap
            data.forEach(item => {
                stokMap[item.id_barang] = {
                    stok: parseInt(item.stok),
                    satuan: item.satuan || 'Pcs',
                    nama: item.nama_barang,
                    barcode: item.barcode || '—'
                };
                
                const option = document.createElement('option');
                option.value = item.id_barang;
                option.setAttribute('data-barcode', item.barcode || '');
                
                let barcodeLabel = item.barcode ? `(${item.barcode}) ` : '';
                option.textContent = `${item.nama_barang} ${barcodeLabel}- Sisa: ${item.stok} ${item.satuan || 'Pcs'}`;
                selectBarang.appendChild(option);
            });
            
            // Enable dropdown & button
            selectBarang.disabled = false;
            selectBarang.classList.remove('bg-slate-100', 'cursor-not-allowed');
            selectBarang.classList.add('bg-white');
            
            if (btnTambah) {
                btnTambah.disabled = false;
            }
            
            // Show scanner container
            scannerContainer.classList.remove('hidden');
            scannerContainer.classList.add('flex');
            
            helpText.textContent = 'Hanya barang yang memiliki stok di outlet terpilih yang akan tampil.';
        })
        .catch(err => {
            console.error('Error fetching stock:', err);
            helpText.textContent = 'Gagal memuat stok barang. Coba pilih ulang outlet.';
            Swal.fire({
                icon: 'error',
                title: 'Gagal Memuat Stok',
                text: 'Terjadi kesalahan saat memuat stok outlet.'
            });
        });
}

// ─── CART LOGIC ──────────────────────────────────────────────────────────────
function addItemToCart(idBarang = null, customQty = null) {
    const select = document.getElementById('id_barang');
    const id = idBarang || select.value;
    if (!id) {
        Swal.fire({ icon: 'warning', title: 'Pilih Barang', text: 'Pilih barang terlebih dahulu!' });
        return;
    }

    const itemData = stokMap[id];
    if (!itemData) return;

    // Cek apakah sudah ada di cart
    const existing = cart.find(c => c.id == id);
    if (existing) {
        // Increment qty
        let newQty = existing.qty + (customQty || 1);
        if (newQty > itemData.stok) {
            Swal.fire({
                icon: 'error',
                title: 'Stok Tidak Mencukupi!',
                text: `Stok saat ini hanya tersedia ${itemData.stok} ${itemData.satuan}.`
            });
            return;
        }
        existing.qty = newQty;
        renderCart();
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Jumlah barang bertambah!', showConfirmButton: false, timer: 1500 });
        return;
    }

    const qtyInput = document.getElementById('qty');
    let qtyVal = customQty || parseInt(qtyInput.value) || 1;
    
    if (qtyVal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Qty Tidak Valid', text: 'Masukkan jumlah (Qty) minimal 1!' });
        return;
    }
    
    if (qtyVal > itemData.stok) {
        Swal.fire({
            icon: 'error',
            title: 'Stok Tidak Mencukupi!',
            text: `Stok saat ini hanya tersedia ${itemData.stok} ${itemData.satuan}.`
        });
        return;
    }

    cart.push({
        id:      id,
        nama:    itemData.nama,
        barcode: itemData.barcode,
        stok:    itemData.stok,
        satuan:  itemData.satuan,
        qty:     qtyVal
    });

    // Disable option in dropdown
    const opt = select.querySelector(`option[value="${id}"]`);
    if (opt) opt.disabled = true;

    renderCart();
    
    // Reset inputs
    select.value = '';
    qtyInput.value = '';
    updateMaxStok();
}

function removeFromCart(id) {
    cart = cart.filter(c => c.id != id);
    
    // Re-enable option in dropdown
    const select = document.getElementById('id_barang');
    if (select) {
        const opt = select.querySelector(`option[value="${id}"]`);
        if (opt) opt.disabled = false;
    }
    
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
                <span class="inline-flex items-center gap-1 text-sm font-bold text-slate-600">
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

        // Hidden inputs for form submission
        hiddenDiv.innerHTML += `<input type="hidden" name="item_id_barang[]" value="${item.id}">`;
        hiddenDiv.innerHTML += `<input type="hidden" id="hidden_qty_${item.id}" name="item_qty[]" value="${item.qty}">`;
    });
}

function validateForm() {
    // Sync qty values dari input ke hidden inputs
    cart.forEach(item => {
        const qtyEl = document.getElementById('qty_' + item.id);
        const hiddenEl = document.getElementById('hidden_qty_' + item.id);
        if (qtyEl && hiddenEl) {
            hiddenEl.value = qtyEl.value;
        }

        // Validasi qty > stok
        if (parseInt(qtyEl?.value || 0) > item.stok) {
            Swal.fire({
                icon: 'error',
                title: 'Stok Tidak Cukup!',
                html: `Qty <b>${item.nama}</b> melebihi stok outlet (${item.stok} ${item.satuan}).`
            });
            qtyEl.focus();
            return false;
        }
    });

    if (cart.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Daftar Kosong', text: 'Tambahkan minimal 1 barang ke daftar!' });
        return false;
    }
    
    // Check if outlet is selected (for Admin)
    const outletSelect = document.getElementById('outlet_id_select');
    if (outletSelect && !outletSelect.value) {
        Swal.fire({ icon: 'warning', title: 'Pilih Outlet', text: 'Pilih outlet asal pemakaian!' });
        return false;
    }
    
    return true;
}

function updateMaxStok() {
    const id = document.getElementById('id_barang').value;
    const info = document.getElementById('max_stok_info');
    const inputQty = document.getElementById('qty');
    const labelSatuan = document.getElementById('satuan_label');
    
    if (id && stokMap[id]) {
        const sisa = stokMap[id].stok;
        const satuan = stokMap[id].satuan;
        info.innerHTML = `Maksimal input: ${sisa} ${satuan}`;
        inputQty.max = sisa;
        labelSatuan.textContent = satuan;
    } else {
        info.innerHTML = '';
        inputQty.max = '';
        labelSatuan.textContent = 'Qty';
    }
}

// Inisialisasi saat load
updateMaxStok();

// Fitur Scan Barcode (Manual Input / Scanner USB)
const scanInput = document.getElementById('scan_barcode');
if (scanInput) {
    scanInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const barcode = this.value.trim();
            if (!barcode) return;

            let found = null;
            for (const key in stokMap) {
                if (stokMap[key].barcode === barcode) {
                    found = { id: key, ...stokMap[key] };
                    break;
                }
            }

            if (found) {
                addItemToCart(found.id, 1);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Tidak Ditemukan',
                    text: 'Barang dengan barcode ' + barcode + ' tidak ada stok di outlet ini.',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
            this.value = ''; // Reset input scanner
        }
    });
}

// Fitur Scan Barcode (Kamera HP/Webcam)
let html5QrcodeScanner = null;

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
                for (const key in stokMap) {
                    if (stokMap[key].barcode === decodedText) {
                        found = { id: key, ...stokMap[key] };
                        break;
                    }
                }

                Swal.close();
                if (found) {
                    addItemToCart(found.id, 1);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Terpilih & ditambah otomatis!', showConfirmButton: false, timer: 2000 });
                } else {
                    Swal.fire({ icon: 'error', title: 'Tidak Ditemukan', text: 'Barang dengan barcode ' + decodedText + ' tidak ada stok di outlet ini.' });
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

<?php if ($success): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: 'Data pemakaian berhasil disimpan. Stok Outlet telah berkurang otomatis.',
    timer: 2000,
    showConfirmButton: false
}).then(() => {
    window.location.href = '<?= $sistem ?>/pemakaian';
});
<?php endif; ?>
</script>