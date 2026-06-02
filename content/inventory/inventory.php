<?php
if (!canDo('inventory', 'view')) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses untuk melihat inventory.</div>";
    exit;
}

global $conn, $sistem;

// Ambil data inventory
try {
    $invList = $conn->query("
        SELECT i.*, b.nama_barang, b.barcode, b.satuan, b.kategori, t.nama_tipe,
        (SELECT SUM(qty) FROM barang_masuk bm WHERE bm.id_barang = i.id_barang) as total_masuk
        FROM inventory i
        JOIN barang b ON i.id_barang = b.id_barang
        LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
        ORDER BY b.nama_barang ASC
    ")->fetchAll();
} catch (Exception $e) {
    $invList = [];
}
// Handle AJAX History Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_history') {
    ob_clean(); // Bersihkan buffer agar layout sistem.php tidak ikut ter-render
    $id_barang = (int)($_POST['id_barang'] ?? 0);
    try {
        $histories = $conn->prepare("
            SELECT 
                'Masuk' as tipe,
                bm.id_masuk as id_trans,
                bm.tanggal,
                bm.qty,
                bm.keterangan,
                COALESCE(bm.supplier_lainnya, s.nama_supplier, 'Tanpa Supplier') as pihak_terkait,
                bm.created_at
            FROM barang_masuk bm
            LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
            WHERE bm.id_barang = ?
            
            UNION ALL
            
            SELECT 
                'Keluar' as tipe,
                bk.id_keluar as id_trans,
                bk.tanggal,
                bk.qty,
                bk.keterangan,
                COALESCE(o.nama_outlet, 'Tanpa Outlet') as pihak_terkait,
                bk.created_at
            FROM barang_keluar bk
            LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
            WHERE bk.id_barang = ?
            
            ORDER BY tanggal DESC, created_at DESC
            LIMIT 40
        ");
        $histories->execute([$id_barang, $id_barang]);
        $data = $histories->fetchAll();

        if (empty($data)) {
            echo "<div class='text-center py-8 text-slate-400'><i class='fa-solid fa-folder-open text-3xl mb-2 text-slate-200 block'></i>Belum ada histori transaksi.</div>";
        } else {
            foreach ($data as $h) {
                if ($h['tipe'] === 'Masuk') {
                    $color = 'emerald';
                    $sign = '+';
                    $icon = 'fa-arrow-down';
                    $lbl = 'Dari: ' . sanitize($h['pihak_terkait']);
                } else {
                    $color = 'rose';
                    $sign = '-';
                    $icon = 'fa-arrow-up';
                    $lbl = 'Ke: ' . sanitize($h['pihak_terkait']);
                }

                echo "
                <div class='p-3 border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-colors'>
                    <div class='flex justify-between items-start mb-1'>
                        <span class='text-xs font-bold text-slate-600'>" . date('d M Y', strtotime($h['tanggal'])) . " <span class='font-normal text-slate-400 ml-1'>({$h['tipe']})</span></span>
                        <span class='text-xs font-bold text-{$color}-600 bg-{$color}-50 px-2 py-0.5 rounded'>{$sign}" . number_format($h['qty'],0,',','.') . "</span>
                    </div>
                    <p class='text-[11px] font-medium text-slate-600 mb-0.5'><i class='fa-solid {$icon} text-slate-300 mr-1.5'></i> {$lbl}</p>
                    <p class='text-[10px] text-slate-400 italic'>" . sanitize($h['keterangan'] ?: 'Tidak ada keterangan') . "</p>
                </div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='text-red-500 text-xs p-3'>Error loading history: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    exit;
}
?>

<div class="fade-up space-y-5">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Inventory Gudang</h1>
            <p class="text-slate-500 text-sm mt-0.5">Pantau jumlah stok dan alokasi rak untuk semua item barang.</p>
        </div>
    </div>

    <!-- Filter & Search (Bisa pakai javascript filtering) -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="relative w-full sm:w-96">
            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" id="searchInput" placeholder="Cari nama barang, barcode, atau rak..."
                   class="w-full pl-11 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition-all bg-slate-50">
        </div>
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <button onclick="window.print()" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 bg-white hover:bg-slate-50 text-slate-600 px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-semibold transition-colors">
                <i class="fa-solid fa-print"></i> Cetak PDF
            </button>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tblInventory">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Info Barang</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kategori / Tipe</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Masuk</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">Sisa Stok Sistem</th>
                        <th class="px-5 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($invList)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-boxes-stacked text-3xl mb-2 block text-slate-200"></i>
                            Belum ada data inventory terdaftar.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = 1; foreach ($invList as $inv): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors inv-row" 
                        data-search="<?= strtolower($inv['nama_barang'].' '.$inv['barcode']) ?>">
                        
                        <!-- No. Urut -->
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>

                        <!-- Info Barang -->
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800"><?= sanitize($inv['nama_barang']) ?></p>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                <?= $inv['barcode'] ? '<i class="fa-solid fa-barcode"></i> '.$inv['barcode'] : 'Tanpa Barcode' ?>
                            </p>
                        </td>

                        <!-- Kategori & Tipe -->
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-600 border border-indigo-100 mb-1">
                                <?= sanitize($inv['kategori'] ?: '-') ?>
                            </span><br>
                            <span class="text-[10px] text-slate-500">Tipe: <?= sanitize($inv['nama_tipe'] ?: 'Belum Diatur') ?></span>
                        </td>

                        <!-- Total Masuk -->
                        <td class="px-5 py-3.5 text-right">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-sky-50 text-sky-700 font-medium border border-sky-200 text-xs">
                                <i class="fa-solid fa-arrow-down text-[10px]"></i> <?= number_format($inv['total_masuk'] ?: 0, 0, ',', '.') ?>
                            </span>
                        </td>

                        <!-- Stok Sistem -->
                        <td class="px-5 py-3.5 text-right">
                            <p class="text-sm font-bold <?= $inv['stok'] > 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                                <?= number_format($inv['stok'], 0, ',', '.') ?>
                            </p>
                            <p class="text-[10px] text-slate-400 font-medium uppercase"><?= sanitize($inv['satuan'] ?: 'Pcs') ?></p>
                        </td>

                        <!-- Aksi -->
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" onclick="showHistory(<?= $inv['id_barang'] ?>, '<?= htmlspecialchars(addslashes($inv['nama_barang'])) ?>')"
                                   class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center hover:bg-indigo-100 transition-colors border border-indigo-100" title="Kartu Stok (Riwayat)">
                                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                </button>
                                <?php if (canDo('inventory', 'edit')): ?>
                                <a href="<?= $sistem ?>/inventory/edit?id=<?= $inv['id_inventory'] ?>"
                                   class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center hover:bg-sky-100 transition-colors border border-sky-100" title="Edit Stok">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <p class="text-xs text-slate-500 font-medium">Total: <span class="font-bold text-slate-700"><?= count($invList) ?></span> jenis barang tercatat.</p>
        </div>
    </div>
</div>

<!-- Modal History -->
<div id="modalHistory" class="fixed inset-0 z-[100] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0" id="modalHistoryBackdrop" onclick="closeHistory()"></div>
    
    <!-- Modal Content -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md scale-95 opacity-0 transition-all duration-300" id="modalHistoryPanel">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Kartu Stok (Riwayat)</h3>
                    <p class="text-[10px] text-slate-500 font-medium mt-0.5" id="historyBarangName">Nama Barang</p>
                </div>
                <button onclick="closeHistory()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <!-- Body -->
            <div class="p-0 max-h-[60vh] overflow-y-auto" id="historyContent">
                <div class="p-6 text-center text-slate-400">
                    <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-indigo-500"></i>
                    <p class="text-xs">Memuat history...</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 text-center">
                <p class="text-[10px] text-slate-400">Menampilkan hingga 40 transaksi masuk & keluar terbaru.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Simple JS Search Filter
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('.inv-row');
    rows.forEach(row => {
        if (row.getAttribute('data-search').includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Modal History Logic
const modalHistory = document.getElementById('modalHistory');
const backdrop = document.getElementById('modalHistoryBackdrop');
const panel = document.getElementById('modalHistoryPanel');
const content = document.getElementById('historyContent');
const title = document.getElementById('historyBarangName');

function showHistory(idBarang, namaBarang) {
    // Tampilkan modal
    modalHistory.classList.remove('hidden');
    // Animasi masuk
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('scale-95', 'opacity-0');
    }, 10);

    title.innerText = namaBarang;
    content.innerHTML = `
        <div class="p-6 text-center text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-indigo-500"></i>
            <p class="text-xs">Memuat history...</p>
        </div>
    `;

    // Fetch data
    fetch('<?= $sistem ?>/inventory', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            'action': 'get_history',
            'id_barang': idBarang
        })
    })
    .then(response => response.text())
    .then(html => {
        content.innerHTML = html;
    })
    .catch(err => {
        content.innerHTML = `<div class='text-red-500 text-xs p-5 text-center'>Gagal memuat histori.</div>`;
    });
}

function closeHistory() {
    // Animasi keluar
    backdrop.classList.add('opacity-0');
    panel.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modalHistory.classList.add('hidden');
    }, 300);
}
</script>
