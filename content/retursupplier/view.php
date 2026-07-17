<?php
if (!canDo('retursupplier', 'view')) {
    echo "<div class='p-4 text-red-650 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses.</div>";
    exit;
}

global $conn, $sistem;

$id = (int)($_GET['id'] ?? $_GET['keycode'] ?? 0);

try {
    $st = $conn->prepare("
        SELECT r.*, b.nama_barang, b.barcode, b.satuan, b.kategori,
               bm.kode_transaksi as kode_masuk, bm.tanggal as tgl_masuk, bm.qty as qty_masuk,
               COALESCE(s.nama_supplier, bm.supplier_lainnya, 'Tanpa Supplier') as nama_supplier,
               u.nama as nama_user
        FROM retur_supplier r
        JOIN barang b ON r.id_barang = b.id_barang
        LEFT JOIN barang_masuk bm ON r.id_masuk = bm.id_masuk
        LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
        LEFT JOIN users u ON r.id_user = u.id_user
        WHERE r.id_retur = ?
    ");
    $st->execute([$id]);
    $data = $st->fetch();

    if (!$data) {
        echo "<script>window.location.href='$sistem/retursupplier';</script>";
        exit;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<div class="fade-up max-w-4xl mx-auto space-y-5">
    
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="<?= $sistem ?>/retursupplier" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Detail Retur Pembelian</h1>
                <p class="text-slate-500 text-sm">Rincian pengembalian barang ke supplier.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Main Card -->
        <div class="md:col-span-2 space-y-5">
            
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <p class="text-sm font-semibold text-slate-700">
                        <i class="fa-solid fa-boxes-stacked text-indigo-500 mr-2"></i>Informasi Barang Diretur
                    </p>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-rose-50 text-rose-700 font-bold border border-rose-200 text-xs">
                        <?= sanitize($data['kode_retur']) ?>
                    </span>
                </div>
                
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-start gap-4 mb-6 pb-6 border-b border-slate-100">
                        <div class="w-16 h-16 rounded-2xl bg-indigo-50 flex items-center justify-center flex-shrink-0 border border-indigo-100">
                            <i class="fa-solid fa-box text-2xl text-indigo-400"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-800"><?= sanitize($data['nama_barang']) ?></h2>
                            <p class="text-sm text-slate-500 mt-1 font-mono"><i class="fa-solid fa-barcode mr-1"></i> <?= sanitize($data['barcode'] ?: 'Tanpa Barcode') ?></p>
                            <span class="inline-block mt-2 px-2.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200 uppercase">
                                <?= sanitize($data['kategori'] ?: 'Tanpa Kategori') ?>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-y-4 gap-x-6">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jumlah Diretur</p>
                            <p class="text-lg font-bold text-rose-600">-<?= number_format($data['qty'], 0, ',', '.') ?> <span class="text-sm font-medium text-slate-500"><?= sanitize($data['satuan']) ?></span></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Supplier Tujuan</p>
                            <p class="text-sm font-semibold text-slate-800"><?= sanitize($data['nama_supplier']) ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Alasan Retur</p>
                            <p class="text-sm text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100"><?= nl2br(sanitize($data['alasan'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sidebar Card -->
        <div class="space-y-5">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                    <p class="text-sm font-semibold text-slate-700">
                        <i class="fa-solid fa-circle-info text-sky-500 mr-2"></i>Status & Referensi
                    </p>
                </div>
                <div class="p-5 space-y-4">
                    
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tanggal Retur</p>
                        <p class="text-sm font-bold text-slate-800"><?= date('d M Y', strtotime($data['tanggal'])) ?></p>
                    </div>

                    <div class="h-px bg-slate-100 w-full"></div>

                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Referensi Barang Masuk</p>
                        <p class="text-sm font-bold text-sky-700 font-mono"><?= sanitize($data['kode_masuk']) ?></p>
                        <p class="text-xs text-slate-500 mt-1">Tgl Masuk: <?= date('d M Y', strtotime($data['tgl_masuk'])) ?></p>
                        <p class="text-xs text-slate-500">Total Masuk: <?= number_format($data['qty_masuk'], 0, ',', '.') ?> <?= sanitize($data['satuan']) ?></p>
                    </div>

                    <div class="h-px bg-slate-100 w-full"></div>

                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pencatat (User)</p>
                        <div class="flex items-center gap-2 mt-1">
                            <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 flex-shrink-0">
                                <i class="fa-solid fa-user text-[10px]"></i>
                            </div>
                            <p class="text-sm font-semibold text-slate-700"><?= sanitize($data['nama_user'] ?: 'Sistem') ?></p>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Waktu: <?= date('d/m/Y H:i', strtotime($data['created_at'])) ?></p>
                    </div>

                </div>
            </div>
            
        </div>
    </div>
</div>
