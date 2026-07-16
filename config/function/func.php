<?php
/**
 * Cek apakah user adalah admin sistem (Super Admin / SPV / role yang punya hak administrator)
 */
function isAdmin(): bool {
    // Membaca status administrator dinamis dari session (diambil dari tabel roles.is_admin)
    return (bool)($_SESSION['is_admin'] ?? false);
}

/**
 * Wajib admin — redirect ke home jika bukan admin
 */
function requireAdmin(): void {
    global $sistem;
    if (!isAdmin()) {
        echo "<script>window.location.href='$sistem/home';</script>";
        exit;
    }
}



/**
 * Cek apakah user punya akses ke URL tertentu (via id_role dari session)
 */
function hasMenuAccess(string $url_smu): bool {
    global $conn;
    if (isAdmin()) return true; // Admin bypass

    $id_user = (int)($_SESSION['user_id'] ?? 0);
    $id_role = (int)($_SESSION['id_role'] ?? 0);

    try {
        // 1. Cek kustomisasi khusus user di user_menu
        $stUser = $conn->prepare("
            SELECT um.can_view FROM user_menu um
            INNER JOIN sub_menu sm ON um.id_smu = sm.id_smu
            WHERE um.id_user = ? AND sm.url_smu = ?
        ");
        $stUser->execute([$id_user, $url_smu]);
        $userVal = $stUser->fetchColumn();
        if ($userVal !== false) {
            return (bool)$userVal;
        }
    } catch(Exception $e) {}

    // 2. Fallback ke Jabatan (Role) jika tidak ada kustomisasi user
    $st = $conn->prepare("
        SELECT COUNT(*) FROM role_menu rm
        INNER JOIN sub_menu sm ON rm.id_smu = sm.id_smu
        WHERE rm.id_role = ? AND sm.url_smu = ? AND rm.can_view = 1
    ");
    $st->execute([$id_role, $url_smu]);
    return (bool) $st->fetchColumn();
}

/**
 * Cek CRUD permission user untuk URL tertentu berdasarkan id_role session / user override
 * $action: 'view' | 'create' | 'edit' | 'delete' | 'print'
 */
function canDo(string $url_smu, string $action = 'view'): bool {
    global $conn;
    if (isAdmin()) return true; // Admin bypass semua

    $id_user = (int)($_SESSION['user_id'] ?? 0);
    $id_role = (int)($_SESSION['id_role'] ?? 0);
    $col = 'can_' . $action;
    $allowed = ['can_view','can_create','can_edit','can_delete','can_print'];
    if (!in_array($col, $allowed)) return false;

    try {
        // 1. Cek kustomisasi khusus user di user_menu
        $stUser = $conn->prepare("
            SELECT um.$col FROM user_menu um
            INNER JOIN sub_menu sm ON um.id_smu = sm.id_smu
            WHERE um.id_user = ? AND sm.url_smu = ?
        ");
        $stUser->execute([$id_user, $url_smu]);
        $userVal = $stUser->fetchColumn();
        if ($userVal !== false) {
            return (bool)$userVal;
        }

        // 2. Fallback ke Jabatan (Role) jika tidak ada kustomisasi user
        $st = $conn->prepare("
            SELECT rm.$col FROM role_menu rm
            INNER JOIN sub_menu sm ON rm.id_smu = sm.id_smu
            WHERE rm.id_role = ? AND sm.url_smu = ?
            LIMIT 1
        ");
        $st->execute([$id_role, $url_smu]);
        $val = $st->fetchColumn();
        return $val !== false && (bool)$val;
    } catch(Exception $e) { return false; }
}

function sanitize($val): string {
    return htmlspecialchars(trim((string)($val ?? '')), ENT_QUOTES, 'UTF-8');
}

function formatTanggal(string $date): string {
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $d = explode('-', $date);
    return ($d[2] ?? '-').' '.($bulan[(int)($d[1] ?? 0)] ?? '').' '.($d[0] ?? '');
}

function formatRupiah(float $angka): string {
    return 'Rp '.number_format($angka, 0, ',', '.');
}

function jsonResponse(string $status, string $msg, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'msg' => $msg], $data));
    exit;
}

/**
 * Pagination Helper - Generate pagination HTML (AJAX capable)
 * @param int $totalData Total data
 * @param int $limit Jumlah data per halaman
 * @param string $urlBase URL dasar tanpa parameter page
 * @param int $currentPage Halaman saat ini
 * @param string $modul Name of the module for AJAX calls
 * @param string $search Search query
 */
/**
 * Generate "Show N entries" dropdown — diletakkan di ATAS tabel (di toolbar)
 */
function generateShowEntries(int $limit, string $modul = '', string $search = ''): string {
    return '<div class="flex items-center gap-2">
        <span class="text-xs text-slate-500 font-medium">Tampilkan:</span>
        <select onchange="changeLimit(this.value, \'' . $modul . '\', \'' . $search . '\')" 
                class="text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-sky-500/30 bg-white text-slate-600 font-medium">
            <option value="10" ' . ($limit == 10 ? 'selected' : '') . '>10</option>
            <option value="25" ' . ($limit == 25 ? 'selected' : '') . '>25</option>
            <option value="50" ' . ($limit == 50 ? 'selected' : '') . '>50</option>
            <option value="100" ' . ($limit == 100 ? 'selected' : '') . '>100</option>
        </select>
        <span class="text-xs text-slate-400">data</span>
    </div>';
}

function generatePagination(int $totalData, int $limit, string $urlBase, int $currentPage, string $modul = '', string $search = ''): string {
    if (empty($modul)) {
        $parts = explode('/', rtrim($urlBase, '/'));
        $modul = end($parts);
    }
    
    $totalPages = max(1, (int)ceil($totalData / $limit));
    $currentPage = max(1, min($currentPage, $totalPages));
    
    $html = '<div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-white">
        <div class="flex items-center gap-2 text-[11px] text-slate-500">
            <span>Halaman <span class="font-bold text-slate-700">' . $currentPage . '</span> dari <span class="font-bold text-slate-700">' . $totalPages . '</span></span>
            <span class="text-slate-300">|</span>
            <span>Total: <span class="font-bold text-sky-600">' . number_format($totalData, 0, ',', '.') . '</span> data</span>
        </div>
        <div class="flex items-center gap-1">
            <button onclick="goToPage(1, ' . $limit . ', \'' . $modul . '\', \'' . $search . '\')" 
                    class="px-2 py-1 hover:bg-slate-100 rounded-lg text-xs ' . ($currentPage == 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600') . '" title="Halaman Pertama">
                <i class="fa-solid fa-angles-left"></i>
            </button>
            <button onclick="goToPage(' . max(1, $currentPage-1) . ', ' . $limit . ', \'' . $modul . '\', \'' . $search . '\')" 
                    class="px-2 py-1 hover:bg-slate-100 rounded-lg text-xs ' . ($currentPage == 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600') . '" title="Sebelumnya">
                <i class="fa-solid fa-angle-left"></i>
            </button>';
    
    // Generate page numbers
    $rangeStart = max(1, $currentPage - 2);
    $rangeEnd = min($totalPages, $currentPage + 2);
    
    for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
        $activeClass = ($i == $currentPage) ? 'bg-sky-600 text-white font-bold shadow-sm' : 'hover:bg-slate-100 text-slate-600';
        $html .= '<button onclick="goToPage(' . $i . ', ' . $limit . ', \'' . $modul . '\', \'' . $search . '\')" class="w-7 h-7 flex items-center justify-center rounded-lg text-xs ' . $activeClass . '">' . $i . '</button>';
    }
    
    $html .= '
            <button onclick="goToPage(' . min($totalPages, $currentPage+1) . ', ' . $limit . ', \'' . $modul . '\', \'' . $search . '\')" 
                    class="px-2 py-1 hover:bg-slate-100 rounded-lg text-xs ' . ($currentPage == $totalPages ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600') . '" title="Selanjutnya">
                <i class="fa-solid fa-angle-right"></i>
            </button>
            <button onclick="goToPage(' . $totalPages . ', ' . $limit . ', \'' . $modul . '\', \'' . $search . '\')" 
                    class="px-2 py-1 hover:bg-slate-100 rounded-lg text-xs ' . ($currentPage == $totalPages ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600') . '" title="Halaman Terakhir">
                <i class="fa-solid fa-angles-right"></i>
            </button>
        </div>
    </div>';
    
    return $html;
}

/**
 * Catat aktivitas user ke tabel audit_log
 */
function writeAuditLog(string $aksi, string $tabel, int $id_record, string $deskripsi): bool {
    global $conn;
    $id_user = (int)($_SESSION['user_id'] ?? 0);
    if ($id_user <= 0) return false;
    try {
        $st = $conn->prepare("
            INSERT INTO audit_log (id_user, aksi, tabel, id_record, deskripsi)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $st->execute([$id_user, $aksi, $tabel, $id_record, $deskripsi]);
    } catch(Exception $e) {
        return false;
    }
}
