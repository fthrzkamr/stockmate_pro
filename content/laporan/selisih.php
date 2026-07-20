<?php
if (!canDo('laporan', 'view') && !canDo('laporanselisih', 'view')) {
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
$status_filter = $_GET['status'] ?? 'Approved'; // Default ke Approved karena selisih valid setelah disetujui
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

$my_outlet_id = $_SESSION['outlet_id'] ?? null;
$where = ["1=1"];
$params = [];

if ($my_outlet_id) {
    $where[] = "so.id_outlet = ?";
    $params[] = $my_outlet_id;
} elseif ($outlet_filter) {
    $where[] = "so.id_outlet = ?";
    $params[] = $outlet_filter;
}

if ($tgl_awal) {
    $where[] = "so.tanggal >= ?";
    $params[] = $tgl_awal;
}
if ($tgl_akhir) {
    $where[] = "so.tanggal <= ?";
    $params[] = $tgl_akhir;
}

if ($status_filter && $status_filter !== 'all') {
    $where[] = "so.status_approval = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where[] = "(so.no_so LIKE ? OR b.nama_barang LIKE ? OR b.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(" AND ", $where);

try {
    if (!$is_print) {
        $countQuery = "
            SELECT COUNT(*) 
            FROM stock_opname so
            JOIN barang b ON so.id_barang = b.id_barang
            JOIN outlet o ON so.id_outlet = o.id_outlet
            WHERE $where_sql
        ";
        $stmtCount = $conn->prepare($countQuery);
        $stmtCount->execute($params);
        $totalData = $stmtCount->fetchColumn();
        $totalPages = max(1, ceil($totalData / $limit));
    }

    $query = "
        SELECT so.*, b.nama_barang, b.barcode, b.satuan, o.nama_outlet, u.nama as operator, app.nama as approver
        FROM stock_opname so
        JOIN barang b ON so.id_barang = b.id_barang
        JOIN outlet o ON so.id_outlet = o.id_outlet
        LEFT JOIN users u ON so.id_user = u.id_user
        LEFT JOIN users app ON so.approved_by = app.id_user
        WHERE $where_sql
        ORDER BY so.tanggal DESC, so.no_so DESC, b.nama_barang ASC
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
        <title>Laporan Selisih Stock Opname</title>
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
            .surplus { color: #137333; font-weight: bold; }
            .deficit { color: #c5221f; font-weight: bold; }
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
            <h1 style="margin-top: 8px; font-size: 15px;">Laporan Selisih Stock Opname</h1>
            <p>Rentang Periode: <?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></p>
        </div>

        <div class="meta">
            <div>
                Outlet: <?= $outlet_filter ? 'Outlet ID: ' . htmlspecialchars($outlet_filter) : 'Semua Outlet' ?><br>
                Status: <?= htmlspecialchars($status_filter) ?>
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
                    <th style="width: 10%">Tanggal</th>
                    <th style="width: 15%">Outlet</th>
                    <th style="width: 15%">Ref SO</th>
                    <th>Nama Barang / Barcode</th>
                    <th class="text-center" style="width: 10%">Awal (Sist)</th>
                    <th class="text-center" style="width: 10%">Akhir (Fisik)</th>
                    <th class="text-center" style="width: 10%">Selisih</th>
                    <th class="text-center" style="width: 8%">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px;">Tidak ada data selisih opname yang ditemukan.</td>
                </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($data as $r): 
                        $selisih = $r['stok_akhir'] - $r['stok_awal'];
                        $selisih_text = '0';
                        $selisih_class = '';
                        if ($selisih > 0) {
                            $selisih_text = '+' . $selisih;
                            $selisih_class = 'surplus';
                        } elseif ($selisih < 0) {
                            $selisih_text = $selisih;
                            $selisih_class = 'deficit';
                        }
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                        <td><?= htmlspecialchars($r['nama_outlet']) ?></td>
                        <td class="mono"><?= htmlspecialchars($r['no_so']) ?></td>
                        <td>
                            <b><?= htmlspecialchars($r['nama_barang']) ?></b><br>
                            <span class="mono" style="color: #666; font-size: 10px;"><?= htmlspecialchars($r['barcode']) ?></span>
                        </td>
                        <td class="text-center mono"><?= number_format($r['stok_awal']) ?> <?= htmlspecialchars($r['satuan']) ?></td>
                        <td class="text-center mono"><?= number_format($r['stok_akhir']) ?> <?= htmlspecialchars($r['satuan']) ?></td>
                        <td class="text-center mono <?= $selisih_class ?>"><?= $selisih_text ?></td>
                        <td class="text-center">
                            <span class="badge badge-<?= $r['status_approval'] === 'Approved' ? 'success' : ($r['status_approval'] === 'Pending' ? 'warning' : 'danger') ?>">
                                <?= htmlspecialchars($r['status_approval']) ?>
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
                <h1 class="text-xl font-bold text-slate-800">Laporan Selisih Stock Opname</h1>
                <p class="text-slate-500 text-sm">Analisis perbandingan antara stok sistem dengan stok fisik hasil opname di outlet.</p>
            </div>
        </div>
        
        <?php if (canDo('laporan', 'print') || canDo('laporanselisih', 'print')): ?>
        <button type="button" onclick="cetakLaporan()"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Cetak Laporan
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <input type="hidden" name="menu" value="laporanselisih">
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
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Cabang / Outlet</label>
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
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Status Approval</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white" onchange="document.getElementById('filterForm').submit()">
                    <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                </select>
            </div>

            <!-- Keyword Search -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Cari Barang / SO</label>
                <div class="flex gap-2">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama barang, SO..."
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
            <?php echo generateShowEntries($limit, 'laporanselisih', $search); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-12">No</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Tanggal</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Outlet</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Ref SO</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang / Barcode</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Stok Awal (Sistem)</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Stok Akhir (Fisik)</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Selisih</th>
                        <th class="px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-150">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-scale-unbalanced text-3xl mb-2 block text-slate-300"></i>
                            Tidak ada data laporan selisih opname.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($data as $row): 
                            $selisih = $row['stok_akhir'] - $row['stok_awal'];
                            $selisih_color = 'text-slate-500';
                            $selisih_text = '0';
                            if ($selisih > 0) {
                                $selisih_color = 'text-emerald-600 font-bold';
                                $selisih_text = '+' . $selisih;
                            } elseif ($selisih < 0) {
                                $selisih_color = 'text-rose-600 font-bold';
                                $selisih_text = $selisih;
                            }

                            $status = $row['status_approval'];
                            $status_color = 'amber';
                            if ($status === 'Approved') $status_color = 'emerald';
                            if ($status === 'Rejected') $status_color = 'rose';
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-5 py-3.5 text-center text-slate-400 font-medium"><?= $no++ ?></td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
                            </td>
                            <td class="px-5 py-3.5 font-bold text-slate-800">
                                <?= sanitize($row['nama_outlet']) ?>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-600">
                                <?= sanitize($row['no_so']) ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="font-bold text-slate-700"><?= sanitize($row['nama_barang']) ?></p>
                                <p class="text-[11px] text-slate-400 font-mono mt-0.5"><?= sanitize($row['barcode'] ?: '—') ?></p>
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono font-semibold text-slate-600">
                                <?= number_format($row['stok_awal']) ?> <?= sanitize($row['satuan'] ?: 'Pcs') ?>
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono font-bold text-slate-800">
                                <?= number_format($row['stok_akhir']) ?> <?= sanitize($row['satuan'] ?: 'Pcs') ?>
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono <?= $selisih_color ?>">
                                <?= $selisih_text ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="bg-<?= $status_color ?>-50 text-<?= $status_color ?>-700 border border-<?= $status_color ?>-150 px-2.5 py-0.5 rounded-full text-[10px] font-bold inline-flex items-center gap-1 shadow-sm">
                                    <span class="w-1 h-1 rounded-full bg-<?= $status_color ?>-500"></span>
                                    <?= $status ?>
                                </span>
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
                <button type="button" onclick="goToPage(<?= max(1, $page - 1) ?>, <?= $limit ?>, 'laporanselisih', '<?= urlencode($search) ?>')"
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
                            echo '<button type="button" onclick="goToPage('.$i.', '.$limit.', \'laporanselisih\', \''.urlencode($search).'\')" class="w-8 h-8 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">'.$i.'</button>';
                        }
                    }
                    ?>
                </div>

                <!-- Next Button -->
                <button type="button" onclick="goToPage(<?= min($totalPages, $page + 1) ?>, <?= $limit ?>, 'laporanselisih', '<?= urlencode($search) ?>')"
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
    const printUrl = '<?= $sistem ?>/laporanselisih?' + params + '&print=true';
    
    // Buka popup print
    window.open(printUrl, '_blank', 'width=1000,height=700,toolbar=no,menubar=no,scrollbars=yes');
}
</script>