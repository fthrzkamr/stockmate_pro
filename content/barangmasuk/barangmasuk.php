<?php
if (!canDo('barangmasuk', 'view')) {
    echo "<div class='p-4 text-red-650 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat daftar barang masuk.</div>";
    exit;
}

global $conn, $sistem;

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

// Hitung total data untuk pagination
try {
    $totalData = (int)$conn->query("SELECT COUNT(DISTINCT COALESCE(kode_transaksi, id_masuk)) FROM barang_masuk")->fetchColumn();
} catch (Exception $e) {
    $totalData = 0;
}

// Fetch history of barang masuk dengan pagination (Group by trx)
try {
    $list = $conn->query("
        SELECT COALESCE(bm.kode_transaksi, bm.id_masuk) as ref_trx, 
               MAX(bm.id_masuk) as id_masuk, bm.tanggal, bm.keterangan, bm.supplier_lainnya, 
               s.nama_supplier, u.nama as operator,
               COUNT(bm.id_barang) as total_item, 
               SUM(bm.qty) as total_qty,
               MAX(bm.status) as status_trx
        FROM barang_masuk bm
        LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
        LEFT JOIN users u ON bm.id_user = u.id_user
        GROUP BY ref_trx
        ORDER BY bm.tanggal DESC, id_masuk DESC
        LIMIT $limit OFFSET $offset
    ")->fetchAll();
} catch (Exception $e) {
    $list = [];
}
?>

<div class="fade-up space-y-5">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Barang Masuk (Penerimaan)</h1>
            <p class="text-slate-500 text-sm mt-0.5">Riwayat penerimaan stok barang dari supplier ke Gudang Pusat.</p>
        </div>
        <?php if (canDo('barangmasuk', 'create')): ?>
        <a href="<?= $sistem ?>/barangmasuk/i"
           class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm shadow-sky-100">
            <i class="fa-solid fa-plus text-xs"></i> Input Barang Masuk
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
        
        <!-- Search Bar -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/20 gap-3">
            <div class="relative flex-1 max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input id="searchMasuk" type="text" placeholder="Cari nama barang, barcode, atau supplier..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
            </div>
            <?php echo generateShowEntries($limit, 'barangmasuk'); ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblMasuk">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Item</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Qty</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider w-24">Status</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Keterangan</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-truck-ramp-box text-3xl mb-2 block text-slate-200"></i>
                            Belum ada riwayat transaksi barang masuk.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($list as $r): 
                        $statusText = $r['status_trx'] ?: 'Diterima';
                        $statusColor = $statusText === 'Dibatalkan' ? 'rose' : 'emerald';
                        $statusIcon = $statusText === 'Dibatalkan' ? 'xmark' : 'check';
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors masuk-row" 
                        data-search="<?= strtolower($r['ref_trx'].' '.$r['nama_supplier'].' '.$r['keterangan']) ?>">
                        
                        <!-- No -->
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>
                        
                        <!-- Tanggal -->
                        <td class="px-5 py-3.5 text-sm text-slate-600 font-medium">
                            <?= date('d M Y', strtotime($r['tanggal'])) ?>
                        </td>

                        <!-- Supplier -->
                        <td class="px-5 py-3.5 text-sm font-semibold text-slate-800">
                            <?= sanitize($r['nama_supplier'] ?: ($r['supplier_lainnya'] ?: 'Tanpa Supplier')) ?>
                        </td>

                        <!-- Total Item -->
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-800">
                            <?= number_format($r['total_item']) ?> Jenis
                        </td>

                        <!-- Total Qty -->
                        <td class="px-5 py-3.5 text-center text-sm font-bold text-sky-600">
                            +<?= number_format($r['total_qty']) ?>
                        </td>

                        <!-- Status -->
                        <td class="px-5 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-<?= $statusColor ?>-50 text-<?= $statusColor ?>-700 border border-<?= $statusColor ?>-200 text-xs font-semibold">
                                <i class="fa-solid fa-<?= $statusIcon ?>"></i> <?= sanitize($statusText) ?>
                            </span>
                        </td>

                        <!-- Keterangan -->
                        <td class="px-5 py-3.5 text-xs text-slate-500 max-w-xs truncate" title="<?= sanitize($r['keterangan']) ?>">
                            <?= sanitize($r['keterangan'] ?: '—') ?>
                        </td>

                        <!-- Aksi -->
                        <td class="px-5 py-3.5 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if (canDo('barangmasuk', 'view')): ?>
                                <button type="button" onclick="openLgModal('<?= $sistem ?>/barangmasuk/v/<?= $r['ref_trx'] ?>')"
                                   class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center hover:bg-sky-100 transition-colors border border-sky-100" title="Detail Transaksi">
                                     <i class="fa-solid fa-circle-info text-xs"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (canDo('barangmasuk', 'delete') && $statusText !== 'Dibatalkan'): ?>
                                <button onclick="cancelMasuk('<?= $r['ref_trx'] ?>', '<?= sanitize($r['nama_supplier'] ?: 'Supplier') ?>', <?= $r['total_qty'] ?>)"
                                        class="w-8 h-8 rounded-lg bg-red-50 text-red-500 border border-red-100 hover:bg-red-100 flex items-center justify-center transition-colors" title="Batalkan Transaksi">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
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
        <?php echo generatePagination($totalData, $limit, $sistem . '/barangmasuk', $page); ?>
    </div>
</div>

<script>
// Pencarian Client-side
document.getElementById('searchMasuk')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.masuk-row').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
});

// Batalkan Transaksi Barang Masuk (Kurangi kembali stok & hapus data masuk)
function cancelMasuk(id, nama, qty) {
    Swal.fire({
        title: 'Batalkan Penerimaan Barang?',
        html: `Apakah Anda yakin ingin membatalkan penerimaan <b>${nama}</b> sebanyak <b>${qty} pcs</b>?<br><span class="text-xs text-rose-500 font-semibold">*Stok barang di Gudang Pusat akan dikurangi kembali.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Batalkan!',
        cancelButtonText: 'Kembali'
    }).then(result => {
        if (!result.isConfirmed) return;
        
        Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('action', 'cancel_transaction');
        fd.append('trx_code', id);
        
        fetch('<?= $sistem ?>/barangmasuk/v/' + id, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil Dibatalkan!', text: res.msg, timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi gagal atau sesi habis.' }));
    });
}
</script>