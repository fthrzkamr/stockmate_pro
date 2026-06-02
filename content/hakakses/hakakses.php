<?php
requireAdmin();
global $conn, $sistem;

$type    = $_GET['type'] ?? $_POST['type'] ?? 'role'; // 'role' atau 'user'
$id_role = (int)($_GET['id_role'] ?? $_POST['id_role'] ?? 0);
$id_user = (int)($_GET['id_user'] ?? $_POST['id_user'] ?? 0);

// Handle tambah jabatan baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    ob_clean();
    header('Content-Type: application/json');
    $nama_role = trim($_POST['nama_role'] ?? '');
    $is_admin  = (int)($_POST['is_admin'] ?? 0);
    if (!$nama_role) { echo json_encode(['status'=>'error','msg'=>'Nama jabatan wajib diisi.']); exit; }
    try {
        $stmt = $conn->prepare("INSERT INTO roles (nama_role, is_admin) VALUES (?, ?)");
        $stmt->execute([$nama_role, $is_admin]);
        $new_id = $conn->lastInsertId();
        writeAuditLog('CREATE', 'roles', (int)$new_id, "Menambahkan jabatan baru: $nama_role");
        echo json_encode(['status'=>'success','msg'=>"Jabatan <b>$nama_role</b> berhasil ditambahkan!"]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
    }
    exit;
}

// Handle hapus jabatan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    ob_clean();
    header('Content-Type: application/json');
    $del_role = (int)($_POST['id_role'] ?? 0);
    if ($del_role <= 1) { echo json_encode(['status'=>'error','msg'=>'Jabatan ini tidak dapat dihapus.']); exit; }
    try {
        $info = $conn->prepare("SELECT nama_role FROM roles WHERE id_role = ?");
        $info->execute([$del_role]);
        $rInfo = $info->fetch();
        if (!$rInfo) { echo json_encode(['status'=>'error','msg'=>'Jabatan tidak ditemukan.']); exit; }
        // Cek apakah masih ada user yang memakai jabatan ini
        $cek = $conn->prepare("SELECT COUNT(*) FROM users WHERE id_role = ?");
        $cek->execute([$del_role]);
        if ((int)$cek->fetchColumn() > 0) {
            echo json_encode(['status'=>'error','msg'=>"Jabatan <b>{$rInfo['nama_role']}</b> masih digunakan oleh user aktif, tidak dapat dihapus."]); exit;
        }
        $conn->prepare("DELETE FROM roles WHERE id_role = ?")->execute([$del_role]);
        writeAuditLog('DELETE', 'roles', $del_role, "Menghapus jabatan: {$rInfo['nama_role']}");
        echo json_encode(['status'=>'success','msg'=>"Jabatan <b>{$rInfo['nama_role']}</b> berhasil dihapus."]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
    }
    exit;
}

