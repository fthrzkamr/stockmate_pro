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
        if ($action === 'add_barang_kat') {
            $nama_barang = trim($_POST['nama_barang'] ?? '');
            $raw_id_kat  = $_POST['id_kategori'] ?? '';
            $new_kat_txt = trim($_POST['new_kategori'] ?? '');
            $raw_id_tipe = $_POST['id_tipe'] ?? '';
            $new_tipe_txt= trim($_POST['new_tipe'] ?? '');
            $satuan      = trim($_POST['satuan'] ?? 'Pcs');
            $min_stok    = (int)($_POST['min_stok'] ?? 5);

            if (!$nama_barang) throw new Exception('Nama barang wajib diisi.');

            // Proses Kategori (Ada/Baru)
            if ($raw_id_kat === 'new' || ($raw_id_kat === '' && $new_kat_txt !== '')) {
                if (!$new_kat_txt) throw new Exception('Nama kategori baru wajib diisi.');
                $stC = $conn->prepare("SELECT id_kategori FROM kategori_barang WHERE LOWER(nama_kategori) = LOWER(?)");
                $stC->execute([$new_kat_txt]);
                $id_kat = (int)$stC->fetchColumn();
                if (!$id_kat) {
                    $insC = $conn->prepare("INSERT INTO kategori_barang (nama_kategori) VALUES (?)");
                    $insC->execute([$new_kat_txt]);
                    $id_kat = (int)$conn->lastInsertId();
                }
            } else {
                $id_kat = (int)$raw_id_kat;
            }
            if (!$id_kat) throw new Exception('Kategori wajib dipilih atau dibuat baru.');

            // Proses Tipe Barang (Ada/Baru)
            if ($raw_id_tipe === 'new' || ($raw_id_tipe === '' && $new_tipe_txt !== '')) {
                if (!$new_tipe_txt) throw new Exception('Nama tipe baru wajib diisi.');
                $stT = $conn->prepare("SELECT id_tipe FROM tipe_barang WHERE id_kategori = ? AND LOWER(nama_tipe) = LOWER(?)");
                $stT->execute([$id_kat, $new_tipe_txt]);
                $id_tipe = (int)$stT->fetchColumn();
                if (!$id_tipe) {
                    $insT = $conn->prepare("INSERT INTO tipe_barang (id_kategori, nama_tipe) VALUES (?, ?)");
                    $insT->execute([$id_kat, $new_tipe_txt]);
                    $id_tipe = (int)$conn->lastInsertId();
                }
            } else {
                $id_tipe = (int)$raw_id_tipe;
            }
            if (!$id_tipe) throw new Exception('Tipe barang wajib dipilih atau dibuat baru.');

            $stKat = $conn->prepare("SELECT nama_kategori FROM kategori_barang WHERE id_kategori = ?");
            $stKat->execute([$id_kat]);
            $katName = $stKat->fetchColumn() ?: (string)$id_kat;

            // Generate 15 digit random barcode
            $barcode = '';
            for ($i = 0; $i < 15; $i++) {
                $barcode .= mt_rand(0, 9);
            }

            $stmt = $conn->prepare("
                INSERT INTO barang (barcode, nama_barang, kategori, satuan, min_stok, id_tipe, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$barcode, $nama_barang, $katName, $satuan, $min_stok, $id_tipe]);
            $new_id = (int)$conn->lastInsertId();

            $conn->exec("INSERT IGNORE INTO inventory (id_barang, stok) VALUES ($new_id, 0)");
            writeAuditLog('CREATE', 'barang', $new_id, "Menambahkan barang dari Master Kategori: $nama_barang");

            echo json_encode(['status' => 'success', 'msg' => "Barang <b>$nama_barang</b> dengan Kategori & Tipe berhasil ditambahkan!"]);
            exit;
        }

        if ($action === 'edit_barang_kat') {
            $id_barang   = (int)($_POST['id_barang'] ?? 0);
            $nama_barang = trim($_POST['nama_barang'] ?? '');
            $raw_id_kat  = $_POST['id_kategori'] ?? '';
            $new_kat_txt = trim($_POST['new_kategori'] ?? '');
            $raw_id_tipe = $_POST['id_tipe'] ?? '';
            $new_tipe_txt= trim($_POST['new_tipe'] ?? '');

            if (!$id_barang || !$nama_barang) throw new Exception('Data tidak valid.');

            // Proses Kategori (Ada/Baru)
            if ($raw_id_kat === 'new' || ($raw_id_kat === '' && $new_kat_txt !== '')) {
                if (!$new_kat_txt) throw new Exception('Nama kategori baru wajib diisi.');
                $stC = $conn->prepare("SELECT id_kategori FROM kategori_barang WHERE LOWER(nama_kategori) = LOWER(?)");
                $stC->execute([$new_kat_txt]);
                $id_kat = (int)$stC->fetchColumn();
                if (!$id_kat) {
                    $insC = $conn->prepare("INSERT INTO kategori_barang (nama_kategori) VALUES (?)");
                    $insC->execute([$new_kat_txt]);
                    $id_kat = (int)$conn->lastInsertId();
                }
            } else {
                $id_kat = (int)$raw_id_kat;
            }

            // Proses Tipe Barang (Ada/Baru)
            if ($raw_id_tipe === 'new' || ($raw_id_tipe === '' && $new_tipe_txt !== '')) {
                if (!$new_tipe_txt) throw new Exception('Nama tipe baru wajib diisi.');
                $stT = $conn->prepare("SELECT id_tipe FROM tipe_barang WHERE id_kategori = ? AND LOWER(nama_tipe) = LOWER(?)");
                $stT->execute([$id_kat, $new_tipe_txt]);
                $id_tipe = (int)$stT->fetchColumn();
                if (!$id_tipe) {
                    $insT = $conn->prepare("INSERT INTO tipe_barang (id_kategori, nama_tipe) VALUES (?, ?)");
                    $insT->execute([$id_kat, $new_tipe_txt]);
                    $id_tipe = (int)$conn->lastInsertId();
                }
            } else {
                $id_tipe = (int)$raw_id_tipe;
            }

            $stKat = $conn->prepare("SELECT nama_kategori FROM kategori_barang WHERE id_kategori = ?");
            $stKat->execute([$id_kat]);
            $katName = $stKat->fetchColumn() ?: (string)$id_kat;

            $stmt = $conn->prepare("UPDATE barang SET nama_barang = ?, kategori = ?, id_tipe = ? WHERE id_barang = ?");
            $stmt->execute([$nama_barang, $katName, $id_tipe, $id_barang]);

            writeAuditLog('UPDATE', 'barang', $id_barang, "Memperbarui barang/kategori: $nama_barang");
            echo json_encode(['status' => 'success', 'msg' => 'Data barang, kategori & tipe berhasil diperbarui!']);
            exit;
        }

        if ($action === 'delete_barang') {
            $id_barang = (int)($_POST['id_barang'] ?? 0);
            if (!$id_barang) throw new Exception('ID barang tidak valid.');

            $conn->prepare("DELETE FROM barang WHERE id_barang = ?")->execute([$id_barang]);
            writeAuditLog('DELETE', 'barang', $id_barang, "Menghapus barang ID #$id_barang");

            echo json_encode(['status' => 'success', 'msg' => 'Data barang berhasil dihapus!']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        exit;
    }
}

