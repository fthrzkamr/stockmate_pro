<?php
if (!canDo('kategoribarang', 'view') && !isAdmin()) {
    echo "<div class='p-4 text-red-600 bg-red-50 border border-red-200 rounded-xl text-sm'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit;
}

global $conn, $sistem;

// ── AJAX HANDLER ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'add_kategori') {
            $nama = trim($_POST['nama_kategori'] ?? '');
            if (!$nama) throw new Exception('Nama kategori wajib diisi.');

            $stmt = $conn->prepare("INSERT INTO kategori_barang (nama_kategori) VALUES (?)");
            $stmt->execute([$nama]);
            $id = $conn->lastInsertId();

            writeAuditLog('CREATE', 'kategori_barang', $id, "Menambahkan kategori barang baru: $nama");
            echo json_encode(['status' => 'success', 'msg' => 'Kategori baru berhasil ditambahkan!']);
            exit;
        }

        if ($action === 'edit_kategori') {
            $id = (int)($_POST['id_kategori'] ?? 0);
            $nama = trim($_POST['nama_kategori'] ?? '');
            if (!$id || !$nama) throw new Exception('Data tidak valid.');

            $stmt = $conn->prepare("UPDATE kategori_barang SET nama_kategori = ? WHERE id_kategori = ?");
            $stmt->execute([$nama, $id]);

            writeAuditLog('UPDATE', 'kategori_barang', $id, "Memperbarui nama kategori barang: $nama");
            echo json_encode(['status' => 'success', 'msg' => 'Nama kategori berhasil diperbarui!']);
            exit;
        }

        if ($action === 'delete_kategori') {
            $id = (int)($_POST['id_kategori'] ?? 0);
            if (!$id) throw new Exception('ID kategori tidak valid.');

            $conn->prepare("DELETE FROM tipe_barang WHERE id_kategori = ?")->execute([$id]);
            $conn->prepare("DELETE FROM kategori_barang WHERE id_kategori = ?")->execute([$id]);

            writeAuditLog('DELETE', 'kategori_barang', $id, "Menghapus kategori barang ID #$id");
            echo json_encode(['status' => 'success', 'msg' => 'Kategori dan tipe terkait berhasil dihapus!']);
            exit;
        }

        if ($action === 'add_tipe') {
            $id_kategori = (int)($_POST['id_kategori'] ?? 0);
            $nama_tipe = trim($_POST['nama_tipe'] ?? '');
            if (!$id_kategori || !$nama_tipe) throw new Exception('Kategori dan nama tipe wajib diisi.');

            $stmt = $conn->prepare("INSERT INTO tipe_barang (id_kategori, nama_tipe) VALUES (?, ?)");
            $stmt->execute([$id_kategori, $nama_tipe]);
            $id = $conn->lastInsertId();

            writeAuditLog('CREATE', 'tipe_barang', $id, "Menambahkan tipe barang: $nama_tipe");
            echo json_encode(['status' => 'success', 'msg' => 'Tipe barang berhasil ditambahkan!']);
            exit;
        }

        if ($action === 'edit_tipe') {
            $id = (int)($_POST['id_tipe'] ?? 0);
            $nama_tipe = trim($_POST['nama_tipe'] ?? '');
            $id_kategori = (int)($_POST['id_kategori'] ?? 0);
            if (!$id || !$nama_tipe) throw new Exception('Data tidak valid.');

            if ($id_kategori > 0) {
                $stmt = $conn->prepare("UPDATE tipe_barang SET nama_tipe = ?, id_kategori = ? WHERE id_tipe = ?");
                $stmt->execute([$nama_tipe, $id_kategori, $id]);
            } else {
                $stmt = $conn->prepare("UPDATE tipe_barang SET nama_tipe = ? WHERE id_tipe = ?");
                $stmt->execute([$nama_tipe, $id]);
            }

            writeAuditLog('UPDATE', 'tipe_barang', $id, "Memperbarui tipe barang: $nama_tipe");
            echo json_encode(['status' => 'success', 'msg' => 'Tipe barang berhasil diperbarui!']);
            exit;
        }

        if ($action === 'delete_tipe') {
            $id = (int)($_POST['id_tipe'] ?? 0);
            if (!$id) throw new Exception('ID tipe tidak valid.');

            $conn->prepare("DELETE FROM tipe_barang WHERE id_tipe = ?")->execute([$id]);

            writeAuditLog('DELETE', 'tipe_barang', $id, "Menghapus tipe barang ID #$id");
            echo json_encode(['status' => 'success', 'msg' => 'Tipe barang berhasil dihapus!']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        exit;
    }
}