// Handle AJAX Save / Reset Override
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json');

    // Aksi Reset Kustomisasi User kembali ke Bawaan Jabatan
    if (isset($_POST['reset_override'])) {
        $target_user = (int)$_POST['id_user'];
        if (!$target_user) { echo json_encode(['status'=>'error','msg'=>'User tidak valid.']); exit; }
        try {
            $conn->prepare("DELETE FROM user_menu WHERE id_user = ?")->execute([$target_user]);
            echo json_encode(['status'=>'success','msg'=>'Kustomisasi user dibersihkan. User kembali menggunakan hak akses bawaan jabatannya!']);
        } catch(Exception $e) {
            echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
        }
        exit;
    }

    if (isset($_POST['save_access'])) {
        $perms = $_POST['permissions'] ?? [];

        if ($type === 'user') {
            $target_user = (int)$_POST['id_user'];
            if (!$target_user) { echo json_encode(['status'=>'error','msg'=>'User tidak valid.']); exit; }

            try {
                $conn->beginTransaction();

                // 1. Dapatkan role bawaan user tersebut
                $usr = $conn->prepare("SELECT id_role FROM users WHERE id_user = ?");
                $usr->execute([$target_user]);
                $user_role = (int)$usr->fetchColumn();

                // Dapatkan hak akses bawaan role
                $role_defaults = [];
                $stRole = $conn->prepare("SELECT id_smu, can_view, can_create, can_edit, can_delete, can_print FROM role_menu WHERE id_role = ?");
                $stRole->execute([$user_role]);
                foreach ($stRole->fetchAll() as $row) {
                    $role_defaults[(int)$row['id_smu']] = $row;
                }

                // Dapatkan semua ID SMU di sistem
                $all_smus = $conn->query("SELECT id_smu FROM sub_menu")->fetchAll(PDO::FETCH_COLUMN);

                // 2. Hapus kustomisasi user sebelumnya
                $conn->prepare("DELETE FROM user_menu WHERE id_user = ?")->execute([$target_user]);

                // 3. Simpan kustomisasi baru ke user_menu HANYA JIKA BERBEDA dengan bawaan jabatan
                $st = $conn->prepare("
                    INSERT INTO user_menu (id_user, id_smu, can_view, can_create, can_edit, can_delete, can_print)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($all_smus as $id_smu) {
                    $id_smu = (int)$id_smu;
                    
                    // State dari form (jika unchecked semua, maka tidak ada di $perms)
                    $p = $perms[$id_smu] ?? [];
                    $f_view   = isset($p['can_view'])   ? 1 : 0;
                    $f_create = isset($p['can_create']) ? 1 : 0;
                    $f_edit   = isset($p['can_edit'])   ? 1 : 0;
                    $f_delete = isset($p['can_delete']) ? 1 : 0;
                    $f_print  = isset($p['can_print'])  ? 1 : 0;

                    // State bawaan role
                    $r = $role_defaults[$id_smu] ?? [];
                    $r_view   = isset($r['can_view'])   ? (int)$r['can_view']   : 0;
                    $r_create = isset($r['can_create']) ? (int)$r['can_create'] : 0;
                    $r_edit   = isset($r['can_edit'])   ? (int)$r['can_edit']   : 0;
                    $r_delete = isset($r['can_delete']) ? (int)$r['can_delete'] : 0;
                    $r_print  = isset($r['can_print'])  ? (int)$r['can_print']  : 0;

                    // Jika berbeda, jadikan override (KUSTOM)
                    if ($f_view !== $r_view || $f_create !== $r_create || $f_edit !== $r_edit || $f_delete !== $r_delete || $f_print !== $r_print) {
                        $st->execute([$target_user, $id_smu, $f_view, $f_create, $f_edit, $f_delete, $f_print]);
                    }
                }

                $conn->commit();
                echo json_encode(['status'=>'success','msg'=>'Kustomisasi hak akses user berhasil disimpan!']);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
            }
            exit;
        } else {
            // Aksi simpan per-Jabatan (Role)
            $target_role = (int)$_POST['id_role'];
            if (!$target_role) { echo json_encode(['status'=>'error','msg'=>'Jabatan tidak valid.']); exit; }

            try {
                $conn->beginTransaction();
                $conn->prepare("DELETE FROM role_menu WHERE id_role = ?")->execute([$target_role]);
                $st = $conn->prepare("
                    INSERT INTO role_menu (id_role, id_smu, can_view, can_create, can_edit, can_delete, can_print)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($perms as $id_smu => $p) {
                    $id_smu = (int)$id_smu;
                    if ($id_smu <= 0) continue;
                    $view   = isset($p['can_view'])   ? 1 : 0;
                    $create = isset($p['can_create']) ? 1 : 0;
                    $edit   = isset($p['can_edit'])   ? 1 : 0;
                    $delete = isset($p['can_delete']) ? 1 : 0;
                    $print  = isset($p['can_print'])  ? 1 : 0;

                    if ($view || $create || $edit || $delete || $print) {
                        $st->execute([$target_role, $id_smu, $view, $create, $edit, $delete, $print]);
                    }
                }
                $conn->commit();
                echo json_encode(['status'=>'success','msg'=>'Hak akses Jabatan berhasil disimpan.']);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
            }
            exit;
        }
    }
}

// Ambil daftar semua Role/Jabatan
try {
    $all_roles = $conn->query("SELECT r.*, COUNT(u.id_user) AS jml_user FROM roles r LEFT JOIN users u ON u.id_role = r.id_role GROUP BY r.id_role ORDER BY r.id_role ASC")->fetchAll();
} catch (Exception $e) { $all_roles = []; }

// Ambil daftar semua User (kecuali developer id=1 agar aman)
try {
    $all_users = $conn->query("
        SELECT u.id_user, u.nama, u.username, r.nama_role, u.id_role 
        FROM users u 
        LEFT JOIN roles r ON u.id_role = r.id_role 
        WHERE u.id_user != 1
        ORDER BY u.nama ASC
    ")->fetchAll();
} catch (Exception $e) { $all_users = []; }

// Ambil struktur menu: Kategori → Menu → Sub Menu
$menus_structure = [];
try {
    $kategori = $conn->query("SELECT * FROM kategori_menu ORDER BY urutan_kmu ASC")->fetchAll();
    foreach ($kategori as $k) {
        $menus = $conn->prepare("
            SELECT m.*, ic.nama_icon FROM menu m
            LEFT JOIN icon ic ON m.id_icon = ic.id_icon
            WHERE m.id_kmu = ? ORDER BY m.urutan_menu ASC
        ");
        $menus->execute([$k['id_kmu']]);
        $menu_list = $menus->fetchAll();
        $menu_group = [];
        foreach ($menu_list as $m) {
            $subs = $conn->prepare("SELECT * FROM sub_menu WHERE id_menu = ? ORDER BY urutan_smu ASC");
            $subs->execute([$m['id_menu']]);
            $sub_list = $subs->fetchAll();
            if ($sub_list) $menu_group[] = ['data' => $m, 'subs' => $sub_list];
        }
        if ($menu_group) $menus_structure[] = ['kategori' => $k, 'menus' => $menu_group];
    }
} catch (Exception $e) {}

// Info data yang dipilih
$selected_role = null;
$selected_user = null;
$current_access = [];
$inherited_access = [];
$custom_active_smus = [];

if ($type === 'user' && $id_user > 0) {
    foreach ($all_users as $u) {
        if ((int)$u['id_user'] === $id_user) { $selected_user = $u; break; }
    }
    if ($selected_user) {
        // 1. Ambil hak akses bawaan role user tersebut
        try {
            $stRole = $conn->prepare("SELECT id_smu, can_view, can_create, can_edit, can_delete, can_print FROM role_menu WHERE id_role = ?");
            $stRole->execute([$selected_user['id_role']]);
            foreach ($stRole->fetchAll() as $row) {
                $inherited_access[(int)$row['id_smu']] = $row;
            }
        } catch (Exception $e) {}

        // 2. Ambil kustomisasi user dari user_menu jika ada
        try {
            $stUser = $conn->prepare("SELECT id_smu, can_view, can_create, can_edit, can_delete, can_print FROM user_menu WHERE id_user = ?");
            $stUser->execute([$id_user]);
            $userRows = $stUser->fetchAll();
            
            // Tandai sub_menu mana saja yang ter-override
            foreach ($userRows as $row) {
                $current_access[(int)$row['id_smu']] = $row;
                $custom_active_smus[] = (int)$row['id_smu'];
            }
        } catch(Exception $e) {}
    }
} else if ($type === 'role' && $id_role > 0) {
    foreach ($all_roles as $r) {
        if ((int)$r['id_role'] === $id_role) { $selected_role = $r; break; }
    }
    // Ambil hak akses untuk role
    if ($selected_role) {
        try {
            $st = $conn->prepare("SELECT id_smu, can_view, can_create, can_edit, can_delete, can_print FROM role_menu WHERE id_role = ?");
            $st->execute([$id_role]);
            foreach ($st->fetchAll() as $row) {
                $current_access[(int)$row['id_smu']] = $row;
            }
        } catch (Exception $e) {}
    }
}

$crud_cols = [
    'can_view'   => ['label'=>'Lihat',  'icon'=>'fa-eye',        'color'=>'sky'],
    'can_create' => ['label'=>'Tambah', 'icon'=>'fa-plus',       'color'=>'emerald'],
    'can_edit'   => ['label'=>'Edit',   'icon'=>'fa-pen',        'color'=>'amber'],
    'can_delete' => ['label'=>'Hapus',  'icon'=>'fa-trash',      'color'=>'red'],
    'can_print'  => ['label'=>'Cetak',  'icon'=>'fa-print',      'color'=>'violet'],
];
?>

<div class="fade-up space-y-6">

    <!-- Tab Swticher -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-2">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Manajemen Hak Akses</h1>
            <p class="text-slate-500 text-sm mt-0.5">Kelola izin menu secara global per jabatan, atau kustom khusus per user.</p>
        </div>
        
        <!-- Tab Navigation Switcher -->
        <div class="inline-flex p-1 bg-slate-100 rounded-xl">
            <a href="<?= $sistem ?>/hakakses?type=role"
               class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all
                      <?= $type === 'role' ? 'bg-white text-sky-600 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
                <i class="fa-solid fa-users-gear text-sm"></i> Hak Akses Jabatan
            </a>
            <a href="<?= $sistem ?>/hakakses?type=user"
               class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all
                      <?= $type === 'user' ? 'bg-white text-sky-600 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
                <i class="fa-solid fa-user-gear text-sm"></i> Kustomisasi User
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

        <!-- Sidebar List (Roles / Users) -->
        <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden sticky top-20">
                <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <?= $type === 'user' ? 'Daftar Pengguna' : 'Daftar Jabatan' ?>
                    </p>
                    <?php if ($type === 'role'): ?>
                    <button onclick="openTambahJabatan()"
                            class="w-6 h-6 rounded-lg bg-sky-100 text-sky-600 hover:bg-sky-200 flex items-center justify-center transition-colors" title="Tambah Jabatan Baru">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="divide-y divide-slate-100 max-h-[calc(100vh-250px)] overflow-y-auto">
                    <?php if ($type === 'user'): ?>
                        <?php if (empty($all_users)): ?>
                            <div class="p-4 text-center text-slate-400 text-xs">Belum ada user.</div>
                        <?php else: ?>
                            <?php foreach ($all_users as $u): ?>
                            <a href="<?= $sistem ?>/hakakses?type=user&id_user=<?= $u['id_user'] ?>"
                               class="flex items-center gap-3 px-4 py-3 transition-all text-sm
                                      <?= $id_user == $u['id_user'] ? 'bg-sky-50 border-l-4 border-sky-500' : 'hover:bg-slate-50 border-l-4 border-transparent' ?>">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0 text-slate-500">
                                    <i class="fa-solid fa-user text-xs"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold truncate <?= $id_user == $u['id_user'] ? 'text-sky-700' : 'text-slate-700' ?>">
                                        <?= sanitize($u['nama']) ?>
                                    </p>
                                    <p class="text-[10px] tracking-wide text-slate-400">
                                        <?= sanitize($u['nama_role']) ?>
                                    </p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php foreach ($all_roles as $r): ?>
                        <div class="flex items-center gap-1 pr-2 border-b border-slate-50 hover:bg-slate-50 transition-colors group <?= $id_role == $r['id_role'] ? 'bg-sky-50' : '' ?>">
                            <a href="<?= $sistem ?>/hakakses?type=role&id_role=<?= $r['id_role'] ?>"
                               class="flex items-center gap-3 px-4 py-3.5 flex-1 text-sm border-l-4 <?= $id_role == $r['id_role'] ? 'border-sky-500' : 'border-transparent' ?>">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold
                                            <?= $id_role == $r['id_role'] ? 'bg-sky-500 text-white' : 'bg-slate-100 text-slate-500' ?>">
                                    <i class="fa-solid fa-user-shield"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold truncate <?= $id_role == $r['id_role'] ? 'text-sky-700' : 'text-slate-700' ?>">
                                        <?= sanitize($r['nama_role']) ?>
                                    </p>
                                    <p class="text-[10px] tracking-wider <?= $id_role == $r['id_role'] ? 'text-sky-400' : 'text-slate-400' ?>">
                                        <?= $r['jml_user'] ?> Anggota
                                    </p>
                                </div>
                            </a>
                            <?php if ($r['id_role'] > 1): ?>
                            <button onclick="hapusJabatan(<?= $r['id_role'] ?>, '<?= sanitize($r['nama_role']) ?>')"
                                    class="w-6 h-6 rounded-md bg-red-50 text-red-400 hover:bg-red-100 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all flex-shrink-0" title="Hapus Jabatan">
                                <i class="fa-solid fa-trash text-[9px]"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Panel Hak Akses -->
        <div class="lg:col-span-3">
            <?php 
            $no_selection = ($type === 'user' && (!$id_user || !$selected_user)) || ($type === 'role' && (!$id_role || !$selected_role));
            if ($no_selection): 
            ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col items-center justify-center py-24 text-center">
                <div class="w-16 h-16 bg-sky-50 rounded-full flex items-center justify-center mb-4 text-sky-400">
                    <i class="fa-solid fa-key-skeleton text-2xl"></i>
                </div>
                <h3 class="text-slate-700 font-bold">Pilih Target</h3>
                <p class="text-slate-400 text-sm mt-1">
                    <?= $type === 'user' ? 'Klik nama Pengguna untuk mulai mengustomisasi hak aksesnya.' : 'Klik nama Jabatan untuk mengatur hak akses bawaannya.' ?>
                </p>
            </div>

            <?php else: ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                <!-- Header Panel -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center font-bold shadow-md shadow-sky-500/20">
                            <i class="fa-solid <?= $type === 'user' ? 'fa-user-gear' : 'fa-users-gear' ?> text-sm"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-800">
                                <?= $type === 'user' ? sanitize($selected_user['nama']) : sanitize($selected_role['nama_role']) ?>
                            </p>
                            <p class="text-[11px] font-medium text-slate-500">
                                <?php if ($type === 'user'): ?>
                                    Jabatan Bawaan: <span class="text-sky-600 font-bold"><?= sanitize($selected_user['nama_role']) ?></span>
                                    <?php if (!empty($custom_active_smus)): ?>
                                        <span class="ml-2 bg-amber-50 text-amber-700 px-2 py-0.5 rounded-md text-[9px] font-semibold border border-amber-200">Hak Akses Dikustomisasi</span>
                                    <?php else: ?>
                                        <span class="ml-2 bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-md text-[9px] font-semibold border border-emerald-200">Mengikuti Jabatan</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= count($current_access) ?> menu di-aktifkan
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <?php if ($type === 'user' && !empty($custom_active_smus)): ?>
                        <button type="button" onclick="resetOverride(<?= $id_user ?>)"
                                class="px-3 py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors border border-rose-100">
                            <i class="fa-solid fa-rotate-left mr-1"></i>Kembalikan ke Bawaan Jabatan
                        </button>
                        <?php endif; ?>
                        
                        <button type="button" onclick="toggleAll(true)"
                                class="px-3 py-1.5 text-xs font-semibold text-sky-600 bg-sky-50 hover:bg-sky-100 rounded-lg transition-colors border border-sky-100">
                            <i class="fa-solid fa-check-double mr-1"></i>Semua Akses
                        </button>
                        <button type="button" onclick="toggleAll(false)"
                                class="px-3 py-1.5 text-xs font-semibold text-slate-500 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors border border-slate-200">
                            <i class="fa-solid fa-ban mr-1"></i>Hapus Semua
                        </button>
                    </div>
                </div>

                <form id="formHakAkses" method="POST">
                    <input type="hidden" name="save_access" value="1">
                    <input type="hidden" name="type" value="<?= $type ?>">
                    <?php if ($type === 'user'): ?>
                        <input type="hidden" name="id_user" value="<?= $id_user ?>">
                    <?php else: ?>
                        <input type="hidden" name="id_role" value="<?= $id_role ?>">
                    <?php endif; ?>

                    <!-- Legend -->
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-3 border-b border-slate-100 bg-white">
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Aksi:</span>
                            <?php foreach ($crud_cols as $col => $info): ?>
                            <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                                <i class="fa-solid <?= $info['icon'] ?> text-<?= $info['color'] ?>-500 text-[10px]"></i>
                                <?= $info['label'] ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($type === 'user'): ?>
                        <div class="text-[10px] text-slate-400 font-semibold italic">
                            * Perubahan otomatis meng-kustomisasi user ini.
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="overflow-x-auto">
                        <?php foreach ($menus_structure as $group): ?>
                        <!-- Kategori Header -->
                        <div class="px-6 py-2 bg-slate-50 border-b border-slate-100">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                <?= sanitize($group['kategori']['nama_kmu']) ?>
                            </span>
                        </div>

                        <?php foreach ($group['menus'] as $menu_item): ?>
                        <!-- Menu Group Header -->
                        <div class="px-6 py-2 bg-white border-b border-slate-50 flex items-center gap-2">
                            <i class="fa-solid <?= sanitize($menu_item['data']['nama_icon'] ?? 'fa-folder') ?> text-sky-500 text-xs w-4 text-center"></i>
                            <span class="text-xs font-bold text-slate-600"><?= sanitize($menu_item['data']['nama_menu']) ?></span>
                        </div>

                        <?php foreach ($menu_item['subs'] as $s): ?>
                        <?php
                            $smu_id = (int)$s['id_smu'];
                            
                            // Ambil hak akses aktif untuk checkbox
                            if ($type === 'user') {
                                // Jika user punya kustomisasi di sub_menu ini, gunakan itu
                                if (in_array($smu_id, $custom_active_smus)) {
                                    $perm = $current_access[$smu_id] ?? [];
                                    $is_custom = true;
                                } else {
                                    // Fallback ke bawaan role
                                    $perm = $inherited_access[$smu_id] ?? [];
                                    $is_custom = false;
                                }
                            } else {
                                $perm = $current_access[$smu_id] ?? [];
                                $is_custom = false;
                            }
                        ?>
                        <div class="flex items-center border-b border-slate-100 hover:bg-slate-50/50 transition-colors">
                            <!-- Nama Sub Menu & Status Kustom -->
                            <div class="flex flex-col px-8 py-3 w-64 flex-shrink-0">
                                <span class="text-sm text-slate-700 font-medium"><?= sanitize($s['nama_smu']) ?></span>
                                <?php if ($type === 'user'): ?>
                                    <span class="text-[9px] mt-0.5 font-bold uppercase tracking-wider">
                                        <?= $is_custom 
                                            ? '<span class="text-amber-500"><i class="fa-solid fa-user-lock mr-0.5"></i> Kustom (Override)</span>' 
                                            : '<span class="text-slate-400"><i class="fa-solid fa-users mr-0.5"></i> Bawaan Jabatan</span>' ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- CRUD Checkboxes -->
                            <div class="flex items-center flex-1 divide-x divide-slate-100">
                                <?php foreach ($crud_cols as $col => $info): ?>
                                <label class="flex flex-col items-center justify-center gap-1.5 flex-1 py-3 px-2 cursor-pointer hover:bg-<?= $info['color'] ?>-50 transition-colors group">
                                    <input type="checkbox"
                                           name="permissions[<?= $smu_id ?>][<?= $col ?>]"
                                           value="1"
                                           class="w-4 h-4 accent-<?= $info['color'] ?>-500 rounded"
                                           <?= !empty($perm[$col]) ? 'checked' : '' ?>>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 group-hover:text-<?= $info['color'] ?>-500 transition-colors hidden sm:block">
                                        <?= $info['label'] ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Footer Save -->
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                        <button type="submit"
                                class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">
                            <i class="fa-solid fa-floppy-disk text-xs"></i> 
                            <?= $type === 'user' ? 'Simpan Kustomisasi User' : 'Simpan Hak Akses Jabatan' ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleAll(state) {
    document.querySelectorAll('#formHakAkses input[type="checkbox"]').forEach(cb => cb.checked = state);
}

function resetOverride(idUser) {
    Swal.fire({
        title: 'Kembalikan ke Default?',
        text: "Kustomisasi hak akses user ini akan dihapus dan user akan kembali menggunakan hak akses bawaan jabatannya.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Kembalikan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const fd = new FormData();
            fd.append('reset_override', '1');
            fd.append('id_user', idUser);
            
            fetch('<?= $sistem ?>/hakakses', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
                            .then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
                    }
                });
        }
    });
}

