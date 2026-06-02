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
 * Auto-migration & Auto-sync hak akses untuk Role Administrator (is_admin=1).
 * Ini memastikan kolom CRUD ada di DB dan Admin otomatis punya akses ke menu baru.
 */
function syncAdminPermissions(): void {
    global $conn;
    // Jalankan migrasi tabel roles, users, dan role_menu
    require_once(__DIR__ . '/migration_rbac.php');
    // Bersihkan menu duplikat
    require_once(__DIR__ . '/clean_menu.php');
    
    try {
        // Cari id_role mana saja yang memiliki hak is_admin = 1
        $admin_roles = $conn->query("SELECT id_role FROM roles WHERE is_admin = 1")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($admin_roles)) return;

        $total_menu = $conn->query("SELECT COUNT(*) FROM sub_menu")->fetchColumn();
        
        foreach ($admin_roles as $id_role) {
            $has_menu = $conn->prepare("SELECT COUNT(*) FROM role_menu WHERE id_role = ?");
            $has_menu->execute([$id_role]);
            
            if ((int)$total_menu !== (int)$has_menu->fetchColumn()) {
                $all_smus = $conn->query("SELECT id_smu FROM sub_menu")->fetchAll(PDO::FETCH_COLUMN);
                $conn->beginTransaction();
                $stmt = $conn->prepare("
                    INSERT INTO role_menu (id_role, id_smu, can_view, can_create, can_edit, can_delete, can_print)
                    VALUES (?, ?, 1, 1, 1, 1, 1)
                    ON DUPLICATE KEY UPDATE 
                        can_view=1, can_create=1, can_edit=1, can_delete=1, can_print=1
                ");
                foreach ($all_smus as $id_smu) {
                    $stmt->execute([$id_role, $id_smu]);
                }
                $conn->commit();
            }
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
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

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
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