// Filter & Pagination
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

$whereClauses = ["1=1"];
$params = [];

if ($search) {
    $whereClauses[] = "(t.nama_tipe LIKE ? OR k.nama_kategori LIKE ? OR EXISTS (SELECT 1 FROM barang b2 WHERE b2.id_tipe = t.id_tipe AND b2.nama_barang LIKE ?))";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(" AND ", $whereClauses);

// Count Total Data
try {
    $stCount = $conn->prepare("
        SELECT COUNT(t.id_tipe) 
        FROM tipe_barang t 
        LEFT JOIN kategori_barang k ON t.id_kategori = k.id_kategori 
        WHERE $whereSql
    ");
    $stCount->execute($params);
    $totalData = (int)$stCount->fetchColumn();
} catch (Exception $e) {
    $totalData = 0;
}

// Fetch Master Kategori & Tipe with linked barang names
try {
    $sql = "
        SELECT 
            t.id_tipe,
            t.nama_tipe,
            k.id_kategori,
            k.nama_kategori,
            (SELECT GROUP_CONCAT(b.nama_barang ORDER BY b.id_barang DESC SEPARATOR '||') FROM barang b WHERE b.id_tipe = t.id_tipe) as items_str,
            (SELECT COUNT(b.id_barang) FROM barang b WHERE b.id_tipe = t.id_tipe) as total_barang
        FROM tipe_barang t
        LEFT JOIN kategori_barang k ON t.id_kategori = k.id_kategori
        WHERE $whereSql
        ORDER BY k.id_kategori ASC, t.id_tipe ASC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $listData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // List all categories for dropdowns
    $kategori_list = $conn->query("SELECT * FROM kategori_barang ORDER BY id_kategori ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $listData = [];
    $kategori_list = [];
}
?>

<div class="fade-up space-y-5">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Master Kategori & Tipe Barang</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kelola data kategori utama, tipe barang, dan barang yang terhubung.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="bukaModalKategori()"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm shadow-indigo-100">
                <i class="fa-solid fa-plus text-xs"></i> Tambah Kategori
            </button>
            <button type="button" onclick="bukaModalTipe()"
                    class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm shadow-sky-100">
                <i class="fa-solid fa-plus-circle text-xs"></i> Tambah Tipe
            </button>
        </div>
    </div>

    <!-- Filter Card (Style Retur Supplier) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="menu" value="kategoribarang">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            
            <div class="flex-1">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Pencarian</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari kategori, tipe, atau nama barang..."
                           class="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 transition-all bg-slate-50">
                </div>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full sm:w-auto px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-semibold transition-all">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Table Card (Style Retur Supplier & Master Barang) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mt-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/20">
            <h3 class="text-sm font-bold text-slate-700">Data Master Kategori</h3>
            <?php echo generateShowEntries($limit, 'kategoribarang', urlencode($search)); ?>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-36">Kategori</th>
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-56">Tipe Barang</th>
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Nama Barang Terkait</th>
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($listData)): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-folder-open text-3xl mb-2 block text-slate-200"></i>
                            Tidak ada data kategori/tipe barang yang ditemukan.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($listData as $row): 
                        $items = !empty($row['items_str']) ? explode('||', $row['items_str']) : [];
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        
                        <!-- No. Urut -->
                        <td class="px-5 py-4 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>

                        <!-- Kategori -->
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 font-bold text-xs border border-indigo-100">
                                    <?= htmlspecialchars($row['nama_kategori'] ?: '-') ?>
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-slate-800">Kategori <?= htmlspecialchars($row['nama_kategori'] ?: '-') ?></p>
                                    <button type="button" onclick="editKategori(<?= $row['id_kategori'] ?>, '<?= htmlspecialchars(addslashes($row['nama_kategori'])) ?>')" class="text-[10px] text-indigo-600 hover:underline">
                                        Edit Kategori
                                    </button>
                                </div>
                            </div>
                        </td>

                        <!-- Tipe Barang -->
                        <td class="px-5 py-4">
                            <p class="text-sm font-bold text-slate-800"><?= htmlspecialchars($row['nama_tipe']) ?></p>
                            <span class="text-[10px] text-slate-400 font-medium">ID Tipe: #<?= $row['id_tipe'] ?></span>
                        </td>

                        <!-- Nama Barang Terkait -->
                        <td class="px-5 py-4">
                            <?php if (empty($items)): ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-400 border border-slate-200">
                                <i class="fa-solid fa-circle-minus text-[10px]"></i> Belum ada barang terkait
                            </span>
                            <?php else: ?>
                            <div class="space-y-1.5 max-w-xl">
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach (array_slice($items, 0, 5) as $itemNama): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-100">
                                        <i class="fa-solid fa-box text-[10px] text-sky-400"></i> <?= htmlspecialchars($itemNama) ?>
                                    </span>
                                    <?php endforeach; ?>
                                    <?php if (count($items) > 5): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        +<?= count($items) - 5 ?> barang lainnya
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium">Total: <b><?= count($items) ?></b> barang terdaftar menggunakan tipe ini.</p>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="px-5 py-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button type="button" onclick="editTipe(<?= $row['id_tipe'] ?>, '<?= htmlspecialchars(addslashes($row['nama_tipe'])) ?>', <?= $row['id_kategori'] ?>)"
                                        class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center transition-colors border border-amber-100" title="Edit Tipe">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <button type="button" onclick="hapusTipe(<?= $row['id_tipe'] ?>, '<?= htmlspecialchars(addslashes($row['nama_tipe'])) ?>')"
                                        class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition-colors border border-rose-100" title="Hapus Tipe">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar (Style Retur Supplier) -->
        <?php echo generatePagination($totalData, $limit, $sistem . '/kategoribarang', $page, 'kategoribarang', urlencode($search)); ?>
    </div>

</div>

<!-- Modal Form Kategori -->
<div id="modalKat" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="modalKatContent">
        <form id="formKat" onsubmit="simpanKategori(event)">
            <input type="hidden" name="action" id="katAction" value="add_kategori">
            <input type="hidden" name="id_kategori" id="katId" value="">
            
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800" id="katTitle">Tambah Kategori Baru</h3>
                <button type="button" onclick="tutupModalKat()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Nama Kategori <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_kategori" id="katInputNama" placeholder="Contoh: 1, 2, 3 atau Nama Kategori..." class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 outline-none" required>
                    <p class="text-[10px] text-slate-400 mt-1">Nama atau angka kategori yang akan tampil di pilihan form.</p>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button type="button" onclick="tutupModalKat()" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-200 text-sm font-semibold transition-colors border border-slate-200">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-bold shadow-sm transition-colors">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Form Tipe -->
<div id="modalTipe" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="modalTipeContent">
        <form id="formTipe" onsubmit="simpanTipe(event)">
            <input type="hidden" name="action" id="tipeAction" value="add_tipe">
            <input type="hidden" name="id_tipe" id="tipeId" value="">
            
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800" id="tipeTitle">Tambah Tipe Barang</h3>
                <button type="button" onclick="tutupModalTipe()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div id="boxKatSelect">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Induk Kategori <span class="text-red-500">*</span></label>
                    <select name="id_kategori" id="tipeSelectKat" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-sky-400 focus:ring-2 focus:ring-sky-500/20 outline-none bg-white" required>
                        <option value="">— Pilih Kategori —</option>
                        <?php foreach ($kategori_list as $k): ?>
                        <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Nama Tipe Barang <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_tipe" id="tipeInputNama" placeholder="Contoh: Bahan Baku Makanan..." class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-sky-400 focus:ring-2 focus:ring-sky-500/20 outline-none" required>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button type="button" onclick="tutupModalTipe()" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-200 text-sm font-semibold transition-colors border border-slate-200">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-sky-600 text-white hover:bg-sky-700 text-sm font-bold shadow-sm transition-colors">
                    Simpan Tipe
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal Kategori Logic
function bukaModalKategori() {
    document.getElementById('katTitle').textContent = 'Tambah Kategori Baru';
    document.getElementById('katAction').value = 'add_kategori';
    document.getElementById('katId').value = '';
    document.getElementById('katInputNama').value = '';

    const modal = document.getElementById('modalKat');
    const content = document.getElementById('modalKatContent');
    modal.classList.remove('hidden');
    setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); }, 10);
}

