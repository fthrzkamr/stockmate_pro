<?php
if (!canDo('inventory', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat inventory.</div>";
    exit;
}

global $conn, $sistem;

// Pagination & Filter parameters
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

$whereSql = "1=1";
$params = [];

if ($search) {
    $whereSql .= " AND (b.nama_barang LIKE ? OR b.barcode LIKE ? OR t.nama_tipe LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Hitung total data untuk pagination
try {
    $stmtCount = $conn->prepare("
        SELECT COUNT(*) 
        FROM inventory i
        JOIN barang b ON i.id_barang = b.id_barang
        LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
        WHERE $whereSql
    ");
    $stmtCount->execute($params);
    $totalData = (int)$stmtCount->fetchColumn();
} catch (Exception $e) {
    $totalData = 0;
}

// Ambil data inventory dengan pagination
try {
    $stmt = $conn->prepare("
        SELECT i.*, b.nama_barang, b.barcode, b.satuan, b.kategori, t.nama_tipe,
        (SELECT SUM(qty) FROM barang_masuk bm WHERE bm.id_barang = i.id_barang) as total_masuk
        FROM inventory i
        JOIN barang b ON i.id_barang = b.id_barang
        LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
        WHERE $whereSql
        ORDER BY b.nama_barang ASC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $invList = $stmt->fetchAll();
} catch (Exception $e) {
    $invList = [];
}
// Handle AJAX History Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_history') {
    ob_clean(); // Bersihkan buffer agar layout sistem.php tidak ikut ter-render
    $id_barang = (int)($_POST['id_barang'] ?? 0);
    $bulan = (int)($_POST['bulan'] ?? date('n'));
    $tahun = (int)($_POST['tahun'] ?? date('Y'));
    
    try {
        $histories = $conn->prepare("
            SELECT 
                1 as sort_weight,
                'Masuk' as tipe,
                bm.id_masuk as id_trans,
                bm.tanggal,
                bm.qty,
                bm.keterangan,
                COALESCE(bm.supplier_lainnya, s.nama_supplier, 'Tanpa Supplier') as pihak_terkait,
                bm.created_at
            FROM barang_masuk bm
            LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
            WHERE bm.id_barang = ? AND MONTH(bm.tanggal) = ? AND YEAR(bm.tanggal) = ? AND bm.status = 'Selesai'
            
            UNION ALL

            SELECT 
                2 as sort_weight,
                'Batal Masuk' as tipe,
                bm.id_masuk as id_trans,
                bm.tanggal as tanggal,
                bm.qty,
                CONCAT('Batal Masuk dari ', COALESCE(bm.supplier_lainnya, s.nama_supplier, 'Supplier')) as keterangan,
                'Koreksi Sistem' as pihak_terkait,
                bm.created_at as created_at
            FROM barang_masuk bm
            LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
            WHERE bm.id_barang = ? AND bm.status = 'Dibatalkan' AND MONTH(bm.tanggal) = ? AND YEAR(bm.tanggal) = ?
            
            UNION ALL
            
            SELECT 
                2 as sort_weight,
                'Retur Supplier' as tipe,
                rs.id_retur as id_trans,
                rs.tanggal as tanggal,
                rs.qty,
                CONCAT('Retur karena: ', COALESCE(rs.alasan, 'Tidak ada alasan')) as keterangan,
                COALESCE(s.nama_supplier, bm.supplier_lainnya, 'Supplier') as pihak_terkait,
                rs.created_at as created_at
            FROM retur_supplier rs
            LEFT JOIN barang_masuk bm ON rs.id_masuk = bm.id_masuk
            LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
            WHERE rs.id_barang = ? AND MONTH(rs.tanggal) = ? AND YEAR(rs.tanggal) = ?
            
            UNION ALL
            
            SELECT 
                1 as sort_weight,
                'Keluar' as tipe,
                bk.id_keluar as id_trans,
                bk.tanggal,
                bk.qty,
                bk.keterangan,
                COALESCE(o.nama_outlet, 'Tanpa Outlet') as pihak_terkait,
                bk.created_at
            FROM barang_keluar bk
            LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
            WHERE bk.id_barang = ? AND MONTH(bk.tanggal) = ? AND YEAR(bk.tanggal) = ?
            
            UNION ALL
            
            SELECT 
                2 as sort_weight,
                'Dikembalikan' as tipe,
                bk.id_keluar as id_trans,
                bk.tanggal as tanggal,
                bk.qty,
                CONCAT('Dikembalikan dari ', COALESCE(o.nama_outlet, 'Outlet')) as keterangan,
                'Gudang Pusat' as pihak_terkait,
                bk.created_at as created_at
            FROM barang_keluar bk
            LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
            WHERE bk.id_barang = ? AND bk.status = 'Dikembalikan' AND MONTH(bk.tanggal) = ? AND YEAR(bk.tanggal) = ?
            
            ORDER BY tanggal DESC, created_at DESC, sort_weight DESC
        ");
        $histories->execute([$id_barang, $bulan, $tahun, $id_barang, $bulan, $tahun, $id_barang, $bulan, $tahun, $id_barang, $bulan, $tahun, $id_barang, $bulan, $tahun]);
        $data = $histories->fetchAll();

        if (empty($data)) {
            echo "<div class='text-center py-8 text-slate-400'><i class='fa-solid fa-folder-open text-3xl mb-2 text-slate-200 block'></i>Belum ada histori transaksi.</div>";
        } else {
            foreach ($data as $h) {
                if ($h['tipe'] === 'Masuk') {
                    $color = 'emerald';
                    $sign = '+';
                    $icon = 'fa-arrow-down';
                    $lbl = 'Dari: ' . sanitize($h['pihak_terkait']);
                } elseif ($h['tipe'] === 'Dikembalikan') {
                    $color = 'sky';
                    $sign = '+';
                    $icon = 'fa-rotate-left';
                    $lbl = 'Ke: ' . sanitize($h['pihak_terkait']);
                } elseif ($h['tipe'] === 'Batal Masuk' || $h['tipe'] === 'Retur Supplier') {
                    $color = 'rose';
                    $sign = '-';
                    $icon = 'fa-rotate-left';
                    $lbl = sanitize($h['pihak_terkait']);
                } else {
                    $color = 'rose';
                    $sign = '-';
                    $icon = 'fa-arrow-up';
                    $lbl = 'Ke: ' . sanitize($h['pihak_terkait']);
                }

                echo "
                <div class='p-3 border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-colors'>
                    <div class='flex justify-between items-start mb-1'>
                        <span class='text-xs font-bold text-slate-600'>" . date('d M Y', strtotime($h['tanggal'])) . " <span class='font-normal text-slate-400 ml-1'>({$h['tipe']})</span></span>
                        <span class='text-xs font-bold text-{$color}-600 bg-{$color}-50 px-2 py-0.5 rounded'>{$sign}" . number_format($h['qty'],0,',','.') . "</span>
                    </div>
                    <p class='text-[11px] font-medium text-slate-600 mb-0.5'><i class='fa-solid {$icon} text-slate-300 mr-1.5'></i> {$lbl}</p>
                    <p class='text-[10px] text-slate-400 italic'>" . sanitize($h['keterangan'] ?: 'Tidak ada keterangan') . "</p>
                </div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='text-red-500 text-xs p-3'>Error loading history: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    exit;
}
?>

<div class="fade-up space-y-5">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Inventory Gudang</h1>
            <p class="text-slate-500 text-sm mt-0.5">Pantau jumlah stok dan alokasi rak untuk semua item barang.</p>
        </div>
    </div>



    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mt-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/20 gap-3">
            <form method="GET" id="filterForm" class="relative flex-1 max-w-sm flex items-center gap-2">
                <input type="hidden" name="menu" value="inventory">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="limit" value="<?= $limit ?>">
                <div class="relative w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input id="searchInventory" name="search" type="text" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama barang atau barcode..."
                           class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:sky-500/30 focus:border-sky-400 transition-all bg-white">
                </div>
            </form>
            <?php echo generateShowEntries($limit, 'inventory', urlencode($search)); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblInventory">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Info Barang</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kategori / Tipe</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Masuk</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">Sisa Stok Sistem</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($invList)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-boxes-stacked text-3xl mb-2 block text-slate-200"></i>
                            Belum ada data inventory terdaftar.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($invList as $inv): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors inv-row" 
                        data-search="<?= strtolower($inv['nama_barang'].' '.$inv['barcode']) ?>">
                        
                        <!-- No. Urut -->
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>

                        <!-- Info Barang -->
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800"><?= sanitize($inv['nama_barang']) ?></p>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                <?= $inv['barcode'] ? '<i class="fa-solid fa-barcode"></i> '.$inv['barcode'] : 'Tanpa Barcode' ?>
                            </p>
                        </td>

                        <!-- Kategori & Tipe -->
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-600 border border-indigo-100 mb-1">
                                <?= sanitize($inv['kategori'] ?: '-') ?>
                            </span><br>
                            <span class="text-[10px] text-slate-500">Tipe: <?= sanitize($inv['nama_tipe'] ?: 'Belum Diatur') ?></span>
                        </td>

                        <!-- Total Masuk -->
                        <td class="px-5 py-3.5 text-right">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-sky-50 text-sky-700 font-medium border border-sky-200 text-xs">
                                <i class="fa-solid fa-arrow-down text-[10px]"></i> <?= number_format($inv['total_masuk'] ?: 0, 0, ',', '.') ?>
                            </span>
                        </td>

                        <!-- Stok Sistem -->
                        <td class="px-5 py-3.5 text-right">
                            <p class="text-sm font-bold <?= $inv['stok'] > 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                                <?= number_format($inv['stok'], 0, ',', '.') ?>
                            </p>
                            <p class="text-[10px] text-slate-400 font-medium uppercase"><?= sanitize($inv['satuan'] ?: 'Pcs') ?></p>
                        </td>

                        <!-- Aksi -->
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" onclick="showHistory(<?= $inv['id_barang'] ?>, '<?= htmlspecialchars(addslashes($inv['nama_barang'])) ?>')"
                                   class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center hover:bg-indigo-100 transition-colors border border-indigo-100" title="Kartu Stok (Riwayat)">
                                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                </button>
                                <?php if (canDo('inventory', 'edit')): ?>
                                <a href="<?= $sistem ?>/inventory/edit?id=<?= $inv['id_inventory'] ?>"
                                   class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center hover:bg-sky-100 transition-colors border border-sky-100" title="Edit Stok">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php echo generatePagination($totalData, $limit, $sistem . '/inventory', $page); ?>
    </div>
</div>

<!-- Modal History -->
<div id="modalHistory" class="fixed inset-0 z-[100] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0" id="modalHistoryBackdrop" onclick="closeHistory()"></div>
    
            <!-- Modal Content -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md scale-95 opacity-0 transition-all duration-300" id="modalHistoryPanel">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Kartu Stok (Riwayat)</h3>
                        <p class="text-[10px] text-slate-500 font-medium mt-0.5" id="historyBarangName">Nama Barang</p>
                    </div>
                    <button onclick="closeHistory()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
                <!-- Filters -->
                <div class="flex gap-2">
                    <select id="histBulan" class="flex-1 text-xs px-2 py-1.5 border border-slate-200 rounded-lg bg-white outline-none focus:border-indigo-400">
                        <?php 
                        $bulanArr = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                        $curBulan = date('n');
                        foreach ($bulanArr as $k => $v) {
                            $sel = $k == $curBulan ? 'selected' : '';
                            echo "<option value='$k' $sel>$v</option>";
                        }
                        ?>
                    </select>
                    <select id="histTahun" class="w-24 text-xs px-2 py-1.5 border border-slate-200 rounded-lg bg-white outline-none focus:border-indigo-400">
                        <?php 
                        $curTahun = date('Y');
                        for ($y = $curTahun; $y >= $curTahun - 3; $y--) {
                            echo "<option value='$y'>$y</option>";
                        }
                        ?>
                    </select>
                    <button type="button" onclick="loadHistoryFilter()" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 border border-indigo-100 rounded-lg text-xs font-semibold hover:bg-indigo-100">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                </div>
            </div>
            
            <!-- Body -->
            <div class="p-0 overflow-y-auto flex-1 bg-white" id="historyContent">
                <div class="p-6 text-center text-slate-400">
                    <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-indigo-500"></i>
                    <p class="text-xs">Memuat history...</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 text-center">
                <p class="text-[10px] text-slate-400">Menampilkan transaksi bulan yang dipilih.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Modal History Logic
const histModal = document.getElementById('modalHistory');
const histBackdrop = document.getElementById('modalHistoryBackdrop');
const histPanel = document.getElementById('modalHistoryPanel');
const histContent = document.getElementById('historyContent');
const histTitle = document.getElementById('historyBarangName');
let currentHistoryId = null;

function showHistory(idBarang, namaBarang) {
    currentHistoryId = idBarang;
    // Tampilkan modal
    histModal.classList.remove('hidden');
    // Animasi masuk
    setTimeout(() => {
        histBackdrop.classList.remove('opacity-0');
        histPanel.classList.remove('scale-95', 'opacity-0');
    }, 10);

    histTitle.innerText = namaBarang;
    loadHistoryData();
}

function loadHistoryFilter() {
    if (currentHistoryId) loadHistoryData();
}

function loadHistoryData() {
    const idBarang = currentHistoryId;
    const bulan = document.getElementById('histBulan').value;
    const tahun = document.getElementById('histTahun').value;

    histContent.innerHTML = `
        <div class="p-6 text-center text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-indigo-500"></i>
            <p class="text-xs">Memuat history...</p>
        </div>
    `;

    // Fetch data
    fetch('<?= $sistem ?>/inventory', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            'action': 'get_history',
            'id_barang': idBarang,
            'bulan': bulan,
            'tahun': tahun
        })
    })
    .then(r => r.text())
    .then(html => histContent.innerHTML = html)
    .catch(() => histContent.innerHTML = '<div class="p-6 text-center text-red-500 text-xs">Gagal memuat history.</div>');
}

function closeHistory() {
    // Animasi keluar
    histBackdrop.classList.add('opacity-0');
    histPanel.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        histModal.classList.add('hidden');
    }, 300);
}
</script>
