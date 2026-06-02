<?php
if (!canDo('barang', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat detail barang.</div>";
    exit;
}

global $conn, $sistem;

$id_barang = (int)($_GET['id'] ?? $_GET['keycode'] ?? 0);
if (!$id_barang) {
    echo "<script>window.location.href='$sistem/barang';</script>";
    exit;
}

try {
    $st = $conn->prepare("
        SELECT b.*, s.nama_supplier, s.telepon as supplier_telp,
               (COALESCE((SELECT SUM(qty) FROM barang_masuk WHERE id_barang = b.id_barang), 0) - 
                COALESCE((SELECT SUM(qty) FROM barang_keluar WHERE id_barang = b.id_barang), 0)) as stok
        FROM barang b
        LEFT JOIN supplier s ON b.id_supplier = s.id_supplier
        WHERE b.id_barang = ?
    ");
    $st->execute([$id_barang]);
    $b = $st->fetch();
} catch (Exception $e) {
    $b = null;
}

if (!$b) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Barang tidak ditemukan.</div>";
    exit;
}
?>

<div class="fade-up max-w-xl mx-auto space-y-5">
    
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/barang" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Detail Barang</h1>
            <p class="text-slate-500 text-sm">Detail data fisik dan supplier barang.</p>
        </div>
    </div>

    <!-- Details Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-4">
            <div class="w-12 h-12 bg-sky-50 border border-sky-100 rounded-2xl flex items-center justify-center text-sky-700 text-xl font-bold flex-shrink-0">
                <i class="fa-solid fa-box"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-800"><?= sanitize($b['nama_barang']) ?></h3>
                <span class="text-xs text-slate-400 font-mono">Barcode: <?= $b['barcode'] ?: 'Tidak ada' ?></span>
            </div>
        </div>

        <div class="p-6 space-y-4 divide-y divide-slate-100">
            
            <!-- Spesifikasi -->
            <div class="grid grid-cols-2 gap-4 pb-4">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-1">Kategori</span>
                    <span class="text-sm font-semibold text-slate-700"><?= sanitize($b['kategori']) ?></span>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-1">Satuan</span>
                    <span class="text-sm font-semibold text-slate-700"><?= sanitize($b['satuan']) ?></span>
                </div>
            </div>

            <!-- Stok info -->
            <div class="grid grid-cols-2 gap-4 py-4">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-1">Stok Saat Ini (Gudang)</span>
                    <span class="text-base font-bold text-slate-850"><?= number_format($b['stok']) ?> <?= sanitize($b['satuan']) ?></span>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-1">Batas Minimum Stok</span>
                    <span class="text-sm font-bold text-slate-700"><?= number_format($b['min_stok']) ?> <?= sanitize($b['satuan']) ?></span>
                </div>
            </div>

            <!-- Status Aktif -->
            <div class="py-4 flex justify-between items-center">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-0.5">Status Barang</span>
                    <span class="text-xs text-slate-500">Ketersediaan barang dalam sistem</span>
                </div>
                <?php if ($b['is_active']): ?>
                    <span class="inline-flex items-center gap-1.5 text-emerald-700 text-xs font-semibold bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 text-slate-500 text-xs font-semibold bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Nonaktif
                    </span>
                <?php endif; ?>
            </div>

            <!-- Supplier -->
            <div class="pt-4 space-y-2">
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Pemasok / Supplier Utama</span>
                <?php if ($b['id_supplier']): ?>
                <div class="bg-slate-50 border border-slate-150 rounded-xl p-3.5 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-700"><?= sanitize($b['nama_supplier']) ?></p>
                        <p class="text-xs text-slate-400">Telp: <?= sanitize($b['supplier_telp'] ?: 'Tidak ada nomor telepon') ?></p>
                    </div>
                    <i class="fa-solid fa-truck text-slate-300 text-xl"></i>
                </div>
                <?php else: ?>
                <p class="text-xs text-slate-450 italic">Barang ini belum dikaitkan dengan supplier utama.</p>
                <?php endif; ?>
            </div>

        </div>

        <!-- Footer Edit link -->
        <?php if (canDo('barang', 'edit')): ?>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <a href="<?= $sistem ?>/barang/e/<?= $b['id_barang'] ?>"
               class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-5 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm shadow-amber-100">
                <i class="fa-solid fa-pen-to-square text-xs"></i> Edit Barang
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>