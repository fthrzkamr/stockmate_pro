<?php
if (!canDo('outlet', 'edit')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Akses ditolak.</div>";
    exit;
}

global $conn, $sistem;

$id = (int)($_GET['id'] ?? $_GET['keycode'] ?? 0);
if (!$id) {
    echo "<script>window.location.href='$sistem/outlet';</script>";
    exit;
}

try {
    $st = $conn->prepare("SELECT * FROM outlet WHERE id_outlet = ?");
    $st->execute([$id]);
    $outlet = $st->fetch();
} catch (Exception $e) {
    $outlet = null;
}

if (!$outlet) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Outlet tidak ditemukan.</div>";
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = trim($_POST['nama_outlet'] ?? '');
    $alamat    = trim($_POST['alamat'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$nama) {
        $error = "Nama outlet tidak boleh kosong!";
    } else {
        try {
            // Cek duplikat (kecuali diri sendiri)
            $cek = $conn->prepare("SELECT id_outlet FROM outlet WHERE nama_outlet = ? AND id_outlet != ?");
            $cek->execute([$nama, $id]);
            if ($cek->fetch()) {
                $error = "Nama outlet <b>" . sanitize($nama) . "</b> sudah digunakan outlet lain!";
            } else {
                $conn->prepare("UPDATE outlet SET nama_outlet=?, alamat=?, is_active=? WHERE id_outlet=?")
                     ->execute([$nama, $alamat ?: null, $is_active, $id]);
                
                writeAuditLog('UPDATE', 'outlet', $id, "Memperbarui outlet: $nama (Status: " . ($is_active ? 'Aktif' : 'Nonaktif') . ")");
                
                $_SESSION['flash_success'] = "Data outlet <b>" . sanitize($nama) . "</b> berhasil diperbarui!";
                echo "<script>window.location.href='$sistem/outlet';</script>"; 
                exit;
            }
        } catch (Exception $e) {
            $error = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}
?>

<div class="fade-up max-w-lg mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/outlet"
           class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Outlet</h1>
            <p class="text-slate-500 text-sm mt-0.5">Perbarui informasi outlet cabang <span class="font-semibold text-indigo-600"><?= sanitize($outlet['nama_outlet']) ?></span>.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm shadow-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0 text-red-500"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <p class="text-sm font-semibold text-slate-700">
                <i class="fa-solid fa-store text-indigo-500 mr-2"></i>Edit: <?= sanitize($outlet['nama_outlet']) ?>
            </p>
            <span class="text-xs text-slate-400 font-medium">ID: #<?= $outlet['id_outlet'] ?></span>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">
                    Nama Outlet <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="nama_outlet" required
                       value="<?= htmlspecialchars($_POST['nama_outlet'] ?? $outlet['nama_outlet']) ?>"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">
                    Alamat / Keterangan Lokasi
                </label>
                <textarea name="alamat" rows="3"
                          class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all"><?= htmlspecialchars($_POST['alamat'] ?? $outlet['alamat']) ?></textarea>
            </div>
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-700">Status Aktif Outlet</p>
                    <p class="text-[10px] text-slate-400">Non-aktifkan jika outlet sementara tidak beroperasi.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" <?= ($_POST['is_active'] ?? $outlet['is_active']) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <a href="<?= $sistem ?>/outlet"
               class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">Batal</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>