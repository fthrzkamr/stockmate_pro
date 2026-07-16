<?php
session_start();
require_once('config/connection/connection.php');
require_once('config/function/func.php');

header('Content-Type: application/json');

$kunci = $_SESSION['user_id'] ?? '';
$data = [
    'pending_so' => 0,
    'low_stock'  => 0,
    'total'      => 0,
    'items'      => []
];

if (!$kunci) {
    echo json_encode($data);
    exit;
}

// Cek Pending Approval Stock Opname (Hanya untuk yang punya hak akses menu approvalso)
if (hasMenuAccess('approvalso')) {
    $pending_count = (int) $conn->query("SELECT COUNT(*) FROM stock_opname WHERE status_approval = 'Pending'")->fetchColumn();
    $data['pending_so'] = $pending_count;
    if ($pending_count > 0) {
        $data['items'][] = [
            'type' => 'approval_so',
            'title' => 'Persetujuan Stock Opname',
            'desc' => "Terdapat $pending_count pengajuan SO baru memerlukan verifikasi Anda.",
            'link' => 'approvalso',
            'icon' => 'fa-signature',
            'iconBg' => 'bg-amber-500/10 text-amber-600 border border-amber-500/20 shadow-sm'
        ];
    }
}

// Cek Barang Hampir Habis (Hanya untuk yang punya hak akses menu barang / laporan)
if (hasMenuAccess('barang') || hasMenuAccess('laporan') || hasMenuAccess('laporanstok')) {
    // Get low stock count
    $low_stock_query = "
        SELECT b.id_barang, b.nama_barang, b.barcode, b.min_stok,
               (COALESCE((SELECT SUM(qty) FROM barang_masuk WHERE id_barang = b.id_barang), 0) - 
                COALESCE((SELECT SUM(qty) FROM barang_keluar WHERE id_barang = b.id_barang), 0)) as current_stok
        FROM barang b
        WHERE b.is_active = 1
    ";
    
    $low_stock_items = [];
    try {
        $stmt = $conn->query($low_stock_query);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_items as $row) {
            if ($row['current_stok'] <= $row['min_stok'] && $row['min_stok'] > 0) {
                $low_stock_items[] = $row;
            }
        }
    } catch (Exception $e) {}
    
    $low_stock_count = count($low_stock_items);
    $data['low_stock'] = $low_stock_count;
    
    if ($low_stock_count > 0) {
        // Add summary item
        $data['items'][] = [
            'type' => 'low_stock_summary',
            'title' => 'Stok Kritis Terdeteksi',
            'desc' => "Ada $low_stock_count barang dengan jumlah di bawah batas minimum.",
            'link' => 'laporanstok',
            'icon' => 'fa-triangle-exclamation',
            'iconBg' => 'bg-rose-500/10 text-rose-600 border border-rose-500/20 shadow-sm'
        ];
        
        // Add all individual low stock items
        foreach ($low_stock_items as $item) {
            $data['items'][] = [
                'type' => 'low_stock_item',
                'title' => sanitize($item['nama_barang']),
                'desc' => "Stok: " . number_format($item['current_stok']) . " (Min: " . number_format($item['min_stok']) . ")",
                'link' => 'laporanstok?search=' . urlencode($item['barcode'] ?: $item['nama_barang']),
                'icon' => 'fa-box',
                'iconBg' => 'bg-slate-50 text-slate-500 border border-slate-200'
            ];
        }
    }
}

$data['total'] = $data['pending_so'] + $data['low_stock'];

echo json_encode($data);
?>
