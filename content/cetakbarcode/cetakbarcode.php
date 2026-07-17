<?php
if (!canDo('cetakbarcode', 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit;
}

global $conn, $sistem;

// AJAX Handle Generate Barcode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_barcode') {
    ob_clean();
    header('Content-Type: application/json');
    if (!canDo('cetakbarcode', 'edit')) {
        echo json_encode(['status' => 'error', 'msg' => 'Tidak ada akses mengedit barang.']);
        exit;
    }
    
    $id_barang = (int)$_POST['id_barang'];
    if (!$id_barang) {
        echo json_encode(['status' => 'error', 'msg' => 'ID Barang tidak valid.']);
        exit;
    }

    try {
        // Generate a new barcode: 15 digit random numbers
        $newBarcode = '';
        for ($i = 0; $i < 15; $i++) {
            $newBarcode .= mt_rand(0, 9);
        }

        $stmt = $conn->prepare("UPDATE barang SET barcode = ? WHERE id_barang = ?");
        $stmt->execute([$newBarcode, $id_barang]);
        
        writeAuditLog('UPDATE', 'barang', $id_barang, "Generate barcode otomatis: $newBarcode");
        
        echo json_encode(['status' => 'success', 'msg' => 'Barcode berhasil di-generate!', 'barcode' => $newBarcode]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// Pagination & Filter
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

$whereClauses = ["1=1"];
$params = [];

if ($search) {
    $whereClauses[] = "(b.nama_barang LIKE ? OR b.barcode LIKE ? OR b.kategori LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filter Status Barcode (Semua, Punya Barcode, Belum Punya)
$filterBarcode = $_GET['status_barcode'] ?? 'all';
if ($filterBarcode === 'has') {
    $whereClauses[] = "(b.barcode IS NOT NULL AND b.barcode != '')";
} elseif ($filterBarcode === 'empty') {
    $whereClauses[] = "(b.barcode IS NULL OR b.barcode = '')";
}

$whereSql = implode(" AND ", $whereClauses);

try {
    $countQuery = "SELECT COUNT(id_barang) FROM barang b WHERE $whereSql";
    $stmtCount = $conn->prepare($countQuery);
    $stmtCount->execute($params);
    $totalData = $stmtCount->fetchColumn();
    $totalPages = ceil($totalData / $limit);

    $query = "
        SELECT b.id_barang, b.nama_barang, b.barcode, b.kategori, b.satuan, t.nama_tipe
        FROM barang b
        LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
        WHERE $whereSql
        ORDER BY b.id_barang DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
} catch (Exception $e) {
    $data = [];
    $totalData = 0;
    $totalPages = 0;
}
?>

<div class="fade-up max-w-7xl mx-auto space-y-5">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Generator & Cetak Barcode</h1>
            <p class="text-slate-500 text-sm mt-1">Buat kode barcode untuk barang yang belum memilikinya dan cetak label harga/stiker barcode.</p>
        </div>
        <div>
            <button type="button" onclick="cetakTerpilih()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm shadow-indigo-500/30 inline-flex items-center gap-2 hidden" id="btnCetakTerpilih">
                <i class="fa-solid fa-print"></i> Cetak Label Terpilih (<span id="countTerpilih">0</span>)
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-3">
        <form id="filterForm" method="GET" class="flex flex-col sm:flex-row w-full gap-3">
            <input type="hidden" name="menu" value="cetakbarcode">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama barang, barcode..." 
                       class="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>
            
            <select name="status_barcode" class="px-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 bg-white" onchange="document.getElementById('filterForm').submit()">
                <option value="all" <?= $filterBarcode === 'all' ? 'selected' : '' ?>>Semua Barang</option>
                <option value="has" <?= $filterBarcode === 'has' ? 'selected' : '' ?>>Sudah Ada Barcode</option>
                <option value="empty" <?= $filterBarcode === 'empty' ? 'selected' : '' ?>>Belum Ada Barcode</option>
            </select>

            <button type="submit" class="bg-slate-100 text-slate-600 hover:bg-slate-200 px-5 py-2 rounded-xl text-sm font-semibold transition-colors whitespace-nowrap">
                Filter
            </button>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-6 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <?php echo generateShowEntries($limit, 'cetakbarcode', $search); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 w-10 text-center">
                            <input type="checkbox" id="checkAll" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                        </th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Barang</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Kategori</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Barcode</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">Tidak ada data barang.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): 
                            $has_barcode = !empty($row['barcode']);
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors" id="row-<?= $row['id_barang'] ?>">
                            <td class="px-6 py-4 text-center">
                                <?php if ($has_barcode): ?>
                                <input type="checkbox" name="cetak[]" value="<?= $row['id_barang'] ?>" class="cb-cetak w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                                <?php else: ?>
                                <span class="text-slate-300"><i class="fa-solid fa-ban"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-800"><?= sanitize($row['nama_barang']) ?></p>
                                <p class="text-[10px] font-semibold text-slate-400 mt-0.5"><?= sanitize($row['nama_tipe']) ?> &bull; <?= sanitize($row['satuan']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <?= sanitize($row['kategori']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($has_barcode): ?>
                                <span class="font-mono bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs font-bold" id="bc-<?= $row['id_barang'] ?>">
                                    <i class="fa-solid fa-barcode text-slate-400 mr-1"></i><?= sanitize($row['barcode']) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-500 px-2 py-1 rounded-md border border-rose-100" id="bc-<?= $row['id_barang'] ?>">Belum Ada</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!$has_barcode && canDo('cetakbarcode', 'edit')): ?>
                                <button type="button" onclick="generateBarcode(<?= $row['id_barang'] ?>)"
                                        class="bg-amber-50 text-amber-600 hover:bg-amber-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors border border-amber-200">
                                    <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Generate
                                </button>
                                <?php elseif ($has_barcode): ?>
                                <button type="button" onclick="bukaModalCetak(<?= $row['id_barang'] ?>, '<?= addslashes($row['nama_barang']) ?>')"
                                        class="bg-sky-50 text-sky-600 hover:bg-sky-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors border border-sky-100">
                                    <i class="fa-solid fa-print mr-1"></i> Cetak (1 item)
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="text-sm text-slate-500 font-medium">
                Menampilkan <span class="font-bold text-slate-700"><?= min($offset + 1, $totalData) ?></span> - 
                <span class="font-bold text-slate-700"><?= min($offset + $limit, $totalData) ?></span> dari 
                <span class="font-bold text-slate-700"><?= $totalData ?></span> data
            </div>
            <div class="flex items-center gap-1">
                <button type="button" onclick="goToPage(<?= max(1, $page - 1) ?>, <?= $limit ?>, 'cetakbarcode', '<?= urlencode($search) ?>&status_barcode=<?= $filterBarcode ?>')" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $page <= 1 ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-left text-xs"></i></button>
                <button type="button" onclick="goToPage(<?= min($totalPages, $page + 1) ?>, <?= $limit ?>, 'cetakbarcode', '<?= urlencode($search) ?>&status_barcode=<?= $filterBarcode ?>')" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 <?= $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-right text-xs"></i></button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Setting Cetak Multiple -->
<div id="modalPrintOpts" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="modalPrintOptsContent">
        <form method="POST" action="<?= $sistem ?>/pcetakbarcode" target="_blank" id="formCetakMultiple">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">Pengaturan Cetak Barcode</h3>
                <button type="button" onclick="closeModalPrint()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-indigo-50 text-indigo-700 p-3 rounded-xl text-sm font-medium flex gap-3 border border-indigo-100">
                    <i class="fa-solid fa-info-circle mt-0.5"></i>
                    <p>Anda memilih <b id="lblJmlItems">0</b> jenis barang.</p>
                </div>
                <!-- Container untuk menyimpan ID Barang yang diprint -->
                <div id="containerIdBarang"></div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Jumlah Cetak PER Barang</label>
                    <input type="number" name="qty_per_item" value="1" min="1" max="100" class="w-full px-4 py-2 text-sm border border-slate-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 outline-none" required>
                    <p class="text-[10px] text-slate-400 mt-1">Misal jika diisi 3, maka setiap barang akan dicetak 3 stiker.</p>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button type="button" onclick="closeModalPrint()" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-200 text-sm font-semibold transition-colors border border-slate-200">Batal</button>
                <button type="submit" onclick="closeModalPrint()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-bold shadow-sm transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-print"></i> Proses Cetak
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Checkbox Logic
const checkAll = document.getElementById('checkAll');
const cbItems = document.querySelectorAll('.cb-cetak');
const btnCetak = document.getElementById('btnCetakTerpilih');
const countSpan = document.getElementById('countTerpilih');

function updateBtnCetak() {
    let checkedCount = 0;
    cbItems.forEach(cb => { if(cb.checked) checkedCount++; });
    
    countSpan.textContent = checkedCount;
    if (checkedCount > 0) {
        btnCetak.classList.remove('hidden');
    } else {
        btnCetak.classList.add('hidden');
        checkAll.checked = false;
    }
}

if (checkAll) {
    checkAll.addEventListener('change', function() {
        cbItems.forEach(cb => cb.checked = this.checked);
        updateBtnCetak();
    });
}

cbItems.forEach(cb => {
    cb.addEventListener('change', updateBtnCetak);
});

// Generate Barcode AJAX
function generateBarcode(id) {
    Swal.fire({
        title: 'Generate Barcode?',
        text: "Sistem akan membuatkan kode barcode unik secara otomatis untuk barang ini.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Buatkan!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            const fd = new FormData();
            fd.append('action', 'generate_barcode');
            fd.append('id_barang', id);
            
            fetch('<?= $sistem ?>/cetakbarcode', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload()); // Reload agar UI terupdate dan checkbox muncul
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                }
            }).catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal terhubung ke server.' }));
        }
    });
}

// Modal Print Logic
function cetakTerpilih() {
    let selectedIds = [];
    cbItems.forEach(cb => { if(cb.checked) selectedIds.push(cb.value); });
    if (selectedIds.length === 0) return;
    
    document.getElementById('lblJmlItems').textContent = selectedIds.length;
    
    // Siapkan hidden inputs
    const container = document.getElementById('containerIdBarang');
    container.innerHTML = '';
    selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id_barang[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const modal = document.getElementById('modalPrintOpts');
    const content = document.getElementById('modalPrintOptsContent');
    modal.classList.remove('hidden');
    setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); }, 10);
}

function bukaModalCetak(id, nama) {
    document.getElementById('lblJmlItems').innerHTML = '1 <i>(' + nama + ')</i>';
    const container = document.getElementById('containerIdBarang');
    container.innerHTML = `<input type="hidden" name="id_barang[]" value="${id}">`;
    
    const modal = document.getElementById('modalPrintOpts');
    const content = document.getElementById('modalPrintOptsContent');
    modal.classList.remove('hidden');
    setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); }, 10);
}

function closeModalPrint() {
    const modal = document.getElementById('modalPrintOpts');
    const content = document.getElementById('modalPrintOptsContent');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => { modal.classList.add('hidden'); }, 300);
}
</script>
