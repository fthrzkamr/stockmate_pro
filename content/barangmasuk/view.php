<?php
// Handle POST action for cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_transaction') {
    header('Content-Type: application/json');
    if (!canDo('barangmasuk', 'delete')) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak memiliki hak akses untuk membatalkan transaksi.']);
        exit;
    }

    $id_masuk = (int)($_POST['id_masuk'] ?? $_GET['keycode'] ?? $_GET['id'] ?? 0);
    if (!$id_masuk) {
        echo json_encode(['status' => 'error', 'msg' => 'ID Transaksi tidak valid.']);
        exit;
    }

    $conn->beginTransaction();
    try {
        // 1. Ambil detail barang masuk terlebih dahulu
        $st = $conn->prepare("
            SELECT bm.*, b.nama_barang, 
                   (COALESCE((SELECT SUM(qty) FROM barang_masuk WHERE id_barang = b.id_barang), 0) - 
                    COALESCE((SELECT SUM(qty) FROM barang_keluar WHERE id_barang = b.id_barang), 0)) as stok_sekarang 
            FROM barang_masuk bm
            JOIN barang b ON bm.id_barang = b.id_barang
            WHERE bm.id_masuk = ?
        ");
        $st->execute([$id_masuk]);
        $trx = $st->fetch();

        if (!$trx) {
            throw new Exception('Transaksi tidak ditemukan.');
        }

        // 2. Cek apakah pengurangan stok akan membuat stok menjadi minus
        // (Opsional: Biasanya boleh minus jika diizinkan, tapi lebih aman dicegah atau diberi warning)
        if ($trx['stok_sekarang'] < $trx['qty']) {
            throw new Exception("Tidak dapat membatalkan transaksi. Stok sekarang ({$trx['stok_sekarang']}) lebih kecil dari jumlah masuk ({$trx['qty']}).");
        }

        // 3. Hapus data transaksi barang masuk
        $del = $conn->prepare("DELETE FROM barang_masuk WHERE id_masuk = ?");
        $del->execute([$id_masuk]);

        // 5. Catat audit log pembatalan
        writeAuditLog(
            'DELETE', 
            'barang_masuk', 
            $id_masuk, 
            "Membatalkan penerimaan barang masuk: {$trx['nama_barang']} (Qty: {$trx['qty']})"
        );

        $conn->commit();
        echo json_encode(['status' => 'success', 'msg' => "Transaksi penerimaan {$trx['nama_barang']} berhasil dibatalkan."]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// Handle GET request to display details
if (!canDo('barangmasuk', 'view')) {
    echo "<div class='p-4 text-red-650 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat detail barang masuk.</div>";
    exit;
}

global $conn, $sistem;

$id_masuk = (int)($_GET['id'] ?? $_GET['keycode'] ?? 0);
if (!$id_masuk) {
    echo "<script>window.location.href='$sistem/barangmasuk';</script>";
    exit;
}

try {
    $st = $conn->prepare("
        SELECT bm.*, b.nama_barang, b.barcode, b.kategori, b.satuan, s.nama_supplier, s.telepon as supplier_telp, u.nama as operator
        FROM barang_masuk bm
        JOIN barang b ON bm.id_barang = b.id_barang
        LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
        LEFT JOIN users u ON bm.id_user = u.id_user
        WHERE bm.id_masuk = ?
    ");
    $st->execute([$id_masuk]);
    $trx = $st->fetch();
} catch (Exception $e) {
    $trx = null;
}

if (!$trx) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Transaksi tidak ditemukan.</div>";
    exit;
}
?>

<div class="fade-up max-w-xl mx-auto space-y-5">
    
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/barangmasuk" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Detail Penerimaan Barang</h1>
            <p class="text-slate-500 text-sm">Informasi lengkap transaksi masuk barang.</p>
        </div>
    </div>

    <!-- Detail Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        
        <!-- Header Info -->
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-sky-50 border border-sky-100 rounded-xl flex items-center justify-center text-sky-700 text-base">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">No. Penerimaan: #<?= $trx['id_masuk'] ?></h3>
                    <p class="text-xs text-slate-400">Diinput pada <?= date('d M Y H:i', strtotime($trx['created_at'])) ?></p>
                </div>
            </div>
            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold px-2 py-0.5 rounded-lg">
                Selesai
            </span>
        </div>

        <div class="p-6 space-y-4 divide-y divide-slate-100">
            
            <!-- Metadata -->
            <div class="grid grid-cols-2 gap-4 pb-4 text-xs">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-0.5">Tanggal Transaksi</span>
                    <span class="text-sm font-semibold text-slate-700"><?= date('d F Y', strtotime($trx['tanggal'])) ?></span>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-0.5">Operator Penerima</span>
                    <span class="text-sm font-semibold text-slate-700"><?= sanitize($trx['operator'] ?: 'System') ?></span>
                </div>
            </div>

            <!-- Barang Detail -->
            <div class="py-4 space-y-3">
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Informasi Barang</span>
                <div class="bg-slate-50 border border-slate-150 rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-slate-700"><?= sanitize($trx['nama_barang']) ?></p>
                        <p class="text-[10px] text-slate-400 mt-0.5">
                            Kategori: <?= sanitize($trx['kategori'] ?: 'Lainnya') ?> | Barcode: <?= sanitize($trx['barcode'] ?: '—') ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-slate-400">Jumlah Masuk</p>
                        <p class="text-base font-black text-slate-800"><?= number_format($trx['qty']) ?> <?= sanitize($trx['satuan'] ?: 'Pcs') ?></p>
                    </div>
                </div>
            </div>

            <!-- Supplier Detail -->
            <div class="py-4 space-y-2">
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Pemasok / Supplier</span>
                <?php if ($trx['id_supplier']): ?>
                <div class="border border-slate-200 rounded-xl p-3.5 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-700"><?= sanitize($trx['nama_supplier']) ?></p>
                        <p class="text-xs text-slate-450 mt-0.5">Hubungi: <?= sanitize($trx['supplier_telp'] ?: '—') ?></p>
                    </div>
                    <i class="fa-solid fa-building-user text-slate-300 text-lg"></i>
                </div>
                <?php else: ?>
                <p class="text-xs text-slate-450 italic">Penerimaan barang tanpa supplier (Pembelian lokal/langsung).</p>
                <?php endif; ?>
            </div>

            <!-- Keterangan -->
            <div class="pt-4">
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-1">Catatan / Keterangan</span>
                <p class="text-sm text-slate-600 bg-slate-50/50 border border-slate-100 rounded-xl p-3.5 italic">
                    <?= sanitize($trx['keterangan'] ?: 'Tidak ada catatan tambahan untuk transaksi ini.') ?>
                </p>
            </div>

        </div>

        <!-- Footer -->
        <?php if (canDo('barangmasuk', 'delete')): ?>
        <div class="flex justify-end px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <button onclick="cancelMasuk(<?= $trx['id_masuk'] ?>, '<?= sanitize($trx['nama_barang']) ?>', <?= $trx['qty'] ?>)"
                    class="inline-flex items-center gap-2 bg-rose-50 hover:bg-rose-100 text-rose-600 px-5 py-2 rounded-xl text-sm font-semibold transition-all border border-rose-100">
                <i class="fa-solid fa-trash-can"></i> Batalkan Transaksi
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function cancelMasuk(id, nama, qty) {
    Swal.fire({
        title: 'Batalkan Penerimaan Barang?',
        html: `Apakah Anda yakin ingin membatalkan penerimaan <b>${nama}</b> sebanyak <b>${qty} pcs</b>?<br><span class="text-xs text-rose-500 font-semibold">*Stok barang di Gudang Pusat akan dikurangi kembali.</span>`,
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
        fd.append('id_masuk', id);
        
        fetch('<?= $sistem ?>/barangmasuk/v/' + id, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil Dibatalkan!', text: res.msg, timer: 1500, showConfirmButton: false })
                        .then(() => window.location.href = '<?= $sistem ?>/barangmasuk');
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi gagal atau sesi habis.' }));
    });
}
</script>