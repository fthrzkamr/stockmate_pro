<?php
if (!canDo('barangmasuk', 'view')) {
    echo "<div class='p-4 text-red-650 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat daftar barang masuk.</div>";
    exit;
}

global $conn, $sistem;

// Fetch history of barang masuk
try {
    $list = $conn->query("
        SELECT bm.*, b.nama_barang, b.barcode, b.satuan, s.nama_supplier, u.nama as operator
        FROM barang_masuk bm
        JOIN barang b ON bm.id_barang = b.id_barang
        LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
        LEFT JOIN users u ON bm.id_user = u.id_user
        ORDER BY bm.tanggal DESC, bm.id_masuk DESC
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
        <div class="flex items-center px-5 py-4 border-b border-slate-100 bg-slate-50/20">
            <div class="relative flex-1 max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input id="searchMasuk" type="text" placeholder="Cari nama barang, barcode, atau supplier..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblMasuk">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Barang</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Qty</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Keterangan</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Operator</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
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
                    <?php $no = 1; foreach ($list as $r): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors masuk-row" 
                        data-search="<?= strtolower($r['nama_barang'].' '.$r['barcode'].' '.$r['nama_supplier'].' '.$r['keterangan']) ?>">
                        
                        <!-- No -->
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>
                        
                        <!-- Tanggal -->
                        <td class="px-5 py-3.5 text-sm text-slate-600 font-medium">
                            <?= date('d M Y', strtotime($r['tanggal'])) ?>
                        </td>

                        <!-- Barang -->
                        <td class="px-5 py-3.5">
                            <span class="text-sm font-semibold text-slate-800 block"><?= sanitize($r['nama_barang']) ?></span>
                            <?php if ($r['barcode']): ?>
                            <span class="bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-mono text-[10px] mt-0.5 inline-block">
                                <i class="fa-solid fa-barcode mr-1 text-[9px]"></i><?= $r['barcode'] ?>
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- Supplier -->
                        <td class="px-5 py-3.5 text-sm text-slate-600">
                            <?= sanitize($r['nama_supplier'] ?: '—') ?>
                        </td>

                        <!-- Qty -->
                        <td class="px-5 py-3.5 text-sm font-bold text-slate-800 text-right">
                            <?= number_format($r['qty']) ?> <span class="text-slate-400 text-xs font-normal"><?= sanitize($r['satuan'] ?: 'Pcs') ?></span>
                        </td>

                        <!-- Keterangan -->
                        <td class="px-5 py-3.5 text-xs text-slate-500 max-w-xs truncate" title="<?= sanitize($r['keterangan']) ?>">
                            <?= sanitize($r['keterangan'] ?: '—') ?>
                        </td>

                        <!-- Operator -->
                        <td class="px-5 py-3.5 text-xs text-slate-600 font-medium">
                            <?= sanitize($r['operator'] ?: 'System') ?>
                        </td>

                        <!-- Aksi -->
                        <td class="px-5 py-3.5 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="<?= $sistem ?>/barangmasuk/v/<?= $r['id_masuk'] ?>"
                                   class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center hover:bg-sky-100 transition-colors border border-sky-100" title="Detail">
                                    <i class="fa-solid fa-circle-info text-xs"></i>
                                </a>
                                <?php if (canDo('barangmasuk', 'delete')): ?>
                                <button onclick="cancelMasuk(<?= $r['id_masuk'] ?>, '<?= sanitize($r['nama_barang']) ?>', <?= $r['qty'] ?>)"
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
        fd.append('id_masuk', id);
        
        fetch('<?= $sistem ?>/barangmasuk/v/' + id, { method: 'POST', body: fd })
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