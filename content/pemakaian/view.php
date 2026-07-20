<?php
// Handle POST action for cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_transaction') {
    ob_clean();
    header('Content-Type: application/json');
    if (!canDo('pemakaian', 'delete')) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak memiliki hak akses untuk membatalkan transaksi ini.']);
        exit;
    }

    $trx_code = $_POST['trx_code'] ?? '';
    if (!$trx_code) {
        echo json_encode(['status' => 'error', 'msg' => 'ID Transaksi tidak valid.']);
        exit;
    }

    $conn->beginTransaction();
    try {
        // Ambil semua item dari transaksi ini
        $st = $conn->prepare("
            SELECT p.*, b.nama_barang 
            FROM pemakaian p
            JOIN barang b ON p.id_barang = b.id_barang
            WHERE COALESCE(p.kode_transaksi, p.id_pemakaian) = ?
        ");
        $st->execute([$trx_code]);
        $items = $st->fetchAll();

        if (!$items) {
            throw new Exception('Transaksi pemakaian tidak ditemukan.');
        }

        // Hapus data transaksi pemakaian dan kembalikan stok outlet
        foreach ($items as $trx) {
            // Kembalikan ke stok_outlet
            $conn->exec("
                UPDATE stok_outlet 
                SET stok = stok + {$trx['qty']} 
                WHERE id_outlet = {$trx['id_outlet']} AND id_barang = {$trx['id_barang']}
            ");
            // Log audit
            writeAuditLog(
                'DELETE', 
                'pemakaian', 
                $trx['id_pemakaian'], 
                "Membatalkan pemakaian: {$trx['nama_barang']} (Qty: {$trx['qty']}). Stok dikembalikan ke Outlet."
            );
        }

        // Hapus record pemakaian
        $del = $conn->prepare("DELETE FROM pemakaian WHERE COALESCE(kode_transaksi, id_pemakaian) = ?");
        $del->execute([$trx_code]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'msg' => "Transaksi pemakaian berhasil dibatalkan dan stok dikembalikan ke Outlet."]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

if (!canDo('pemakaian', 'view')) exit;
global $conn;

$trx_code = $_GET['id'] ?? $_GET['keycode'] ?? '';
if (!$trx_code) exit;

try {
    $st = $conn->prepare("
        SELECT p.*, b.nama_barang, b.barcode, b.satuan, o.nama_outlet, u.nama as operator
        FROM pemakaian p
        JOIN barang b ON p.id_barang = b.id_barang
        LEFT JOIN outlet o ON p.id_outlet = o.id_outlet
        LEFT JOIN users u ON p.id_user = u.id_user
        WHERE COALESCE(p.kode_transaksi, p.id_pemakaian) = ?
    ");
    $st->execute([$trx_code]);
    $items = $st->fetchAll();
} catch (Exception $e) {
    $items = [];
}

if (!$items) {
    echo "<div class='p-8 text-center text-red-500 font-medium'><i class='fa-solid fa-triangle-exclamation text-2xl mb-2 block'></i>Transaksi tidak ditemukan.</div>";
    exit;
}

$first = $items[0];
$total_qty = 0;
foreach ($items as $i) {
    $total_qty += $i['qty'];
}
?>

<!-- Modal Header -->
<div class="px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center shadow-sm border border-indigo-100/50">
            <i class="fa-solid fa-clipboard-list text-xl"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-slate-800">Detail Pemakaian Barang</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Ref: <span class="font-mono text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100"><?= htmlspecialchars($trx_code) ?></span></p>
        </div>
    </div>
    <button type="button" onclick="closeLgModal()" class="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <i class="fa-solid fa-xmark text-lg"></i>
    </button>
</div>

<!-- Modal Body -->
<div class="p-6 space-y-6">
    <!-- Meta Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
            <div class="w-8 h-8 bg-indigo-500/10 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-shop text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Outlet Asal</p>
                <p class="text-sm font-bold text-slate-800 leading-tight"><?= sanitize($first['nama_outlet'] ?? 'Tanpa Outlet') ?></p>
            </div>
        </div>
        <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
            <div class="w-8 h-8 bg-sky-500/10 text-sky-600 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-calendar-user text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tanggal & Operator</p>
                <p class="text-sm font-bold text-slate-800 leading-tight"><?= date('d F Y', strtotime($first['tanggal'])) ?></p>
                <p class="text-xs text-slate-500 font-semibold mt-1">Oleh: <?= sanitize($first['operator'] ?? 'System') ?></p>
            </div>
        </div>
    </div>

    <!-- Items Section -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Daftar Barang Digunakan</p>
            <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= count($items) ?> Item</span>
        </div>
        <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm bg-white">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang / Barcode</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-right w-36">Qty Digunakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-150">
                    <?php foreach ($items as $i): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="font-bold text-slate-700"><?= sanitize($i['nama_barang'] ?? '') ?></p>
                            <p class="text-[11px] text-slate-400 mt-0.5 font-mono"><?= sanitize($i['barcode'] ?? '') ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono">
                            <span class="font-bold text-rose-600">-<?= number_format($i['qty']) ?></span>
                            <span class="text-xs text-slate-450 font-sans font-semibold ml-1"><?= sanitize($i['satuan'] ?? 'Pcs') ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php 
                        $satuans = array_unique(array_filter(array_column($items, 'satuan')));
                        $total_unit = (count($satuans) === 1) ? reset($satuans) : (count($items) === 1 ? ($first['satuan'] ?: 'Item') : 'Item');
                    ?>
                    <tr class="bg-slate-50/80 border-t-2 border-slate-200 font-bold">
                        <td class="px-5 py-4 text-xs text-slate-500 uppercase tracking-wider">Total Akumulasi Qty</td>
                        <td class="px-5 py-4 text-right font-mono text-base text-rose-600" colspan="1">-<?= number_format($total_qty) ?> <?= sanitize($total_unit) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Keterangan Section -->
    <?php if (!empty($first['keterangan'])): ?>
    <div class="bg-amber-50/50 border border-amber-200/60 p-4 rounded-2xl flex gap-3 shadow-sm shadow-amber-50/20">
        <div class="w-8 h-8 bg-amber-500/10 text-amber-600 rounded-xl flex items-center justify-center shrink-0">
            <i class="fa-solid fa-comment-dots text-sm"></i>
        </div>
        <div>
            <p class="text-[10px] font-bold text-amber-650 uppercase tracking-wider mb-1">Catatan / Keterangan</p>
            <p class="text-xs text-slate-650 leading-relaxed italic">"<?= sanitize($first['keterangan'] ?? '') ?>"</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Footer -->
<div class="px-6 py-4 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-3 rounded-b-2xl">
    <button type="button" onclick="closeLgModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 text-sm font-semibold transition-colors">Tutup</button>
</div>