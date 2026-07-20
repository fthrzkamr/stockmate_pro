<?php
if (!canDo('laporan', 'view') && !canDo('laporankeluar', 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit;
}

global $conn, $sistem;

$is_print = isset($_GET['print']) && $_GET['print'] == 'true';

// Filters
$outlet_filter = $_GET['outlet'] ?? '';
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

$my_outlet_id = $_SESSION['outlet_id'] ?? null;
$where = ["1=1"];
$params = [];

if ($my_outlet_id) {
    $where[] = "bk.id_outlet = ?";
    $params[] = $my_outlet_id;
} elseif ($outlet_filter) {
    $where[] = "bk.id_outlet = ?";
    $params[] = $outlet_filter;
}

if ($tgl_awal) {
    $where[] = "bk.tanggal >= ?";
    $params[] = $tgl_awal;
}
if ($tgl_akhir) {
    $where[] = "bk.tanggal <= ?";
    $params[] = $tgl_akhir;
}

if ($status_filter && $status_filter !== 'all') {
    $where[] = "bk.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where[] = "(bk.kode_transaksi LIKE ? OR o.nama_outlet LIKE ? OR bk.keterangan LIKE ? OR EXISTS (
        SELECT 1 FROM barang_keluar bk2
        JOIN barang b2 ON bk2.id_barang = b2.id_barang
        WHERE COALESCE(bk2.kode_transaksi, bk2.id_keluar) = COALESCE(bk.kode_transaksi, bk.id_keluar)
        AND (b2.nama_barang LIKE ? OR b2.barcode LIKE ?)
    ))";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(" AND ", $where);

try {
    if (!$is_print) {
        // Count distinct grouped transactions
        $countQuery = "
            SELECT COUNT(DISTINCT COALESCE(bk.kode_transaksi, bk.id_keluar)) 
            FROM barang_keluar bk
            LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
            WHERE $where_sql
        ";
        $stmtCount = $conn->prepare($countQuery);
        $stmtCount->execute($params);
        $totalData = $stmtCount->fetchColumn();
        $totalPages = max(1, ceil($totalData / $limit));
    }

    $query = "
        SELECT COALESCE(bk.kode_transaksi, bk.id_keluar) as ref_trx, 
               MAX(bk.tanggal) as tanggal, 
               MAX(bk.keterangan) as keterangan, 
               MAX(o.nama_outlet) as nama_outlet, 
               MAX(o.alamat) as alamat_outlet,
               MAX(u.nama) as operator,
               COUNT(bk.id_barang) as total_item,
               SUM(bk.qty) as total_qty,
               MAX(bk.status) as status
        FROM barang_keluar bk
        LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
        LEFT JOIN users u ON bk.id_user = u.id_user
        WHERE $where_sql
        GROUP BY ref_trx
        ORDER BY tanggal DESC, MAX(bk.id_keluar) DESC
    ";

    if (!$is_print) {
        $query .= " LIMIT $limit OFFSET $offset";
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $data = [];
    $totalData = 0;
    $totalPages = 0;
}

// Render mode cetak (Print mode)
if ($is_print) {
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Laporan Distribusi Barang (Keluar) - Ringkasan</title>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; font-size: 11px; margin: 20px; line-height: 1.4; }
            .header { text-align: center; margin-bottom: 20px; padding-bottom: 5px; }
            .header h1 { margin: 8px 0 0; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
            .header p { margin: 4px 0 0; font-size: 11px; color: #666; }
            .meta { display: flex; justify-content: space-between; margin-bottom: 15px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 7px 10px; text-align: left; }
            th { background-color: #f5f5f5; font-weight: bold; font-size: 10px; text-transform: uppercase; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .mono { font-family: monospace; }
            .badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; }
            .badge-success { background-color: #e6f4ea; color: #137333; }
            .badge-warning { background-color: #fef7e0; color: #b06000; }
            .badge-danger { background-color: #fce8e6; color: #c5221f; }
            .badge-slate { background-color: #f1f3f4; color: #5f6368; }
            @media print {
                body { margin: 10px; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div style="font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #1e293b;">PT PSY BERKAH INDONESIA</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 2px; line-height: 1.3;">Bintaro Jaya, Jl. Bintaro Utama 9 Jalan Elang Raya No.17, Pondok Pucung, Pondok Aren, South Tangerang City, Banten 15229</div>
            <h1 style="margin-top: 8px; font-size: 15px;">Laporan Barang Keluar (Pengiriman Outlet)</h1>
            <p>Rentang Periode: <?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></p>
        </div>

        <div class="meta">
            <div>
                Outlet Tujuan: <?php
                    if ($outlet_filter) {
                        $o_stmt = $conn->prepare("SELECT nama_outlet FROM outlet WHERE id_outlet = ?");
                        $o_stmt->execute([$outlet_filter]);
                        echo htmlspecialchars($o_stmt->fetchColumn() ?: 'ID: ' . $outlet_filter);
                    } else {
                        echo 'Semua Outlet';
                    }
                ?><br>
                Total Distribusi: <?= count($data) ?> Dokumen
            </div>
            <div class="text-right">
                Dicetak: <?= date('d/m/Y H:i') ?><br>
                Oleh: <?= htmlspecialchars($_SESSION['username'] ?? 'System') ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 4%">No</th>
                    <th style="width: 11%">Tanggal</th>
                    <th style="width: 18%">Ref Transaksi</th>
                    <th>Outlet Tujuan</th>
                    <th class="text-center" style="width: 10%">Total Item</th>
                    <th class="text-center" style="width: 10%">Total Qty</th>
                    <th style="width: 13%">Pengirim (User)</th>
                    <th class="text-center" style="width: 10%">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px;">Tidak ada data distribusi barang yang ditemukan.</td>
                </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($data as $r): 
                        $statusClass = 'warning';
                        if ($r['status'] === 'Diterima') $statusClass = 'success';
                        if ($r['status'] === 'Ditolak') $statusClass = 'danger';
                        if ($r['status'] === 'Dikembalikan') $statusClass = 'slate';
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                        <td class="mono"><?= htmlspecialchars($r['ref_trx'] ?: '—') ?></td>
                        <td>
                            <b><?= htmlspecialchars($r['nama_outlet'] ?: 'Tanpa Outlet') ?></b>
                            <?php if (!empty($r['alamat_outlet'])): ?>
                            <br><small style="color: #666; font-size: 10px;"><?= htmlspecialchars($r['alamat_outlet']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center mono"><?= number_format($r['total_item']) ?> Jenis</td>
                        <td class="text-center mono" style="font-weight: bold; color: #c5221f;">-<?= number_format($r['total_qty']) ?></td>
                        <td><?= htmlspecialchars($r['operator'] ?: 'System') ?></td>
                        <td class="text-center">
                            <span class="badge badge-<?= $statusClass ?>">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <script>
            window.onload = function() {
                window.print();
                setTimeout(function() { window.close(); }, 500);
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Load List Outlet untuk dropdown filter
$outlets = [];
try {
    $outlets = $conn->query("SELECT * FROM outlet WHERE is_active = 1 ORDER BY nama_outlet ASC")->fetchAll();
} catch (Exception $e) {}
?>

<div class="fade-up max-w-7xl mx-auto space-y-5">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="<?= $sistem ?>/laporan" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Laporan Barang Keluar</h1>
                <p class="text-slate-500 text-sm">Ringkasan transaksi pengiriman barang ke outlet/cabang yang dikelompokkan per dokumen.</p>
            </div>
        </div>
        
        <?php if (canDo('laporan', 'print') || canDo('laporankeluar', 'print')): ?>
        <button type="button" onclick="cetakLaporan()"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Cetak Laporan
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <input type="hidden" name="menu" value="laporankeluar">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="limit" value="<?= $limit ?>">

            <!-- Filter Tanggal Awal -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Tanggal Mulai</label>
                <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" 
                       class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
            </div>

            <!-- Filter Tanggal Akhir -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Tanggal Selesai</label>
                <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" 
                       class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
            </div>

            <!-- Filter Outlet (Admin/SPV only) -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Outlet Tujuan</label>
                <?php if ($my_outlet_id): ?>
                    <input type="text" readonly value="<?= sanitize($_SESSION['nama_outlet'] ?? 'Outlet Anda') ?>"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 text-slate-500 font-semibold cursor-not-allowed">
                <?php else: ?>
                    <select name="outlet" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Semua Outlet</option>
                        <?php foreach ($outlets as $o): ?>
                        <option value="<?= $o['id_outlet'] ?>" <?= $outlet_filter == $o['id_outlet'] ? 'selected' : '' ?>>
                            <?= sanitize($o['nama_outlet']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <!-- Filter Status -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Status Pengiriman</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white" onchange="document.getElementById('filterForm').submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="Transit" <?= $status_filter === 'Transit' ? 'selected' : '' ?>>Transit (Proses)</option>
                    <option value="Diterima" <?= $status_filter === 'Diterima' ? 'selected' : '' ?>>Diterima</option>
                    <option value="Ditolak" <?= $status_filter === 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    <option value="Dikembalikan" <?= $status_filter === 'Dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
                </select>
            </div>

            <!-- Keyword Search -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Cari Transaksi</label>
                <div class="flex gap-2">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ref Trx, outlet, barang..."
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
                    <button type="submit" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-250 text-indigo-600 px-3.5 rounded-xl text-sm font-semibold transition-colors">
                        Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Data List -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Show Entries Header -->
        <div class="flex items-center justify-end px-5 py-3 border-b border-slate-100 bg-slate-50/20">
            <?php echo generateShowEntries($limit, 'laporankeluar', $search); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-12">No</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Tanggal</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Ref Transaksi</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Outlet Tujuan</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Total Item</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Total Qty</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Pengirim (User)</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Status</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-20">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-150">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-truck-fast text-3xl mb-2 block text-slate-300"></i>
                            Tidak ada data transaksi barang keluar.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($data as $row): 
                            $status = $row['status'];
                            $status_color = 'amber';
                            if ($status === 'Diterima') $status_color = 'emerald';
                            if ($status === 'Ditolak') $status_color = 'rose';
                            if ($status === 'Dikembalikan') $status_color = 'slate';
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-5 py-3.5 text-center text-slate-400 font-medium"><?= $no++ ?></td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-600">
                                <?= sanitize($row['ref_trx'] ?: '—') ?>
                            </td>
                             <td class="px-5 py-3.5">
                                 <p class="font-bold text-slate-800"><?= sanitize($row['nama_outlet'] ?: 'Tanpa Outlet') ?></p>
                                 <?php if (!empty($row['alamat_outlet'])): ?>
                                 <p class="text-[11px] text-slate-400 font-medium flex items-center gap-1 mt-0.5"><i class="fa-solid fa-location-dot text-rose-400 text-[10px]"></i> <?= sanitize($row['alamat_outlet']) ?></p>
                                 <?php endif; ?>
                             </td>
                            <td class="px-5 py-3.5 text-center text-slate-600 font-semibold">
                                <?= number_format($row['total_item']) ?> Jenis
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono font-bold text-rose-600">
                                -<?= number_format($row['total_qty']) ?>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-medium">
                                <?= sanitize($row['operator'] ?: 'System') ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="bg-<?= $status_color ?>-50 text-<?= $status_color ?>-700 border border-<?= $status_color ?>-150 px-2.5 py-0.5 rounded-full text-[10px] font-bold inline-flex items-center gap-1 shadow-sm">
                                    <span class="w-1 h-1 rounded-full bg-<?= $status_color ?>-500"></span>
                                    <?= $status ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <button type="button" onclick="openLgModal('<?= $sistem ?>/barangkeluar/v/<?= $row['ref_trx'] ?>?readonly=1')"
                                        class="inline-flex items-center gap-1 bg-sky-50 text-sky-600 border border-sky-100 hover:bg-sky-100 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm">
                                    <i class="fa-solid fa-circle-info"></i> Info
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="text-sm text-slate-500 font-medium">
                Menampilkan <span class="font-bold text-slate-700"><?= min($offset + 1, $totalData) ?></span> - 
                <span class="font-bold text-slate-700"><?= min($offset + $limit, $totalData) ?></span> dari 
                <span class="font-bold text-slate-700"><?= $totalData ?></span> data
            </div>
            
            <div class="flex items-center gap-1">
                <!-- Prev Button -->
                <button type="button" onclick="goToPage(<?= max(1, $page - 1) ?>, <?= $limit ?>, 'laporankeluar', '<?= urlencode($search) ?>')"
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
                            echo '<button type="button" onclick="goToPage('.$i.', '.$limit.', \'laporankeluar\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">'.$i.'</button>';
                        }
                    }
                    ?>
                </div>

                <!-- Next Button -->
                <button type="button" onclick="goToPage(<?= min($totalPages, $page + 1) ?>, <?= $limit ?>, 'laporankeluar', '<?= urlencode($search) ?>')"
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
function cetakLaporan() {
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(form)).toString();
    const printUrl = '<?= $sistem ?>/laporankeluar?' + params + '&print=true';
    
    // Buka popup print
    window.open(printUrl, '_blank', 'width=1000,height=700,toolbar=no,menubar=no,scrollbars=yes');
}
</script>