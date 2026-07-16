<?php
session_start();
ob_start();
require_once('config/connection/connection.php');
require_once('config/function/func.php');
$menu   = isset($_GET['menu']) ? htmlspecialchars($_GET['menu']) : 'home';
$kunci  = $_SESSION['user_id']   ?? null;
$admin  = $_SESSION['username']  ?? null;
$nama   = $_SESSION['nama']      ?? null;
$role   = $_SESSION['role']      ?? null;
$id_role = $_SESSION['id_role']  ?? null;
$is_admin = $_SESSION['is_admin'] ?? null;
// Update sistem url since folder changed to stockmate_pro
$sistem = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/stockmate_pro';

if (!$kunci) { header("location:$sistem/signin"); exit; }

// Jika user sudah login sebelumnya dan session id_role / is_admin belum ada, isi secara otomatis dari DB
if ($kunci && ($id_role === null || $is_admin === null)) {
    try {
        $stmt = $conn->prepare("
            SELECT u.id_role, r.is_admin 
            FROM users u
            LEFT JOIN roles r ON u.id_role = r.id_role
            WHERE u.id_user = ?
        ");
        $stmt->execute([$kunci]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['id_role']  = (int)$row['id_role'];
            $_SESSION['is_admin'] = (int)$row['is_admin'];
            $id_role  = (int)$row['id_role'];
            $is_admin = (int)$row['is_admin'];
        }
    } catch(Exception $e) {}
}

// (Auto-sync hak akses telah dinonaktifkan karena migrasi selesai)

// INTERSEPTOR AJAX POST UNTUK PENGHAPUSAN USER
// Mencegah HTML dari sistem.php ter-render ketika memproses AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $menu === 'administrator') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id_user'] ?? 0);
    if ($id === (int)($kunci ?? 0)) {
        echo json_encode(['status'=>'error','msg'=>'Anda tidak bisa menghapus akun sendiri!']);
        exit;
    }
    try {
        $conn->prepare("DELETE FROM users WHERE id_user = ?")->execute([$id]);
        echo json_encode(['status'=>'success','msg'=>'User berhasil dihapus.']);
    } catch(Exception $e) {
        echo json_encode(['status'=>'error','msg'=>'Gagal: '.$e->getMessage()]);
    }
    exit;
}

