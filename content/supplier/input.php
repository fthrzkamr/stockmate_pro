<?php
if (!canDo('supplier', 'create')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk menambah supplier.</div>";
    exit;
}

global $conn, $sistem;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_supplier = trim($_POST['nama_supplier'] ?? '');
    $alamat        = trim($_POST['alamat'] ?? '');
    $telepon       = trim($_POST['telepon'] ?? '');

    if (!$nama_supplier) {
        $error = 'Nama Supplier wajib diisi.';
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO supplier (nama_supplier, alamat, telepon, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$nama_supplier, $alamat, $telepon]);
            
            $new_id = (int)$conn->lastInsertId();
            writeAuditLog('CREATE', 'supplier', $new_id, "Menambahkan supplier baru: $nama_supplier");
            
            $_SESSION['flash_success'] = "Supplier <b>$nama_supplier</b> berhasil ditambahkan.";
            echo "<script>window.location.href='$sistem/supplier';</script>";
            exit;
        } catch (Exception $e) {
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
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
            <h1 class="text-xl font-bold text-slate-800">Tambah Supplier</h1>
            <p class="text-slate-500 text-sm">Daftarkan pemasok/supplier baru.</p>
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
                <i class="fa-solid fa-truck text-sky-500 mr-2"></i>Informasi Supplier
            </p>
        </div>
        
        <div class="p-6 space-y-5">

            <!-- Nama Supplier -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nama Supplier <span class="text-red-500">*</span></label>
                <input type="text" name="nama_supplier" value="<?= htmlspecialchars($_POST['nama_supplier'] ?? '') ?>" required
                       placeholder="Contoh: PT Sumber Pangan Abadi"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>

            <!-- Telepon -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">No. Telepon / HP</label>
                <input type="text" name="telepon" value="<?= htmlspecialchars($_POST['telepon'] ?? '') ?>"
                       placeholder="Contoh: 08123456789"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>

            <!-- Alamat -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Alamat Lengkap</label>
                <textarea name="alamat" rows="3" placeholder="Tulis alamat kantor atau gudang supplier..."
                          class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
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
                <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Supplier
            </button>
        </div>
    </form>
</div>