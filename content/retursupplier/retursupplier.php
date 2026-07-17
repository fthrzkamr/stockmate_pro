<?php
if (!canDo('retursupplier', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses ke halaman Retur Pembelian.</div>";
    exit;
}

global $conn, $sistem;

// Filter
$search = $_GET['search'] ?? '';
$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-t');

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

$where = "r.tanggal BETWEEN ? AND ?";
$params = [$tgl_mulai, $tgl_sampai];

if ($search) {
    $where .= " AND (r.kode_retur LIKE ? OR b.nama_barang LIKE ? OR s.nama_supplier LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Total Data
$stCount = $conn->prepare("
    SELECT COUNT(*) FROM retur_supplier r
    JOIN barang b ON r.id_barang = b.id_barang
    LEFT JOIN barang_masuk bm ON r.id_masuk = bm.id_masuk
    LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
    WHERE $where
");
$stCount->execute($params);
$totalData = (int)$stCount->fetchColumn();

// List Data
$stList = $conn->prepare("
    SELECT r.*, b.nama_barang, b.barcode, b.satuan, 
           bm.kode_transaksi as kode_masuk,
           COALESCE(s.nama_supplier, bm.supplier_lainnya, 'Tanpa Supplier') as nama_supplier,
           u.nama as nama_user
    FROM retur_supplier r
    JOIN barang b ON r.id_barang = b.id_barang
    LEFT JOIN barang_masuk bm ON r.id_masuk = bm.id_masuk
    LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
    LEFT JOIN users u ON r.id_user = u.id_user
    WHERE $where
    ORDER BY r.tanggal DESC, r.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stList->execute($params);
$list = $stList->fetchAll();
?>

<div class="fade-up space-y-5">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Retur Pembelian (Supplier)</h1>
            <p class="text-slate-500 text-sm mt-0.5">Daftar barang masuk yang dikembalikan ke supplier.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if (canDo('retursupplier', 'create')): ?>
            <a href="<?= $sistem ?>/retursupplier/i" 
               class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-plus text-xs"></i> Tambah Retur
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="menu" value="retursupplier">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            
            <div class="flex-1">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Pencarian</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Kode Retur, Barang, Supplier..."
                           class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 transition-all bg-slate-50">
                </div>
            </div>
            
            <div class="w-full sm:w-40">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>" 
                       class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 transition-all bg-slate-50">
            </div>
            
            <div class="w-full sm:w-40">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sampai Tanggal</label>
                <input type="date" name="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>" 
                       class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 transition-all bg-slate-50">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full sm:w-auto px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-semibold transition-all">
                    Terapkan
                </button>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mt-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/20">
            <h3 class="text-sm font-bold text-slate-700">Data Retur</h3>
            <?php echo generateShowEntries($limit, 'retursupplier', urlencode($search) . "&tgl_mulai=$tgl_mulai&tgl_sampai=$tgl_sampai"); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Tanggal & Kode</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Info Barang</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Supplier (Ref Masuk)</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">Qty Retur</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-folder-open text-3xl mb-2 block text-slate-200"></i>
                            Tidak ada data retur yang ditemukan.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($list as $row): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-3 text-center text-sm font-semibold text-slate-500"><?= $no++ ?></td>
                        
                        <td class="px-5 py-3">
                            <p class="text-sm font-bold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></p>
                            <span class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-600 border border-rose-100">
                                <i class="fa-solid fa-arrow-right-arrow-left"></i> <?= htmlspecialchars($row['kode_retur']) ?>
                            </span>
                        </td>
                        
                        <td class="px-5 py-3">
                            <p class="text-sm font-semibold text-slate-800"><?= sanitize($row['nama_barang']) ?></p>
                            <p class="text-[10px] text-slate-500 font-mono"><?= sanitize($row['barcode'] ?: 'Tanpa Barcode') ?></p>
                        </td>

                        <td class="px-5 py-3">
                            <p class="text-sm font-medium text-slate-700"><?= sanitize($row['nama_supplier']) ?></p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Ref: <?= sanitize($row['kode_masuk']) ?></p>
                        </td>

                        <td class="px-5 py-3 text-right">
                            <span class="text-sm font-bold text-rose-600">-<?= number_format($row['qty'], 0, ',', '.') ?></span>
                            <p class="text-[10px] text-slate-400"><?= sanitize($row['satuan']) ?></p>
                        </td>

                        <td class="px-5 py-3">
                            <div class="flex justify-center">
                                <a href="<?= $sistem ?>/retursupplier/v?id=<?= $row['id_retur'] ?>"
                                   class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center hover:bg-sky-100 transition-colors" title="Detail Retur">
                                    <i class="fa-regular fa-file-lines text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php echo generatePagination($totalData, $limit, $sistem . '/retursupplier', $page, 'retursupplier', urlencode($search) . "&tgl_mulai=$tgl_mulai&tgl_sampai=$tgl_sampai"); ?>
    </div>
</div>
