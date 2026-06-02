<?php
if (!canDo('barangkeluar', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat barang keluar.</div>";
    exit;
}

global $conn, $sistem;

try {
    $keluarList = $conn->query("
        SELECT bk.*, b.nama_barang, b.barcode, b.satuan, o.nama_outlet, u.nama as nama_admin
        FROM barang_keluar bk
        JOIN barang b ON bk.id_barang = b.id_barang
        LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
        LEFT JOIN users u ON bk.id_user = u.id_user
        ORDER BY bk.id_keluar DESC
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
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblKeluar">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Tgl / Tujuan</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Item Barang</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">Jumlah Kirim</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($keluarList)): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-truck-fast text-3xl mb-2 block text-slate-200"></i>
                            Belum ada riwayat pengiriman barang ke outlet.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = 1; foreach ($keluarList as $row): 
                        $statusColor = $row['status'] === 'Diterima' ? 'emerald' : 'amber';
                        $statusIcon  = $row['status'] === 'Diterima' ? 'check-double' : 'clock';
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors bk-row" 
                        data-search="<?= strtolower($row['nama_barang'].' '.$row['nama_outlet'].' '.$row['tanggal']) ?>">
                        
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>

                        <td class="px-5 py-3.5">
                            <p class="text-sm font-bold text-slate-800"><?= date('d M Y', strtotime($row['tanggal'])) ?></p>
                            <p class="text-[11px] font-medium text-slate-500 mt-0.5">Ke: <span class="text-indigo-600 font-bold"><?= sanitize($row['nama_outlet'] ?: 'Tanpa Outlet') ?></span></p>
                        </td>

                        <td class="px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800"><?= sanitize($row['nama_barang']) ?></p>
                            <?php if ($row['barcode']): ?>
                            <span class="bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-mono text-[10px] mt-0.5 inline-block">
                                <i class="fa-solid fa-barcode mr-1 text-[9px]"></i><?= $row['barcode'] ?>
                            </span>
                            <?php endif; ?>
                        </td>

                        <td class="px-5 py-3.5 text-right">
                            <p class="text-sm font-bold text-rose-600">-<?= number_format($row['qty'], 0, ',', '.') ?></p>
                            <p class="text-[10px] text-slate-400 font-medium uppercase"><?= sanitize($row['satuan']) ?></p>
                        </td>

                        <td class="px-5 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-<?= $statusColor ?>-50 text-<?= $statusColor ?>-700 border border-<?= $statusColor ?>-200 text-xs font-semibold">
                                <i class="fa-solid fa-<?= $statusIcon ?>"></i> <?= sanitize($row['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
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
</script>