document.getElementById('formHakAkses')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('<?= $sistem ?>/hakakses', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.msg, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal!', text: res.msg });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi gagal.' }));
});

// ─── Tambah Jabatan Baru ────────────────────────────
function openTambahJabatan() {
    Swal.fire({
        title: 'Tambah Jabatan Baru',
        html: `
            <div class="text-left space-y-4 mt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Nama Jabatan <span class="text-red-500">*</span></label>
                    <input id="swal-nama-role" type="text" placeholder="Contoh: Admin Operasional"
                           class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400">
                </div>
                <div class="flex items-center gap-3 bg-slate-50 rounded-xl px-4 py-3 border border-slate-200">
                    <input type="checkbox" id="swal-is-admin" value="1" class="w-4 h-4 rounded accent-sky-600">
                    <div>
                        <label for="swal-is-admin" class="text-sm font-semibold text-slate-700 cursor-pointer block">Jabatan Administrator</label>
                        <p class="text-[10px] text-slate-400">Centang jika jabatan ini memiliki akses penuh (bypass semua izin).</p>
                    </div>
                </div>
            </div>`,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-plus mr-1"></i> Tambah',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#94a3b8',
        preConfirm: () => {
            const nama = document.getElementById('swal-nama-role').value.trim();
            if (!nama) { Swal.showValidationMessage('Nama jabatan wajib diisi!'); return false; }
            return { nama_role: nama, is_admin: document.getElementById('swal-is-admin').checked ? 1 : 0 };
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('add_role', '1');
        fd.append('nama_role', result.value.nama_role);
        fd.append('is_admin', result.value.is_admin);
        fetch('<?= $sistem ?>/hakakses', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', html: res.msg, timer: 1500, showConfirmButton: false })
                        .then(() => location.href = '<?= $sistem ?>/hakakses?type=role');
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', html: res.msg });
                }
            }).catch(() => Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi gagal.' }));
    });
}

// ─── Hapus Jabatan ─────────────────────────────────
function hapusJabatan(id, nama) {
    Swal.fire({
        title: 'Hapus Jabatan?',
        html: `Jabatan <b>${nama}</b> akan dihapus beserta semua pengaturan hak aksesnya.<br><span class="text-xs text-rose-500 font-semibold">* User yang memakai jabatan ini tidak akan terhapus, namun jabatannya menjadi kosong.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Menghapus...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('delete_role', '1');
        fd.append('id_role', id);
        fetch('<?= $sistem ?>/hakakses', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Dihapus!', html: res.msg, timer: 1500, showConfirmButton: false })
                        .then(() => location.href = '<?= $sistem ?>/hakakses?type=role');
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', html: res.msg });
                }
            }).catch(() => Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi gagal.' }));
    });
}
</script>