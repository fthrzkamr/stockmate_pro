<?php
session_start();
require_once(__DIR__ . '/../../config/connection/db.php');
require_once(__DIR__ . '/../../config/function/func.php');

header('Content-Type: application/json');

// Cek jika request AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
    exit;
}

// Validasi modul
$allowedModules = ['barang', 'supplier', 'outlet', 'inventory', 'barangmasuk', 'barangkeluar'];
$modul = sanitize($_GET['modul'] ?? '');
if (!in_array($modul, $allowedModules)) {
    echo json_encode(['status' => 'error', 'msg' => 'Modul tidak valid']);
    exit;
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;
$search = sanitize($_GET['search'] ?? '');

try {
    switch ($modul) {
        case 'barang':
            $query = "SELECT b.*, s.nama_supplier, t.nama_tipe FROM barang b 
                      LEFT JOIN supplier s ON b.id_supplier = s.id_supplier 
                      LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe";
            $countQuery = "SELECT COUNT(*) FROM barang b LEFT JOIN supplier s ON b.id_supplier = s.id_supplier";
            if (!empty($search)) {
                $query .= " WHERE b.nama_barang LIKE '%$search%' OR b.barcode LIKE '%$search%' OR s.nama_supplier LIKE '%$search%'";
                $countQuery .= " WHERE b.nama_barang LIKE '%$search%' OR b.barcode LIKE '%$search%' OR s.nama_supplier LIKE '%$search%'";
            }
            $dataQuery = $query . " ORDER BY b.nama_barang ASC LIMIT $limit OFFSET $offset";
            $result = $conn->query($dataQuery)->fetchAll();
            $totalData = (int)$conn->query($countQuery)->fetchColumn();
            $listType = 'barang';
            break;
            
        case 'supplier':
            $query = "SELECT * FROM supplier";
            if (!empty($search)) {
                $query .= " WHERE nama_supplier LIKE '%$search%' OR alamat LIKE '%$search%' OR telepon LIKE '%$search%'";
            }
            $dataQuery = $query . " ORDER BY nama_supplier ASC LIMIT $limit OFFSET $offset";
            $result = $conn->query($dataQuery)->fetchAll();
            $totalData = (int)$conn->query("SELECT COUNT(*) FROM supplier" . (!empty($search) ? " WHERE nama_supplier LIKE '%$search%' OR alamat LIKE '%$search%' OR telepon LIKE '%$search%'" : ""))->fetchColumn();
            $listType = 'supplier';
            break;
            
        case 'outlet':
            $query = "SELECT o.*, (SELECT COUNT(*) FROM barang_keluar bk WHERE bk.id_outlet = o.id_outlet) as total_pengiriman FROM outlet o";
            if (!empty($search)) {
                $query .= " WHERE nama_outlet LIKE '%$search%' OR alamat LIKE '%$search%'";
            }
            $dataQuery = $query . " ORDER BY nama_outlet ASC LIMIT $limit OFFSET $offset";
            $result = $conn->query($dataQuery)->fetchAll();
            $totalData = (int)$conn->query("SELECT COUNT(*) FROM outlet" . (!empty($search) ? " WHERE nama_outlet LIKE '%$search%' OR alamat LIKE '%$search%'" : ""))->fetchColumn();
            $listType = 'outlet';
            break;
            
        case 'inventory':
            $query = "SELECT i.*, b.nama_barang, b.barcode, b.satuan, b.kategori, t.nama_tipe,
                     (SELECT SUM(qty) FROM barang_masuk bm WHERE bm.id_barang = i.id_barang) as total_masuk
                     FROM inventory i JOIN barang b ON i.id_barang = b.id_barang
                     LEFT JOIN tipe_barang t ON b.id_tipe = t.id_tipe";
            $countQuery = "SELECT COUNT(*) FROM inventory i JOIN barang b ON i.id_barang = b.id_barang";
            if (!empty($search)) {
                $query .= " WHERE b.nama_barang LIKE '%$search%' OR b.barcode LIKE '%$search%'";
                $countQuery .= " WHERE b.nama_barang LIKE '%$search%' OR b.barcode LIKE '%$search%'";
            }
            $dataQuery = $query . " ORDER BY b.nama_barang ASC LIMIT $limit OFFSET $offset";
            $result = $conn->query($dataQuery)->fetchAll();
            $totalData = (int)$conn->query($countQuery)->fetchColumn();
            $listType = 'inventory';
            break;
            
        case 'barangmasuk':
            $query = "SELECT bm.*, b.nama_barang, b.barcode, b.satuan, s.nama_supplier, u.nama as operator
                      FROM barang_masuk bm
                      JOIN barang b ON bm.id_barang = b.id_barang
                      LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
                      LEFT JOIN users u ON bm.id_user = u.id_user";
            $countQuery = "SELECT COUNT(*) FROM barang_masuk bm JOIN barang b ON bm.id_barang = b.id_barang";
            if (!empty($search)) {
                $query .= " WHERE b.nama_barang LIKE '%$search%' OR b.barcode LIKE '%$search%' OR s.nama_supplier LIKE '%$search%'";
                $countQuery .= " WHERE b.nama_barang LIKE '%$search%' OR b.barcode LIKE '%$search%' OR s.nama_supplier LIKE '%$search%'";
            }
            $dataQuery = $query . " ORDER BY bm.tanggal DESC, bm.id_masuk DESC LIMIT $limit OFFSET $offset";
            $result = $conn->query($dataQuery)->fetchAll();
            $totalData = (int)$conn->query($countQuery)->fetchColumn();
            $listType = 'barangmasuk';
            break;
            
        case 'barangkeluar':
            $query = "SELECT bk.*, b.nama_barang, b.barcode, b.satuan, o.nama_outlet, u.nama as nama_admin
                      FROM barang_keluar bk
                      JOIN barang b ON bk.id_barang = b.id_barang
                      LEFT JOIN outlet o ON bk.id_outlet = o.id_outlet
                      LEFT JOIN users u ON bk.id_user = u.id_user";
            $countQuery = "SELECT COUNT(*) FROM barang_keluar bk JOIN barang b ON bk.id_barang = b.id_barang";
            if (!empty($search)) {
                $query .= " WHERE b.nama_barang LIKE '%$search%' OR o.nama_outlet LIKE '%$search%' OR bk.tanggal LIKE '%$search%'";
                $countQuery .= " WHERE b.nama_barang LIKE '%$search%' OR o.nama_outlet LIKE '%$search%' OR bk.tanggal LIKE '%$search%'";
            }
            $dataQuery = $query . " ORDER BY bk.id_keluar DESC LIMIT $limit OFFSET $offset";
            $result = $conn->query($dataQuery)->fetchAll();
            $totalData = (int)$conn->query($countQuery)->fetchColumn();
            $listType = 'barangkeluar';
            break;
            
        default:
            echo json_encode(['status' => 'error', 'msg' => 'Modul tidak ditemukan']);
            exit;
    }
    
    // Render HTML untuk data
    ob_start();
    require_once(__DIR__ . '/../../content/' . $modul . '/_' . $listType . '_list.php');
    $tableHtml = ob_get_clean();
    
    // Render pagination
    $paginationHtml = generatePagination($totalData, $limit, '/stockmate_pro/' . $modul, $page);
    
    echo json_encode([
        'status' => 'success',
        'data' => $result,
        'table_html' => $tableHtml,
        'pagination_html' => $paginationHtml,
        'total_data' => $totalData,
        'page' => $page,
        'limit' => $limit
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
