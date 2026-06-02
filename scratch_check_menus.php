<?php
require_once('config/connection/connection.php');
header('Content-Type: text/plain');

echo "=== KATEGORI ===\n";
foreach($conn->query("SELECT * FROM kategori_menu")->fetchAll() as $r) {
    print_r($r);
}

echo "\n=== MENU ===\n";
foreach($conn->query("SELECT * FROM menu")->fetchAll() as $r) {
    print_r($r);
}

echo "\n=== SUB MENU ===\n";
foreach($conn->query("SELECT * FROM sub_menu")->fetchAll() as $r) {
    print_r($r);
}