// Pagination & Search Filter
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

$whereClauses = ["1=1"];
$params = [];

if ($search) {
    $whereClauses[] = "(b.nama_barang LIKE ? OR b.barcode LIKE ? OR b.kategori LIKE ? OR t.nama_tipe LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(" AND ", $whereClauses);

// Count Total Data
try {
    $stCount = $conn->prepare("
        SELECT COUNT(*) 
        FROM barang b 
        LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe 
        LEFT JOIN kategori_barang k ON t.id_kategori = k.id_kategori 
        WHERE $whereSql
    ");
    $stCount->execute($params);
    $totalData = (int)$stCount->fetchColumn();
} catch (Exception $e) {
    $totalData = 0;
}

// Fetch List Data (Barang -> Kategori -> Tipe)
try {
    $sql = "
        SELECT 
            b.id_barang,
            b.nama_barang,
            b.barcode,
            b.satuan,
            b.min_stok,
            b.kategori,
            b.id_tipe,
            t.nama_tipe,
            k.id_kategori,
            COALESCE(k.nama_kategori, b.kategori) as nama_kategori
        FROM barang b
        LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe
        LEFT JOIN kategori_barang k ON t.id_kategori = k.id_kategori
        WHERE $whereSql
        ORDER BY b.id_barang DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $listData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // List categories and types for modal dropdowns
    $kategori_list = $conn->query("SELECT * FROM kategori_barang ORDER BY id_kategori ASC")->fetchAll(PDO::FETCH_ASSOC);
    $tipe_list = $conn->query("SELECT * FROM tipe_barang ORDER BY id_tipe ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $listData = [];
    $kategori_list = [];
    $tipe_list = [];
}
?>

<div class="fade-up space-y-5">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Master Kategori & Barang</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kelola data barang beserta pemetaan Kategori dan Tipe barang.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="bukaModalTambah()"
                    class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-plus text-xs"></i> Tambah Data Barang
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
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama barang, kategori, atau tipe..."
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
            <h3 class="text-sm font-bold text-slate-700">Daftar Barang, Kategori & Tipe</h3>
            <?php echo generateShowEntries($limit, 'kategoribarang', urlencode($search)); ?>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12 text-center">No.</th>
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider min-w-[220px]">Nama Barang</th>
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-36">Kategori</th>
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider w-56">Tipe Barang</th>
                        <th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($listData)): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i class="fa-solid fa-box-open text-3xl mb-2 block text-slate-200"></i>
                            Tidak ada data barang terdaftar.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = $offset + 1; foreach ($listData as $b): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        
                        <!-- No. Urut -->
                        <td class="px-5 py-4 text-center text-sm font-semibold text-slate-500 font-mono"><?= $no++ ?></td>

                        <!-- Nama Barang -->
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center font-bold text-sm flex-shrink-0 border border-sky-100">
                                    <i class="fa-solid fa-box text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800"><?= sanitize($b['nama_barang']) ?></p>
                                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        <?= $b['barcode'] ? '<i class="fa-solid fa-barcode mr-1"></i>'.$b['barcode'] : 'Tanpa Barcode' ?>
                                    </p>
                                </div>
                            </div>
                        </td>

                        <!-- Kategori -->
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center justify-center px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-xs border border-indigo-100">
                                Kategori <?= sanitize($b['nama_kategori'] ?: '-') ?>
                            </span>
                        </td>

                        <!-- Tipe Barang -->
                        <td class="px-5 py-4">
                            <?php if (!empty($b['nama_tipe'])): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-100">
                                <i class="fa-solid fa-tags text-[10px] text-sky-500"></i> <?= sanitize($b['nama_tipe']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-xs text-slate-400 italic">Belum Diatur</span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="px-5 py-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button type="button" 
                                        onclick="bukaModalEdit(<?= $b['id_barang'] ?>, '<?= htmlspecialchars(addslashes($b['nama_barang'])) ?>', '<?= (int)($b['id_kategori'] ?: 1) ?>', '<?= (int)($b['id_tipe'] ?: 0) ?>')"
                                        class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center transition-colors border border-amber-100" title="Edit Data">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <button type="button" 
                                        onclick="hapusBarang(<?= $b['id_barang'] ?>, '<?= htmlspecialchars(addslashes($b['nama_barang'])) ?>')"
                                        class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition-colors border border-rose-100" title="Hapus Data">
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

<!-- Modal Tambah / Edit Data Barang & Kategori -->
<div id="modalData" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="modalDataContent">
        <form id="formData" onsubmit="simpanData(event)">
            <input type="hidden" name="action" id="formAction" value="add_barang_kat">
            <input type="hidden" name="id_barang" id="formIdBarang" value="">
            
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800" id="formTitle">Tambah Data Barang & Kategori</h3>
                <button type="button" onclick="tutupModalData()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div class="p-6 space-y-4">
                <!-- Nama Barang -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Nama Barang <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_barang" id="inputNamaBarang" placeholder="Contoh: Sunlight Jeruk Nipis 755ml" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-sky-400 focus:ring-2 focus:ring-sky-500/20 outline-none" required>
                </div>

                <!-- Kategori -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Kategori <span class="text-red-500">*</span></label>
                    </div>
                    <select name="id_kategori" id="selectKategori" onchange="onModalKatChange()" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-sky-400 focus:ring-2 focus:ring-sky-500/20 outline-none bg-white" required>
                        <option value="">— Pilih Kategori —</option>
                        <?php foreach ($kategori_list as $k): ?>
                        <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                        <option value="new" class="font-bold text-sky-600">+ Tambah Kategori Baru...</option>
                    </select>
                    <div id="boxNewKategori" class="hidden mt-2">
                        <input type="text" name="new_kategori" id="inputNewKategori" placeholder="Ketik Nama Kategori Baru..." class="w-full px-4 py-2.5 text-sm border border-sky-300 rounded-xl focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 outline-none bg-sky-50/50">
                    </div>
                </div>

                <!-- Tipe Barang -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Tipe Barang <span class="text-red-500">*</span></label>
                    </div>
                    <select name="id_tipe" id="selectTipe" onchange="onModalTipeChange()" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:border-sky-400 focus:ring-2 focus:ring-sky-500/20 outline-none bg-white" required>
                        <option value="">— Pilih Tipe Barang —</option>
                    </select>
                    <div id="boxNewTipe" class="hidden mt-2">
                        <input type="text" name="new_tipe" id="inputNewTipe" placeholder="Ketik Nama Tipe Baru..." class="w-full px-4 py-2.5 text-sm border border-sky-300 rounded-xl focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 outline-none bg-sky-50/50">
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button type="button" onclick="tutupModalData()" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-200 text-sm font-semibold transition-colors border border-slate-200">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-sky-600 text-white hover:bg-sky-700 text-sm font-bold shadow-sm transition-colors">
                    Simpan Data
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const allTipe = <?= json_encode($tipe_list) ?>;

function onModalKatChange(targetTipeId = null) {
    const valKat = document.getElementById('selectKategori').value;
    const boxNewKategori = document.getElementById('boxNewKategori');
    const inputNewKategori = document.getElementById('inputNewKategori');
    const selectTipe = document.getElementById('selectTipe');

    if (valKat === 'new') {
        boxNewKategori.classList.remove('hidden');
        inputNewKategori.setAttribute('required', 'required');
    } else {
        boxNewKategori.classList.add('hidden');
        inputNewKategori.removeAttribute('required');
        inputNewKategori.value = '';
    }
    
    selectTipe.innerHTML = '<option value="">— Pilih Tipe Barang —</option>';
    
    if (valKat && valKat !== 'new') {
        const filtered = allTipe.filter(t => t.id_kategori == valKat);
        filtered.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id_tipe;
            opt.textContent = t.nama_tipe;
            if (targetTipeId && t.id_tipe == targetTipeId) {
                opt.selected = true;
            }
            selectTipe.appendChild(opt);
        });
    }

    // Always append option to add new Tipe
    const optNewT = document.createElement('option');
    optNewT.value = 'new';
    optNewT.className = 'font-bold text-sky-600';
    optNewT.textContent = '+ Tambah Tipe Baru...';
    selectTipe.appendChild(optNewT);

    if (targetTipeId && targetTipeId === 'new') {
        selectTipe.value = 'new';
    } else if (!selectTipe.value && selectTipe.options.length > 2) {
        selectTipe.selectedIndex = 1;
    }
    onModalTipeChange();
}

function onModalTipeChange() {
    const valTipe = document.getElementById('selectTipe').value;
    const boxNewTipe = document.getElementById('boxNewTipe');
    const inputNewTipe = document.getElementById('inputNewTipe');

    if (valTipe === 'new') {
        boxNewTipe.classList.remove('hidden');
        inputNewTipe.setAttribute('required', 'required');
    } else {
        boxNewTipe.classList.add('hidden');
        inputNewTipe.removeAttribute('required');
        inputNewTipe.value = '';
    }
}

function bukaModalTambah() {
    document.getElementById('formTitle').textContent = 'Tambah Data Barang & Kategori';
    document.getElementById('formAction').value = 'add_barang_kat';
    document.getElementById('formIdBarang').value = '';
    document.getElementById('inputNamaBarang').value = '';
    document.getElementById('selectKategori').value = '';
    document.getElementById('selectTipe').innerHTML = '<option value="">— Pilih Tipe Barang —</option>';

    const modal = document.getElementById('modalData');
    const content = document.getElementById('modalDataContent');
    modal.classList.remove('hidden');
    setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); }, 10);
}

