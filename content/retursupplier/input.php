<?php
if (!canDo('retursupplier', 'create')) {
    echo "<div class='p-4 text-red-650 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk menambah retur.</div>";
    exit;
}

global $conn, $sistem;

$error = '';
$success = '';

// Ambil daftar Transaksi Barang Masuk (di-group berdasarkan kode_transaksi)
try {
    $stMasuk = $conn->query("
        SELECT bm.kode_transaksi, bm.tanggal, 
               COALESCE(s.nama_supplier, bm.supplier_lainnya, 'Tanpa Supplier') as nama_supplier,
               COUNT(bm.id_masuk) as total_item
        FROM barang_masuk bm
        LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
        WHERE bm.kode_transaksi IS NOT NULL AND bm.status != 'Dibatalkan'
        GROUP BY bm.kode_transaksi, bm.tanggal, nama_supplier
        ORDER BY bm.tanggal DESC
        LIMIT 200
    ");
    $transaksiList = $stMasuk->fetchAll();
} catch (Exception $e) {
    $transaksiList = [];
}

$selected_trx = $_GET['trx'] ?? '';
$items = [];

if ($selected_trx) {
    // Ambil item dari transaksi yang dipilih
    $stItems = $conn->prepare("
        SELECT bm.id_masuk, bm.qty, b.nama_barang, b.satuan, b.id_barang,
               (SELECT COALESCE(SUM(qty), 0) FROM retur_supplier rs WHERE rs.id_masuk = bm.id_masuk) as qty_retur
        FROM barang_masuk bm
        JOIN barang b ON bm.id_barang = b.id_barang
        WHERE bm.kode_transaksi = ? AND bm.status != 'Dibatalkan'
    ");
    $stItems->execute([$selected_trx]);
    $items = $stItems->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal   = date('Y-m-d');
    $alasan    = trim($_POST['alasan'] ?? '');
    $id_user   = $_SESSION['sess_mngid'] ?? null;
    $retur_qtys = $_POST['qty_retur'] ?? [];
    $trx_code  = $_POST['trx_code'] ?? '';

    $total_retur = 0;
    foreach ($retur_qtys as $q) {
        $total_retur += (int)$q;
    }

    if (empty($trx_code)) {
        $error = "Pilih Nota / Transaksi Barang Masuk terlebih dahulu.";
    } elseif ($total_retur <= 0) {
        $error = "Minimal harus ada 1 barang yang diretur (Jumlah Qty Retur > 0).";
    } elseif (empty($alasan)) {
        $error = "Alasan retur wajib diisi.";
    } else {
        try {
            $conn->beginTransaction();
            $kode_retur = 'RET-' . time() . rand(10, 99);
            $retur_count = 0;

            foreach ($retur_qtys as $id_masuk => $qty_retur) {
                $qty_retur = (int)$qty_retur;
                if ($qty_retur > 0) {
                    // Cek max retur
                    $stCek = $conn->prepare("
                        SELECT bm.qty, bm.id_barang,
                               (SELECT COALESCE(SUM(qty), 0) FROM retur_supplier rs WHERE rs.id_masuk = bm.id_masuk) as sudah_retur
                        FROM barang_masuk bm 
                        WHERE bm.id_masuk = ?
                    ");
                    $stCek->execute([$id_masuk]);
                    $rowMasuk = $stCek->fetch();

                    if ($rowMasuk) {
                        $max_bisa_retur = $rowMasuk['qty'] - $rowMasuk['sudah_retur'];
                        if ($qty_retur > $max_bisa_retur) {
                            throw new Exception("Jumlah retur salah satu barang melebihi batas yang diizinkan ($max_bisa_retur).");
                        }
                        
                        $id_barang = $rowMasuk['id_barang'];

                        // 1. Insert ke tabel retur_supplier
                        $stIns = $conn->prepare("
                            INSERT INTO retur_supplier (kode_retur, tanggal, id_masuk, id_barang, qty, alasan, id_user)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stIns->execute([$kode_retur, $tanggal, $id_masuk, $id_barang, $qty_retur, $alasan, $id_user]);
                        $id_retur = $conn->lastInsertId();

                        // 2. Kurangi stok di tabel inventory
                        $stUpdate = $conn->prepare("UPDATE inventory SET stok = stok - ? WHERE id_barang = ?");
                        $stUpdate->execute([$qty_retur, $id_barang]);

                        $retur_count++;
                    }
                }
            }

            // 3. Catat audit
            if ($retur_count > 0) {
                writeAuditLog('CREATE', 'retur_supplier', 0, "Retur barang kolektif (Kode: $kode_retur, Alasan: $alasan)");
                $conn->commit();
                $_SESSION['flash_success'] = "Retur barang berhasil diproses dan stok telah disesuaikan.";
                echo "<script>window.location.href='$sistem/retursupplier';</script>";
                exit;
            } else {
                throw new Exception("Tidak ada item yang berhasil diproses.");
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Gagal memproses retur: " . $e->getMessage();
        }
    }
}
?>

<div class="fade-up max-w-4xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/retursupplier" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Input Retur Pembelian</h1>
            <p class="text-slate-500 text-sm">Kembalikan barang ke supplier berdasarkan Nota Transaksi.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-triangle-exclamation text-rose-500 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 space-y-5 mb-5">
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Pilih Nota / Transaksi Barang Masuk <span class="text-rose-500">*</span></label>
            <select id="selectTrx" class="w-full select2-masuk" onchange="window.location.href='<?= $sistem ?>/retursupplier/i?trx=' + this.value">
                <option value="">-- Ketik Kode Transaksi atau Nama Supplier --</option>
                <?php foreach ($transaksiList as $t): ?>
                <option value="<?= $t['kode_transaksi'] ?>" <?= $selected_trx === $t['kode_transaksi'] ? 'selected' : '' ?>>
                    <?= $t['kode_transaksi'] ?> | <?= date('d/m/Y', strtotime($t['tanggal'])) ?> | Supplier: <?= sanitize($t['nama_supplier']) ?> (<?= $t['total_item'] ?> Item)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($selected_trx && !empty($items)): ?>
    <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <input type="hidden" name="trx_code" value="<?= htmlspecialchars($selected_trx) ?>">
        
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <p class="text-sm font-semibold text-slate-700">
                <i class="fa-solid fa-boxes-stacked text-indigo-500 mr-2"></i>Daftar Item pada Transaksi: <span class="text-sky-600 font-mono ml-1"><?= htmlspecialchars($selected_trx) ?></span>
            </p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Nama Barang</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Qty Masuk</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Sudah Retur</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Maks Bisa Retur</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-40 text-right">Qty Retur Saat Ini</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php 
                    $has_returnable = false;
                    foreach ($items as $item): 
                        $max = $item['qty'] - $item['qty_retur'];
                        if ($max > 0) $has_returnable = true;
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors <?= $max <= 0 ? 'opacity-50' : '' ?>">
                        <td class="px-5 py-3">
                            <p class="text-sm font-bold text-slate-700"><?= sanitize($item['nama_barang']) ?></p>
                            <p class="text-[10px] text-slate-500"><?= sanitize($item['satuan']) ?></p>
                        </td>
                        <td class="px-5 py-3 text-center text-sm font-medium text-slate-600"><?= $item['qty'] ?></td>
                        <td class="px-5 py-3 text-center text-sm font-medium text-rose-500"><?= $item['qty_retur'] ?></td>
                        <td class="px-5 py-3 text-center text-sm font-bold text-emerald-600"><?= $max ?></td>
                        <td class="px-5 py-3 text-right">
                            <?php if ($max > 0): ?>
                            <input type="number" name="qty_retur[<?= $item['id_masuk'] ?>]" min="0" max="<?= $max ?>" value="0"
                                   class="w-24 px-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/30 transition-all font-bold text-center">
                            <?php else: ?>
                            <span class="text-[10px] text-rose-500 font-bold bg-rose-50 px-2 py-1 rounded">Habis</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($has_returnable): ?>
        <div class="p-6 border-t border-slate-100 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tanggal Retur (Hari Ini)</label>
                    <input type="text" readonly value="<?= date('d M Y') ?>"
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-100 text-slate-600 font-semibold cursor-not-allowed outline-none">
                    <input type="hidden" name="tanggal" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Alasan Retur <span class="text-rose-500">*</span></label>
                    <textarea name="alasan" rows="2" required placeholder="Contoh: Barang rusak, cacat produksi..."
                              class="w-full px-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 transition-all bg-white"><?= htmlspecialchars($_POST['alasan'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
            <a href="<?= $sistem ?>/retursupplier" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 transition-colors text-sm font-semibold text-slate-600">Batal</a>
            <button type="submit" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Proses Retur Item
            </button>
        </div>
        <?php else: ?>
        <div class="p-6 text-center">
            <p class="text-rose-600 font-bold mb-2">Semua item pada transaksi ini sudah diretur maksimal.</p>
            <a href="<?= $sistem ?>/retursupplier/i" class="text-sky-600 text-sm hover:underline">Pilih transaksi lain</a>
        </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-masuk').select2({ width: '100%', placeholder: '-- Ketik Kode Transaksi atau Nama Supplier --' });
});
</script>
