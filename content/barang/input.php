<?php
if (!canDo('barang', 'create')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk menambah barang.</div>";
    exit;
}

global $conn, $sistem;

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
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcodes      = $_POST['barcode'] ?? [];
    $nama_barangs  = $_POST['nama_barang'] ?? [];
    $kategoris     = $_POST['kategori'] ?? [];
    $satuans       = $_POST['satuan'] ?? [];
    $min_stoks     = $_POST['min_stok'] ?? [];
    $id_tipes      = $_POST['id_tipe'] ?? [];

    if (empty($nama_barangs)) {
        $error = 'Minimal satu barang harus diinput.';
    } else {
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO barang (barcode, nama_barang, kategori, satuan, min_stok, id_tipe, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            
            $addedCount = 0;
            
            foreach ($nama_barangs as $index => $nama) {
                $nama = trim($nama);
                if (!$nama) continue; // Skip empty rows
                
                $barcode = trim($barcodes[$index] ?? '');
                if (empty($barcode)) {
                    for ($i = 0; $i < 15; $i++) {
                        $barcode .= mt_rand(0, 9);
                    }
                }
                
                $id_kat = (int)($kategoris[$index] ?? 0);
                $kategori = 'Lainnya';
                foreach ($kategori_list as $k) {
                    if ($k['id_kategori'] == $id_kat) {
                        $kategori = $k['nama_kategori'];
                        break;
                    }
                }

                $satuan = trim($satuans[$index] ?? 'Pcs');
                $min_stok = (int)($min_stoks[$index] ?? 0);
                $id_tipe = (int)($id_tipes[$index] ?? 0) ?: null;
                
                if (!$id_tipe) {
                    $errors[] = "Barang '$nama' gagal ditambahkan: Tipe Barang wajib dipilih.";
                    continue;
                }

                // Cek duplikat barcode
                if ($barcode) {
                    $cek = $conn->prepare("SELECT nama_barang FROM barang WHERE barcode = ?");
                    $cek->execute([$barcode]);
                    $exBarang = $cek->fetchColumn();
                    if ($exBarang) {
                        $errors[] = "Barcode <b>$barcode</b> gagal: Sudah digunakan oleh barang <b>$exBarang</b>";
                        continue;
                    }
                }

                $stmt->execute([
                    $barcode ?: null,
                    $nama,
                    $kategori,
                    $satuan,
                    $min_stok,
                    $id_tipe
                ]);
                
                $new_id = (int)$conn->lastInsertId();
                $conn->exec("INSERT IGNORE INTO inventory (id_barang, stok) VALUES ($new_id, 0)");
                writeAuditLog('CREATE', 'barang', $new_id, "Menambahkan barang baru: $nama (Barcode: " . ($barcode ?: '-') . ")");
                
                $addedCount++;
            }

            if ($addedCount > 0) {
                $conn->commit();
                $_SESSION['flash_success'] = "Berhasil menambahkan <b>$addedCount</b> barang baru ke Master Data.";
                if (!empty($errors)) {
                    $_SESSION['flash_success'] .= " (Beberapa baris dilewati karena error)";
                }
                echo "<script>window.location.href='$sistem/barang';</script>";
                exit;
            } else {
                $conn->rollBack();
                $error = 'Tidak ada barang yang berhasil ditambahkan. Silakan cek error di bawah.';
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}
?>

<style>
/* Animasi slide untuk baris baru */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.new-row { animation: slideDown 0.3s ease-out forwards; }
</style>

<div class="fade-up max-w-7xl mx-auto space-y-5">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="<?= $sistem ?>/barang" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Tambah Master Barang</h1>
                <p class="text-slate-500 text-sm">Daftarkan banyak item sekaligus ke dalam sistem.</p>
            </div>
        </div>
    </div>

    <!-- Alert Error -->
    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm shadow-sm">
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

    <!-- Form Container -->
    <form method="POST" id="formBarangMulti" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col min-h-[500px]">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <p class="text-sm font-semibold text-slate-700">
                <i class="fa-solid fa-table-list text-sky-500 mr-2"></i>Daftar Item Baru
            </p>
            <div class="flex gap-2">
                <button type="button" onclick="startScanner()"
                        class="inline-flex items-center gap-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                    <i class="fa-solid fa-camera"></i> Barcode Scanner
                </button>
                <button type="button" onclick="addRow()"
                        class="inline-flex items-center gap-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                    <i class="fa-solid fa-plus"></i> Tambah Baris
                </button>
            </div>
        </div>

        <!-- Kamera Scanner Box (Tersembunyi) -->
        <div id="scannerBox" class="hidden bg-slate-800 text-white p-4 text-center space-y-3 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider"><i class="fa-solid fa-camera mr-1"></i> Scanner Aktif</span>
                <button type="button" onclick="stopScanner()" class="text-xs text-rose-400 hover:text-rose-300 font-semibold">Tutup Kamera</button>
            </div>
            <div id="interactiveReader" class="w-full max-w-sm mx-auto overflow-hidden rounded-xl bg-black"></div>
            <p class="text-[10px] text-slate-400">Posisikan barcode produk tepat di kotak. Hasil scan akan mengisi baris aktif (yg disorot kuning).</p>
        </div>
        
        <div class="flex-1 p-0 overflow-x-auto">
            <table class="w-full text-left whitespace-nowrap" id="tblItems">
                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-10 text-center">No</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-48">Barcode</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider min-w-[200px]">Nama Barang <span class="text-red-500">*</span></th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-36">Kategori</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-40">Tipe <span class="text-red-500">*</span></th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-24">Satuan</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-24">Min Stok</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-10 text-center"><i class="fa-solid fa-trash"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyItems" class="divide-y divide-slate-100">
                    <!-- Rows will be injected here by JS -->
                </tbody>
            </table>
        </div>

        <!-- Footer Buttons -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <a href="<?= $sistem ?>/barang"
               class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Semua Barang
            </button>
        </div>
    </form>
</div>

<!-- Load HTML5-QRCode Library untuk mobile scanning -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<script>
const tbody = document.getElementById('tbodyItems');
let rowCount = 0;
let activeRowId = null; // Menandakan baris mana yg akan diisi barcode hasil scan

const allTipe = <?= json_encode($tipe_barang_list) ?>;
const allKategori = <?= json_encode($kategori_list) ?>;

// Opsi dropdown untuk diclone ke setiap baris
const optsKategori = `
    <option value="">- Pilih Kategori -</option>
    <?php foreach ($kategori_list as $k): ?>
    <option value="<?= $k['id_kategori'] ?>"><?= sanitize($k['nama_kategori']) ?></option>
    <?php endforeach; ?>
`;

const optsSatuan = `
    <option value="Pcs" selected>Pcs</option>
    <option value="Botol">Botol</option>
    <option value="Pack">Pack</option>
    <option value="Pouch">Pouch</option>
    <option value="Karton">Karton</option>
    <option value="Liter">Liter</option>
    <option value="Kg">Kg</option>
`;

function onKategoriChange(selectElem) {
    const tr = selectElem.closest('tr');
    const tipeSelect = tr.querySelector('select[name="id_tipe[]"]');
    const idKat = selectElem.value;
    
    tipeSelect.innerHTML = '<option value="">- Tipe -</option>';
    if (!idKat) return;

    const filtered = allTipe.filter(t => t.id_kategori == idKat);
    filtered.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id_tipe;
        opt.textContent = t.nama_tipe;
        tipeSelect.appendChild(opt);
    });
}

