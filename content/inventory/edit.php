<?php
if (!canDo('inventory', 'edit')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk mengubah inventory.</div>";
    exit;
}

global $conn, $sistem;

$id_inventory = (int)($_GET['id'] ?? 0);
if (!$id_inventory) {
    echo "<script>window.location.href='$sistem/inventory';</script>";
    exit;
}

// Ambil data
try {
    $st = $conn->prepare("
        SELECT i.*, b.nama_barang, b.barcode, b.satuan, b.kategori, t.nama_tipe
        FROM inventory i
        JOIN barang b ON i.id_barang = b.id_barang
        LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
        WHERE i.id_inventory = ?
    ");
    $st->execute([$id_inventory]);
    $inv = $st->fetch();
} catch (Exception $e) {
    $inv = null;
}

if (!$inv) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Data inventory tidak ditemukan.</div>";
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stok = (int)($_POST['stok'] ?? 0);

    try {
        $stmt = $conn->prepare("UPDATE inventory SET stok = ? WHERE id_inventory = ?");
        $stmt->execute([$stok, $id_inventory]);
        
        writeAuditLog('UPDATE', 'inventory', $id_inventory, "Memperbarui stok manual item: {$inv['nama_barang']} menjadi $stok");
        
        $_SESSION['flash_success'] = "Stok untuk <b>{$inv['nama_barang']}</b> berhasil diperbarui secara manual.";
        echo "<script>window.location.href='$sistem/inventory';</script>";
        exit;
    } catch (Exception $e) {
        $error = 'Gagal menyimpan data: ' . $e->getMessage();
    }
}
?>

<div class="fade-up max-w-xl mx-auto space-y-5">
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/inventory" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Stok Manual</h1>
            <p class="text-slate-500 text-sm mt-0.5">Ubah stok fisik gudang.</p>
        </div>
    </div>

    <!-- Alert Error -->
    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm shadow-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0 text-red-500"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <p class="text-sm font-semibold text-slate-700">
                <i class="fa-solid fa-boxes-stacked text-indigo-500 mr-2"></i>Informasi Penyimpanan
            </p>
        </div>
        
        <div class="p-6 space-y-6">
            
            <!-- Info Produk (Readonly) -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Item Barang</p>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-base font-bold text-slate-800"><?= sanitize($inv['nama_barang']) ?></p>
                        <p class="text-xs text-slate-500 font-mono mt-1">Barcode: <?= $inv['barcode'] ?: '-' ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500">Stok Sistem</p>
                        <p class="text-lg font-bold <?= $inv['stok'] > 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                            <?= number_format($inv['stok'], 0, ',', '.') ?> <span class="text-xs font-medium text-slate-500"><?= sanitize($inv['satuan']) ?></span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Penyesuaian Stok -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Penyesuaian Stok Gudang</label>
                <div class="relative">
                    <input type="number" name="stok" value="<?= htmlspecialchars($_POST['stok'] ?? $inv['stok']) ?>"
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all font-bold text-slate-800">
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400 uppercase">
                        <?= sanitize($inv['satuan']) ?>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400 mt-1.5 leading-relaxed">
                    Ubah angka ini hanya jika terjadi selisih fisik gudang (Stock Opname manual).
                </p>
            </div>
            
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700 flex gap-2">
                <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                <p>
                    <strong>Peringatan:</strong> Mengubah angka stok secara manual akan menimpa (override) kalkulasi otomatis dari transaksi barang masuk & keluar!
                </p>
            </div>
        </div>

        <!-- Footer Buttons -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <a href="<?= $sistem ?>/inventory"
               class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Stok
            </button>
        </div>
    </form>
</div>
