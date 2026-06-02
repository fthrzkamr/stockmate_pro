<?php
if (!canDo('supplier', 'edit')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk mengubah data supplier.</div>";
    exit;
}

global $conn, $sistem;

$id_supplier = (int)($_GET['id'] ?? $_GET['keycode'] ?? 0);
if (!$id_supplier) {
    echo "<script>window.location.href='$sistem/supplier';</script>";
    exit;
}

// Ambil data supplier
try {
    $st = $conn->prepare("SELECT * FROM supplier WHERE id_supplier = ?");
    $st->execute([$id_supplier]);
    $supplier = $st->fetch();
} catch (Exception $e) {
    $supplier = null;
}

if (!$supplier) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Supplier tidak ditemukan.</div>";
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_supplier = trim($_POST['nama_supplier'] ?? '');
    $alamat        = trim($_POST['alamat'] ?? '');
    $telepon       = trim($_POST['telepon'] ?? '');
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    if (!$nama_supplier) {
        $error = 'Nama Supplier wajib diisi.';
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE supplier 
                SET nama_supplier = ?, alamat = ?, telepon = ?, is_active = ?
                WHERE id_supplier = ?
            ");
            $stmt->execute([$nama_supplier, $alamat, $telepon, $is_active, $id_supplier]);
            
            writeAuditLog('UPDATE', 'supplier', $id_supplier, "Memperbarui supplier: $nama_supplier (Status: " . ($is_active ? 'Aktif' : 'Nonaktif') . ")");
            
            $_SESSION['flash_success'] = "Supplier <b>$nama_supplier</b> berhasil diperbarui.";
            echo "<script>window.location.href='$sistem/supplier';</script>";
            exit;
        } catch (Exception $e) {
            $error = 'Gagal memperbarui data: ' . $e->getMessage();
        }
    }
}
?>

<div class="fade-up max-w-2xl mx-auto space-y-5">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/supplier" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Supplier</h1>
            <p class="text-slate-500 text-sm">Perbarui data pemasok <span class="font-semibold text-sky-600"><?= sanitize($supplier['nama_supplier']) ?></span>.</p>
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
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <p class="text-sm font-semibold text-slate-700">
                <i class="fa-solid fa-truck text-sky-500 mr-2"></i>Detail Supplier
            </p>
            <span class="text-xs text-slate-400 font-medium">ID: #<?= $supplier['id_supplier'] ?></span>
        </div>
        
        <div class="p-6 space-y-5">

            <!-- Nama Supplier -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nama Supplier <span class="text-red-500">*</span></label>
                <input type="text" name="nama_supplier" value="<?= htmlspecialchars($_POST['nama_supplier'] ?? $supplier['nama_supplier']) ?>" required
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>

            <!-- Telepon -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">No. Telepon / HP</label>
                <input type="text" name="telepon" value="<?= htmlspecialchars($_POST['telepon'] ?? $supplier['telepon']) ?>"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>

            <!-- Alamat -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Alamat Lengkap</label>
                <textarea name="alamat" rows="3"
                          class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all"><?= htmlspecialchars($_POST['alamat'] ?? $supplier['alamat']) ?></textarea>
            </div>

            <!-- Status Aktif (Soft Delete Toggle) -->
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-700">Status Aktif Supplier</p>
                    <p class="text-[10px] text-slate-400">Nonaktifkan supplier untuk menyembunyikannya dari form transaksi.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" <?= ($_POST['is_active'] ?? $supplier['is_active']) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                </label>
            </div>

        </div>

        <!-- Footer Buttons -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <a href="<?= $sistem ?>/supplier"
               class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Perbarui Supplier
            </button>
        </div>
    </form>
</div>