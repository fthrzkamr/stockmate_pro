<?php
if (!canDo('supplier', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat data supplier.</div>";
    exit;
}

global $conn, $sistem;

// Handle AJAX Delete Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_supplier') {
    ob_clean();
    header('Content-Type: application/json');
    if (!canDo('supplier', 'delete') && !canDo('supplier', 'edit')) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak memiliki hak akses untuk menghapus supplier ini.']);
        exit;
    }
    
    $id_supplier = (int)($_POST['id_supplier'] ?? 0);
    if ($id_supplier <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'ID Supplier tidak valid.']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("SELECT nama_supplier FROM supplier WHERE id_supplier = ?");
        $stmt->execute([$id_supplier]);
        $nama = $stmt->fetchColumn();
        
        if ($nama) {
            $conn->prepare("DELETE FROM supplier WHERE id_supplier = ?")->execute([$id_supplier]);
            writeAuditLog('DELETE', 'supplier', $id_supplier, "Menghapus supplier: $nama");
            
            echo json_encode([
                'status' => 'success', 
                'msg' => "Supplier <b>$nama</b> berhasil dihapus dari sistem."
            ]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Supplier tidak ditemukan.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

// Hitung total data untuk pagination
try {
    $totalData = (int)$conn->query("SELECT COUNT(*) FROM supplier")->fetchColumn();
} catch (Exception $e) {
    $totalData = 0;
}

// Ambil data supplier dengan pagination
try {
    $suppliers = $conn->query("SELECT * FROM supplier ORDER BY nama_supplier ASC LIMIT $limit OFFSET $offset")->fetchAll();
} catch (Exception $e) {
    $suppliers = [];
}
?>

<div class="fade-up space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Daftar Supplier</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kelola pemasok penyedia barang/bahan baku utama.</p>
        </div>
        <?php if (canDo('supplier', 'create')): ?>
        <a href="<?= $sistem ?>/supplier/i"
           class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm shadow-sky-100">
            <i class="fa-solid fa-plus text-xs"></i> Tambah Supplier
        </a>
        <?php endif; ?>
    </div>

    <!-- Flash Message -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div id="flashMsg" class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-check text-emerald-500 flex-shrink-0"></i>
        <span><?= $_SESSION['flash_success'] ?></span>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        
        <!-- Search and Filter Bar -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/20 gap-3">
            <div class="relative flex-1 max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input id="searchSupplier" type="text" placeholder="Cari nama, alamat, atau telepon..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
            </div>
            <?php echo generateShowEntries($limit, 'supplier'); ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblSupplier">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Telepon</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Alamat</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-truck text-3xl mb-2 block text-slate-200"></i>
                            Belum ada data supplier terdaftar.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($suppliers as $s): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors supplier-row" 
                        data-search="<?= strtolower($s['nama_supplier'].' '.$s['alamat'].' '.$s['telepon']) ?>">
                        
                        <!-- No. Urut -->
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>

                        <!-- Nama Supplier -->
                        <td class="px-5 py-3.5 font-semibold text-slate-800 text-sm">
                            <?= sanitize($s['nama_supplier']) ?>
                        </td>

                        <!-- Telepon -->
                        <td class="px-5 py-3.5 text-sm text-slate-600 font-mono">
                            <?= sanitize($s['telepon'] ?: '—') ?>
                        </td>

                        <!-- Alamat -->
                        <td class="px-5 py-3.5 text-sm text-slate-500 max-w-xs truncate">
                            <?= sanitize($s['alamat'] ?: '—') ?>
                        </td>

                        <!-- Status Aktif (Soft Delete) -->
                        <td class="px-5 py-3.5">
                            <?php if ($s['is_active']): ?>
                                <span class="inline-flex items-center gap-1 text-emerald-700 text-xs font-semibold bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-slate-500 text-xs font-semibold bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Nonaktif
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-2">
                                <?php if (canDo('supplier', 'edit')): ?>
                                <a href="<?= $sistem ?>/supplier/e/<?= $s['id_supplier'] ?>"
                                   class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center hover:bg-amber-100 transition-colors border border-amber-100" title="Edit">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (canDo('supplier', 'delete') || canDo('supplier', 'edit')): ?>
                                <button onclick="hapusSupplier(<?= $s['id_supplier'] ?>, '<?= sanitize($s['nama_supplier']) ?>')"
                                        class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition-colors border border-rose-100" 
                                        title="Hapus Supplier">
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
        
        <!-- Pagination -->
        <?php echo generatePagination($totalData, $limit, $sistem . '/supplier', $page, 'supplier', ''); ?>
    </div>
</div>

<script>
// Pencarian Client-side
document.getElementById('searchSupplier')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.supplier-row').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
});

// Hapus Supplier
function hapusSupplier(id, nama) {
    Swal.fire({
        title: 'Hapus Supplier?',
        html: `Apakah Anda yakin ingin menghapus supplier <b>${nama}</b> secara permanen?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (!result.isConfirmed) return;
        
        Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('action', 'delete_supplier');
        fd.append('id_supplier', id);
        
        fetch('<?= $sistem ?>/supplier', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', html: res.msg, timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi gagal atau sesi habis.' }));
    });
}
</script>