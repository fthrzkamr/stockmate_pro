<?php
session_start();
require_once('config/connection/connection.php');
require_once('config/function/func.php');

header('Content-Type: application/json');

$kunci = $_SESSION['user_id'] ?? '';
$data = [
    'pending_so' => 0,
    'low_stock'  => 0,
    'total'      => 0
];

if (!$kunci) {
    echo json_encode($data);
    exit;
}

// Cek Pending Approval Stock Opname (Hanya untuk yang punya hak akses menu approvalso)
if (hasMenuAccess('approvalso')) {
    $data['pending_so'] = (int) $conn->query("SELECT COUNT(*) FROM stock_opname WHERE status_approval = 'Pending'")->fetchColumn();
}

// Cek Barang Hampir Habis (Hanya untuk yang punya hak akses menu barang)
if (hasMenuAccess('barang')) {
    $data['low_stock'] = (int) $conn->query("
        SELECT COUNT(*) FROM (
            SELECT b.id_barang, b.min_stok,
                   (COALESCE((SELECT SUM(qty) FROM barang_masuk WHERE id_barang = b.id_barang), 0) - 
                    COALESCE((SELECT SUM(qty) FROM barang_keluar WHERE id_barang = b.id_barang), 0)) as current_stok
            FROM barang b
        ) t WHERE current_stok <= min_stok AND min_stok > 0
    ")->fetchColumn();
}

$data['total'] = $data['pending_so'] + $data['low_stock'];

echo json_encode($data);
?>
