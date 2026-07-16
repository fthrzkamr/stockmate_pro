<?php
global $conn, $nama, $sistem;

// Stats
try { $totalBarang = (int)$conn->query("SELECT COUNT(*) FROM barang")->fetchColumn(); } catch(Exception $e) { $totalBarang = 0; }
try { $totalOutlet = (int)$conn->query("SELECT COUNT(*) FROM outlet WHERE is_active = 1")->fetchColumn(); } catch(Exception $e) { $totalOutlet = 0; }
try { $pendingTerima = (int)$conn->query("SELECT COUNT(*) FROM barang_keluar WHERE status='Pending'")->fetchColumn(); } catch(Exception $e) { $pendingTerima = 0; }
try {
    $hampirHabis = (int)$conn->query("
        SELECT COUNT(*) FROM (
            SELECT b.id_barang, b.min_stok,
                   (COALESCE((SELECT SUM(qty) FROM barang_masuk WHERE id_barang = b.id_barang), 0) -
                    COALESCE((SELECT SUM(qty) FROM barang_keluar WHERE id_barang = b.id_barang), 0)) as current_stok
            FROM barang b
        ) t WHERE current_stok <= min_stok AND min_stok > 0
    ")->fetchColumn();
} catch(Exception $e) { $hampirHabis = 0; }

// Transaksi Terakhir
try {
    $recentMasuk = $conn->query("
        SELECT bm.tanggal, b.nama_barang, bm.qty, b.satuan
        FROM barang_masuk bm JOIN barang b ON bm.id_barang = b.id_barang
        ORDER BY bm.id_masuk DESC LIMIT 5
    ")->fetchAll();
} catch(Exception $e) { $recentMasuk = []; }

try {
    $recentKeluar = $conn->query("
        SELECT bk.tanggal, b.nama_barang, bk.qty, b.satuan, o.nama_outlet, bk.status
        FROM barang_keluar bk
        JOIN barang b ON bk.id_barang = b.id_barang
        LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
        ORDER BY bk.id_keluar DESC LIMIT 5
    ")->fetchAll();
} catch(Exception $e) { $recentKeluar = []; }
?>

<div class="fade-up space-y-6">

    <!-- Page Header -->
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
        <p class="text-slate-500 text-sm mt-1">
            Selamat datang, <span class="text-sky-600 font-semibold"><?= htmlspecialchars($nama) ?></span>
            <span class="text-slate-300 mx-2">•</span>
            <span class="text-slate-500"><?= date('d F Y') ?></span>
        </p>
    </div>

    <!-- Alert Stok Menipis -->
    <?php if ($hampirHabis > 0): ?>
    <div class="flex items-center gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 flex-shrink-0"></i>
        <span><strong><?= $hampirHabis ?> barang</strong> hampir habis / di bawah stok minimum.</span>
        <a href="<?= $sistem ?>/barang" class="ml-auto text-amber-600 font-bold text-xs bg-amber-100 hover:bg-amber-200 px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
            Cek Barang &rarr;
        </a>
    </div>
    <?php endif; ?>

    <!-- Pending Terima Barang -->
    <?php if ($pendingTerima > 0): ?>
    <div class="flex items-center gap-3 bg-indigo-50 border border-indigo-200 text-indigo-800 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-truck-fast text-indigo-500 flex-shrink-0"></i>
        <span><strong><?= $pendingTerima ?> pengiriman</strong> menunggu konfirmasi penerimaan di outlet.</span>
        <a href="<?= $sistem ?>/terimabarang?tab=pending" class="ml-auto text-indigo-600 font-bold text-xs bg-indigo-100 hover:bg-indigo-200 px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
            Lihat &rarr;
        </a>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center mb-3 border border-sky-100">
                <i class="fa-solid fa-boxes-stacked text-sm"></i>
            </div>
            <p class="text-2xl font-black text-slate-800"><?= number_format($totalBarang) ?></p>
            <p class="text-slate-500 text-xs mt-1 font-medium">Total Barang</p>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3 border border-emerald-100">
                <i class="fa-solid fa-store text-sm"></i>
            </div>
            <p class="text-2xl font-black text-slate-800"><?= number_format($totalOutlet) ?></p>
            <p class="text-slate-500 text-xs mt-1 font-medium">Outlet Aktif</p>
        </div>
    </div>

    <!-- Transaksi Terakhir -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <!-- Barang Masuk Terakhir -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-800"><i class="fa-solid fa-arrow-down text-blue-500 mr-2"></i>Barang Masuk Terakhir</h3>
                <a href="<?= $sistem ?>/barangmasuk" class="text-xs text-sky-600 font-semibold hover:underline">Lihat semua</a>
            </div>
            <div class="divide-y divide-slate-50">
                <?php if (empty($recentMasuk)): ?>
                <p class="text-center text-slate-400 text-xs py-8">Belum ada data.</p>
                <?php else: foreach ($recentMasuk as $r): ?>
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800"><?= sanitize($r['nama_barang']) ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5"><?= date('d M Y', strtotime($r['tanggal'])) ?></p>
                    </div>
                    <span class="text-sm font-bold text-blue-600">+<?= number_format($r['qty']) ?> <span class="text-xs text-slate-400 font-normal"><?= sanitize($r['satuan']) ?></span></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Barang Keluar Terakhir -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-800"><i class="fa-solid fa-arrow-up text-rose-500 mr-2"></i>Barang Keluar Terakhir</h3>
                <a href="<?= $sistem ?>/barangkeluar" class="text-xs text-sky-600 font-semibold hover:underline">Lihat semua</a>
            </div>
            <div class="divide-y divide-slate-50">
                <?php if (empty($recentKeluar)): ?>
                <p class="text-center text-slate-400 text-xs py-8">Belum ada data.</p>
                <?php else: foreach ($recentKeluar as $r): ?>
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800"><?= sanitize($r['nama_barang']) ?></p>
                        <p class="text-[11px] text-slate-400 mt-0.5"><?= date('d M Y', strtotime($r['tanggal'])) ?> · <?= sanitize($r['nama_outlet'] ?: '—') ?></p>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-bold text-rose-600">-<?= number_format($r['qty']) ?> <span class="text-xs text-slate-400 font-normal"><?= sanitize($r['satuan']) ?></span></span>
                        <p class="text-[10px] mt-0.5 <?= $r['status'] === 'Diterima' ? 'text-emerald-500' : 'text-amber-500' ?> font-semibold"><?= $r['status'] ?></p>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

</div>
