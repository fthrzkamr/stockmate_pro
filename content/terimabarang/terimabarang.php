<?php
if (!canDo('terimabarang', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit;
}

global $conn, $sistem;

$id_user   = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';

// Handle AJAX: Terima barang (ubah status -> Diterima)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    if ($_POST['action'] === 'terima' && canDo('terimabarang', 'edit')) {
        $id_keluar = (int)($_POST['id_keluar'] ?? 0);
        try {
            // Cek status masih Pending
            $cek = $conn->prepare("SELECT id_keluar, status, id_outlet FROM barang_keluar WHERE id_keluar = ?");
            $cek->execute([$id_keluar]);
            $row = $cek->fetch();

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
            } elseif ($row['status'] !== 'Pending') {
                echo json_encode(['status' => 'error', 'message' => 'Barang ini sudah diterima sebelumnya.']);
            } else {
                // Update status -> trigger otomatis tambah stok_outlet
                $upd = $conn->prepare("UPDATE barang_keluar SET status = 'Diterima' WHERE id_keluar = ? AND status = 'Pending'");
                $upd->execute([$id_keluar]);
                echo json_encode(['status' => 'success', 'message' => 'Barang berhasil diterima! Stok outlet telah diperbarui.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    exit;
}

// Ambil outlet user (Stock Keeper hanya lihat outlet-nya sendiri)
$outlet_filter = '';
$outlet_params = [];

// Cek apakah user punya outlet_id (Stock Keeper)
$userInfo = $conn->prepare("SELECT outlet_id, nama FROM users WHERE id_user = ?");
$userInfo->execute([$id_user]);
$userInfo = $userInfo->fetch();
$my_outlet_id = $userInfo['outlet_id'] ?? null;

// Admin Gudang bisa lihat semua, Stock Keeper hanya outletnya
if ($my_outlet_id) {
    $outlet_filter = "AND bk.id_outlet = ?";
    $outlet_params = [$my_outlet_id];
}

// Filter tab: all / pending / diterima / ditolak
$tab = $_GET['tab'] ?? 'pending';
$tab_filter = '';
if ($tab === 'pending')   $tab_filter = "AND bk.status = 'Pending'";
if ($tab === 'diterima')  $tab_filter = "AND bk.status = 'Diterima'";
if ($tab === 'ditolak')   $tab_filter = "AND bk.status = 'Ditolak'";

// Pagination
$page   = isset($_GET['page'])  ? max(1, (int)$_GET['page'])          : 1;
$limit  = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

// Hitung total data
$sqlCount = "SELECT COUNT(DISTINCT COALESCE(bk.kode_transaksi, bk.id_keluar)) FROM barang_keluar bk WHERE 1=1 $outlet_filter $tab_filter";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($outlet_params);
$totalData = (int)$stmtCount->fetchColumn();

// Ambil data
$sql = "
    SELECT COALESCE(bk.kode_transaksi, bk.id_keluar) as ref_trx, 
           MAX(bk.id_keluar) as id_keluar, bk.tanggal, bk.keterangan, 
           o.nama_outlet, u.nama as nama_admin,
           COUNT(bk.id_barang) as total_item,
           SUM(bk.qty) as total_qty,
           MAX(bk.status) as status_trx
    FROM barang_keluar bk
    LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
    LEFT JOIN users u  ON bk.id_user = u.id_user
    WHERE 1=1 $outlet_filter $tab_filter
    GROUP BY ref_trx
    ORDER BY bk.tanggal DESC, id_keluar DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($sql);
$stmt->execute($outlet_params);
$list = $stmt->fetchAll();

// Hitung badge untuk tab
$sqlPending  = "SELECT COUNT(DISTINCT COALESCE(kode_transaksi, id_keluar)) FROM barang_keluar bk WHERE status='Pending' $outlet_filter";
$sqlDiterima = "SELECT COUNT(DISTINCT COALESCE(kode_transaksi, id_keluar)) FROM barang_keluar bk WHERE status='Diterima' $outlet_filter";
$sqlDitolak  = "SELECT COUNT(DISTINCT COALESCE(kode_transaksi, id_keluar)) FROM barang_keluar bk WHERE status='Ditolak' $outlet_filter";
$stmtP = $conn->prepare($sqlPending);  $stmtP->execute($outlet_params);  $countPending  = (int)$stmtP->fetchColumn();
$stmtD = $conn->prepare($sqlDiterima); $stmtD->execute($outlet_params); $countDiterima = (int)$stmtD->fetchColumn();
$stmtR = $conn->prepare($sqlDitolak);  $stmtR->execute($outlet_params);  $countDitolak  = (int)$stmtR->fetchColumn();
?>

<div class="fade-up space-y-5">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Terima Barang</h1>
            <p class="text-slate-500 text-sm mt-0.5">
                <?php if ($my_outlet_id): ?>
                    Pengiriman barang masuk untuk outlet Anda dari gudang pusat.
                <?php else: ?>
                    Daftar seluruh pengiriman barang dari gudang ke semua outlet.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($my_outlet_id): ?>
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-2 text-sm">
            <p class="text-xs text-indigo-500 font-medium">Outlet Anda</p>
            <p class="font-bold text-indigo-700">
                <?php
                    $outletInfo = $conn->prepare("SELECT nama_outlet FROM outlet WHERE id_outlet = ?");
                    $outletInfo->execute([$my_outlet_id]);
                    echo sanitize($outletInfo->fetchColumn() ?: '—');
                ?>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tab Navigation -->
    <div class="flex flex-wrap gap-1 bg-slate-100 rounded-xl p-1 w-fit">
        <?php
        $tabs = [
            'pending'  => ['label' => 'Menunggu Diterima', 'count' => $countPending,  'color' => 'amber'],
            'diterima' => ['label' => 'Sudah Diterima',    'count' => $countDiterima, 'color' => 'emerald'],
            'ditolak'  => ['label' => 'Ditolak',           'count' => $countDitolak,  'color' => 'rose'],
            'all'      => ['label' => 'Semua',             'count' => $countPending + $countDiterima + $countDitolak, 'color' => 'sky'],
        ];
        foreach ($tabs as $key => $t):
            $isActive = $tab === $key;
        ?>
        <a href="?tab=<?= $key ?>" 
           class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold transition-all <?= $isActive ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500 hover:text-slate-700' ?>">
            <?= $t['label'] ?>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold
                <?= $isActive ? "bg-{$t['color']}-100 text-{$t['color']}-700" : 'bg-slate-200 text-slate-500' ?>">
                <?= $t['count'] ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Flash Message -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm shadow-sm">
        <i class="fa-solid fa-circle-check text-emerald-500 flex-shrink-0"></i>
        <span><?= $_SESSION['flash_success'] ?></span>
    </div>
    <?php unset($_SESSION['flash_success']); endif; ?>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Toolbar -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/20 gap-3">
            <div class="relative flex-1 max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input id="searchTerima" type="text" placeholder="Cari nama barang atau outlet..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400 transition-all bg-white">
            </div>
            <?php echo generateShowEntries($limit, 'terimabarang'); ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-10 text-center">No</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Ref & Tanggal</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Outlet Tujuan</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Item Dikirim</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Qty</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-14 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-<?= $tab === 'pending' ? 'clock' : 'check-double' ?> text-4xl mb-3 block text-slate-200"></i>
                            <?php if ($tab === 'pending'): ?>
                                Tidak ada pengiriman yang menunggu konfirmasi.
                            <?php elseif ($tab === 'diterima'): ?>
                                Belum ada pengiriman yang diterima.
                            <?php else: ?>
                                Belum ada data pengiriman barang.
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($list as $row): 
                        $statusColor = 'amber';
                        $statusIcon = 'clock';
                        if ($row['status_trx'] === 'Diterima') { $statusColor = 'emerald'; $statusIcon = 'check-double'; }
                        if ($row['status_trx'] === 'Ditolak')  { $statusColor = 'rose'; $statusIcon = 'xmark'; }
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors terima-row"
                        data-search="<?= strtolower($row['ref_trx'].' '.$row['nama_outlet']) ?>">
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-400 font-mono"><?= $no++ ?></td>
                        
                        <!-- Ref & Tanggal -->
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-bold text-indigo-600 font-mono mb-0.5"><?= sanitize($row['ref_trx']) ?></p>
                            <p class="text-xs text-slate-500"><?= date('d M Y, H:i', strtotime($row['tanggal'])) ?></p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Oleh: <?= sanitize($row['nama_admin'] ?: 'System') ?></p>
                        </td>

                        <!-- Outlet -->
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-100">
                                <i class="fa-solid fa-store text-[9px]"></i>
                                <?= sanitize($row['nama_outlet'] ?: '—') ?>
                            </span>
                        </td>

                        <!-- Item Count -->
                        <td class="px-5 py-3.5 text-center">
                            <span class="text-sm font-bold text-slate-700"><?= number_format($row['total_item']) ?></span>
                            <span class="text-xs text-slate-400 ml-1">Jenis</span>
                        </td>

                        <!-- Jumlah -->
                        <td class="px-5 py-3.5 text-center">
                            <span class="text-sm font-bold text-indigo-600"><?= number_format($row['total_qty']) ?></span>
                        </td>

                        <!-- Status -->
                        <td class="px-5 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-<?= $statusColor ?>-50 text-<?= $statusColor ?>-700 border border-<?= $statusColor ?>-200 text-xs font-semibold">
                                <i class="fa-solid fa-<?= $statusIcon ?> text-[9px]"></i> <?= $row['status_trx'] ?>
                            </span>
                        </td>

                        <!-- Aksi -->
                        <td class="px-5 py-3.5 text-center">
                            <?php if (canDo('terimabarang', 'view')): ?>
                            <button type="button" onclick="openLgModal('<?= $sistem ?>/terimabarang/v/<?= $row['ref_trx'] ?>')"
                                    class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 inline-flex items-center justify-center hover:bg-indigo-100 transition-colors border border-indigo-100" title="Detail Penerimaan">
                                <i class="fa-solid fa-circle-info text-xs"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-slate-300 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php echo generatePagination($totalData, $limit, $sistem . '/terimabarang', $page, 'terimabarang'); ?>
    </div>
</div>

<script>
// Search
document.getElementById('searchTerima')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.terima-row').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
});

</script>