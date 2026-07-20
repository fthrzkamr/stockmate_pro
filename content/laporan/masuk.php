<?php
if (!canDo('laporan', 'view') && !canDo('laporanmasuk', 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit;
}

global $conn, $sistem;

$is_print = isset($_GET['print']) && $_GET['print'] == 'true';

// Filters
$supplier_filter = $_GET['supplier'] ?? '';
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($supplier_filter) {
    $where[] = "bm.id_supplier = ?";
    $params[] = $supplier_filter;
}

if ($tgl_awal) {
    $where[] = "bm.tanggal >= ?";
    $params[] = $tgl_awal;
}
if ($tgl_akhir) {
    $where[] = "bm.tanggal <= ?";
    $params[] = $tgl_akhir;
}

if ($search) {
    $where[] = "(bm.kode_transaksi LIKE ? OR s.nama_supplier LIKE ? OR bm.supplier_lainnya LIKE ? OR bm.keterangan LIKE ? OR EXISTS (
        SELECT 1 FROM barang_masuk bm2
        JOIN barang b2 ON bm2.id_barang = b2.id_barang
        WHERE COALESCE(bm2.kode_transaksi, bm2.id_masuk) = COALESCE(bm.kode_transaksi, bm.id_masuk)
        AND (b2.nama_barang LIKE ? OR b2.barcode LIKE ?)
    ))";
    $params[] = "%$search%";
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
            SELECT COUNT(DISTINCT COALESCE(bm.kode_transaksi, bm.id_masuk)) 
            FROM barang_masuk bm
            LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
            WHERE $where_sql
        ";
        $stmtCount = $conn->prepare($countQuery);
        $stmtCount->execute($params);
        $totalData = $stmtCount->fetchColumn();
        $totalPages = max(1, ceil($totalData / $limit));
    }

    $query = "
        SELECT COALESCE(bm.kode_transaksi, bm.id_masuk) as ref_trx, 
               MAX(bm.tanggal) as tanggal, 
               MAX(bm.keterangan) as keterangan, 
               MAX(bm.supplier_lainnya) as supplier_lainnya, 
               MAX(s.nama_supplier) as nama_supplier, 
               MAX(u.nama) as operator,
               COUNT(bm.id_barang) as total_item, 
               SUM(bm.qty) as total_qty
        FROM barang_masuk bm
        LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
        LEFT JOIN users u ON bm.id_user = u.id_user
        WHERE $where_sql
        GROUP BY ref_trx
        ORDER BY tanggal DESC, MAX(bm.id_masuk) DESC
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
        <title>Laporan Penerimaan Barang (Masuk) - Ringkasan</title>
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
            <h1 style="margin-top: 8px; font-size: 15px;">Laporan Penerimaan Barang (Masuk)</h1>
            <p>Rentang Periode: <?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></p>
        </div>

        <div class="meta">
            <div>
                Supplier: <?php
                    if ($supplier_filter) {
                        $s_stmt = $conn->prepare("SELECT nama_supplier FROM supplier WHERE id_supplier = ?");
                        $s_stmt->execute([$supplier_filter]);
                        echo htmlspecialchars($s_stmt->fetchColumn() ?: 'ID: ' . $supplier_filter);
                    } else {
                        echo 'Semua Supplier';
                    }
                ?><br>
                Total Transaksi: <?= count($data) ?> Dokumen
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
                    <th style="width: 12%">Tanggal</th>
                    <th style="width: 15%">Ref Transaksi</th>
                    <th>Supplier</th>
                    <th class="text-center" style="width: 10%">Total Item</th>
                    <th class="text-center" style="width: 10%">Total Qty</th>
                    <th style="width: 15%">Penerima (User)</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px;">Tidak ada data penerimaan barang yang ditemukan.</td>
                </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($data as $r): 
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                        <td class="mono"><?= htmlspecialchars($r['ref_trx'] ?: '—') ?></td>
                        <td><b><?= htmlspecialchars($r['nama_supplier'] ?: ($r['supplier_lainnya'] ?: 'Tanpa Supplier')) ?></b></td>
                        <td class="text-center mono"><?= number_format($r['total_item']) ?> Jenis</td>
                        <td class="text-center mono" style="font-weight: bold; color: #137333;">+<?= number_format($r['total_qty']) ?></td>
                        <td><?= htmlspecialchars($r['operator'] ?: 'System') ?></td>
                        <td><?= htmlspecialchars($r['keterangan'] ?: '—') ?></td>
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

