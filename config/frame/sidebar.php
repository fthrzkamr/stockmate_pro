<?php
/**
 * Dynamic Sidebar — menu dari database berdasarkan user yang login
 */

// Pastikan variabel global tersedia
global $conn, $kunci, $id_role, $sistem, $menu;

if (isAdmin()) {
    // Admin bypass: load semua kategori yang memiliki sub-menu aktif
    $qKate = $conn->prepare("
        SELECT DISTINCT km.id_kmu, km.nama_kmu, km.urutan_kmu
        FROM sub_menu sm
        INNER JOIN menu m           ON sm.id_menu = m.id_menu
        INNER JOIN kategori_menu km ON m.id_kmu = km.id_kmu
        ORDER BY km.urutan_kmu ASC
    ");
    $qKate->execute();
} else {
    // Non-admin: load kategori berdasarkan custom user override atau fallback ke role
    $qKate = $conn->prepare("
        SELECT DISTINCT km.id_kmu, km.nama_kmu, km.urutan_kmu
        FROM sub_menu sm
        INNER JOIN menu m           ON sm.id_menu = m.id_menu
        INNER JOIN kategori_menu km ON m.id_kmu = km.id_kmu
        LEFT JOIN role_menu rm      ON rm.id_smu = sm.id_smu AND rm.id_role = :id_role
        LEFT JOIN user_menu um      ON um.id_smu = sm.id_smu AND um.id_user = :id_user
        WHERE (COALESCE(um.can_view, rm.can_view) = 1)
        ORDER BY km.urutan_kmu ASC
    ");
    $qKate->execute([':id_role' => $id_role, ':id_user' => $kunci]);
}
$kategoriList = $qKate->fetchAll();
?>

<?php if (empty($kategoriList)): ?>
    <!-- Fallback: tidak ada menu yang di-assign -->
    <div class="px-3 py-8 text-center">
        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-lock text-slate-400 text-lg"></i>
        </div>
        <p class="text-slate-800 font-bold text-sm">Belum ada menu</p>
        <p class="text-slate-500 text-xs mt-1">Hubungi administrator.</p>
    </div>

<?php else: ?>

    <!-- Dashboard selalu tampil -->
    <div class="mb-4">
        <a href="<?= $sistem ?>/home"
           onclick="loadcontent('home', event)"
           class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200
                  <?= ($menu === 'home' || $menu === '')
                      ? 'bg-sky-50 text-sky-700 font-bold border border-sky-100/50 shadow-sm'
                      : 'text-slate-600 font-medium hover:bg-slate-50 hover:text-slate-900' ?>">
            <i class="fa-solid fa-gauge-high w-5 text-center <?= ($menu === 'home' || $menu === '') ? 'text-sky-600' : 'text-slate-400' ?>"></i>
            <span x-show="sidebarOpen" x-cloak class="truncate">Dashboard</span>
        </a>
    </div>

    <?php foreach ($kategoriList as $kate): ?>

    <!-- Kategori Group -->
    <div class="mb-5">
        <p x-show="sidebarOpen" x-cloak
           class="text-slate-400 text-[10px] font-bold uppercase tracking-widest px-3 mb-2">
            <?= htmlspecialchars($kate['nama_kmu']) ?>
        </p>
        <div x-show="!sidebarOpen" x-cloak class="border-b border-slate-200 mb-2 mx-2"></div>

        <?php
        if (isAdmin()) {
            $qMenu = $conn->prepare("
                SELECT DISTINCT m.id_menu, m.nama_menu, ic.nama_icon
                FROM sub_menu sm
                INNER JOIN menu m ON sm.id_menu = m.id_menu
                LEFT JOIN icon ic ON m.id_icon = ic.id_icon
                WHERE m.id_kmu = :kmu
                ORDER BY m.urutan_menu ASC
            ");
            $qMenu->execute([':kmu' => $kate['id_kmu']]);
        } else {
            $qMenu = $conn->prepare("
                SELECT DISTINCT m.id_menu, m.nama_menu, ic.nama_icon
                FROM sub_menu sm
                INNER JOIN menu m      ON sm.id_menu = m.id_menu
                LEFT JOIN icon ic      ON m.id_icon = ic.id_icon
                LEFT JOIN role_menu rm ON rm.id_smu = sm.id_smu AND rm.id_role = :id_role
                LEFT JOIN user_menu um ON um.id_smu = sm.id_smu AND um.id_user = :id_user
                WHERE m.id_kmu = :kmu AND (COALESCE(um.can_view, rm.can_view) = 1)
                ORDER BY m.urutan_menu ASC
            ");
            $qMenu->execute([':id_role' => $id_role, ':id_user' => $kunci, ':kmu' => $kate['id_kmu']]);
        }
        $menuList = $qMenu->fetchAll();
        ?>

        <div class="space-y-1">
        <?php foreach ($menuList as $mnu): ?>

            <?php
            if (isAdmin()) {
                $qSub = $conn->prepare("
                    SELECT sm.nama_smu, sm.url_smu
                    FROM sub_menu sm
                    WHERE sm.id_menu = :mid
                    ORDER BY sm.urutan_smu ASC
                ");
                $qSub->execute([':mid' => $mnu['id_menu']]);
            } else {
                $qSub = $conn->prepare("
                    SELECT sm.nama_smu, sm.url_smu
                    FROM sub_menu sm
                    LEFT JOIN role_menu rm ON rm.id_smu = sm.id_smu AND rm.id_role = :id_role
                    LEFT JOIN user_menu um ON um.id_smu = sm.id_smu AND um.id_user = :id_user
                    WHERE sm.id_menu = :mid AND (COALESCE(um.can_view, rm.can_view) = 1)
                    ORDER BY sm.urutan_smu ASC
                ");
                $qSub->execute([':id_role' => $id_role, ':id_user' => $kunci, ':mid' => $mnu['id_menu']]);
            }
            $subList = $qSub->fetchAll();

            $isMenuActive = false;
            foreach ($subList as $s) {
                if ($menu === $s['url_smu']) { $isMenuActive = true; break; }
            }

            $icon = $mnu['nama_icon'] ?: 'fa-circle-dot';
            ?>

            <?php if (count($subList) === 1): ?>
                <!-- Single sub -->
                <?php $s = $subList[0]; ?>
                <a href="<?= $sistem ?>/<?= htmlspecialchars($s['url_smu']) ?>"
                   onclick="loadcontent('<?= htmlspecialchars($s['url_smu']) ?>', event)"
                   class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200
                          <?= $isMenuActive
                              ? 'bg-sky-50 text-sky-700 font-bold border border-sky-100/50 shadow-sm'
                              : 'text-slate-600 font-medium hover:bg-slate-50 hover:text-slate-900' ?>"
                   title="<?= htmlspecialchars($mnu['nama_menu']) ?>">
                    <i class="fa-solid <?= htmlspecialchars($icon) ?> w-5 text-center
                               <?= $isMenuActive ? 'text-sky-600' : 'text-slate-400' ?>"></i>
                    <span x-show="sidebarOpen" x-cloak class="truncate"><?= htmlspecialchars($mnu['nama_menu']) ?></span>
                </a>

            <?php else: ?>
                <!-- Multiple sub -->
                <div x-data="{ open: <?= $isMenuActive ? 'true' : 'false' ?> }">
                    <button @click="open = !open"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200
                                   <?= $isMenuActive
                                       ? 'text-sky-700 font-bold bg-sky-50'
                                       : 'text-slate-600 font-medium hover:bg-slate-50 hover:text-slate-900' ?>"
                            title="<?= htmlspecialchars($mnu['nama_menu']) ?>">
                        <i class="fa-solid <?= htmlspecialchars($icon) ?> w-5 text-center
                                   <?= $isMenuActive ? 'text-sky-600' : 'text-slate-400' ?>"></i>
                        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left truncate">
                            <?= htmlspecialchars($mnu['nama_menu']) ?>
                        </span>
                        <i x-show="sidebarOpen" x-cloak
                           class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200
                                  <?= $isMenuActive ? 'text-sky-600' : 'text-slate-400' ?>"
                           :class="open ? 'rotate-180' : ''"></i>
                    </button>

                    <!-- Sub Items -->
                    <div x-show="open && sidebarOpen" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="ml-5 mt-1 border-l-2 border-slate-100 pl-3 space-y-1 py-1">
                        <?php foreach ($subList as $sub):
                            if ($sub['url_smu'] === 'administrator/i') continue;
                            $isSubActive = ($menu === $sub['url_smu']);
                        ?>
                        <a href="<?= $sistem ?>/<?= htmlspecialchars($sub['url_smu']) ?>"
                           onclick="loadcontent('<?= htmlspecialchars($sub['url_smu']) ?>', event)"
                           class="sidebar-link flex items-center gap-2 px-3 py-2 rounded-lg text-xs transition-all duration-200
                                  <?= $isSubActive
                                      ? 'text-sky-700 font-bold bg-sky-50 shadow-sm border border-sky-100/50'
                                      : 'text-slate-500 font-medium hover:text-slate-800 hover:bg-slate-50' ?>">
                            <span class="flex-1 truncate"><?= htmlspecialchars($sub['nama_smu']) ?></span>
                            <?php if ($sub['url_smu'] === 'approvalso'): ?>
                                <span id="badge-so" class="hidden bg-red-500 text-white text-[9px] px-1.5 py-0.5 rounded-md font-bold">0</span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php endif; ?>

        <?php endforeach; ?>
        </div>
    </div>

    <?php endforeach; ?>

<?php endif; ?>