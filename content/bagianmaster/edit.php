<?php
requireAdmin();
global $conn, $sistem;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo "<script>window.location.href='$sistem/bagianmaster';</script>"; exit; }

try {
    $row = $conn->prepare("SELECT * FROM bagian WHERE id_bagian = ?");
    $row->execute([$id]);
    $bagian = $row->fetch();
} catch(Exception $e) { $bagian = null; }

if (!$bagian) { echo "<script>window.location.href='$sistem/bagianmaster';</script>"; exit; }

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_bagian'] ?? '');
    if (!$nama) {
        $error = 'Nama bagian wajib diisi.';
    } else {
        $cek = $conn->prepare("SELECT COUNT(*) FROM bagian WHERE LOWER(nama_bagian) = LOWER(?) AND id_bagian != ?");
        $cek->execute([$nama, $id]);
        if ($cek->fetchColumn() > 0) {
            $error = "Nama bagian <b>$nama</b> sudah digunakan.";
        } else {
            try {
                $conn->prepare("UPDATE bagian SET nama_bagian = ? WHERE id_bagian = ?")->execute([$nama, $id]);
                $_SESSION['flash_success'] = "Bagian <b>$nama</b> berhasil diperbarui.";
                $success = true;
            } catch(Exception $e) {
                $error = 'Gagal menyimpan: '.$e->getMessage();
            }
        }
    }
}
?>

<div class="fade-up max-w-lg mx-auto space-y-5">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/bagianmaster" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Bagian</h1>
            <p class="text-slate-500 text-sm">Perbarui nama departemen / divisi.</p>
        </div>
    </div>

    <!-- Form -->
    <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <p class="text-sm font-semibold text-slate-700"><i class="fa-solid fa-building text-sky-500 mr-2"></i>Data Bagian</p>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nama Bagian <span class="text-red-500">*</span></label>
                <input type="text" name="nama_bagian" value="<?= htmlspecialchars($_POST['nama_bagian'] ?? $bagian['nama_bagian']) ?>"
                       required placeholder="Contoh: Gudang Pusat"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3">
            <a href="<?= $sistem ?>/bagianmaster" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition-colors">Batal</a>
            <button type="submit" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
<?php if ($success): ?>
Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Bagian berhasil diperbarui.', timer: 1500, showConfirmButton: false })
    .then(() => { window.location.href = '<?= $sistem ?>/bagianmaster'; });
<?php endif; ?>
<?php if ($error): ?>
Swal.fire({ icon: 'error', title: 'Gagal!', html: '<?= addslashes($error) ?>' });
<?php endif; ?>
</script>