function bukaModalEdit(idBarang, namaBarang, idKat, idTipe) {
    document.getElementById('formTitle').textContent = 'Edit Barang & Kategori';
    document.getElementById('formAction').value = 'edit_barang_kat';
    document.getElementById('formIdBarang').value = idBarang;
    document.getElementById('inputNamaBarang').value = namaBarang;
    document.getElementById('selectKategori').value = idKat;
    
    onModalKatChange(idTipe);

    const modal = document.getElementById('modalData');
    const content = document.getElementById('modalDataContent');
    modal.classList.remove('hidden');
    setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); }, 10);
}

function tutupModalData() {
    const modal = document.getElementById('modalData');
    const content = document.getElementById('modalDataContent');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => { modal.classList.add('hidden'); }, 300);
}

function simpanData(e) {
    e.preventDefault();
    Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    const fd = new FormData(document.getElementById('formData'));
    fetch('<?= $sistem ?>/kategoribarang', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', html: res.msg, timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
        }
    }).catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi ke server gagal.' }));
}

function hapusBarang(id, nama) {
    Swal.fire({
        title: 'Hapus Barang ' + nama + '?',
        text: 'Data barang ini akan dihapus dari sistem.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const fd = new FormData();
            fd.append('action', 'delete_barang');
            fd.append('id_barang', id);

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
