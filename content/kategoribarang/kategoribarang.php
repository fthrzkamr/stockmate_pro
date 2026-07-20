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

            // Delete types first or set id_kategori to null
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

// Fetch Kategori & Tipe
try {
    $kategori_list = $conn->query("SELECT * FROM kategori_barang ORDER BY id_kategori ASC")->fetchAll(PDO::FETCH_ASSOC);
    $tipe_list = $conn->query("SELECT * FROM tipe_barang ORDER BY id_tipe ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Group types by id_kategori
    $groupedTypes = [];
    foreach ($tipe_list as $t) {
        $groupedTypes[$t['id_kategori']][] = $t;
    }
} catch (Exception $e) {
    $kategori_list = [];
    $groupedTypes = [];
}
?>

<div class="fade-up max-w-6xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Master Kategori & Tipe Barang</h1>
            <p class="text-slate-500 text-sm mt-1">Kelola data kategori utama dan tipe barang yang terhubung secara dinamis.</p>
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

    <!-- Cards Layout per Category -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php if (empty($kategori_list)): ?>
        <div class="col-span-3 bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
            <i class="fa-solid fa-tags text-4xl mb-3 text-slate-300 block"></i>
            <p class="font-semibold text-slate-600">Belum Ada Master Kategori</p>
            <p class="text-xs text-slate-400 mt-1">Klik tombol "+ Tambah Kategori" untuk membuat kategori baru.</p>
        </div>
        <?php else: ?>
        <?php foreach ($kategori_list as $kat): 
            $katId = $kat['id_kategori'];
            $types = $groupedTypes[$katId] ?? [];
        ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col hover:border-slate-300 transition-all">
            
            <!-- Category Header -->
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center font-bold text-sm">
                        <?= htmlspecialchars($kat['nama_kategori']) ?>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Kategori: <?= htmlspecialchars($kat['nama_kategori']) ?></h3>
                        <p class="text-[11px] text-slate-400 font-medium"><?= count($types) ?> Tipe Terhubung</p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center gap-1">
                    <button type="button" onclick="editKategori(<?= $katId ?>, '<?= htmlspecialchars(addslashes($kat['nama_kategori'])) ?>')"
                            class="w-7 h-7 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 flex items-center justify-center transition-colors" title="Edit Kategori">
                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                    </button>
                    <button type="button" onclick="hapusKategori(<?= $katId ?>, '<?= htmlspecialchars(addslashes($kat['nama_kategori'])) ?>')"
                            class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors" title="Hapus Kategori">
                        <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- List of Types -->
            <div class="p-4 flex-1 space-y-2">
                <div class="flex justify-between items-center mb-2 px-1">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Daftar Tipe Barang</span>
                    <button type="button" onclick="bukaModalTipe(<?= $katId ?>)" class="text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline">
                        + Tipe Baru
                    </button>
                </div>

                <?php if (empty($types)): ?>
                <div class="py-6 text-center text-xs text-slate-400 italic bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                    Belum ada tipe barang untuk kategori ini.
                </div>
                <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($types as $t): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/80 border border-slate-100 hover:bg-slate-100/60 transition-colors">
                        <span class="text-xs font-semibold text-slate-700 flex items-center gap-2">
                            <i class="fa-solid fa-angle-right text-[10px] text-sky-500"></i>
                            <?= htmlspecialchars($t['nama_tipe']) ?>
                        </span>
                        <div class="flex items-center gap-1 opacity-80 hover:opacity-100">
                            <button type="button" onclick="editTipe(<?= $t['id_tipe'] ?>, '<?= htmlspecialchars(addslashes($t['nama_tipe'])) ?>', <?= $t['id_kategori'] ?>)"
                                    class="w-6 h-6 rounded text-slate-400 hover:text-amber-600 flex items-center justify-center text-[11px]">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" onclick="hapusTipe(<?= $t['id_tipe'] ?>, '<?= htmlspecialchars(addslashes($t['nama_tipe'])) ?>')"
                                    class="w-6 h-6 rounded text-slate-400 hover:text-rose-600 flex items-center justify-center text-[11px]">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
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
