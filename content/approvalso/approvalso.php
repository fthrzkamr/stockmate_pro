<?php
if (!canDo('approvalso', 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses ke halaman Approval SO.</div>";
    exit;
}

global $conn, $sistem;

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

$my_outlet_id = $_SESSION['outlet_id'] ?? null;
$whereClauses = ["1=1"];
$params = [];

if ($my_outlet_id) {
    $whereClauses[] = "so.id_outlet = ?";
    $params[] = $my_outlet_id;
} else {
    if (!empty($_GET['outlet'])) {
        $whereClauses[] = "so.id_outlet = ?";
        $params[] = $_GET['outlet'];
    }
}

// Default filter showing Pending at the top or filtering by status
$statusFilter = $_GET['status'] ?? 'Pending';
if ($statusFilter !== 'all') {
    $whereClauses[] = "so.status_approval = ?";
    $params[] = $statusFilter;
}

$tipeSOFilter = $_GET['tipe_so'] ?? 'all';
if ($tipeSOFilter === 'harian') {
    $whereClauses[] = "(so.periode_bulan IS NULL OR so.periode_bulan = 0)";
} elseif ($tipeSOFilter === 'bulanan') {
    $whereClauses[] = "so.periode_bulan IS NOT NULL AND so.periode_bulan > 0";
}

if ($search) {
    $whereClauses[] = "(so.no_so LIKE ? OR b.nama_barang LIKE ? OR b.barcode LIKE ? OR u.nama LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(" AND ", $whereClauses);

try {
    // Count distinct SO sheets
    $countQuery = "
        SELECT COUNT(DISTINCT so.no_so) 
        FROM stock_opname so
        JOIN barang b ON so.id_barang = b.id_barang
        LEFT JOIN users u ON so.id_user = u.id_user
        WHERE $whereSql
    ";
    $stmtCount = $conn->prepare($countQuery);
    $stmtCount->execute($params);
    $totalData = $stmtCount->fetchColumn();
    $totalPages = ceil($totalData / $limit);

    // Fetch SO sheets
    $query = "
        SELECT so.no_so,
               MAX(so.id_so) as max_id_so,
               so.tanggal,
               so.periode_bulan,
               so.periode_tahun,
               so.status_approval,
               o.nama_outlet,
               u.nama as nama_user,
               COUNT(DISTINCT so.id_barang) as total_item,
               MAX(so.created_at) as created_at
        FROM stock_opname so
        JOIN barang b ON so.id_barang = b.id_barang
        JOIN outlet o ON so.id_outlet = o.id_outlet
        LEFT JOIN users u ON so.id_user = u.id_user
        WHERE $whereSql
        GROUP BY so.no_so, so.tanggal, so.periode_bulan, so.periode_tahun, so.status_approval, o.nama_outlet, u.nama
        ORDER BY so.status_approval DESC, so.tanggal DESC, max_id_so DESC
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

$bulanNama = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
    5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
    9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];
?>

<div class="fade-up max-w-7xl mx-auto space-y-5">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Persetujuan Stock Opname (Approval)</h1>
            <p class="text-slate-500 text-sm mt-1">Lakukan verifikasi, approval, atau penolakan pengajuan stock opname harian outlet.</p>
        </div>
    </div>
    
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm" data-autohide>
        <i class="fa-solid fa-circle-check mt-0.5 flex-shrink-0"></i>
        <span><?= $_SESSION['flash_success'] ?></span>
    </div>
    <?php unset($_SESSION['flash_success']); endif; ?>

<?php
// Get List Outlet untuk Filter (Hanya untuk Admin/SPV)
if (!$my_outlet_id) {
    try {
        $outlets = $conn->query("SELECT * FROM outlet ORDER BY nama_outlet ASC")->fetchAll();
    } catch (Exception $e) {
        $outlets = [];
    }
}
?>
    <!-- Filters -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-3">
        <form id="filterForm" method="GET" class="flex flex-col sm:flex-row w-full gap-3">
            <input type="hidden" name="menu" value="approvalso">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nomor SO, nama staf..." 
                       class="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all">
            </div>
            
            <!-- Filter Tipe SO -->
            <?php $tipeSOFilter = $_GET['tipe_so'] ?? 'all'; ?>
            <select name="tipe_so" class="px-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white" onchange="document.getElementById('filterForm').submit()">
                <option value="all" <?= $tipeSOFilter === 'all' ? 'selected' : '' ?>>Semua Tipe SO</option>
                <option value="harian" <?= $tipeSOFilter === 'harian' ? 'selected' : '' ?>>SO Harian</option>
                <option value="bulanan" <?= $tipeSOFilter === 'bulanan' ? 'selected' : '' ?>>SO Bulanan</option>
            </select>

            <!-- Filter Status -->
            <select name="status" class="px-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white" onchange="document.getElementById('filterForm').submit()">
                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Menunggu Approval (Pending)</option>
                <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua Status</option>
            </select>

            <?php if (!$my_outlet_id && !empty($outlets)): ?>
            <select name="outlet" class="px-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white" onchange="document.getElementById('filterForm').submit()">
                <option value="">Semua Outlet</option>
                <?php foreach ($outlets as $o): ?>
                <option value="<?= $o['id_outlet'] ?>" <?= ($_GET['outlet'] ?? '') == $o['id_outlet'] ? 'selected' : '' ?>>
                    <?= sanitize($o['nama_outlet']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button type="submit" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-5 py-2 rounded-xl text-sm font-semibold transition-colors border border-indigo-200 whitespace-nowrap">
                Cari
            </button>
            <?php if($search || !empty($_GET['outlet']) || $statusFilter !== 'Pending'): ?>
            <a href="<?= $sistem ?>/approvalso" class="bg-slate-100 text-slate-600 hover:bg-slate-200 px-5 py-2 rounded-xl text-sm font-semibold transition-colors whitespace-nowrap">
                Reset
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Show Entries Header -->
        <div class="flex items-center justify-end px-6 py-3.5 border-b border-slate-100 bg-slate-50/20">
            <?php echo generateShowEntries($limit, 'approvalso', $search); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider w-10 text-center">No</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Tanggal</th>
                        <?php if(!$my_outlet_id): ?>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Outlet</th>
                        <?php endif; ?>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Nomor SO</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center">Total Item</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center">Status</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Staff Pengaju</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?= $my_outlet_id ? '7' : '8' ?>" class="px-6 py-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                                <i class="fa-solid fa-check-double text-2xl text-slate-400"></i>
                            </div>
                            <h3 class="text-slate-800 font-bold mb-1">Tidak Ada Data Pending</h3>
                            <p class="text-slate-500 text-sm">Semua pengajuan stock opname telah ditinjau.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($data as $row): 
                            $status = $row['status_approval'];
                            $color = 'amber';
                            if ($status === 'Approved') $color = 'emerald';
                            if ($status === 'Rejected') $color = 'rose';
                        ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4 text-center text-slate-400 font-medium"><?= $no++ ?></td>
                            
                            <td class="px-6 py-4">
                                <?php if (!empty($row['periode_bulan'])): ?>
                                    <span class="font-bold text-slate-800"><?= $bulanNama[(int)$row['periode_bulan']] ?? 'Bulan' ?> <?= $row['periode_tahun'] ?></span>
                                    <p class="text-[9px] text-slate-400 font-bold mt-0.5 uppercase tracking-wider">SO Bulanan</p>
                                <?php else: ?>
                                    <span class="font-semibold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
                                    <p class="text-[9px] text-slate-400 font-bold mt-0.5 uppercase tracking-wider">SO Harian</p>
                                <?php endif; ?>
                            </td>

                            <?php if(!$my_outlet_id): ?>
                            <td class="px-6 py-4">
                                <span class="font-bold text-indigo-600"><?= sanitize($row['nama_outlet']) ?></span>
                            </td>
                            <?php endif; ?>

                            <td class="px-6 py-4 font-semibold text-slate-700 font-mono">
                                <?= sanitize($row['no_so']) ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <p class="text-sm font-semibold text-slate-800"><?= number_format($row['total_item']) ?> Items</p>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <span class="bg-<?= $color ?>-50 text-<?= $color ?>-700 border border-<?= $color ?>-200 px-3 py-1 rounded-full text-[10px] font-bold inline-flex items-center gap-1 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-<?= $color ?>-500"></span>
                                    <?= $status === 'Pending' ? 'Menunggu Approval' : $status ?>
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-indigo-500/10 flex items-center justify-center text-[10px] text-indigo-600 font-bold border border-indigo-200/50">
                                        <?= strtoupper(substr($row['nama_user'] ?? '?', 0, 1)) ?>
                                    </div>
                                    <span class="text-xs font-semibold text-slate-600"><?= sanitize($row['nama_user'] ?? 'System') ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <?php if ($status === 'Pending' && canDo('approvalso', 'edit')): ?>
                                <button type="button" onclick="openLgModal('<?= $sistem ?>/approvalso/v/<?= $row['no_so'] ?>')"
                                   class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-1.5 rounded-lg font-bold transition-all shadow-sm flex items-center gap-1 mx-auto">
                                    <i class="fa-solid fa-signature"></i> Tinjau & Proses
                                </button>
                                <?php else: ?>
                                <button type="button" onclick="openLgModal('<?= $sistem ?>/stockopname/v/<?= $row['no_so'] ?>')"
                                   class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center hover:bg-sky-100 transition-colors border border-sky-100 mx-auto" title="Lihat Detail">
                                    <i class="fa-solid fa-circle-info text-xs"></i>
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
                <button type="button" onclick="goToPage(<?= max(1, $page - 1) ?>, <?= $limit ?>, 'approvalso', '<?= urlencode($search) ?>')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                        <?= $page <= 1 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                
                <div class="flex items-center px-2 gap-1 hidden sm:flex">
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($totalPages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<button type="button" class="w-8 h-8 rounded-lg text-sm font-bold bg-indigo-600 text-white">'.$i.'</button>';
                        } else {
                            echo '<button type="button" onclick="goToPage('.$i.', '.$limit.', \'approvalso\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">'.$i.'</button>';
                        }
                    }
                    ?>
                </div>

                <button type="button" onclick="goToPage(<?= min($totalPages, $page + 1) ?>, <?= $limit ?>, 'approvalso', '<?= urlencode($search) ?>')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors <?= $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>"
                        <?= $page >= $totalPages ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>