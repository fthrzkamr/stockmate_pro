<?php
if (!canDo('stockopname', 'create')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses.</div>";
    exit;
}

global $conn, $sistem;

$my_outlet_id = $_SESSION['outlet_id'] ?? null;
$selected_outlet = $my_outlet_id ?: ($_GET['outlet_id'] ?? $_POST['outlet_id'] ?? null);

$error = '';
$success = '';

// Ambil list outlet jika user adalah admin/SPV
$adminOutlets = [];
if (!$my_outlet_id) {
    try {
        $adminOutlets = $conn->query("SELECT * FROM outlet WHERE is_active = 1 ORDER BY nama_outlet ASC")->fetchAll();
    } catch (Exception $e) { }
}

// Ambil semua barang aktif dan stok awalnya di outlet yang dipilih jika tidak ada SO yang pending
$barangList = [];
$pendingSO = false;
if ($selected_outlet) {
    try {
        // Cek apakah ada pengajuan stock opname yang masih pending untuk outlet ini
        $cekPending = $conn->prepare("SELECT no_so, tanggal FROM stock_opname WHERE id_outlet = ? AND status_approval = 'Pending' LIMIT 1");
        $cekPending->execute([$selected_outlet]);
        $pendingSO = $cekPending->fetch(PDO::FETCH_ASSOC);

        if (!$pendingSO) {
            $st = $conn->prepare("
                SELECT b.id_barang, b.nama_barang, b.barcode, b.satuan, so.stok as stok_awal
                FROM stok_outlet so
                JOIN barang b ON so.id_barang = b.id_barang
                WHERE so.id_outlet = ? AND b.is_active = 1
                ORDER BY b.nama_barang ASC
            ");
            $st->execute([$selected_outlet]);
            $barangList = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error = "Gagal memuat barang: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = date('Y-m-d');
    $post_outlet_id = $my_outlet_id ?: (int)($_POST['outlet_id'] ?? 0);
    $items_id = $_POST['id_barang'] ?? [];
    $items_akhir = $_POST['stok_akhir'] ?? [];

    if (!$post_outlet_id) {
        $error = "Pilih outlet terlebih dahulu.";
    } elseif (empty($items_id)) {
        $error = "Tidak ada barang yang di-input.";
    } else {
        try {
            $conn->beginTransaction();

            // Cek apakah masih ada stock opname yang pending untuk outlet tersebut secara global
            $cekGlobal = $conn->prepare("SELECT COUNT(*) FROM stock_opname WHERE id_outlet = ? AND status_approval = 'Pending'");
            $cekGlobal->execute([$post_outlet_id]);
            $adaPending = $cekGlobal->fetchColumn();

            if ($adaPending > 0) {
                throw new Exception("Masih ada pengajuan Stock Opname sebelumnya untuk outlet ini yang statusnya Pending/Menunggu Approval. Selesaikan pengajuan tersebut terlebih dahulu.");
            }

            // Generate nomor SO: SO-OUTLETID-YYYYMMDD-RANDOM
            $no_so = 'SO-' . str_pad($post_outlet_id, 2, '0', STR_PAD_LEFT) . '-' . date('Ymd', strtotime($tanggal)) . '-' . rand(100, 999);

            $stmt = $conn->prepare("
                INSERT INTO stock_opname (no_so, tipe_so, tanggal, id_outlet, id_barang, id_user, stok_awal, stok_akhir, status_approval)
                VALUES (?, 'Outlet', ?, ?, ?, ?, ?, ?, 'Pending')
            ");

            foreach ($items_id as $id_barang) {
                $stok_awal = (int)($_POST['stok_awal'][$id_barang] ?? 0);
                $stok_akhir = ($_POST['stok_akhir'][$id_barang] === '') ? $stok_awal : (int)$_POST['stok_akhir'][$id_barang];

                $stmt->execute([
                    $no_so,
                    $tanggal,
                    $post_outlet_id,
                    $id_barang,
                    $_SESSION['user_id'],
                    $stok_awal,
                    $stok_akhir
                ]);
            }

            $id_so_last = $conn->lastInsertId();
            writeAuditLog('CREATE', 'stock_opname', $id_so_last, "Input Stock Opname harian $no_so oleh Staff.");

            $conn->commit();
            $_SESSION['flash_success'] = "Stock Opname berhasil diajukan dengan nomor: $no_so";
            $success = true;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<div class="fade-up max-w-4xl mx-auto space-y-5">
    
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/stockopname" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Form Input Stock Opname</h1>
            <p class="text-slate-500 text-sm">Catat hasil perhitungan fisik barang di outlet hari ini.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
        <!-- Meta Section -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Tanggal -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Tanggal Perhitungan (Hari Ini)</label>
                <input type="text" readonly value="<?= date('d M Y') ?>"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-100 text-slate-600 font-semibold cursor-not-allowed outline-none">
                <input type="hidden" name="tanggal" value="<?= date('Y-m-d') ?>">
            </div>

            <!-- Pilih Outlet -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Outlet <span class="text-red-500">*</span></label>
                <?php if ($my_outlet_id): ?>
                    <?php
                    // Ambil nama outlet
                    $outletName = $conn->query("SELECT nama_outlet FROM outlet WHERE id_outlet = " . (int)$my_outlet_id)->fetchColumn();
                    ?>
                    <input type="text" value="<?= sanitize($outletName) ?>" disabled 
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-100 text-slate-500 font-semibold">
                    <input type="hidden" name="outlet_id" value="<?= $my_outlet_id ?>">
                <?php else: ?>
                    <select name="outlet_id" required onchange="window.location.href='<?= $sistem ?>/stockopname/i?outlet_id=' + this.value"
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
            <?php if ($pendingSO): ?>
            <div class="bg-rose-50 border border-rose-250 rounded-2xl p-6 text-center text-rose-700 shadow-sm shadow-rose-100/30 max-w-2xl mx-auto my-4 fade-up">
                <div class="w-16 h-16 bg-rose-500/10 text-rose-600 rounded-2xl flex items-center justify-center shadow-sm border border-rose-100/50 mx-auto mb-4">
                    <i class="fa-solid fa-triangle-exclamation text-2xl animate-pulse"></i>
                </div>
                <h3 class="font-bold text-lg text-rose-800">Tidak Dapat Melakukan Stock Opname</h3>
                <p class="text-xs text-rose-650 leading-relaxed mt-2.5">
                    Outlet ini masih memiliki pengajuan Stock Opname dengan status <b>Menunggu Approval (Pending)</b> dengan nomor referensi <span class="font-mono bg-rose-100 border border-rose-200 px-1.5 py-0.5 rounded text-rose-800 font-bold"><?= htmlspecialchars($pendingSO['no_so']) ?></span> yang diajukan pada <b><?= date('d M Y', strtotime($pendingSO['tanggal'])) ?></b>.
                </p>
                <p class="text-xs text-rose-600/90 mt-2 font-medium leading-relaxed">
                    Silakan tunggu SPV/Management menyetujui atau menolak pengajuan tersebut terlebih dahulu, atau hapus pengajuan sebelumnya di menu utama sebelum membuat pengajuan baru.
                </p>
                <div class="mt-6 flex justify-center">
                    <a href="<?= $sistem ?>/stockopname" class="bg-rose-600 hover:bg-rose-700 text-white text-xs px-5 py-2.5 rounded-xl font-bold transition-all shadow-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>
            <?php else: ?>
            <!-- Barang List Section -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Daftar Hitung Fisik Barang</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Input jumlah fisik riil di kolom "Stok Akhir Fisik".</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="setSemuaSesuaiSistem()"
                                class="bg-indigo-50 hover:bg-indigo-100 text-indigo-650 px-3.5 py-1.5 border border-indigo-200 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-square-check"></i> Set Semua Sesuai Sistem
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang / Barcode</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-36">Stok Sistem (Awal)</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-48">Stok Akhir Fisik</th>
                                <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-36">Selisih Real-time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-150">
                            <?php if (empty($barangList)): ?>
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-cubes text-3xl mb-2 block text-slate-350"></i>
                                    Belum ada barang yang didistribusikan atau diterima di outlet ini.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($barangList as $b): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <p class="font-bold text-slate-700"><?= sanitize($b['nama_barang']) ?></p>
                                        <p class="text-[11px] text-slate-400 mt-0.5 font-mono"><?= sanitize($b['barcode'] ?: '—') ?></p>
                                        <input type="hidden" name="id_barang[]" value="<?= $b['id_barang'] ?>">
                                        <input type="hidden" name="stok_awal[<?= $b['id_barang'] ?>]" value="<?= $b['stok_awal'] ?>" id="awal_<?= $b['id_barang'] ?>">
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-semibold text-slate-600 font-mono">
                                        <?= number_format($b['stok_awal']) ?> <span class="text-xs text-slate-400 font-normal"><?= sanitize($b['satuan'] ?: 'Pcs') ?></span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="relative flex items-center justify-center">
                                            <input type="number" name="stok_akhir[<?= $b['id_barang'] ?>]" placeholder="Hitung fisik..." min="0"
                                                   id="akhir_<?= $b['id_barang'] ?>" oninput="hitungSelisih(<?= $b['id_barang'] ?>)"
                                                   class="w-full text-center px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all text-sm font-bold">
                                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                                <span class="text-xs font-semibold text-slate-400"><?= sanitize($b['satuan'] ?: 'Pcs') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-bold font-mono text-slate-500" id="selisih_<?= $b['id_barang'] ?>">
                                        —
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Action inside Table Card -->
                <div class="px-6 py-4 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-3">
                    <a href="<?= $sistem ?>/stockopname" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 text-sm font-semibold transition-colors">Batal</a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk text-xs"></i> Ajukan Stock Opname
                    </button>
                </div>
            </div>
            <?php endif; ?>
        <?php else: ?>
        <div class="bg-indigo-50 border border-indigo-150 rounded-2xl p-6 text-center text-indigo-700">
            <i class="fa-solid fa-store text-3xl mb-2 block text-indigo-400 animate-bounce"></i>
            <p class="font-bold">Pilih Outlet Terlebih Dahulu</p>
            <p class="text-xs text-indigo-600/80 mt-1">Silakan pilih outlet asal untuk menampilkan daftar barang yang perlu dihitung stok fisiknya.</p>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
function hitungSelisih(id) {
    const inputAwal = document.getElementById('awal_' + id);
    const inputAkhir = document.getElementById('akhir_' + id);
    const textSelisih = document.getElementById('selisih_' + id);
    
    if (!inputAwal || !inputAkhir || !textSelisih) return;
    
    const awal = parseInt(inputAwal.value) || 0;
    
    if (inputAkhir.value === '') {
        textSelisih.textContent = '—';
        textSelisih.className = "px-5 py-3.5 text-center font-bold font-mono text-slate-500";
        return;
    }
    
    const akhir = parseInt(inputAkhir.value) || 0;
    const selisih = akhir - awal;
    
    if (selisih === 0) {
        textSelisih.textContent = '0 (Pas)';
        textSelisih.className = "px-5 py-3.5 text-center font-bold font-mono text-slate-450";
    } else if (selisih > 0) {
        textSelisih.textContent = '+' + selisih + ' (Lebih)';
        textSelisih.className = "px-5 py-3.5 text-center font-bold font-mono text-emerald-650";
    } else {
        textSelisih.textContent = selisih + ' (Kurang)';
        textSelisih.className = "px-5 py-3.5 text-center font-bold font-mono text-rose-650";
    }
}

function setSemuaSesuaiSistem() {
    const inputs = document.querySelectorAll('input[id^="akhir_"]');
    inputs.forEach(input => {
        const id = input.id.replace('akhir_', '');
        const inputAwal = document.getElementById('awal_' + id);
        if (inputAwal) {
            input.value = inputAwal.value;
            hitungSelisih(id);
        }
    });
    
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Semua kolom telah disesuaikan dengan sistem!',
        showConfirmButton: false,
        timer: 1500
    });
}
</script>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Pengajuan Berhasil!',
    text: 'Stock Opname telah diajukan dan sedang menunggu approval SPV.',
    timer: 2500,
    showConfirmButton: false
}).then(() => {
    window.location.href = '<?= $sistem ?>/stockopname';
});
</script>
<?php endif; ?>