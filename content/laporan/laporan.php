<?php
if (!canDo('laporan', 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses ke Laporan.</div>";
    exit;
}

global $conn, $sistem;

// Fetch Summary Stats
try {
    // 1. Total Stok Gudang Pusat
    $stokGudang = $conn->query("SELECT SUM(stok) FROM inventory")->fetchColumn() ?: 0;

    // 2. Total Stok Outlet (Gabungan)
    $stokOutlet = $conn->query("SELECT SUM(stok) FROM stok_outlet")->fetchColumn() ?: 0;

    // 3. Total Pending SO
    $pendingSO = $conn->query("SELECT COUNT(DISTINCT no_so) FROM stock_opname WHERE status_approval = 'Pending'")->fetchColumn() ?: 0;

    // 4. Barang Low Stock di Gudang Pusat
    $lowStockQuery = "
        SELECT b.nama_barang, COALESCE(inv.stok, 0) as stok, b.min_stok, b.satuan
        FROM barang b
        LEFT JOIN inventory inv ON b.id_barang = inv.id_barang
        WHERE b.is_active = 1 AND COALESCE(inv.stok, 0) <= b.min_stok
        ORDER BY inv.stok ASC 
        LIMIT 5
    ";
    $lowStockItems = $conn->query($lowStockQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stokGudang = 0;
    $stokOutlet = 0;
    $pendingSO = 0;
    $lowStockItems = [];
}
?>

<div class="fade-up max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Laporan & Analitik</h1>
        <p class="text-slate-500 text-sm mt-1">Pusat pemantauan stok gudang, outlet, riwayat barang masuk/keluar, dan laporan selisih opname.</p>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Stat 1: Gudang -->
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-5 text-white shadow-md relative overflow-hidden">
            <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-indigo-400/20 text-8xl font-bold">
                <i class="fa-solid fa-warehouse"></i>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/20">
                    <i class="fa-solid fa-warehouse text-base"></i>
                </div>
                <span class="text-xs font-semibold tracking-wider uppercase opacity-85">Stok Gudang Pusat</span>
            </div>
            <div class="mt-4">
                <h3 class="text-3xl font-extrabold font-mono"><?= number_format($stokGudang) ?></h3>
                <p class="text-[10px] text-indigo-100/80 mt-1 font-medium">Total unit barang yang tersedia di Main HO</p>
            </div>
        </div>

        <!-- Stat 2: Outlet -->
        <div class="bg-gradient-to-br from-sky-500 to-sky-600 rounded-2xl p-5 text-white shadow-md relative overflow-hidden">
            <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-sky-450/20 text-8xl font-bold">
                <i class="fa-solid fa-store"></i>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/20">
                    <i class="fa-solid fa-store text-base"></i>
                </div>
                <span class="text-xs font-semibold tracking-wider uppercase opacity-85">Stok Outlet (Gabungan)</span>
            </div>
            <div class="mt-4">
                <h3 class="text-3xl font-extrabold font-mono"><?= number_format($stokOutlet) ?></h3>
                <p class="text-[10px] text-sky-100/80 mt-1 font-medium">Total stok terdistribusi di semua cabang</p>
            </div>
        </div>

        <!-- Stat 3: Pending SO -->
        <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl p-5 text-white shadow-md relative overflow-hidden">
            <div class="absolute right-0 bottom-0 translate-x-4 translate-y-4 text-amber-400/20 text-8xl font-bold">
                <i class="fa-solid fa-clipboard-question"></i>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/20">
                    <i class="fa-solid fa-clipboard-question text-base animate-pulse"></i>
                </div>
                <span class="text-xs font-semibold tracking-wider uppercase opacity-85">Pending Stock Opname</span>
            </div>
            <div class="mt-4">
                <h3 class="text-3xl font-extrabold font-mono"><?= number_format($pendingSO) ?></h3>
                <p class="text-[10px] text-amber-100/80 mt-1 font-medium">Pengajuan yang membutuhkan tinjauan SPV</p>
            </div>
        </div>
    </div>

    <!-- Reports Hub Grid & Low Stock Sidebar -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Report Cards (2 cols) -->
        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
            
            <!-- Card 1: Laporan Stok -->
            <a href="<?= $sistem ?>/laporanstok" class="group bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-350 p-6 rounded-2xl shadow-sm transition-all duration-200 flex flex-col justify-between h-48">
                <div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-650 group-hover:bg-indigo-600 group-hover:text-white rounded-2xl flex items-center justify-center transition-all duration-300 shadow-sm">
                        <i class="fa-solid fa-boxes-stacked text-lg"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 mt-4 group-hover:text-indigo-650 transition-colors">Laporan Stok Barang</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">Lihat dan cetak ketersediaan stok barang secara real-time baik di Gudang Pusat maupun per cabang/outlet.</p>
                </div>
                <span class="text-xs font-bold text-indigo-600 flex items-center gap-1 mt-2">
                    Buka Laporan <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                </span>
            </a>

            <!-- Card 2: Laporan Selisih -->
            <a href="<?= $sistem ?>/laporanselisih" class="group bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-350 p-6 rounded-2xl shadow-sm transition-all duration-200 flex flex-col justify-between h-48">
                <div>
                    <div class="w-12 h-12 bg-rose-50 text-rose-655 group-hover:bg-rose-600 group-hover:text-white rounded-2xl flex items-center justify-center transition-all duration-300 shadow-sm">
                        <i class="fa-solid fa-scale-unbalanced text-lg"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 mt-4 group-hover:text-rose-650 transition-colors">Laporan Selisih Opname</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">Analisis hasil Stock Opname, selisih kurang/lebih antara sistem dan perhitungan fisik riil di outlet.</p>
                </div>
                <span class="text-xs font-bold text-rose-600 flex items-center gap-1 mt-2">
                    Buka Laporan <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                </span>
            </a>

            <!-- Card 3: Laporan Masuk -->
            <a href="<?= $sistem ?>/laporanmasuk" class="group bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-350 p-6 rounded-2xl shadow-sm transition-all duration-200 flex flex-col justify-between h-48">
                <div>
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-650 group-hover:bg-emerald-600 group-hover:text-white rounded-2xl flex items-center justify-center transition-all duration-300 shadow-sm">
                        <i class="fa-solid fa-arrow-down-long text-lg"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 mt-4 group-hover:text-emerald-655 transition-colors">Laporan Barang Masuk</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">Catatan riwayat pasokan barang dari supplier ke Gudang Pusat yang difilter berdasarkan rentang tanggal.</p>
                </div>
                <span class="text-xs font-bold text-emerald-650 flex items-center gap-1 mt-2">
                    Buka Laporan <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                </span>
            </a>

            <!-- Card 4: Laporan Keluar -->
            <a href="<?= $sistem ?>/laporankeluar" class="group bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-350 p-6 rounded-2xl shadow-sm transition-all duration-200 flex flex-col justify-between h-48">
                <div>
                    <div class="w-12 h-12 bg-sky-50 text-sky-650 group-hover:bg-sky-600 group-hover:text-white rounded-2xl flex items-center justify-center transition-all duration-300 shadow-sm">
                        <i class="fa-solid fa-arrow-up-long text-lg"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 mt-4 group-hover:text-sky-655 transition-colors">Laporan Barang Keluar</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">Riwayat pengiriman barang dari Gudang Pusat ke masing-masing outlet beserta status penerimaannya.</p>
                </div>
                <span class="text-xs font-bold text-sky-600 flex items-center gap-1 mt-2">
                    Buka Laporan <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                </span>
            </a>

        </div>

        <!-- Sidebar: Low Stock Warning (1 col) -->
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4 flex flex-col">
            <div>
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-500 animate-pulse"></i>
                    Warning Stok Menipis (HO)
                </h3>
                <p class="text-slate-500 text-[11px] mt-0.5">Daftar barang di gudang pusat yang telah mencapai atau di bawah batas minimum.</p>
            </div>
            
            <div class="flex-1 divide-y divide-slate-100">
                <?php if (empty($lowStockItems)): ?>
                <div class="py-8 text-center text-slate-400 text-xs">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-500 mb-1 block"></i>
                    Semua stok barang di Gudang Pusat aman.
                </div>
                <?php else: ?>
                    <?php foreach ($lowStockItems as $item): 
                        $pct = $item['min_stok'] > 0 ? ($item['stok'] / $item['min_stok']) * 100 : 0;
                        $color = 'amber';
                        if ($item['stok'] == 0) $color = 'rose';
                    ?>
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-bold text-slate-700 truncate max-w-[150px]"><?= sanitize($item['nama_barang']) ?></span>
                            <span class="font-bold text-<?= $color ?>-600 font-mono"><?= number_format($item['stok']) ?> / <?= number_format($item['min_stok']) ?> <span class="text-[10px] text-slate-400 font-normal"><?= sanitize($item['satuan']) ?></span></span>
                        </div>
                        <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-<?= $color ?>-500 rounded-full" style="width: <?= min(100, $pct) ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>