function editKategori(id, nama) {
    document.getElementById('katTitle').textContent = 'Edit Nama Kategori';
    document.getElementById('katAction').value = 'edit_kategori';
    document.getElementById('katId').value = id;
    document.getElementById('katInputNama').value = nama;

    const modal = document.getElementById('modalKat');
    const content = document.getElementById('modalKatContent');
    modal.classList.remove('hidden');
    setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); }, 10);
}

function tutupModalKat() {
    const modal = document.getElementById('modalKat');
    const content = document.getElementById('modalKatContent');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => { modal.classList.add('hidden'); }, 300);
}

function simpanKategori(e) {
    e.preventDefault();
    Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    const fd = new FormData(document.getElementById('formKat'));
    fetch('<?= $sistem ?>/kategoribarang', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
        }
    }).catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi ke server gagal.' }));
}

function hapusKategori(id, nama) {
    Swal.fire({
        title: 'Hapus Kategori ' + nama + '?',
        html: `Menghapus kategori ini akan <b>menghapus seluruh tipe barang yang terhubung</b> dengannya!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus Semua!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const fd = new FormData();
            fd.append('action', 'delete_kategori');
            fd.append('id_kategori', id);

            fetch('<?= $sistem ?>/kategoribarang', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                }
            }).catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi ke server gagal.' }));
        }
    });
}

// Modal Tipe Logic
function bukaModalTipe(idKategori = null) {
    document.getElementById('tipeTitle').textContent = 'Tambah Tipe Barang Baru';
    document.getElementById('tipeAction').value = 'add_tipe';
    document.getElementById('tipeId').value = '';
    document.getElementById('tipeInputNama').value = '';
    
    const boxKat = document.getElementById('boxKatSelect');
    const selectKat = document.getElementById('tipeSelectKat');
    boxKat.classList.remove('hidden');
    selectKat.required = true;
    
    if (idKategori) {
        selectKat.value = idKategori;
    }

    const modal = document.getElementById('modalTipe');
    const content = document.getElementById('modalTipeContent');
    modal.classList.remove('hidden');
    setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); }, 10);
}

function editTipe(id, nama, idKategori = null) {
    document.getElementById('tipeTitle').textContent = 'Edit Tipe Barang';
    document.getElementById('tipeAction').value = 'edit_tipe';
    document.getElementById('tipeId').value = id;
    document.getElementById('tipeInputNama').value = nama;
    
    const boxKat = document.getElementById('boxKatSelect');
    const selectKat = document.getElementById('tipeSelectKat');
    boxKat.classList.remove('hidden');
    selectKat.required = true;
    if (idKategori) {
        selectKat.value = idKategori;
    }

    const modal = document.getElementById('modalTipe');
    const content = document.getElementById('modalTipeContent');
    modal.classList.remove('hidden');
    setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); }, 10);
}

function tutupModalTipe() {
    const modal = document.getElementById('modalTipe');
    const content = document.getElementById('modalTipeContent');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => { modal.classList.add('hidden'); }, 300);
}

function simpanTipe(e) {
    e.preventDefault();
    Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    const fd = new FormData(document.getElementById('formTipe'));
    fetch('<?= $sistem ?>/kategoribarang', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
        }
    }).catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi ke server gagal.' }));
}

function hapusTipe(id, nama) {
    Swal.fire({
        title: 'Hapus Tipe ' + nama + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const fd = new FormData();
            fd.append('action', 'delete_tipe');
            fd.append('id_tipe', id);

            fetch('<?= $sistem ?>/kategoribarang', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                }
            }).catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi ke server gagal.' }));
        }
    });
}
</script>
