<?php
requireAdmin();
global $conn, $sistem;

// Handle AJAX POST Delete (Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id_to_delete = (int)($_POST['id_user'] ?? 0);
    if ($id_to_delete <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'ID User tidak valid.']);
        exit;
    }
    if ($id_to_delete === (int)($_SESSION['user_id'] ?? 0)) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak bisa menonaktifkan akun Anda sendiri!']);
        exit;
    }
    try {
        $uInfo = $conn->prepare("SELECT nama, username FROM users WHERE id_user = ?");
        $uInfo->execute([$id_to_delete]);
        $usr = $uInfo->fetch();
        if ($usr) {
            // Soft delete: update is_active = 0
            $conn->prepare("UPDATE users SET is_active = 0 WHERE id_user = ?")->execute([$id_to_delete]);
            // Tulis ke audit log
            writeAuditLog('DELETE', 'users', $id_to_delete, "Menonaktifkan (Soft Delete) user: {$usr['nama']} (@{$usr['username']})");
            echo json_encode(['status' => 'success', 'msg' => "User <b>{$usr['nama']}</b> berhasil dinonaktifkan."]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'User tidak ditemukan.']);
        }
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// Ambil data users + bagian + role
try {
    $users = $conn->query("
        SELECT u.id_user, u.nama, u.username, r.nama_role, u.is_active, b.nama_bagian
        FROM users u
        LEFT JOIN bagian b ON u.id_bagian = b.id_bagian
        LEFT JOIN roles r ON u.id_role = r.id_role
        ORDER BY u.nama ASC
    ")->fetchAll();
} catch(Exception $e) { $users = []; }
?>

<div class="fade-up space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Data User</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kelola akun pengguna sistem.</p>
        </div>
        <a href="<?= $sistem ?>/administrator/i"
           class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm shadow-sky-100">
            <i class="fa-solid fa-plus text-xs"></i> Tambah User
        </a>
    </div>

    <!-- Flash Message -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div id="flashMsg" class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-check flex-shrink-0"></i>
        <span><?= $_SESSION['flash_success'] ?></span>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- Search Bar -->
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-100">
            <div class="relative flex-1 max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input id="searchUser" type="text" placeholder="Cari nama atau username..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-slate-50">
            </div>
            <span class="text-slate-500 text-xs font-medium"><?= count($users) ?> user</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblUser">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">User</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Role</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Bagian</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="tblBody">
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-users text-3xl mb-2 block text-slate-200"></i>
                            Belum ada data user.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-slate-50 transition-colors user-row" data-search="<?= strtolower($u['nama'].' '.$u['username']) ?>">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center font-bold text-sm flex-shrink-0">
                                    <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800"><?= sanitize($u['nama']) ?></p>
                                    <p class="text-xs text-slate-400">@<?= sanitize($u['username']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase
                                <?= (int)($u['id_role'] ?? 0) === 1 ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700' ?>">
                                <?= sanitize($u['nama_role'] ?? '-') ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-600">
                            <?= sanitize($u['nama_bagian'] ?? '-') ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <?php if ($u['is_active']): ?>
                                <span class="inline-flex items-center gap-1.5 text-emerald-700 text-xs font-semibold bg-emerald-50 px-2.5 py-1 rounded-lg">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 text-slate-500 text-xs font-semibold bg-slate-100 px-2.5 py-1 rounded-lg">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Nonaktif
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-2">
                                <a href="<?= $sistem ?>/administrator/edit?id=<?= $u['id_user'] ?>"
                                   class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center hover:bg-amber-100 transition-colors" title="Edit User">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </a>
                                <?php if ($u['id_user'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                <button onclick="hapusUser(<?= $u['id_user'] ?>, '<?= sanitize($u['nama']) ?>')"
                                        class="w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition-colors" title="Hapus">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
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
// Search filter
document.getElementById('searchUser')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.user-row').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
});

// Hapus user dengan SweetAlert2
function hapusUser(id, nama) {
    Swal.fire({
        title: 'Hapus User?',
        html: `Akun <b>${nama}</b> akan dihapus beserta seluruh hak aksesnya.<br>Tindakan ini tidak bisa dibatalkan!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        borderRadius: '12px'
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id_user', id);
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