<?php
if (!canDo('pemakaian', 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses.</div>";
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
    $whereClauses[] = "p.id_outlet = ?";
    $params[] = $my_outlet_id;
} else {
    if (!empty($_GET['outlet'])) {
        $whereClauses[] = "p.id_outlet = ?";
        $params[] = $_GET['outlet'];
    }
}

if ($search) {
    $whereClauses[] = "(b.nama_barang LIKE ? OR b.barcode LIKE ? OR p.keterangan LIKE ? OR p.kode_transaksi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(" AND ", $whereClauses);

try {
    // Count distinct grouped transactions
    $countQuery = "
        SELECT COUNT(DISTINCT COALESCE(p.kode_transaksi, CONCAT('PEM-', p.id_pemakaian))) 
        FROM pemakaian p
        JOIN barang b ON p.id_barang = b.id_barang
        WHERE $whereSql
    ";
    $stmtCount = $conn->prepare($countQuery);
    $stmtCount->execute($params);
    $totalData = $stmtCount->fetchColumn();
    $totalPages = ceil($totalData / $limit);

    // Fetch grouped transactions
    $query = "
        SELECT COALESCE(p.kode_transaksi, CONCAT('PEM-', p.id_pemakaian)) as ref_trx,
               MAX(p.id_pemakaian) as max_id_pemakaian, 
               p.tanggal, 
               p.keterangan, 
               o.nama_outlet, 
               u.nama as nama_user,
               COUNT(DISTINCT p.id_barang) as total_item,
               SUM(p.qty) as total_qty,
               MAX(p.created_at) as created_at
        FROM pemakaian p
        JOIN barang b ON p.id_barang = b.id_barang
        JOIN outlet o ON p.id_outlet = o.id_outlet
        LEFT JOIN users u ON p.id_user = u.id_user
        WHERE $whereSql
        GROUP BY ref_trx
        ORDER BY p.tanggal DESC, max_id_pemakaian DESC
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
            <h1 class="text-2xl font-bold text-slate-800">Riwayat Pemakaian Barang</h1>
            <p class="text-slate-500 text-sm mt-1">Catatan penggunaan barang habis pakai atau dikonsumsi oleh outlet.</p>
        </div>
        <?php if (canDo('pemakaian', 'create')): ?>
        <a href="<?= $sistem ?>/pemakaian/i" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
            <i class="fa-solid fa-plus"></i> Input Pemakaian
        </a>
        <?php endif; ?>
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
            <input type="hidden" name="menu" value="pemakaian">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari kode transaksi, nama barang, keterangan..." 
                       class="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all">
            </div>
            
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
            <?php if($search || !empty($_GET['outlet'])): ?>
            <a href="<?= $sistem ?>/pemakaian" class="bg-slate-100 text-slate-600 hover:bg-slate-200 px-5 py-2 rounded-xl text-sm font-semibold transition-colors whitespace-nowrap">
                Reset
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Show Entries Header -->
        <div class="flex items-center justify-end px-6 py-3.5 border-b border-slate-100 bg-slate-50/20">
            <?php echo generateShowEntries($limit, 'pemakaian', $search); ?>
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
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center">Total Jenis</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center">Total Qty</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Keterangan</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider">User</th>
                        <th class="px-6 py-4 font-bold text-slate-600 text-xs uppercase tracking-wider text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?= $my_outlet_id ? '7' : '8' ?>" class="px-6 py-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                                <i class="fa-solid fa-clipboard-list text-2xl text-slate-400"></i>
                            </div>
                            <h3 class="text-slate-800 font-bold mb-1">Belum Ada Data Pemakaian</h3>
                            <p class="text-slate-500 text-sm">Catatan pemakaian barang akan tampil di sini.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($data as $row): 
                        ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4 text-center text-slate-400 font-medium"><?= $no++ ?></td>
                            
                            <td class="px-6 py-4">
                                <span class="font-semibold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
                                <div class="text-[10px] text-slate-400 mt-0.5 font-mono"><?= sanitize($row['ref_trx']) ?></div>
                            </td>

                            <?php if(!$my_outlet_id): ?>
                            <td class="px-6 py-4">
                                <span class="font-bold text-indigo-600"><?= sanitize($row['nama_outlet']) ?></span>
                            </td>
                            <?php endif; ?>

                            <td class="px-6 py-4 text-center">
                                <p class="text-sm font-semibold text-slate-800"><?= number_format($row['total_item']) ?> Items</p>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg">
                                    <i class="fa-solid fa-minus text-[10px]"></i>
                                    <span class="font-bold"><?= number_format($row['total_qty']) ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-slate-600 max-w-xs truncate" title="<?= sanitize($row['keterangan']) ?>">
                                <?= sanitize($row['keterangan'] ?: '-') ?>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[10px] text-slate-600 font-bold">
                                        <?= strtoupper(substr($row['nama_user'] ?? '?', 0, 1)) ?>
                                    </div>
                                    <span class="text-xs font-semibold text-slate-600"><?= sanitize($row['nama_user'] ?? 'System') ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if (canDo('pemakaian', 'view')): ?>
                                    <button type="button" onclick="openLgModal('<?= $sistem ?>/pemakaian/v/<?= $row['ref_trx'] ?>')"
                                       class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center hover:bg-sky-100 transition-colors border border-sky-100" title="Detail Pemakaian">
                                        <i class="fa-solid fa-circle-info text-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (canDo('pemakaian', 'delete')): ?>
                                    <button onclick="cancelPemakaian('<?= $row['ref_trx'] ?>', '<?= sanitize($row['nama_outlet']) ?>', <?= $row['total_qty'] ?>)"
                                            class="w-8 h-8 rounded-lg bg-red-50 text-red-500 border border-red-100 hover:bg-red-100 flex items-center justify-center transition-colors" title="Batalkan Pemakaian & Kembalikan Stok">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
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
                <!-- Prev Button -->
                <button type="button" onclick="goToPage(<?= max(1, $page - 1) ?>, <?= $limit ?>, 'pemakaian', '<?= urlencode($search) ?>')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                        <?= $page <= 1 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                
                <!-- Page Numbers -->
                <div class="flex items-center px-2 gap-1 hidden sm:flex">
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($totalPages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<button type="button" class="w-8 h-8 rounded-lg text-sm font-bold bg-indigo-600 text-white">'.$i.'</button>';
                        } else {
                            echo '<button type="button" onclick="goToPage('.$i.', '.$limit.', \'pemakaian\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">'.$i.'</button>';
                        }
                    }
                    ?>
                </div>

                <!-- Next Button -->
                <button type="button" onclick="goToPage(<?= min($totalPages, $page + 1) ?>, <?= $limit ?>, 'pemakaian', '<?= urlencode($search) ?>')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors <?= $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>"
                        <?= $page >= $totalPages ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Batalkan & Hapus Transaksi Pemakaian
function cancelPemakaian(trxCode, outlet, qty) {
    Swal.fire({
        title: 'Batalkan Pemakaian?',
        html: `Apakah Anda yakin ingin membatalkan transaksi pemakaian untuk <b>${outlet || 'Outlet'}</b> (Total: ${qty} qty)?<br><span class="text-xs text-rose-500 font-semibold">*Stok barang di outlet terpilih akan dikembalikan.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Batalkan!',
        cancelButtonText: 'Kembali'
    }).then(result => {
        if (!result.isConfirmed) return;
        
        Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('action', 'cancel_transaction');
        fd.append('trx_code', trxCode);
        
        fetch('<?= $sistem ?>/pemakaian/v/' + trxCode, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi gagal atau sesi habis.' }));
    });
}
</script>