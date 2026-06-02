<?php
global $conn;

try {
    // 1. Bersihkan duplikat di table menu
    $duplicate_menus = $conn->query("
        SELECT nama_menu, MIN(id_menu) as keep_id 
        FROM menu 
        GROUP BY nama_menu 
        HAVING COUNT(*) > 1
    ")->fetchAll();

    foreach ($duplicate_menus as $dm) {
        $stmt = $conn->prepare("DELETE FROM menu WHERE nama_menu = ? AND id_menu != ?");
        $stmt->execute([$dm['nama_menu'], $dm['keep_id']]);
    }

    // 2. Bersihkan duplikat di table sub_menu
    $duplicate_subs = $conn->query("
        SELECT url_smu, MIN(id_smu) as keep_id 
        FROM sub_menu 
        GROUP BY url_smu 
        HAVING COUNT(*) > 1
    ")->fetchAll();

    foreach ($duplicate_subs as $ds) {
        $stmt = $conn->prepare("DELETE FROM sub_menu WHERE url_smu = ? AND id_smu != ?");
        $stmt->execute([$ds['url_smu'], $ds['keep_id']]);
    }

    // 3. Bersihkan role_menu yang tidak valid (orphaned)
    $conn->exec("DELETE FROM role_menu WHERE id_smu NOT IN (SELECT id_smu FROM sub_menu)");
    $conn->exec("DELETE FROM role_menu WHERE id_role NOT IN (SELECT id_role FROM roles)");

} catch (Exception $e) {
    // Silent
}
