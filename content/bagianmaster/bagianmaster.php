<?php
requireAdmin();
global $conn, $sistem;

// Handle AJAX Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id_bagian'] ?? 0);
    try {
        // Cek apakah masih dipakai user
        $cek = $conn->prepare("SELECT COUNT(*) FROM users WHERE id_bagian = ?");
        $cek->execute([$id]);
        if ($cek->fetchColumn() > 0) {
            echo json_encode(['status'=>'error','msg'=>'Bagian masih digunakan oleh user, tidak bisa dihapus.']);
            exit;
        }
        $conn->prepare("DELETE FROM bagian WHERE id_bagian = ?")->execute([$id]);
        echo json_encode(['status'=>'success','msg'=>'Bagian berhasil dihapus.']);
    } catch(Exception $e) {
        echo json_encode(['status'=>'error','msg'=>'Gagal: '.$e->getMessage()]);
    }
    exit;
}

// Ambil semua bagian + jumlah user
try {
    $list = $conn->query("
        SELECT b.*, COUNT(u.id_user) AS jml_user
        FROM bagian b
        LEFT JOIN users u ON u.id_bagian = b.id_bagian
        GROUP BY b.id_bagian
        ORDER BY b.nama_bagian ASC
    ")->fetchAll();
} catch(Exception $e) { $list = []; }
?>

<div class="fade-up space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Master Bagian</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kelola data departemen / divisi perusahaan.</p>
        </div>
        <a href="<?= $sistem ?>/bagianmaster/i"
           class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm">
            <i class="fa-solid fa-plus text-xs"></i> Tambah Bagian
        </a>
    </div>

    <!-- Flash -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-check flex-shrink-0"></i>
        <span><?= $_SESSION['flash_success'] ?></span>
    </div>
    <?php unset($_SESSION['flash_success']); endif; ?>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-10">#</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Nama Bagian</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Jumlah User</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-building text-3xl block mb-2 text-slate-200"></i>
                            Belum ada data bagian.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($list as $no => $b): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-3.5 text-sm text-slate-400 font-medium"><?= $no + 1 ?></td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-building text-sm"></i>
                                </div>
                                <span class="text-sm font-semibold text-slate-800"><?= sanitize($b['nama_bagian']) ?></span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg
                                         <?= $b['jml_user'] > 0 ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' ?>">
                                <i class="fa-solid fa-users text-[10px]"></i>
                                <?= $b['jml_user'] ?> user
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-2">
                                <a href="<?= $sistem ?>/bagianmaster/edit?id=<?= $b['id_bagian'] ?>"
                                   class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center hover:bg-amber-100 transition-colors" title="Edit">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </a>
                                <?php if ($b['jml_user'] == 0): ?>
                                <button onclick="hapusBagian(<?= $b['id_bagian'] ?>, '<?= sanitize($b['nama_bagian']) ?>')"
                                        class="w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition-colors" title="Hapus">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                                <?php else: ?>
                                <span class="w-8 h-8 rounded-lg bg-slate-50 text-slate-300 flex items-center justify-center cursor-not-allowed" title="Sedang dipakai user">
                                    <i class="fa-solid fa-lock text-xs"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function hapusBagian(id, nama) {
    Swal.fire({
        title: 'Hapus Bagian?',
        html: `Bagian <b>${nama}</b> akan dihapus permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id_bagian', id);
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                }
            });
    });
}
</script>