// INTERCEPT AJAX MODAL REQUESTS
// Jika request adalah AJAX (seperti dari openModal/openLgModal), JANGAN render <html>, header, dan sidebar!
// Cukup render content.php saja agar tidak terjadi nested layout (tampilan miring).
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($isAjax) {
    require_once('config/frame/content.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockMate Pro</title>
    <meta name="description" content="Sistem Manajemen Inventori & Gudang PT PSY BERKAH INDONESIA">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#f0f9ff',100:'#e0f2fe',200:'#bae6fd',300:'#7dd3fc',400:'#38bdf8',500:'#0ea5e9',600:'#0284c7',700:'#0369a1',800:'#075985',900:'#0c4a6e' },
                    },
                    fontFamily: { sans: ['Poppins','system-ui','sans-serif'] }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $sistem ?>/assets/css/main.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased" x-data="{ sidebarOpen: true, mobileOpen: false }">

<!-- Global Modals (Placed at top of body to avoid stacking context issues) -->
<div id="modalGlobal" class="hidden fixed inset-0 z-50 overflow-y-auto flex justify-center items-start p-4">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal()"></div>
    <div id="modalContent" class="relative z-10 w-full max-w-lg rounded-2xl shadow-2xl bg-white border border-slate-150 mt-10 mb-10 md:mt-16">
    </div>
</div>
<div id="modalLg" class="hidden fixed inset-0 z-50 overflow-y-auto flex justify-center items-start p-4">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeLgModal()"></div>
    <div id="modalLgContent" class="relative z-10 w-full max-w-3xl rounded-2xl shadow-2xl bg-white border border-slate-150 mt-10 mb-10 md:mt-16">
    </div>
</div>

<!-- Mobile Overlay -->
<div class="fixed inset-0 bg-slate-900/60 z-20 lg:hidden" x-show="mobileOpen" x-cloak @click="mobileOpen=false"></div>

<!-- Sidebar -->
<aside class="fixed top-0 left-0 h-full z-30 transition-all duration-300 bg-white border-r border-slate-200 shadow-xl flex flex-col w-64 -translate-x-full lg:translate-x-0"
       :class="sidebarOpen ? 'w-64' : 'w-16'"
       x-bind:class="{'translate-x-0': mobileOpen, '-translate-x-full lg:translate-x-0': !mobileOpen}">

    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 h-16 border-b border-slate-200 shrink-0">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 bg-gradient-to-tr from-sky-600 to-blue-500 text-white shadow-lg shadow-sky-500/30">
            <i class="fa-solid fa-layer-group text-sm"></i>
        </div>
        <div x-show="sidebarOpen" x-cloak>
            <p class="font-black text-slate-800 text-[15px] leading-tight">Stock<span class="text-sky-600">Mate</span></p>
            <p class="text-slate-500 text-[9px] font-bold tracking-wider uppercase mt-0.5">PT PSY BERKAH INDONESIA</p>
        </div>
    </div>

    <!-- Nav -->
    <nav class="p-3 overflow-y-auto flex-1">
        <?php require_once('config/frame/sidebar.php'); ?>
    </nav>

    <!-- Footer Sidebar -->
    <div class="p-4 border-t border-slate-200 bg-slate-50 text-center shrink-0">
    </div>
</aside>

<!-- Header (Fixed Full Width to guarantee continuous border) -->
<header class="fixed top-0 left-0 w-full z-10 h-16 bg-white/90 backdrop-blur-lg border-b border-slate-200/60 shadow-lg shadow-slate-200/20 transition-all duration-300 flex items-center">
    <div class="flex-1 flex items-center justify-between pr-6 transition-all duration-300 lg:pl-[256px] pl-6" :class="sidebarOpen ? 'lg:pl-[256px] pl-6' : 'lg:pl-[88px] pl-6'">
        <div class="flex items-center gap-4">
            <!-- Sidebar Toggle -->
            <button @click="if(window.innerWidth < 1024) { mobileOpen = !mobileOpen; sidebarOpen = true; } else { sidebarOpen = !sidebarOpen; }"
                    class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center transition-colors text-slate-500">
                <i class="fa-solid fa-bars text-sm"></i>
            </button>
        </div>
        <div class="flex items-center gap-3">
            <!-- Notif Bell & Dropdown -->
            <div x-data="{ 
                notifOpen: false, 
                notifs: [], 
                totalCount: 0
            }" @notifications-updated.window="notifs = $event.detail.items || []; totalCount = $event.detail.total || 0;" class="relative">
                <button @click="notifOpen = !notifOpen; if(notifOpen) { updateNotification(); }" @click.away="notifOpen = false"
                        class="relative w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center transition-colors text-slate-500">
                    <i class="fa-solid fa-bell text-sm"></i>
                    <span id="notifBadge" class="hidden absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-pulse"></span>
                </button>
                
                <!-- Dropdown Menu -->
                <div x-show="notifOpen" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute right-0 mt-2.5 w-80 rounded-2xl shadow-xl border border-slate-200/80 bg-white py-2 z-50 overflow-hidden">
                     
                     <div class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
                         <span class="text-sm font-bold text-slate-800">Notifikasi</span>
                         <span class="text-[10px] bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full font-bold" x-text="totalCount + ' Baru'"></span>
                     </div>
                     
                     <div class="max-h-64 overflow-y-auto divide-y divide-slate-50">
                         <!-- Empty State -->
                         <div x-show="notifs.length === 0" class="p-8 text-center text-slate-400">
                             <i class="fa-solid fa-bell-slash text-xl mb-1.5 text-slate-300"></i>
                             <p class="text-xs font-medium">Tidak ada notifikasi baru</p>
                         </div>
                         
                         <!-- Notification Items -->
                         <template x-for="item in notifs" :key="item.title + item.desc">
                             <a :href="'<?= $sistem ?>/' + item.link" 
                                onclick="loadcontent(this.getAttribute('href').replace('<?= $sistem ?>/', ''), event)"
                                @click="notifOpen = false" 
                                class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50/80 transition-colors">
                                 <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="item.iconBg">
                                     <i class="fa-solid text-xs" :class="item.icon"></i>
                                 </div>
                                 <div class="flex-1 min-w-0">
                                     <p class="text-xs font-bold text-slate-800 truncate" x-text="item.title"></p>
                                     <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed" x-text="item.desc"></p>
                                 </div>
                             </a>
                         </template>
                     </div>
                     
                     <div class="px-4 py-2 border-t border-slate-100 bg-slate-50/50 text-center">
                         <a href="<?= $sistem ?>/home" onclick="loadcontent('home', event); notifOpen = false;" class="text-[10px] text-indigo-600 hover:text-indigo-700 font-bold">Lihat Semua di Dashboard</a>
                     </div>
                </div>
            </div>
            <!-- User Profile & Logout -->
            <div x-data="{ profileOpen: false }" class="relative">
                <button @click="profileOpen = !profileOpen" @click.away="profileOpen = false"
                        class="flex items-center gap-2 pl-2 pr-1 py-1 rounded-full hover:bg-slate-50 transition-colors border border-transparent hover:border-slate-200">
                    <div class="w-8 h-8 rounded-full bg-sky-600 flex items-center justify-center text-xs font-bold text-white shadow-sm">
                        <?= strtoupper(substr($nama ?? 'U', 0, 1)) ?>
                    </div>
                    <span class="hidden sm:block text-sm font-semibold text-slate-700 pr-1"><?= htmlspecialchars($nama ?? '') ?></span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-200" :class="profileOpen ? 'rotate-180' : ''"></i>
                </button>
                
                <div x-show="profileOpen" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute right-0 mt-2 w-48 rounded-xl shadow-lg border border-slate-100 bg-white py-1 z-50">
                     
                    <div class="px-4 py-3 border-b border-slate-100 mb-1">
                        <p class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($nama ?? '') ?></p>
                        <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider truncate"><?= htmlspecialchars(ucwords(str_replace('_',' ',$role ?? 'user'))) ?></p>
                    </div>

                    <a href="<?= $sistem ?>/signout" class="flex items-center gap-2 px-4 py-2 text-sm text-red-500 hover:bg-red-50 hover:text-red-600 font-medium transition-colors">
                        <i class="fa-solid fa-right-from-bracket w-4"></i> Keluar Aplikasi
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<div class="transition-all duration-300 flex flex-col min-h-screen pt-16 overflow-x-hidden lg:ml-64" :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-16'">

    <!-- Content Area -->
    <main class="px-6 pb-6 pt-2 flex-1 w-full min-w-0 max-w-full overflow-x-hidden">
        <?php require_once('config/frame/content.php'); ?>
    </main>

    <!-- Footer -->
    <footer class="px-6 py-4 border-t border-slate-200 text-center bg-white mt-auto">
        <p class="text-slate-500 text-[11px] font-semibold tracking-wider">&copy; <?= date('Y') ?> PT PSY BERKAH INDONESIA</p>
    </footer>
