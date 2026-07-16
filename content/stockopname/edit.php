<?php
global $conn, $sistem;

$no_so = $_GET['id'] ?? $_GET['keycode'] ?? '';
if (!$no_so) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Nomor SO tidak valid.</div>";
    exit;
}

try {
    $st = $conn->prepare("
        SELECT so.*, b.nama_barang, b.barcode, b.satuan, o.nama_outlet, u.nama as operator
        FROM stock_opname so
        JOIN barang b ON so.id_barang = b.id_barang
        LEFT JOIN outlet o ON so.id_outlet = o.id_outlet
        LEFT JOIN users u ON so.id_user = u.id_user
        WHERE so.no_so = ?
    ");
    $st->execute([$no_so]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
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

if (!canDo($current_menu, 'edit')) {
    echo "<div class='p-4 text-rose-600 bg-rose-50 border border-rose-200 rounded-xl text-sm font-medium'>Anda tidak memiliki akses.</div>";
    exit;
}

// Hanya bisa diedit jika statusnya Rejected
if ($first['status_approval'] !== 'Rejected') {
    echo "<div class='p-4 text-amber-600 bg-amber-50 border border-amber-200 rounded-xl text-sm font-medium'>Hanya Stock Opname status Rejected yang dapat direvisi.</div>";
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items_akhir = $_POST['stok_akhir'] ?? [];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
            UPDATE stock_opname 
            SET stok_akhir = ?, status_approval = 'Pending', catatan_reject = NULL, created_at = CURRENT_TIMESTAMP
            WHERE no_so = ? AND id_barang = ?
        ");

        foreach ($items_akhir as $id_barang => $akhir_val) {
            $stok_awal = 0;
            // Dapatkan stok awal dari database
            foreach ($items as $item) {
                if ($item['id_barang'] == $id_barang) {
                    $stok_awal = (int)$item['stok_awal'];
                    break;
                }
            }
            $akhir = ($akhir_val === '') ? $stok_awal : (int)$akhir_val;

            $stmt->execute([
                $akhir,
                $no_so,
                $id_barang
            ]);
        }

        writeAuditLog('UPDATE', 'stock_opname', $first['id_so'], "Revisi Stock Opname $no_so diajukan kembali.");

        $conn->commit();
        $_SESSION['flash_success'] = "Revisi Stock Opname $no_so berhasil diajukan ulang.";
        $success = true;
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>

<div class="fade-up max-w-4xl mx-auto space-y-5">
    
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/<?= $current_menu ?>" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Revisi Stock Opname</h1>
            <p class="text-slate-500 text-sm">Perbaiki perhitungan fisik barang berdasarkan catatan penolakan SPV.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <!-- Catatan Rejection SPV -->
    <div class="bg-rose-50 border border-rose-200 p-4 rounded-2xl flex gap-3 shadow-sm shadow-rose-50/20">
        <div class="w-8 h-8 bg-rose-500/10 text-rose-600 rounded-xl flex items-center justify-center shrink-0">
            <i class="fa-solid fa-circle-exclamation text-sm animate-pulse"></i>
        </div>
        <div>
            <p class="text-[10px] font-bold text-rose-750 uppercase tracking-wider mb-1">Catatan Penolakan SPV</p>
            <p class="text-xs text-rose-800 leading-relaxed font-bold italic">"<?= sanitize($first['catatan_reject'] ?? 'Tidak ada catatan.') ?>"</p>
        </div>
    </div>

    <form method="POST" class="space-y-5">
        <!-- Meta Section (Read-only) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Nomor SO</p>
                <p class="text-sm font-bold text-slate-800 font-mono mt-1"><?= sanitize($no_so) ?></p>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cabang / Outlet</p>
                <p class="text-sm font-bold text-slate-800 mt-1"><?= sanitize($first['nama_outlet']) ?></p>
            </div>
        </div>

        <!-- Barang List Section -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 bg-slate-50/50">
                <h2 class="text-sm font-bold text-slate-800">Daftar Barang & Revisi Fisik</h2>
                <p class="text-xs text-slate-500 mt-0.5">Edit jumlah fisik di kolom "Stok Akhir Fisik" sesuai perhitungan yang benar.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider">Nama Barang / Barcode</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-36">Stok Sistem (Awal)</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-48">Stok Akhir Fisik</th>
                            <th class="px-5 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wider text-center w-36">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-150">
                        <?php foreach ($items as $i): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-5 py-3.5">
                                <p class="font-bold text-slate-700"><?= sanitize($i['nama_barang']) ?></p>
                                <p class="text-[11px] text-slate-400 mt-0.5 font-mono"><?= sanitize($i['barcode'] ?: '—') ?></p>
                                <input type="hidden" id="awal_<?= $i['id_barang'] ?>" value="<?= $i['stok_awal'] ?>">
                            </td>
                            <td class="px-5 py-3.5 text-center font-semibold text-slate-600 font-mono">
                                <?= number_format($i['stok_awal']) ?> <span class="text-xs text-slate-450 font-normal"><?= sanitize($i['satuan'] ?: 'Pcs') ?></span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="relative flex items-center justify-center">
                                    <input type="number" name="stok_akhir[<?= $i['id_barang'] ?>]" value="<?= $i['stok_akhir'] ?>" min="0"
                                           id="akhir_<?= $i['id_barang'] ?>" oninput="hitungSelisih(<?= $i['id_barang'] ?>)"
                                           class="w-full text-center px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all text-sm font-bold">
                                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                        <span class="text-xs font-semibold text-slate-400"><?= sanitize($i['satuan'] ?: 'Pcs') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center font-bold font-mono text-slate-500" id="selisih_<?= $i['id_barang'] ?>">
                                —
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer Action -->
            <div class="px-6 py-4 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-3">
                <a href="<?= $sistem ?>/<?= $current_menu ?>" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 text-sm font-semibold transition-colors">Batal</a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Ajukan Ulang
                </button>
            </div>
        </div>
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

// Jalankan selisih awal saat reload
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($items as $i): ?>
    hitungSelisih(<?= $i['id_barang'] ?>);
    <?php endforeach; ?>
});
</script>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Pengajuan Berhasil!',
    text: 'Revisi Stock Opname berhasil disimpan dan dikirim kembali.',
    timer: 2000,
    showConfirmButton: false
}).then(() => {
    window.location.href = '<?= $sistem ?>/<?= $current_menu ?>';
});
</script>
<?php endif; ?>