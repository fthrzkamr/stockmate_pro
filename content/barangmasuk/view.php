<?php
// Handle POST action for cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_transaction') {
    ob_clean();
    header('Content-Type: application/json');
    if (!canDo('barangmasuk', 'delete')) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak memiliki hak akses untuk membatalkan transaksi.']);
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
            SELECT bm.*, b.nama_barang 
            FROM barang_masuk bm
            JOIN barang b ON bm.id_barang = b.id_barang
            WHERE COALESCE(bm.kode_transaksi, bm.id_masuk) = ?
        ");
        $st->execute([$trx_code]);
        $items = $st->fetchAll();

        if (!$items) {
            throw new Exception('Transaksi tidak ditemukan.');
        }

        // Hapus data transaksi barang masuk
        // Trigger di MySQL otomatis MENAMBAH stok inventory jika ada yg masuk.
        // Kita harus kembalikan manual dgn MENGURANGI.
        foreach ($items as $trx) {
            $conn->exec("UPDATE inventory SET stok = stok - {$trx['qty']} WHERE id_barang = {$trx['id_barang']}");
            // Log for each item
            writeAuditLog(
                'DELETE', 
                'barang_masuk', 
                $trx['id_masuk'], 
                "Membatalkan penerimaan: {$trx['nama_barang']} (Qty: {$trx['qty']})"
            );
        }

        // Now update status instead of delete
        $upd = $conn->prepare("UPDATE barang_masuk SET status = 'Dibatalkan' WHERE COALESCE(kode_transaksi, id_masuk) = ?");
        $upd->execute([$trx_code]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'msg' => "Transaksi berhasil dibatalkan dan stok Gudang telah dikurangi kembali."]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

if (!canDo('barangmasuk', 'view')) exit;
global $conn;

$is_readonly = isset($_GET['readonly']) && $_GET['readonly'] == '1';
$trx_code = $_GET['id'] ?? $_GET['keycode'] ?? '';
if (!$trx_code) exit;

try {
    $st = $conn->prepare("
        SELECT bm.*, b.nama_barang, b.barcode, b.satuan, s.nama_supplier, u.nama as operator
        FROM barang_masuk bm
        JOIN barang b ON bm.id_barang = b.id_barang
        LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
        LEFT JOIN users u ON bm.id_user = u.id_user
        WHERE COALESCE(bm.kode_transaksi, bm.id_masuk) = ?
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
        <div class="w-12 h-12 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center shadow-sm border border-sky-100/50">
            <i class="fa-solid fa-truck-ramp-box text-xl animate-pulse"></i>
        </div>
        <div>
            <div class="flex items-center gap-2">
                <h3 class="text-lg font-bold text-slate-800">Detail Penerimaan</h3>
                <?php
                $statusText = $first['status'] ?: 'Diterima';
                $statusColor = $statusText === 'Dibatalkan' ? 'rose' : 'emerald';
                ?>
                <span class="bg-<?= $statusColor ?>-50 text-<?= $statusColor ?>-700 border border-<?= $statusColor ?>-200 px-2.5 py-0.5 rounded-full text-[10px] font-bold">
                    <?= $statusText ?>
                </span>
            </div>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Ref: <span class="font-mono text-sky-600 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-100"><?= htmlspecialchars($trx_code) ?></span></p>
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
            <div class="w-8 h-8 bg-sky-500/10 text-sky-600 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-truck text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Supplier Pengirim</p>
                <p class="text-sm font-bold text-slate-800 leading-tight"><?= sanitize($first['nama_supplier'] ?? ($first['supplier_lainnya'] ?? 'Tanpa Supplier')) ?></p>
            </div>
        </div>
        <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
            <div class="w-8 h-8 bg-indigo-500/10 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
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
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Daftar Barang Masuk</p>
            <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= count($items) ?> Item</span>
        </div>
        <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm bg-white">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang / Barcode</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-right w-40">Qty Masuk</th>
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
                            <span class="font-black text-emerald-600">+<?= number_format($i['qty']) ?></span>
                            <span class="text-xs text-slate-400 font-sans font-semibold ml-1"><?= sanitize($i['satuan'] ?? 'Pcs') ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50/80 border-t-2 border-slate-200 font-bold">
                        <td class="px-5 py-4 text-xs text-slate-500 uppercase tracking-wider">Total Akumulasi Qty</td>
                        <td class="px-5 py-4 text-right font-mono text-base text-emerald-600">+<?= number_format($total_qty) ?> Pcs</td>
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