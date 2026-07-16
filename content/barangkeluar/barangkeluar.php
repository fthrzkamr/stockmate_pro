<?php
if (!canDo('barangkeluar', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat barang keluar.</div>";
    exit;
}

global $conn, $sistem;

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

// Hitung total data untuk pagination
try {
    $totalData = (int)$conn->query("SELECT COUNT(DISTINCT COALESCE(kode_transaksi, id_keluar)) FROM barang_keluar")->fetchColumn();
} catch (Exception $e) {
    $totalData = 0;
}

// Ambil data barang keluar (Grouping by transaksi) dengan pagination
try {
    $keluarList = $conn->query("
        SELECT COALESCE(bk.kode_transaksi, bk.id_keluar) as ref_trx, 
               MAX(bk.id_keluar) as id_keluar, bk.tanggal, bk.keterangan, 
               o.nama_outlet, u.nama as nama_admin,
               COUNT(bk.id_barang) as total_item,
               SUM(bk.qty) as total_qty,
               MAX(bk.status) as status_trx
        FROM barang_keluar bk
        LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
        LEFT JOIN users u ON bk.id_user = u.id_user
        GROUP BY ref_trx
        ORDER BY bk.tanggal DESC, id_keluar DESC
        LIMIT $limit OFFSET $offset
    ")->fetchAll();
} catch (Exception $e) {
    $keluarList = [];
}
?>

<div class="fade-up space-y-5">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Distribusi ke Outlet</h1>
            <p class="text-slate-500 text-sm mt-0.5">Daftar pengiriman barang keluar dari gudang pusat ke outlet.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if (canDo('barangkeluar', 'input')): ?>
            <a href="<?= $sistem ?>/barangkeluar/i" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                <i class="fa-solid fa-plus text-xs"></i> Buat Pengiriman
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="relative w-full sm:w-96">
                <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="searchInput" placeholder="Cari nama barang, outlet, atau tanggal..."
                       class="w-full pl-11 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-slate-50">
            </div>
            <?php echo generateShowEntries($limit, 'barangkeluar'); ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblKeluar">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Tgl / Tujuan</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Item</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Qty</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($keluarList)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-truck-fast text-3xl mb-2 block text-slate-200"></i>
                            Belum ada riwayat pengiriman barang ke outlet.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($keluarList as $row): 
                        $statusColor = 'amber';
                        $statusIcon = 'clock';
                        if ($row['status_trx'] === 'Diterima') { $statusColor = 'emerald'; $statusIcon = 'check-double'; }
                        if ($row['status_trx'] === 'Ditolak')  { $statusColor = 'rose'; $statusIcon = 'xmark'; }
                        if ($row['status_trx'] === 'Dikembalikan')  { $statusColor = 'slate'; $statusIcon = 'rotate-left'; }
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors bk-row" 
                        data-search="<?= strtolower($row['ref_trx'].' '.$row['nama_outlet'].' '.$row['tanggal']) ?>">
                        
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>

                        <td class="px-5 py-3.5">
                            <p class="text-sm font-bold text-slate-800"><?= date('d M Y', strtotime($row['tanggal'])) ?></p>
                            <p class="text-[11px] font-medium text-slate-500 mt-0.5">Ke: <span class="text-indigo-600 font-bold"><?= sanitize($row['nama_outlet'] ?: 'Tanpa Outlet') ?></span></p>
                        </td>

                        <td class="px-5 py-3.5 text-center">
                            <p class="text-sm font-semibold text-slate-800"><?= number_format($row['total_item']) ?> Jenis</p>
                        </td>

                        <td class="px-5 py-3.5 text-center">
                            <p class="text-sm font-bold text-rose-600">-<?= number_format($row['total_qty']) ?></p>
                        </td>

                        <td class="px-5 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-<?= $statusColor ?>-50 text-<?= $statusColor ?>-700 border border-<?= $statusColor ?>-200 text-xs font-semibold">
                                <i class="fa-solid fa-<?= $statusIcon ?>"></i> <?= sanitize($row['status_trx']) ?>
                            </span>
                        </td>

                        <td class="px-5 py-3.5 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if (canDo('barangkeluar', 'view')): ?>
                                <button type="button" onclick="openLgModal('<?= $sistem ?>/barangkeluar/v/<?= $row['ref_trx'] ?>')"
                                   class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center hover:bg-sky-100 transition-colors border border-sky-100" title="Detail Transaksi">
                                    <i class="fa-solid fa-circle-info text-xs"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (canDo('barangkeluar', 'delete') && !in_array($row['status_trx'], ['Diterima', 'Dikembalikan', 'Ditolak'])): ?>
                                <button onclick="cancelKeluar('<?= $row['ref_trx'] ?>', '<?= sanitize($row['nama_outlet']) ?>', <?= $row['total_qty'] ?>)"
                                        class="w-8 h-8 rounded-lg bg-red-50 text-red-500 border border-red-100 hover:bg-red-100 flex items-center justify-center transition-colors" title="Batalkan Pengiriman &amp; Kembalikan ke Gudang">
                                    <i class="fa-solid fa-rotate-left text-xs"></i>
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
        <?php echo generatePagination($totalData, $limit, $sistem . '/barangkeluar', $page); ?>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('.bk-row');
    rows.forEach(row => {
        if (row.getAttribute('data-search').includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Batalkan Transaksi Barang Keluar
function cancelKeluar(trxCode, outlet, qty) {
    Swal.fire({
        title: 'Batalkan Pengiriman?',
        html: `Apakah Anda yakin ingin membatalkan transaksi pengiriman ke <b>${outlet || 'Outlet'}</b> (Total: ${qty} qty)?<br><span class="text-xs text-rose-500 font-semibold">*Stok barang di Gudang Pusat akan dikembalikan.</span>`,
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
        fd.append('trx_code', trxCode);
        
        fetch('<?= $sistem ?>/barangkeluar/v/' + trxCode, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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