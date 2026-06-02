<?php
if (!canDo('outlet', 'input')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Akses ditolak.</div>";
    exit;
}

global $conn, $sistem;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = trim($_POST['nama_outlet'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if (!$nama) {
        $error = "Nama outlet tidak boleh kosong!";
    } else {
        // Cek duplikat
        $cek = $conn->prepare("SELECT id_outlet FROM outlet WHERE nama_outlet = ?");
        $cek->execute([$nama]);
        if ($cek->fetch()) {
            $error = "Outlet dengan nama <b>$nama</b> sudah terdaftar!";
        } else {
            $conn->prepare("INSERT INTO outlet (nama_outlet, alamat, is_active) VALUES (?, ?, 1)")
                 ->execute([$nama, $alamat ?: null]);
            $_SESSION['flash_success'] = "Outlet <b>$nama</b> berhasil ditambahkan!";
            echo "<script>window.location.href='$sistem/outlet';</script>"; exit;
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
            <h1 class="text-xl font-bold text-slate-800">Tambah Outlet</h1>
            <p class="text-slate-500 text-sm mt-0.5">Daftarkan outlet/cabang baru ke dalam sistem.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm shadow-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <p class="text-sm font-semibold text-slate-700">
                <i class="fa-solid fa-store text-indigo-500 mr-2"></i>Informasi Outlet
            </p>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">
                    Nama Outlet <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="nama_outlet" required autofocus
                       value="<?= htmlspecialchars($_POST['nama_outlet'] ?? '') ?>"
                       placeholder="Contoh: Outlet Steamboat Lt.2, Outlet Yakiniku..."
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">
                    Alamat / Keterangan Lokasi
                </label>
                <textarea name="alamat" rows="3"
                          placeholder="Lantai, gedung, ruangan, atau keterangan lokasi outlet..."
                          class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <a href="<?= $sistem ?>/outlet"
               class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">Batal</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-check text-xs"></i> Simpan Outlet
            </button>
        </div>
    </form>
</div>