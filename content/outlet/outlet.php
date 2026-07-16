<?php
if (!canDo('outlet', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit;
}

global $conn, $sistem;

// Handle delete AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    if ($_POST['action'] === 'delete' && canDo('outlet', 'delete')) {
        $id = (int)$_POST['id'];
        try {
            // Cek apakah outlet punya transaksi
            $used = $conn->prepare("SELECT COUNT(*) FROM barang_keluar WHERE id_outlet = ?");
            $used->execute([$id]);
            if ($used->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Outlet tidak dapat dihapus karena memiliki riwayat transaksi!']);
            } else {
                $conn->prepare("DELETE FROM outlet WHERE id_outlet = ?")->execute([$id]);
                echo json_encode(['status' => 'success', 'message' => 'Outlet berhasil dihapus.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($_POST['action'] === 'toggle_status' && canDo('outlet', 'edit')) {
        $id = (int)$_POST['id'];
        $conn->prepare("UPDATE outlet SET is_active = !is_active WHERE id_outlet = ?")->execute([$id]);
        echo json_encode(['status' => 'success']);
    }
    exit;
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

// Hitung total data
try {
    $totalData = (int)$conn->query("SELECT COUNT(*) FROM outlet")->fetchColumn();
} catch (Exception $e) {
    $totalData = 0;
}

// Ambil data dengan pagination
$outletList = $conn->query("
    SELECT o.*,
        (SELECT COUNT(*) FROM barang_keluar bk WHERE bk.id_outlet = o.id_outlet) as total_pengiriman
    FROM outlet o
    ORDER BY o.nama_outlet ASC
    LIMIT $limit OFFSET $offset
")->fetchAll();
?>

<div class="fade-up space-y-5">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Master Outlet</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kelola data outlet/cabang penerima barang dari gudang pusat.</p>
        </div>
        <?php if (canDo('outlet', 'input')): ?>
        <a href="<?= $sistem ?>/outlet/i"
           class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-sm">
            <i class="fa-solid fa-plus text-xs"></i> Tambah Outlet
        </a>
        <?php endif; ?>
    </div>

    <!-- Flash Message -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm shadow-sm">
        <i class="fa-solid fa-circle-check text-emerald-500 flex-shrink-0"></i>
        <span><?= $_SESSION['flash_success'] ?></span>
    </div>
    <?php unset($_SESSION['flash_success']); endif; ?>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="relative w-full sm:w-80">
                <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="searchOutlet" placeholder="Cari nama outlet..."
                       class="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-slate-50">
            </div>
            <?php echo generateShowEntries($limit, 'outlet'); ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Nama Outlet</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Alamat / Lokasi</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="outletTableBody">
                    <?php if (empty($outletList)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-store text-3xl mb-2 block text-slate-200"></i>
                            Belum ada data outlet. Tambahkan outlet pertama!
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($outletList as $o): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors outlet-row" data-name="<?= strtolower($o['nama_outlet']) ?>">
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-400 font-mono"><?= $no++ ?></td>
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-bold text-slate-800"><?= sanitize($o['nama_outlet']) ?></p>
                            <p class="text-[10px] text-slate-400 mt-0.5"><?= $o['total_pengiriman'] ?> pengiriman diterima</p>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-500 max-w-xs truncate">
                            <?= sanitize($o['alamat'] ?: '—') ?>
                        </td>
                        <td class="px-5 py-3.5 text-center text-xs text-slate-400">
                            <?= date('d M Y', strtotime($o['created_at'])) ?>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <?php if ($o['is_active']): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold">
                                <i class="fa-solid fa-circle text-[6px]"></i> Aktif
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-500 border border-slate-200 text-xs font-semibold">
                                <i class="fa-solid fa-circle text-[6px]"></i> Non-Aktif
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if (canDo('outlet', 'edit')): ?>
                                <a href="<?= $sistem ?>/outlet/e/<?= $o['id_outlet'] ?>"
                                   class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center hover:bg-amber-100 transition-colors border border-amber-100" title="Edit">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (canDo('outlet', 'delete')): ?>
                                <button onclick="deleteOutlet(<?= $o['id_outlet'] ?>, '<?= sanitize($o['nama_outlet']) ?>')"
                                        class="w-8 h-8 rounded-lg bg-red-50 text-red-500 border border-red-100 hover:bg-red-100 flex items-center justify-center transition-colors" title="Hapus">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php echo generatePagination($totalData, $limit, $sistem . '/outlet', $page); ?>
    </div>
</div>

<script>
document.getElementById('searchOutlet').addEventListener('keyup', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.outlet-row').forEach(row => {
        row.style.display = row.getAttribute('data-name').includes(q) ? '' : 'none';
    });
});

function deleteOutlet(id, nama) {
    Swal.fire({
        title: 'Hapus Outlet?',
        html: `Apakah yakin ingin menghapus outlet <b>${nama}</b>?<br><span class='text-xs text-slate-500'>Outlet yang memiliki riwayat transaksi tidak dapat dihapus.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&id=${id}`
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Terhapus!', text: res.message, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
                }
            });
        }
    });
}
</script>