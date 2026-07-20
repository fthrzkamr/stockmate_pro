<?php
if (!canDo('barang', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat data barang.</div>";
    exit;
}

global $conn, $sistem;

// Handle AJAX Delete Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_barang') {
    header('Content-Type: application/json');
    if (!canDo('barang', 'delete') && !canDo('barang', 'edit')) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak memiliki hak akses untuk menghapus barang ini.']);
        exit;
    }
    
    $id_barang = (int)($_POST['id_barang'] ?? 0);
    if ($id_barang <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'ID Barang tidak valid.']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("SELECT nama_barang FROM barang WHERE id_barang = ?");
        $stmt->execute([$id_barang]);
        $nama = $stmt->fetchColumn();
        
        if ($nama) {
            $conn->prepare("DELETE FROM inventory WHERE id_barang = ?")->execute([$id_barang]);
            $conn->prepare("DELETE FROM barang WHERE id_barang = ?")->execute([$id_barang]);
            
            writeAuditLog('DELETE', 'barang', $id_barang, "Menghapus barang: $nama");
            
            echo json_encode([
                'status' => 'success', 
                'msg' => "Barang <b>$nama</b> berhasil dihapus dari sistem."
            ]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Barang tidak ditemukan.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// Pagination & Filter
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

$whereSql = "1=1";
$params = [];

if ($search) {
    $whereSql .= " AND (b.nama_barang LIKE ? OR b.barcode LIKE ? OR b.kategori LIKE ? OR s.nama_supplier LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Hitung total data untuk pagination
try {
    $stmtCount = $conn->prepare("
        SELECT COUNT(*) 
        FROM barang b 
        LEFT JOIN supplier s ON b.id_supplier = s.id_supplier 
        WHERE $whereSql
    ");
    $stmtCount->execute($params);
    $totalData = (int)$stmtCount->fetchColumn();
} catch (Exception $e) {
    $totalData = 0;
}

// Ambil data barang dengan pagination
try {
    $stmt = $conn->prepare("
        SELECT b.*, s.nama_supplier, t.nama_tipe 
        FROM barang b 
        LEFT JOIN supplier s ON b.id_supplier = s.id_supplier 
        LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
        WHERE $whereSql
        ORDER BY b.id_barang DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $barangList = $stmt->fetchAll();
} catch (Exception $e) {
    $barangList = [];
}
?>

<div class="fade-up space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Master Barang</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kelola data seluruh barang dan stok fisik di Gudang Pusat.</p>
        </div>
        <?php if (canDo('barang', 'create')): ?>
        <a href="<?= $sistem ?>/barang/i"
           class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm shadow-sky-100">
            <i class="fa-solid fa-plus text-xs"></i> Tambah Barang
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
            <form method="GET" id="filterForm" class="relative flex-1 max-w-sm flex items-center gap-2">
                <input type="hidden" name="menu" value="barang">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="limit" value="<?= $limit ?>">
                <div class="relative w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input id="searchBarang" name="search" type="text" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, barcode, atau kategori..."
                           class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                </div>
            </form>
            <?php echo generateShowEntries($limit, 'barang', urlencode($search)); ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblBarang">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Barang</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Barcode</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Info Barang</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kategori & Tipe</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($barangList)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-box-open text-3xl mb-2 block text-slate-200"></i>
                            Belum ada data barang terdaftar.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($barangList as $b): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors barang-row" 
                        data-search="<?= strtolower($b['nama_barang'].' '.$b['barcode'].' '.$b['kategori']) ?>">
                        
                        <!-- No. Urut -->
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>

                        <!-- Nama Barang -->
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center font-bold text-sm flex-shrink-0 border border-sky-100">
                                    <i class="fa-solid fa-box text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800"><?= sanitize($b['nama_barang']) ?></p>
                                </div>
                            </div>
                        </td>

                        <!-- Barcode -->
                        <td class="px-5 py-3.5 text-sm text-slate-600 font-mono">
                            <?= $b['barcode'] ? '<span class="bg-slate-100 px-2 py-0.5 rounded font-mono text-xs"><i class="fa-solid fa-barcode mr-1 text-[10px]"></i>'.$b['barcode'].'</span>' : '<span class="text-slate-400 text-xs italic">Tidak ada</span>' ?>
                        </td>

                        <!-- Info Barang -->
                        <td class="px-5 py-3.5 text-sm text-slate-600">
                            Satuan: <?= sanitize($b['satuan'] ?: 'Pcs') ?><br>
                            Supplier: <?= sanitize($b['nama_supplier'] ?? '—') ?>
                        </td>

                        <!-- Kategori & Tipe -->
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-semibold bg-indigo-50 text-indigo-600 border border-indigo-100">
                                <i class="fa-solid fa-tags"></i> <?= sanitize($b['kategori'] ?: '-') ?>
                            </span>
                            <div class="text-[10px] text-slate-400 mt-1">Tipe: <span class="font-medium text-slate-600"><?= sanitize($b['nama_tipe'] ?: 'Belum Diatur') ?></span></div>
                        </td>

                        <!-- Status Aktif (Soft Delete) -->
                        <td class="px-5 py-3.5">
                            <?php if ($b['is_active']): ?>
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
                                <?php if (canDo('barang', 'edit')): ?>
                                <a href="<?= $sistem ?>/barang/e/<?= $b['id_barang'] ?>"
                                   class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center hover:bg-amber-100 transition-colors border border-amber-100" title="Edit">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (canDo('barang', 'delete') || canDo('barang', 'edit')): ?>
                                <button onclick="hapusBarang(<?= $b['id_barang'] ?>, '<?= sanitize($b['nama_barang']) ?>')"
                                        class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition-colors border border-rose-100" 
                                        title="Hapus Barang">
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
        <?php echo generatePagination($totalData, $limit, $sistem . '/barang', $page); ?>
    </div>
</div>

<script>
// Hapus Barang
function hapusBarang(id, nama) {
    Swal.fire({
        title: 'Hapus Barang?',
        html: `Apakah Anda yakin ingin menghapus barang <b>${nama}</b> secara permanen?`,
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
        fd.append('action', 'delete_barang');
        fd.append('id_barang', id);
        
        fetch('<?= $sistem ?>/barang', { method: 'POST', body: fd })
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