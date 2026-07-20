<?php
if (!canDo('laporan', 'view') && !canDo('laporanstok', 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit;
}

global $conn, $sistem;

$is_print = isset($_GET['print']) && $_GET['print'] == 'true';

// Filters
$lokasi_filter = $_GET['lokasi'] ?? 'all'; // all, gudang, outlet_id
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

$my_outlet_id = $_SESSION['outlet_id'] ?? null;
if ($my_outlet_id) {
    $lokasi_filter = $my_outlet_id; // Stock keeper/outlet staff dikunci ke outletnya sendiri
}

$where = ["b.is_active = 1"];
$params = [];

if ($search) {
    $where[] = "(b.nama_barang LIKE ? OR b.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(" AND ", $where);

try {
    // Hitung total data untuk pagination (hanya jika tidak mencetak)
    if (!$is_print) {
        if ($lokasi_filter === 'gudang' || $lokasi_filter === 'all') {
            $countQuery = "SELECT COUNT(*) FROM barang b WHERE $where_sql";
            $stmtCount = $conn->prepare($countQuery);
            $stmtCount->execute($params);
            $totalData = $stmtCount->fetchColumn();
        } else {
            $countQuery = "
                SELECT COUNT(*) 
                FROM stok_outlet so
                JOIN barang b ON b.id_barang = so.id_barang
                WHERE so.id_outlet = ? AND b.is_active = 1
            ";
            $countParams = [$lokasi_filter];
            if ($search) {
                $countQuery .= " AND (b.nama_barang LIKE ? OR b.barcode LIKE ?)";
                $countParams[] = "%$search%";
                $countParams[] = "%$search%";
            }
            $stmtCount = $conn->prepare($countQuery);
            $stmtCount->execute($countParams);
            $totalData = $stmtCount->fetchColumn();
        }
        $totalPages = max(1, ceil($totalData / $limit));
    }

    if ($lokasi_filter === 'gudang') {
        // Query Stok Gudang Pusat
        $query = "
            SELECT b.id_barang, b.nama_barang, b.barcode, b.satuan, b.min_stok, b.kategori, t.nama_tipe,
                   COALESCE(inv.stok, 0) as stok
            FROM barang b
            LEFT JOIN inventory inv ON b.id_barang = inv.id_barang
            LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
            WHERE $where_sql
            ORDER BY b.nama_barang ASC
        ";
    } elseif ($lokasi_filter === 'all') {
        // Query Stok Gabungan (Gudang & Total Outlet)
        $query = "
            SELECT b.id_barang, b.nama_barang, b.barcode, b.satuan, b.min_stok, b.kategori, t.nama_tipe,
                   COALESCE(inv.stok, 0) as stok_gudang,
                   COALESCE((SELECT SUM(stok) FROM stok_outlet WHERE id_barang = b.id_barang), 0) as stok_outlet
            FROM barang b
            LEFT JOIN inventory inv ON b.id_barang = inv.id_barang
            LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
            WHERE $where_sql
            ORDER BY b.nama_barang ASC
        ";
    } else {
        // Query Stok Outlet Spesifik — hanya tampilkan barang yang pernah diterima outlet ini
        $query = "
            SELECT b.id_barang, b.nama_barang, b.barcode, b.satuan, b.min_stok, b.kategori, t.nama_tipe,
                   so.stok as stok, o.nama_outlet
            FROM stok_outlet so
            JOIN barang b   ON b.id_barang = so.id_barang
            LEFT JOIN outlet o ON o.id_outlet = so.id_outlet
            LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
            WHERE so.id_outlet = ? AND b.is_active = 1
        ";
        // Tambahkan filter search jika ada
        if ($search) {
            $query .= " AND (b.nama_barang LIKE ? OR b.barcode LIKE ?)";
        }
        $query .= " ORDER BY b.nama_barang ASC";
        // Reset params — build khusus untuk query ini
        $params = [$lokasi_filter];
        if ($search) {
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
    }

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
        <title>Laporan Stok Barang</title>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; font-size: 11px; margin: 20px; line-height: 1.4; }
            .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 0.5px; }
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
            .badge-danger { background-color: #fce8e6; color: #c5221f; }
            @media print {
                body { margin: 10px; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div style="font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #1e293b;">PT PSY BERKAH INDONESIA</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px;">StockMate Pro - System Management Inventory</div>
            <h1 style="margin-top: 8px; font-size: 16px; border-top: 1px solid #e2e8f0; padding-top: 8px;">Laporan Stok Barang</h1>
            <p>Lokasi Pemantauan: <?php
                if ($lokasi_filter === 'all') echo 'Semua Lokasi (Gudang & Cabang)';
                elseif ($lokasi_filter === 'gudang') echo 'Gudang Pusat (HO)';
                else {
                    $nama_ot = !empty($data) ? ($data[0]['nama_outlet'] ?? '') : '';
                    echo 'Outlet: ' . htmlspecialchars($nama_ot ?: 'Outlet ID: ' . $lokasi_filter);
                }
            ?></p>
        </div>

        <div class="meta">
            <div>
                Total Item: <?= count($data) ?> Barang
            </div>
            <div class="text-right">
                Dicetak: <?= date('d/m/Y H:i') ?><br>
                Oleh: <?= htmlspecialchars($_SESSION['username'] ?? 'System') ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%">No</th>
                    <th style="width: 12%">Barcode</th>
                    <th>Nama Barang</th>
                    <th style="width: 12%">Kategori</th>
                    <th style="width: 15%">Tipe</th>
                    <?php if ($lokasi_filter === 'all'): ?>
                    <th class="text-center" style="width: 12%">Stok Gudang</th>
                    <th class="text-center" style="width: 12%">Stok Outlet</th>
                    <?php else: ?>
                    <th class="text-center" style="width: 15%">Stok Saat Ini</th>
                    <?php endif; ?>
                    <th class="text-center" style="width: 8%">Min Stok</th>
                    <th class="text-center" style="width: 10%">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                <tr>
                    <td colspan="<?= $lokasi_filter === 'all' ? '9' : '8' ?>" class="text-center" style="padding: 20px;">Tidak ada data stok yang ditemukan.</td>
                </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($data as $r): 
                        $cur_stok = $lokasi_filter === 'all' ? ($r['stok_gudang'] + $r['stok_outlet']) : $r['stok'];
                        $is_low = $cur_stok <= $r['min_stok'];
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="mono"><?= htmlspecialchars($r['barcode']) ?></td>
                        <td><b><?= htmlspecialchars($r['nama_barang']) ?></b></td>
                        <td><?= htmlspecialchars($r['kategori']) ?></td>
                        <td><?= htmlspecialchars($r['nama_tipe']) ?></td>
                        
                        <?php if ($lokasi_filter === 'all'): ?>
                        <td class="text-center mono"><?= number_format($r['stok_gudang']) ?> <?= htmlspecialchars($r['satuan']) ?></td>
                        <td class="text-center mono"><?= number_format($r['stok_outlet']) ?> <?= htmlspecialchars($r['satuan']) ?></td>
                        <?php else: ?>
                        <td class="text-center mono"><?= number_format($r['stok']) ?> <?= htmlspecialchars($r['satuan']) ?></td>
                        <?php endif; ?>

                        <td class="text-center mono"><?= number_format($r['min_stok']) ?></td>
                        <td class="text-center">
                            <span class="badge badge-<?= $is_low ? 'danger' : 'success' ?>">
                                <?= $is_low ? 'MENIPIS' : 'AMAN' ?>
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

// Load Dropdown Outlet untuk Filter (khusus admin/SPV)
$outlets = [];
if (!$my_outlet_id) {
    try {
        $outlets = $conn->query("SELECT * FROM outlet WHERE is_active = 1 ORDER BY nama_outlet ASC")->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="fade-up max-w-7xl mx-auto space-y-5">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="<?= $sistem ?>/laporan" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Laporan Ketersediaan Stok</h1>
                <p class="text-slate-500 text-sm">Pantau ketersediaan barang real-time di Gudang Pusat dan seluruh cabang.</p>
            </div>
        </div>
        
        <?php if (canDo('laporan', 'print') || canDo('laporanstok', 'print')): ?>
        <button type="button" onclick="cetakLaporan()"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Cetak Laporan
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <input type="hidden" name="menu" value="laporanstok">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="limit" value="<?= $limit ?>">

            <!-- Filter Lokasi -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Lokasi / Cabang</label>
                <?php if ($my_outlet_id): ?>
                    <input type="text" readonly value="<?= sanitize($_SESSION['nama_outlet'] ?? 'Outlet Anda') ?>"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 text-slate-500 font-semibold cursor-not-allowed">
                <?php else: ?>
                    <select name="lokasi" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="all" <?= $lokasi_filter === 'all' ? 'selected' : '' ?>>Semua Lokasi (Gudang & Cabang)</option>
                        <option value="gudang" <?= $lokasi_filter === 'gudang' ? 'selected' : '' ?>>Gudang Pusat (HO)</option>
                        <?php foreach ($outlets as $o): ?>
                        <option value="<?= $o['id_outlet'] ?>" <?= $lokasi_filter == $o['id_outlet'] ? 'selected' : '' ?>>
                            Outlet: <?= sanitize($o['nama_outlet']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <!-- Keyword Search -->
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Cari Barang</label>
                <div class="flex gap-2">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Masukkan nama barang, barcode..."
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
                    <button type="submit" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-250 text-indigo-600 px-5 rounded-xl text-sm font-semibold transition-colors">
                        Filter
                    </button>
                    <?php if($search): ?>
                    <a href="<?= $sistem ?>/laporanstok?lokasi=<?= $lokasi_filter ?>" class="bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-650 px-4 rounded-xl text-sm font-semibold transition-colors flex items-center">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Data List -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Show Entries Header -->
        <div class="flex items-center justify-end px-5 py-3 border-b border-slate-100 bg-slate-50/20">
            <?php echo generateShowEntries($limit, 'laporanstok', $search); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-12">No</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Barcode</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Kategori</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Tipe</th>
                        
                        <?php if ($lokasi_filter === 'all'): ?>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Stok Gudang</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Stok Outlet</th>
                        <?php else: ?>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Stok Saat Ini</th>
                        <?php endif; ?>

                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Min Stok</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-150">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?= $lokasi_filter === 'all' ? '9' : '8' ?>" class="px-5 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-boxes-stacked text-3xl mb-2 block text-slate-300"></i>
                            Tidak ada data stok barang.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($data as $row): 
                            $cur_stok = $lokasi_filter === 'all' ? ($row['stok_gudang'] + $row['stok_outlet']) : $row['stok'];
                            $is_low = $cur_stok <= $row['min_stok'];
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-5 py-3.5 text-center text-slate-400 font-medium"><?= $no++ ?></td>
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-600">
                                <?= sanitize($row['barcode'] ?: '—') ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="font-bold text-slate-700"><?= sanitize($row['nama_barang']) ?></p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-medium text-xs">
                                <?= sanitize($row['kategori'] ?: '—') ?>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-medium text-xs">
                                <?= sanitize($row['nama_tipe'] ?: '—') ?>
                            </td>
                            
                            <?php if ($lokasi_filter === 'all'): ?>
                            <td class="px-5 py-3.5 text-center font-mono font-semibold text-slate-600">
                                <?= number_format($row['stok_gudang']) ?> <?= sanitize($row['satuan'] ?: 'Pcs') ?>
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono font-semibold text-slate-600">
                                <?= number_format($row['stok_outlet']) ?> <?= sanitize($row['satuan'] ?: 'Pcs') ?>
                            </td>
                            <?php else: ?>
                            <td class="px-5 py-3.5 text-center font-mono font-bold text-slate-800">
                                <?= number_format($row['stok']) ?> <?= sanitize($row['satuan'] ?: 'Pcs') ?>
                            </td>
                            <?php endif; ?>

                            <td class="px-5 py-3.5 text-center font-mono font-semibold text-slate-500">
                                <?= number_format($row['min_stok']) ?>
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                <?php if ($is_low): ?>
                                <span class="bg-rose-50 text-rose-700 border border-rose-150 px-2.5 py-0.5 rounded-full text-[10px] font-bold inline-flex items-center gap-1 shadow-sm">
                                    <span class="w-1 h-1 rounded-full bg-rose-500"></span> Menipis
                                </span>
                                <?php else: ?>
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-150 px-2.5 py-0.5 rounded-full text-[10px] font-bold inline-flex items-center gap-1 shadow-sm">
                                    <span class="w-1 h-1 rounded-full bg-emerald-500"></span> Aman
                                </span>
                                <?php endif; ?>
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
                <button type="button" onclick="goToPage(<?= max(1, $page - 1) ?>, <?= $limit ?>, 'laporanstok', '<?= urlencode($search) ?>')"
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
                            echo '<button type="button" onclick="goToPage('.$i.', '.$limit.', \'laporanstok\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">'.$i.'</button>';
                        }
                    }
                    ?>
                </div>

                <!-- Next Button -->
                <button type="button" onclick="goToPage(<?= min($totalPages, $page + 1) ?>, <?= $limit ?>, 'laporanstok', '<?= urlencode($search) ?>')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors <?= $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>"
                        <?= $page >= $totalPages ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
function cetakLaporan() {
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(form)).toString();
    const printUrl = '<?= $sistem ?>/laporanstok?' + params + '&print=true';
    
    // Buka popup print
    window.open(printUrl, '_blank', 'width=1000,height=700,toolbar=no,menubar=no,scrollbars=yes');
}
</script>