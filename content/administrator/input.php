<?php
requireAdmin();
global $conn, $sistem;

// Ambil master bagian, outlet, dan role
try { $bagianList = $conn->query("SELECT * FROM bagian ORDER BY nama_bagian")->fetchAll(); } catch(Exception $e) { $bagianList = []; }
try { $outletList = $conn->query("SELECT * FROM outlet WHERE is_active=1 ORDER BY nama_outlet")->fetchAll(); } catch(Exception $e) { $outletList = []; }
try { $roleList   = $conn->query("SELECT * FROM roles ORDER BY id_role")->fetchAll(); } catch(Exception $e) { $roleList = []; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = trim($_POST['nama'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $id_role   = (int)($_POST['id_role'] ?? 0) ?: null;
    $id_bagian = (int)($_POST['id_bagian'] ?? 0) ?: null;
    $outlet_id = (int)($_POST['outlet_id'] ?? 0) ?: null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$nama || !$username || !$password || !$id_role) {
        $error = 'Nama, username, password, dan role wajib diisi.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        // Cek username duplikat
        $cek = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->fetchColumn() > 0) {
            $error = "Username <b>$username</b> sudah digunakan. Pilih username lain.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $conn->prepare("
                    INSERT INTO users (nama, username, password, id_role, id_bagian, outlet_id, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nama, $username, $hash, $id_role, $id_bagian, $outlet_id, $is_active]);
                $new_id = (int)$conn->lastInsertId();
                writeAuditLog('CREATE', 'users', $new_id, "Membuat user baru: $nama ($username)");
                $_SESSION['flash_success'] = "User <b>$nama</b> berhasil ditambahkan.";
                $success = true;
            } catch(Exception $e) {
                $error = 'Gagal menyimpan: '.$e->getMessage();
            }
        }
    }
}
?>

<div class="fade-up max-w-2xl mx-auto space-y-5">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= $sistem ?>/administrator" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-500">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Tambah User</h1>
            <p class="text-slate-500 text-sm">Buat akun pengguna baru.</p>
        </div>
    </div>

    <!-- Alert -->
    <?php if ($error): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-xmark mt-0.5 flex-shrink-0"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-circle-check mt-0.5 flex-shrink-0"></i>
        <span><?= $success ?> <a href="<?= $sistem ?>/administrator" class="font-bold underline">Kembali ke daftar</a></span>
    </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <p class="text-sm font-semibold text-slate-700"><i class="fa-solid fa-user-plus text-sky-500 mr-2"></i>Informasi Akun</p>
        </div>
        <div class="p-6 space-y-5">

            <!-- Nama -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" name="nama" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required
                       placeholder="Contoh: Budi Santoso"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>

            <!-- Username -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required
                       placeholder="Contoh: budi.santoso"
                       class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all">
            </div>

            <!-- Password -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Password <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="password" name="password" id="pwd" required minlength="6"
                           placeholder="Minimal 6 karakter"
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all pr-10">
                    <button type="button" onclick="togglePwd()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                        <i id="pwdIcon" class="fa-solid fa-eye text-sm"></i>
                    </button>
                </div>
                <p class="text-xs text-slate-400 mt-1">Password akan di-hash secara otomatis.</p>
            </div>

            <!-- Role & Bagian -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Role / Jabatan <span class="text-red-500">*</span></label>
                    <select name="id_role" required class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                        <option value="">— Pilih Jabatan —</option>
                        <?php foreach ($roleList as $rl): ?>
                        <option value="<?= $rl['id_role'] ?>" <?= (($_POST['id_role'] ?? '') == $rl['id_role']) ? 'selected' : '' ?>>
                            <?= sanitize($rl['nama_role']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Bagian</label>
                    <select name="id_bagian" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                        <option value="">— Pilih Bagian —</option>
                        <?php foreach ($bagianList as $b): ?>
                        <option value="<?= $b['id_bagian'] ?>" <?= (($_POST['id_bagian'] ?? '') == $b['id_bagian']) ? 'selected' : '' ?>>
                            <?= sanitize($b['nama_bagian']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Outlet -->
            <?php if ($outletList): ?>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Outlet (Opsional)</label>
                <select name="outlet_id" class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-all bg-white">
                    <option value="">— Tidak ada / Semua Outlet —</option>
                    <?php foreach ($outletList as $o): ?>
                    <option value="<?= $o['id_outlet'] ?>" <?= (($_POST['outlet_id'] ?? '') == $o['id_outlet']) ? 'selected' : '' ?>>
                        <?= sanitize($o['nama_outlet']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Status Aktif -->
            <div class="flex items-center gap-3 bg-slate-50 rounded-xl px-4 py-3 border border-slate-200">
                <input type="checkbox" name="is_active" id="is_active" value="1" checked
                       class="w-4 h-4 rounded border-slate-300 text-sky-600 accent-sky-600">
                <label for="is_active" class="text-sm font-semibold text-slate-700 cursor-pointer">User Aktif</label>
                <span class="text-xs text-slate-400 ml-auto">User nonaktif tidak bisa login</span>
            </div>

        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3">
            <a href="<?= $sistem ?>/administrator" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan User
            </button>
        </div>
    </form>
</div>

<script>
function togglePwd() {
    const i = document.getElementById('pwd');
    const ic = document.getElementById('pwdIcon');
    if (i.type === 'password') {
        i.type = 'text'; ic.className = 'fa-solid fa-eye-slash text-sm';
    } else {
        i.type = 'password'; ic.className = 'fa-solid fa-eye text-sm';
    }
}
<?php if ($success): ?>
Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'User baru berhasil ditambahkan.', timer: 1500, showConfirmButton: false })
    .then(() => { window.location.href = '<?= $sistem ?>/administrator'; });
<?php endif; ?>
<?php if ($error): ?>
Swal.fire({ icon: 'error', title: 'Gagal!', html: '<?= addslashes($error) ?>' });
<?php endif; ?>
</script>