<?php
if (!canDo('stokoutlet', 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses untuk melihat stok outlet.</div>";
    exit;
}

global $conn, $sistem;

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

// Cek apakah user punya outlet_id (Stock Keeper)
$my_outlet_id = $_SESSION['outlet_id'] ?? null;

// Jika user adalah Stock Keeper, paksa filter ke outlet miliknya
if ($my_outlet_id) {
    $filter_outlet = $my_outlet_id;
    $outlets = []; // Sembunyikan dropdown outlet lain
} else {
    $filter_outlet = $_GET['outlet'] ?? '';
    // Get List Outlet untuk Filter (Hanya untuk Admin/SPV)
    try {
        $outlets = $conn->query("SELECT * FROM outlet ORDER BY nama_outlet ASC")->fetchAll();
    } catch (Exception $e) {
        $outlets = [];
    }
}

$whereClauses = ["1=1"];
$params = [];

// Filter outlet jika ada
if ($filter_outlet) {
    $whereClauses[] = "o.id_outlet = ?";
    $params[] = $filter_outlet;
}

// Jika ada pencarian, kita bisa cari berdasarkan nama outlet, atau ada barang yang cocok
if ($search) {
    $whereClauses[] = "(o.nama_outlet LIKE ? OR EXISTS(SELECT 1 FROM stok_outlet so2 JOIN barang b ON so2.id_barang = b.id_barang WHERE so2.id_outlet = o.id_outlet AND (b.nama_barang LIKE ? OR b.barcode LIKE ?)))";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(" AND ", $whereClauses);

try {
    // Hitung total outlet yang punya stok
    $countQuery = "
        SELECT COUNT(DISTINCT o.id_outlet) 
        FROM outlet o 
        JOIN stok_outlet so ON o.id_outlet = so.id_outlet
        WHERE $whereSql
    ";
    $stmtCount = $conn->prepare($countQuery);
    $stmtCount->execute($params);
    $totalData = $stmtCount->fetchColumn();
    $totalPages = ceil($totalData / $limit);

    // Ambil data outlet beserta total item dan qty
    $query = "
        SELECT 
            o.id_outlet, 
            o.nama_outlet,
            COUNT(DISTINCT so.id_barang) as total_item,
            SUM(so.stok) as total_qty
        FROM outlet o
        JOIN stok_outlet so ON o.id_outlet = so.id_outlet
        WHERE $whereSql 
        GROUP BY o.id_outlet
        ORDER BY o.nama_outlet ASC 
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
            <h1 class="text-2xl font-bold text-slate-800">Stok Outlet</h1>
            <p class="text-slate-500 text-sm mt-1">Pantau ketersediaan barang di setiap outlet/cabang.</p>
        </div>
    </div>

    <!-- Filters & Actions -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
        
        <form id="filterForm" method="GET" class="flex flex-col sm:flex-row w-full max-w-2xl gap-3">
            <input type="hidden" name="menu" value="stokoutlet">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari cabang atau nama barang..." 
                       class="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all">
            </div>

            <button type="submit" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-5 py-2 rounded-xl text-sm font-semibold transition-colors border border-indigo-200 whitespace-nowrap">
                Cari Data
            </button>
            
            <?php if($search): ?>
            <a href="<?= $sistem ?>/stokoutlet" class="bg-slate-100 text-slate-600 hover:bg-slate-200 px-5 py-2 rounded-xl text-sm font-semibold transition-colors whitespace-nowrap">
                Reset
            </a>
            <?php endif; ?>
        </form>

    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Show Entries Header -->
        <div class="flex items-center justify-end px-6 py-3.5 border-b border-slate-100 bg-slate-50/20">
            <?php echo generateShowEntries($limit, 'stokoutlet', $search); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider w-10 text-center">No</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Nama Outlet</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center">Jenis Barang</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center">Total Pcs</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                                <i class="fa-solid fa-store-slash text-2xl text-slate-400"></i>
                            </div>
                            <h3 class="text-slate-800 font-bold mb-1">Belum Ada Stok Outlet</h3>
                            <p class="text-slate-500 text-sm">Tidak ada stok yang terdaftar atau sesuai kriteria pencarian.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($data as $row): 
                        ?>
                        <tr class="hover:bg-slate-50/70 transition-colors group">
                            <td class="px-6 py-4 text-center text-slate-400 font-medium"><?= $no++ ?></td>
                            
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 border border-indigo-100/50">
                                        <i class="fa-solid fa-store"></i>
                                    </div>
                                    <span class="font-bold text-slate-800 text-base"><?= sanitize($row['nama_outlet']) ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <span class="bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-semibold">
                                    <?= number_format($row['total_item']) ?> Item Unik
                                </span>
                            </td>

                            <td class="px-6 py-4 text-center font-mono">
                                <span class="font-bold text-emerald-600 text-base"><?= number_format($row['total_qty']) ?></span>
                                <span class="text-xs text-slate-400 font-sans ml-1">Total Qty</span>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <button type="button" onclick="openLgModal('<?= $sistem ?>/stokoutlet/v/<?= $row['id_outlet'] ?>')"
                                        class="bg-sky-50 text-sky-600 hover:bg-sky-500 hover:text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm">
                                    <i class="fa-solid fa-eye mr-1.5"></i> Lihat Detail
                                </button>
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
                <span class="font-bold text-slate-700"><?= $totalData ?></span> outlet
            </div>
            
            <div class="flex items-center gap-1">
                <!-- Prev Button -->
                <button type="button" onclick="goToPage(<?= max(1, $page - 1) ?>, <?= $limit ?>, 'stokoutlet', '<?= urlencode($search) ?>')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                        <?= $page <= 1 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                
                <!-- Page Numbers -->
                <div class="flex items-center px-2 gap-1 hidden sm:flex">
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($totalPages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<button type="button" onclick="goToPage(1, '.$limit.', \'stokoutlet\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">1</button>';
                        if ($start_page > 2) echo '<span class="px-1 text-slate-400">...</span>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<button type="button" class="w-8 h-8 rounded-lg text-sm font-bold bg-indigo-600 text-white shadow-sm shadow-indigo-500/30">'.$i.'</button>';
                        } else {
                            echo '<button type="button" onclick="goToPage('.$i.', '.$limit.', \'stokoutlet\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">'.$i.'</button>';
                        }
                    }
                    
                    if ($end_page < $totalPages) {
                        if ($end_page < $totalPages - 1) echo '<span class="px-1 text-slate-400">...</span>';
                        echo '<button type="button" onclick="goToPage('.$totalPages.', '.$limit.', \'stokoutlet\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">'.$totalPages.'</button>';
                    }
                    ?>
                </div>

                <!-- Next Button -->
                <button type="button" onclick="goToPage(<?= min($totalPages, $page + 1) ?>, <?= $limit ?>, 'stokoutlet', '<?= urlencode($search) ?>')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors <?= $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>"
                        <?= $page >= $totalPages ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>