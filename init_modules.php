<?php foreach ([
    'content/barang/barang.php',
    'content/barang/input.php',
    'content/barang/edit.php',
    'content/barang/view.php',
    'content/supplier/supplier.php',
    'content/supplier/input.php',
    'content/supplier/edit.php',
    'content/supplier/view.php',
    'content/outlet/outlet.php',
    'content/outlet/input.php',
    'content/outlet/edit.php',
    'content/outlet/view.php',
    'content/barangmasuk/barangmasuk.php',
    'content/barangmasuk/input.php',
    'content/barangmasuk/view.php',
    'content/barangkeluar/barangkeluar.php',
    'content/barangkeluar/input.php',
    'content/barangkeluar/view.php',
    'content/terimabarang/terimabarang.php',
    'content/terimabarang/input.php',
    'content/terimabarang/view.php',
    'content/pemakaian/pemakaian.php',
    'content/pemakaian/input.php',
    'content/pemakaian/view.php',
    'content/stokoutlet/stokoutlet.php',
    'content/stockopname/stockopname.php',
    'content/stockopname/input.php',
    'content/stockopname/edit.php',
    'content/stockopname/view.php',
    'content/approvalso/approvalso.php',
    'content/approvalso/view.php',
    'content/laporan/laporan.php',
    'content/laporan/stok.php',
    'content/laporan/masuk.php',
    'content/laporan/keluar.php',
    'content/laporan/selisih.php',
    'content/administrator/administrator.php',
    'content/administrator/input.php',
    'content/administrator/edit.php',
    'content/hakakses/hakakses.php',
    'content/scanner/scanner.php',
    'content/account/account.php',
] as $file):
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = ucwords(str_replace(['content/','/','_'], ['','/', ' '], $dir));
    file_put_contents($file, "<?php // Module: $name — Segera dibangun\n?>"
        ."<div class='card fade-up'>"
        ."<div class='flex items-center gap-4 py-8 justify-center'>"
        ."<div class='w-14 h-14 bg-indigo-500/10 rounded-2xl flex items-center justify-center'>"
        ."<i class='fa-solid fa-gear text-indigo-400 text-2xl'></i></div>"
        ."<div><p class='text-white font-bold'>$name</p>"
        ."<p class='text-slate-500 text-sm'>Modul ini sedang dalam pengembangan.</p></div>"
        ."</div></div>");
endforeach;
echo "OK: ".count([1]) ." groups created";
