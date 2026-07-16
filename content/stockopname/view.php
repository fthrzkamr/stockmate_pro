<?php
// Handle POST action for deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_so') {
    ob_clean();
    header('Content-Type: application/json');

    $no_so = $_POST['no_so'] ?? '';
    if (!$no_so) {
        echo json_encode(['status' => 'error', 'msg' => 'Nomor SO tidak valid.']);
        exit;
    }

    // Validasi status & tipe. Hanya bisa hapus jika Pending atau Rejected.
    $check = $conn->prepare("SELECT status_approval, periode_bulan FROM stock_opname WHERE no_so = ? LIMIT 1");
    $check->execute([$no_so]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'msg' => 'Data Stock Opname tidak ditemukan.']);
        exit;
    }
    $status = $row['status_approval'];
    $is_monthly = !empty($row['periode_bulan']);
    $current_menu = $is_monthly ? 'stockopname_monthly' : 'stockopname';

    if (!canDo($current_menu, 'delete')) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak memiliki hak akses untuk menghapus data ini.']);
        exit;
    }
    if ($status === 'Approved') {
        echo json_encode(['status' => 'error', 'msg' => 'Stock opname yang sudah disetujui tidak dapat dihapus.']);
        exit;
    }

    $conn->beginTransaction();
    try {
        $del = $conn->prepare("DELETE FROM stock_opname WHERE no_so = ?");
        $del->execute([$no_so]);
        writeAuditLog('DELETE', 'stock_opname', 0, "Menghapus pengajuan stock opname: $no_so");
        $conn->commit();
        echo json_encode(['status' => 'success', 'msg' => 'Stock opname berhasil dihapus.']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

global $conn;

$no_so = $_GET['id'] ?? $_GET['keycode'] ?? '';
if (!$no_so) exit;

try {
    $st = $conn->prepare("
        SELECT so.*, b.nama_barang, b.barcode, b.satuan, o.nama_outlet, u.nama as operator, app.nama as approver
        FROM stock_opname so
        JOIN barang b ON so.id_barang = b.id_barang
        LEFT JOIN outlet o ON so.id_outlet = o.id_outlet
        LEFT JOIN users u ON so.id_user = u.id_user
        LEFT JOIN users app ON so.approved_by = app.id_user
        WHERE so.no_so = ?
    ");
    $st->execute([$no_so]);
    $items = $st->fetchAll();
} catch (Exception $e) {
    $items = [];
}

if (!$items) {
    echo "<div class='p-8 text-center text-red-500 font-medium'><i class='fa-solid fa-triangle-exclamation text-2xl mb-2 block'></i>Stock Opname tidak ditemukan.</div>";
    exit;
}

$first = $items[0];
$is_monthly = !empty($first['periode_bulan']);
$current_menu = $is_monthly ? 'stockopname_monthly' : 'stockopname';

if (!canDo($current_menu, 'view')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses.</div>";
    exit;
}

$status = $first['status_approval'];
$color = 'amber';
if ($status === 'Approved') $color = 'emerald';
if ($status === 'Rejected') $color = 'rose';

$bulanNama = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<!-- Modal Header -->
<div class="px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center shadow-sm border border-indigo-100/50">
            <i class="fa-solid fa-clipboard-list text-xl"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-slate-800">Detail Stock Opname</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Ref SO: <span class="font-mono text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100"><?= htmlspecialchars($no_so) ?></span></p>
        </div>
    </div>
    <button type="button" onclick="closeLgModal()" class="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <i class="fa-solid fa-xmark text-lg"></i>
    </button>
</div>

<!-- Modal Body -->
<div class="p-6 space-y-6">
    <!-- Meta Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
            <div class="w-8 h-8 bg-indigo-500/10 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-store text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Outlet</p>
                <p class="text-sm font-bold text-slate-800 leading-tight"><?= sanitize($first['nama_outlet'] ?? 'Tanpa Outlet') ?></p>
            </div>
        </div>
        <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
            <div class="w-8 h-8 bg-sky-500/10 text-sky-600 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-calendar-user text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tanggal / Periode & Operator</p>
                <?php if ($is_monthly): ?>
                    <p class="text-sm font-bold text-slate-800 leading-tight"><?= $bulanNama[(int)$first['periode_bulan']] ?? 'Bulan ?' ?> <?= $first['periode_tahun'] ?></p>
                    <p class="text-xs text-slate-500 font-semibold mt-1">Tanggal SO: <?= date('d M Y', strtotime($first['tanggal'])) ?> | Staf: <?= sanitize($first['operator'] ?? 'System') ?></p>
                <?php else: ?>
                    <p class="text-sm font-bold text-slate-800 leading-tight"><?= date('d F Y', strtotime($first['tanggal'])) ?></p>
                    <p class="text-xs text-slate-500 font-semibold mt-1">Staf: <?= sanitize($first['operator'] ?? 'System') ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
            <div class="w-8 h-8 bg-<?= $color ?>-500/10 text-<?= $color ?>-600 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-info text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Status Approval</p>
                <span class="bg-<?= $color ?>-50 text-<?= $color ?>-700 border border-<?= $color ?>-150 px-2 py-0.5 rounded-full text-[10px] font-bold inline-flex items-center gap-1 shadow-sm mt-0.5">
                    <span class="w-1 h-1 rounded-full bg-<?= $color ?>-500"></span>
                    <?= $status === 'Pending' ? 'Menunggu Approval' : $status ?>
                </span>
                <?php if ($first['approved_by']): ?>
                    <p class="text-[10px] text-slate-500 font-semibold mt-1">SPV: <?= sanitize($first['approver']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reject Reason Card if Rejected -->
    <?php if ($status === 'Rejected' && !empty($first['catatan_reject'])): ?>
    <div class="bg-rose-50 border border-rose-200 p-4 rounded-2xl flex gap-3 shadow-sm shadow-rose-50/20">
        <div class="w-8 h-8 bg-rose-500/10 text-rose-600 rounded-xl flex items-center justify-center shrink-0">
            <i class="fa-solid fa-triangle-exclamation text-sm animate-bounce"></i>
        </div>
        <div>
            <p class="text-[10px] font-bold text-rose-700 uppercase tracking-wider mb-1">Catatan Penolakan SPV</p>
            <p class="text-xs text-rose-800 leading-relaxed font-semibold italic">"<?= sanitize($first['catatan_reject'] ?? '') ?>"</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Items Section -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Hasil Perhitungan Barang</p>
            <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= count($items) ?> Item</span>
        </div>
        <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm bg-white">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang / Barcode</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Stok Awal (Sistem)</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Stok Akhir (Fisik)</th>
                        <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-150">
                    <?php foreach ($items as $i): 
                        $selisih = $i['stok_akhir'] - $i['stok_awal'];
                        $selisih_color = 'text-slate-500';
                        $selisih_text = '0';
                        if ($selisih > 0) {
                            $selisih_color = 'text-emerald-600 font-bold';
                            $selisih_text = '+' . $selisih;
                        } elseif ($selisih < 0) {
                            $selisih_color = 'text-rose-600 font-bold';
                            $selisih_text = $selisih;
                        }
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="font-bold text-slate-700"><?= sanitize($i['nama_barang'] ?? '') ?></p>
                            <p class="text-[11px] text-slate-400 mt-0.5 font-mono"><?= sanitize($i['barcode'] ?? '') ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-center font-mono font-semibold text-slate-650">
                            <?= number_format($i['stok_awal']) ?> <?= sanitize($i['satuan'] ?? 'Pcs') ?>
                        </td>
                        <td class="px-5 py-3.5 text-center font-mono font-bold text-slate-800">
                            <?= number_format($i['stok_akhir']) ?> <?= sanitize($i['satuan'] ?? 'Pcs') ?>
                        </td>
                        <td class="px-5 py-3.5 text-center font-mono <?= $selisih_color ?>">
                            <?= $selisih_text ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Footer -->
<div class="px-6 py-4 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-3 rounded-b-2xl">
    <button type="button" onclick="closeLgModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 text-sm font-semibold transition-colors">Tutup</button>
</div>