<?php
// Handle POST action for Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    if (!canDo('approvalso', 'edit')) {
        echo json_encode(['status' => 'error', 'msg' => 'Anda tidak memiliki hak akses untuk menyetujui data ini.']);
        exit;
    }

    $no_so = $_POST['no_so'] ?? '';
    if (!$no_so) {
        echo json_encode(['status' => 'error', 'msg' => 'Nomor SO tidak valid.']);
        exit;
    }

    // Ambil detail SO untuk keperluan update stok atau audit
    $stDetail = $conn->prepare("SELECT id_outlet, id_barang, stok_akhir, status_approval FROM stock_opname WHERE no_so = ?");
    $stDetail->execute([$no_so]);
    $items = $stDetail->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        echo json_encode(['status' => 'error', 'msg' => 'Data Stock Opname tidak ditemukan.']);
        exit;
    }

    if ($items[0]['status_approval'] !== 'Pending') {
        echo json_encode(['status' => 'error', 'msg' => 'Stock opname ini sudah diproses sebelumnya.']);
        exit;
    }

    $conn->beginTransaction();
    try {
        if ($_POST['action'] === 'approve_so') {
            // 1. Update status_approval di stock_opname
            $upd = $conn->prepare("
                UPDATE stock_opname 
                SET status_approval = 'Approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP
                WHERE no_so = ?
            ");
            $upd->execute([$_SESSION['user_id'], $no_so]);

            // 2. Sinkronisasikan / Update stok_outlet fisik ke stok_akhir stock opname
            $updStok = $conn->prepare("
                INSERT INTO stok_outlet (id_outlet, id_barang, stok)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE stok = VALUES(stok)
            ");

            foreach ($items as $item) {
                $updStok->execute([
                    $item['id_outlet'],
                    $item['id_barang'],
                    $item['stok_akhir']
                ]);
            }

            writeAuditLog('UPDATE', 'stock_opname', 0, "Menyetujui Stock Opname harian $no_so. Stok outlet berhasil disesuaikan.");
            $msg = 'Stock opname berhasil disetujui! Stok outlet telah disesuaikan.';
        } elseif ($_POST['action'] === 'reject_so') {
            $catatan = trim($_POST['catatan'] ?? '');
            if (!$catatan) {
                throw new Exception('Catatan revisi/penolakan harus diisi.');
            }

            // Update status_approval menjadi Rejected & berikan catatan revisi
            $upd = $conn->prepare("
                UPDATE stock_opname 
                SET status_approval = 'Rejected', catatan_reject = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP
                WHERE no_so = ?
            ");
            $upd->execute([$catatan, $_SESSION['user_id'], $no_so]);

            writeAuditLog('UPDATE', 'stock_opname', 0, "Menolak Stock Opname harian $no_so dengan catatan: $catatan");
            $msg = 'Stock opname ditolak dan dikembalikan ke staff untuk direvisi.';
        } else {
            throw new Exception('Aksi tidak valid.');
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'msg' => $msg]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

if (!canDo('approvalso', 'view')) exit;
global $conn;

$no_so = $_GET['id'] ?? $_GET['keycode'] ?? '';
if (!$no_so) exit;

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
    $items = $st->fetchAll();
} catch (Exception $e) {
    $items = [];
}

if (!$items) {
    echo "<div class='p-8 text-center text-red-500 font-medium'><i class='fa-solid fa-triangle-exclamation text-2xl mb-2 block'></i>Stock Opname tidak ditemukan.</div>";
    exit;
}

$first = $items[0];
?>

<!-- Modal Header -->
<div class="px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center shadow-sm border border-indigo-100/50">
            <i class="fa-solid fa-signature text-xl animate-pulse"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-slate-800">Tinjau Stock Opname</h3>
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
            <div class="w-8 h-8 bg-indigo-500/10 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-store text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Outlet</p>
                <p class="text-sm font-bold text-slate-800 leading-tight"><?= sanitize($first['nama_outlet'] ?? 'Tanpa Outlet') ?></p>
            </div>
        </div>
        <?php
        $bulanNama = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $is_monthly = !empty($first['periode_bulan']);
        ?>
        <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
            <div class="w-8 h-8 bg-sky-500/10 text-sky-600 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-calendar-user text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tanggal / Periode & Operator</p>
                <?php if ($is_monthly): ?>
                    <p class="text-sm font-bold text-slate-800 leading-tight"><?= $bulanNama[(int)$first['periode_bulan']] ?? 'Bulan ?' ?> <?= $first['periode_tahun'] ?></p>
                    <p class="text-xs text-slate-500 font-semibold mt-1">Tanggal Pengajuan: <?= date('d M Y', strtotime($first['tanggal'])) ?> | Staf: <?= sanitize($first['operator'] ?? 'System') ?></p>
                <?php else: ?>
                    <p class="text-sm font-bold text-slate-800 leading-tight"><?= date('d F Y', strtotime($first['tanggal'])) ?></p>
                    <p class="text-xs text-slate-500 font-semibold mt-1">Staf Pengaju: <?= sanitize($first['operator'] ?? 'System') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Items Section -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Perbandingan Stok & Selisih</p>
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
    <button type="button" onclick="closeLgModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 text-sm font-semibold transition-colors">Batal</button>
    
    <?php if ($first['status_approval'] === 'Pending' && canDo('approvalso', 'edit')): ?>
    <button type="button" onclick="prosesApprovalSO('<?= $no_so ?>', 'reject_so')"
            class="px-5 py-2.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 text-sm font-semibold transition-all border border-rose-200 hover:border-rose-300 inline-flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-xmark"></i> Tolak & Minta Revisi
    </button>
    <button type="button" onclick="prosesApprovalSO('<?= $no_so ?>', 'approve_so')"
            class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-bold transition-all shadow-sm shadow-indigo-500/30 inline-flex items-center gap-2">
        <i class="fa-solid fa-check"></i> Setujui (Approve)
    </button>
    <?php endif; ?>
</div>

<script>
function prosesApprovalSO(noSO, actionType) {
    let title = actionType === 'approve_so' ? 'Konfirmasi Approval' : 'Tolak & Minta Revisi';
    let text = actionType === 'approve_so' 
        ? 'Stok outlet akan <b>di-update permanen</b> sesuai dengan angka perhitungan fisik yang diajukan.' 
        : 'Masukkan catatan alasan penolakan agar staf dapat melakukan perbaikan.';
    
    if (actionType === 'approve_so') {
        Swal.fire({
            title: title,
            html: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, Setujui',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                execAjaxSO(noSO, actionType, '');
            }
        });
    } else {
        // Tampilkan prompt input untuk alasan reject
        Swal.fire({
            title: title,
            text: 'Tuliskan catatan revisi untuk staf:',
            input: 'textarea',
            inputPlaceholder: 'Contoh: Hitung ulang stasiun Barista, jumlah susu tidak sesuai...',
            inputAttributes: { 'aria-label': 'Tuliskan catatan revisi' },
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Kirim Tolak',
            cancelButtonText: 'Batal',
            preConfirm: (value) => {
                if (!value.trim()) {
                    Swal.showValidationMessage('Catatan revisi wajib diisi!');
                }
                return value;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                execAjaxSO(noSO, actionType, result.value);
            }
        });
    }
}

function execAjaxSO(noSO, actionType, catatan) {
    Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    const fd = new FormData();
    fd.append('action', actionType);
    fd.append('no_so', noSO);
    if (actionType === 'reject_so') {
        fd.append('catatan', catatan);
    }
    
    fetch('<?= $sistem ?>/approvalso/v/' + noSO, { 
        method: 'POST', 
        body: fd, 
        headers: { 'X-Requested-With': 'XMLHttpRequest' } 
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi gagal atau sesi habis.' }));
}
</script>