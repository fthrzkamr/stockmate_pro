<?php
if (!canDo('stokoutlet', 'view')) exit;
global $conn;

$id_outlet = $_GET['id'] ?? $_GET['keycode'] ?? '';
if (!$id_outlet) exit;

try {
    // Get Outlet Info
    $stOutlet = $conn->prepare("SELECT * FROM outlet WHERE id_outlet = ?");
    $stOutlet->execute([$id_outlet]);
    $outlet = $stOutlet->fetch();

    if (!$outlet) {
        echo "<div class='p-8 text-center text-rose-500 font-medium'><i class='fa-solid fa-triangle-exclamation text-2xl mb-2 block'></i>Outlet tidak ditemukan.</div>";
        exit;
    }

    // Get Stock Data
    $st = $conn->prepare("
        SELECT so.*, b.nama_barang, b.barcode, b.kategori, b.satuan, b.min_stok 
        FROM stok_outlet so
        JOIN barang b ON so.id_barang = b.id_barang
        WHERE so.id_outlet = ?
        ORDER BY b.nama_barang ASC
    ");
    $st->execute([$id_outlet]);
    $items = $st->fetchAll();
} catch (Exception $e) {
    $items = [];
    $outlet = ['nama_outlet' => 'Error Loading Outlet'];
}
?>

<!-- Modal Header -->
<div class="px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center shadow-sm border border-indigo-100/50">
            <i class="fa-solid fa-store text-xl"></i>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Detail Stok</p>
            <h3 class="text-lg font-bold text-slate-800 leading-tight"><?= sanitize($outlet['nama_outlet']) ?></h3>
        </div>
    </div>
    <button type="button" onclick="closeLgModal()" class="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <i class="fa-solid fa-xmark text-lg"></i>
    </button>
</div>

<!-- Modal Body -->
<div class="p-6">
    <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm bg-white">
        <table class="w-full text-left text-sm border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider w-10 text-center">No</th>
                    <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang</th>
                    <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Kategori</th>
                    <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-right w-36">Stok Sisa</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-150">
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-slate-500 text-sm">
                        Belum ada barang di outlet ini.
                    </td>
                </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    $total_qty = 0;
                    foreach ($items as $i): 
                        $total_qty += $i['stok'];
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-3.5 text-center text-slate-400 font-medium"><?= $no++ ?></td>
                        <td class="px-5 py-3.5">
                            <p class="font-bold text-slate-700"><?= sanitize($i['nama_barang'] ?? '') ?></p>
                            <p class="text-[11px] text-slate-400 mt-0.5 font-mono"><?= sanitize($i['barcode'] ?? '') ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-semibold">
                                <?= sanitize($i['kategori'] ?? '-') ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono">
                            <span class="font-bold text-slate-800"><?= number_format($i['stok']) ?></span>
                            <span class="text-xs text-slate-450 font-sans font-semibold ml-1"><?= sanitize($i['satuan'] ?? 'Pcs') ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($items)): ?>
            <tfoot>
                <tr class="bg-slate-50/80 border-t-2 border-slate-200 font-bold">
                    <td colspan="3" class="px-5 py-4 text-right text-xs text-slate-500 uppercase tracking-wider">Total Akumulasi Seluruh Barang</td>
                    <td class="px-5 py-4 text-right font-mono text-base text-slate-800"><?= number_format($total_qty) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Modal Footer -->
<div class="px-6 py-4 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-3 rounded-b-2xl">
    <button type="button" onclick="closeLgModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 text-sm font-semibold transition-colors">Tutup</button>
</div>