function addRow() {
    rowCount++;
    const tr = document.createElement('tr');
    tr.id = 'row_' + rowCount;
    tr.className = 'new-row hover:bg-slate-50 transition-colors group cursor-pointer';
    tr.onclick = function() { setActiveRow(tr.id); };
    
    tr.innerHTML = `
        <td class="px-4 py-2 text-center text-xs font-medium text-slate-400 row-number">${rowCount}</td>
        <td class="px-4 py-2">
            <input type="text" name="barcode[]" id="barcode_${rowCount}" placeholder="Scan/Ketik..." 
                   class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded focus:border-sky-400 focus:ring-1 focus:ring-sky-400 outline-none">
        </td>
        <td class="px-4 py-2">
            <input type="text" name="nama_barang[]" placeholder="Contoh: Sunlight Jeruk Nipis" required
                   class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded focus:border-sky-400 focus:ring-1 focus:ring-sky-400 outline-none">
        </td>
        <td class="px-4 py-2">
            <select name="kategori[]" onchange="onKategoriChange(this)" required class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded focus:border-sky-400 focus:ring-1 focus:ring-sky-400 outline-none bg-white">
                ${optsKategori}
            </select>
        </td>
        <td class="px-4 py-2">
            <select name="id_tipe[]" required class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded focus:border-sky-400 focus:ring-1 focus:ring-sky-400 outline-none bg-white">
                <option value="">- Tipe -</option>
            </select>
        </td>
        <td class="px-4 py-2">
            <select name="satuan[]" class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded focus:border-sky-400 focus:ring-1 focus:ring-sky-400 outline-none bg-white">
                ${optsSatuan}
            </select>
        </td>
        <td class="px-4 py-2">
            <input type="number" name="min_stok[]" value="5" min="0"
                   class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded focus:border-sky-400 focus:ring-1 focus:ring-sky-400 outline-none">
        </td>
        <td class="px-4 py-2 text-center">
            <button type="button" onclick="removeRow('${tr.id}')" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    setActiveRow(tr.id);
    updateRowNumbers();
}

function removeRow(id) {
    const row = document.getElementById(id);
    if (row) row.remove();
    updateRowNumbers();
}

function updateRowNumbers() {
    const rows = tbody.querySelectorAll('tr');
    rows.forEach((r, idx) => {
        r.querySelector('.row-number').innerText = idx + 1;
    });
}

function setActiveRow(id) {
    // Hapus class aktif dari semua baris
    document.querySelectorAll('#tbodyItems tr').forEach(r => {
        r.classList.remove('bg-amber-50');
        r.classList.add('hover:bg-slate-50');
    });
    // Set aktif
    const row = document.getElementById(id);
    if (row) {
        row.classList.remove('hover:bg-slate-50');
        row.classList.add('bg-amber-50');
        activeRowId = id;
    }
}

// Inisialisasi awal 1 baris
document.addEventListener('DOMContentLoaded', () => {
    addRow();
    setActiveRow('row_1');
});

// ─── SCANNER LOGIC ───────────────────────────────────────────────────────────
let html5QrcodeScanner = null;

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
    
    html5QrcodeScanner.start(
        { facingMode: "environment" }, 
        { fps: 24, qrbox: { width: 300, height: 120 }, aspectRatio: 1.777778 },
        (decodedText) => {
            // Masukkan barcode ke input row yang aktif
            if (activeRowId) {
                const inputBarcode = document.querySelector('#' + activeRowId + ' input[name="barcode[]"]');
                if (inputBarcode) {
                    inputBarcode.value = decodedText;
                    // Auto-fokus ke nama barang
                    const inputNama = document.querySelector('#' + activeRowId + ' input[name="nama_barang[]"]');
                    if (inputNama) inputNama.focus();
                }
            }
            
            Swal.fire({
                icon: 'success', title: 'Barcode: ' + decodedText, timer: 1000,
                showConfirmButton: false, position: 'top-end', toast: true
            });
            // Auto stop setelah baca jika dimau, tapi biar bisa lanjut scan jangan distop
        },
        () => {}
    ).catch(err => {
        let errorMsg = 'Izin kamera ditolak atau kamera tidak ditemukan.';
        if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
            errorMsg = 'Kamera memblokir koneksi non-HTTPS.';
        }
        Swal.fire({ icon: 'error', title: 'Kamera Gagal', html: errorMsg });
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