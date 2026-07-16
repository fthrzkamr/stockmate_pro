<?php
if (!canDo('stockopname_monthly', 'create')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses.</div>";
    exit;
}

global $conn, $sistem;

$my_outlet_id = $_SESSION['outlet_id'] ?? null;
$selected_outlet = $my_outlet_id ?: ($_GET['outlet_id'] ?? $_POST['outlet_id'] ?? null);
$selected_bulan = $_POST['bulan'] ?? date('m');
$selected_tahun = $_POST['tahun'] ?? date('Y');

$error = '';
$success = '';

$adminOutlets = [];
if (!$my_outlet_id) {
    try {
        $adminOutlets = $conn->query("SELECT * FROM outlet WHERE is_active = 1 ORDER BY nama_outlet ASC")->fetchAll();
    } catch (Exception $e) { }
}

$barangList = [];
if ($selected_outlet) {
    try {
        $st = $conn->prepare("
            SELECT b.id_barang, b.nama_barang, b.barcode, b.satuan, so.stok as stok_awal
            FROM stok_outlet so
            JOIN barang b ON so.id_barang = b.id_barang
            WHERE so.id_outlet = ? AND b.is_active = 1
            ORDER BY b.nama_barang ASC
        ");
        $st->execute([$selected_outlet]);
        $barangList = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Gagal memuat barang: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_outlet_id = $my_outlet_id ?: (int)($_POST['outlet_id'] ?? 0);
    $items_id = $_POST['id_barang'] ?? [];
    $items_akhir = $_POST['stok_akhir'] ?? [];
    $bulan = (int)$_POST['bulan'];
    $tahun = (int)$_POST['tahun'];

    if (!$post_outlet_id) {
        $error = "Pilih outlet terlebih dahulu.";
    } elseif (empty($items_id)) {
        $error = "Tidak ada barang yang di-input.";
    } else {
        try {
            $conn->beginTransaction();

            $no_so = 'SO-M-' . str_pad($post_outlet_id, 2, '0', STR_PAD_LEFT) . '-' . $tahun . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-' . rand(100, 999);

            $stmt = $conn->prepare("
                INSERT INTO stock_opname (no_so, tipe_so, tanggal, periode_bulan, periode_tahun, id_outlet, id_barang, id_user, stok_awal, stok_akhir, status_approval)
                VALUES (?, 'Outlet', ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");

            foreach ($items_id as $id_barang) {
                $stok_awal = (int)($_POST['stok_awal'][$id_barang] ?? 0);
                $stok_akhir = ($_POST['stok_akhir'][$id_barang] === '') ? $stok_awal : (int)$_POST['stok_akhir'][$id_barang];

                $stmt->execute([
                    $no_so,
                    date('Y-m-d'),
                    $bulan,
                    $tahun,
                    $post_outlet_id,
                    $id_barang,
                    $_SESSION['user_id'],
                    $stok_awal,
                    $stok_akhir
                ]);
            }

            $id_so_last = $conn->lastInsertId();
            writeAuditLog('CREATE', 'stock_opname', $id_so_last, "Input Stock Opname Bulanan $no_so oleh Staff.");

            $conn->commit();
            $_SESSION['flash_success'] = "Stock Opname Bulanan berhasil diajukan dengan nomor: $no_so";
            $success = true;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<div class="fade-up max-w-4xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/stockopname_monthly" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Form Input Stock Opname Bulanan</h1>
            <p class="text-slate-500 text-sm">Catat hasil perhitungan fisik barang bulanan di outlet.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Bulan <span class="text-red-500">*</span></label>
                <select name="bulan" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
                    <?php for ($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $selected_bulan == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tahun <span class="text-red-500">*</span></label>
                <select name="tahun" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
                    <?php for ($y=date('Y')-1; $y<=date('Y')+1; $y++): ?>
                    <option value="<?= $y ?>" <?= $selected_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Outlet <span class="text-red-500">*</span></label>
                <?php if ($my_outlet_id): ?>
                    <?php
                    $outletName = $conn->query("SELECT nama_outlet FROM outlet WHERE id_outlet = " . (int)$my_outlet_id)->fetchColumn();
                    ?>
                    <input type="text" value="<?= sanitize($outletName) ?>" disabled 
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-100 text-slate-500 font-semibold">
                    <input type="hidden" name="outlet_id" value="<?= $my_outlet_id ?>">
                <?php else: ?>
                    <select name="outlet_id" required onchange="window.location.href='<?= $sistem ?>/stockopname_monthly/i?outlet_id=' + this.value"
                            class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-white">
                        <option value="">— Pilih Cabang / Outlet —</option>
                        <?php foreach ($adminOutlets as $o): ?>
                        <option value="<?= $o['id_outlet'] ?>" <?= ($selected_outlet == $o['id_outlet']) ? 'selected' : '' ?>>
                            <?= sanitize($o['nama_outlet']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_outlet): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Daftar Hitung Fisik Barang</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Input jumlah fisik riil di kolom "Stok Akhir Fisik".</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang / Barcode</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-36">Stok Sistem (Awal)</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-48">Stok Akhir Fisik</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-150">
                            <?php foreach ($barangList as $b): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <p class="font-bold text-slate-700"><?= sanitize($b['nama_barang']) ?></p>
                                    <p class="text-[11px] text-slate-400 mt-0.5 font-mono"><?= sanitize($b['barcode'] ?: '—') ?></p>
                                    <input type="hidden" name="id_barang[]" value="<?= $b['id_barang'] ?>">
                                    <input type="hidden" name="stok_awal[<?= $b['id_barang'] ?>]" value="<?= $b['stok_awal'] ?>">
                                </td>
                                <td class="px-5 py-3.5 text-center font-semibold text-slate-600 font-mono">
                                    <?= number_format($b['stok_awal']) ?>
                                </td>
                                <td class="px-5 py-3.5">
                                    <input type="number" name="stok_akhir[<?= $b['id_barang'] ?>]" placeholder="Hitung fisik..." min="0"
                                           class="w-full text-center px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all text-sm font-bold">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-3">
                    <a href="<?= $sistem ?>/stockopname_monthly" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 text-sm font-semibold transition-colors">Batal</a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk text-xs"></i> Ajukan Stock Opname Bulanan
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Pengajuan Berhasil!',
    text: 'Stock Opname Bulanan telah diajukan dan sedang menunggu approval SPV.',
    timer: 2500,
    showConfirmButton: false
}).then(() => {
    window.location.href = '<?= $sistem ?>/stockopname_monthly';
});
</script>
<?php endif; ?>