// Load List Supplier untuk dropdown filter
$suppliers = [];
try {
    $suppliers = $conn->query("SELECT * FROM supplier ORDER BY nama_supplier ASC")->fetchAll();
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
                <h1 class="text-xl font-bold text-slate-800">Laporan Barang Masuk (HO)</h1>
                <p class="text-slate-500 text-sm">Ringkasan transaksi penerimaan pasokan barang dari supplier yang dikelompokkan per dokumen.</p>
            </div>
        </div>
        
        <?php if (canDo('laporan', 'print') || canDo('laporanmasuk', 'print')): ?>
        <button type="button" onclick="cetakLaporan()"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Cetak Laporan
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <input type="hidden" name="menu" value="laporanmasuk">
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

            <!-- Filter Supplier -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Supplier</label>
                <select name="supplier" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white" onchange="document.getElementById('filterForm').submit()">
                    <option value="">Semua Supplier</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id_supplier'] ?>" <?= $supplier_filter == $s['id_supplier'] ? 'selected' : '' ?>>
                        <?= sanitize($s['nama_supplier']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Keyword Search -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Cari Transaksi / Barang</label>
                <div class="flex gap-2">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ref Trx, supplier, barang..."
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
            <?php echo generateShowEntries($limit, 'laporanmasuk', $search); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-12">No</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Tanggal</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Ref Transaksi</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Supplier</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Total Item</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Total Qty</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Penerima (User)</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Keterangan</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-20">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-150">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-truck-ramp-box text-3xl mb-2 block text-slate-300"></i>
                            Tidak ada data transaksi barang masuk.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($data as $row): 
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-5 py-3.5 text-center text-slate-400 font-medium"><?= $no++ ?></td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-650">
                                <?= sanitize($row['ref_trx'] ?: '—') ?>
                            </td>
                            <td class="px-5 py-3.5 font-bold text-slate-800">
                                <?= sanitize($row['nama_supplier'] ?: ($row['supplier_lainnya'] ?: 'Tanpa Supplier')) ?>
                            </td>
                            <td class="px-5 py-3.5 text-center text-slate-600 font-semibold">
                                <?= number_format($row['total_item']) ?> Jenis
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono font-bold text-emerald-600">
                                +<?= number_format($row['total_qty']) ?>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-medium">
                                <?= sanitize($row['operator'] ?: 'System') ?>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500 max-w-xs truncate" title="<?= sanitize($row['keterangan']) ?>">
                                <?= sanitize($row['keterangan'] ?: '—') ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <button type="button" onclick="openLgModal('<?= $sistem ?>/barangmasuk/v/<?= $row['ref_trx'] ?>?readonly=1')"
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
                <button type="button" onclick="goToPage(<?= max(1, $page - 1) ?>, <?= $limit ?>, 'laporanmasuk', '<?= urlencode($search) ?>')"
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
                            echo '<button type="button" onclick="goToPage('.$i.', '.$limit.', \'laporanmasuk\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">'.$i.'</button>';
                        }
                    }
                    ?>
                </div>

                <!-- Next Button -->
                <button type="button" onclick="goToPage(<?= min($totalPages, $page + 1) ?>, <?= $limit ?>, 'laporanmasuk', '<?= urlencode($search) ?>')"
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
    const printUrl = '<?= $sistem ?>/laporanmasuk?' + params + '&print=true';
    
    // Buka popup print
    window.open(printUrl, '_blank', 'width=1000,height=700,toolbar=no,menubar=no,scrollbars=yes');
}
</script>