</div>



<script>
    const usuper = "<?= $sistem ?>";
    const urole  = "<?= $role ?>";
    const uid    = "<?= $kunci ?>";

    function openModal(url) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r=>r.text())
            .then(html=>{
                const content = document.getElementById('modalContent');
                content.innerHTML = html;
                document.getElementById('modalGlobal').classList.remove('hidden');
                
                // Execute scripts inside injected HTML
                const scripts = content.getElementsByTagName('script');
                for (let i = 0; i < scripts.length; i++) {
                    const newScript = document.createElement('script');
                    if (scripts[i].src) newScript.src = scripts[i].src;
                    newScript.text = scripts[i].text;
                    document.body.appendChild(newScript).parentNode.removeChild(newScript);
                }
            });
    }
    function closeModal() { document.getElementById('modalGlobal').classList.add('hidden'); }
    function openLgModal(url) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r=>r.text())
            .then(html=>{
                const content = document.getElementById('modalLgContent');
                content.innerHTML = html;
                document.getElementById('modalLg').classList.remove('hidden');
                
                // Execute scripts inside injected HTML
                const scripts = content.getElementsByTagName('script');
                for (let i = 0; i < scripts.length; i++) {
                    const newScript = document.createElement('script');
                    if (scripts[i].src) newScript.src = scripts[i].src;
                    newScript.text = scripts[i].text;
                    document.body.appendChild(newScript).parentNode.removeChild(newScript);
                }
            });
    }
    function closeLgModal() { document.getElementById('modalLg').classList.add('hidden'); }

    // AJAX load content (Tanpa Refresh Halaman)
    function loadcontent(menu, event = null) {
        if (event) event.preventDefault();
        
        const url = usuper + '/' + menu;
        history.pushState({menu: menu}, '', url);
        
        const mainContent = document.querySelector('main');
        mainContent.innerHTML = '<div class="flex justify-center items-center h-64"><i class="fa-solid fa-spinner fa-spin text-3xl text-sky-600"></i><span class="ml-3 text-slate-500 font-medium tracking-wide">Memuat...</span></div>';
        
        // Tutup sidebar di versi mobile saat menu diklik
        if (window.innerWidth < 1024) {
            document.body.dispatchEvent(new CustomEvent('close-mobile-sidebar'));
        }

        // Highlight link aktif secara manual
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.classList.remove('bg-sky-50', 'text-sky-700', 'font-bold', 'border', 'border-sky-100/50', 'shadow-sm');
            link.classList.add('text-slate-500', 'font-medium', 'hover:text-slate-800', 'hover:bg-slate-50');
            // Untuk ikon
            const icon = link.querySelector('i');
            if (icon) {
                icon.classList.remove('text-sky-600');
                icon.classList.add('text-slate-400');
            }
        });
        
        if (event && event.currentTarget) {
            const el = event.currentTarget;
            el.classList.remove('text-slate-500', 'hover:text-slate-800', 'hover:bg-slate-50', 'text-slate-600', 'hover:text-slate-900');
            el.classList.add('bg-sky-50', 'text-sky-700', 'font-bold', 'border', 'border-sky-100/50', 'shadow-sm');
            const icon = el.querySelector('i');
            if (icon) {
                icon.classList.remove('text-slate-400');
                icon.classList.add('text-sky-600');
            }
        }

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                mainContent.innerHTML = html;
                const scripts = mainContent.getElementsByTagName('script');
                for (let i = 0; i < scripts.length; i++) {
                    const newScript = document.createElement('script');
                    if (scripts[i].src) newScript.src = scripts[i].src;
                    newScript.text = scripts[i].text;
                    document.body.appendChild(newScript).parentNode.removeChild(newScript);
                }
            })
            .catch(err => {
                mainContent.innerHTML = '<div class="text-rose-500 p-4 font-bold text-center">Gagal memuat halaman. Silakan muat ulang.</div>';
            });
    }

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.menu) {
            loadcontent(e.state.menu);
        } else {
            window.location.reload();
        }
    });
</script>
<script src="<?= $sistem ?>/assets/js/main.js?v=<?= time() ?>"></script>
<script src="<?= $sistem ?>/assets/js/ajax-pagination.js?v=<?= time() ?>"></script>
<script>
// Initialize AJAX pagination for current module
document.addEventListener('DOMContentLoaded', function() {
    // Extract module name from URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentUrl = window.location.pathname.split('/').pop();
    
    // Modules that support AJAX pagination
    const ajaxModules = [
        'barang', 'supplier', 'outlet', 'inventory', 'barangmasuk', 'barangkeluar',
        'pemakaian', 'stokoutlet', 'stockopname', 'approvalso', 
        'laporanstok', 'laporanmasuk', 'laporankeluar', 'laporanselisih'
    ];
    if (ajaxModules.includes(currentUrl)) {
        initAjaxPagination(currentUrl);
    }
});
</script>
</body>
</html>
