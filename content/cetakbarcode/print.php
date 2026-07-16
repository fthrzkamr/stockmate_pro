<?php
if (!canDo('cetakbarcode', 'print')) {
    die("Anda tidak memiliki akses untuk mencetak barcode.");
}

global $conn;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_barang'])) {
    die("Tidak ada barang yang dipilih untuk dicetak.");
}

$id_barangs = $_POST['id_barang'];
$qty = (int)($_POST['qty_per_item'] ?? 1);
if ($qty < 1) $qty = 1;

// Siapkan placeholder untuk query IN
$inQuery = implode(',', array_fill(0, count($id_barangs), '?'));

try {
    $stmt = $conn->prepare("
        SELECT id_barang, nama_barang, barcode 
        FROM barang 
        WHERE id_barang IN ($inQuery) AND barcode IS NOT NULL AND barcode != ''
    ");
    $stmt->execute($id_barangs);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

if (empty($items)) {
    die("Barang yang dipilih tidak valid atau tidak memiliki barcode.");
}

// Buat array kumpulan label yang akan dicetak (dikalikan qty)
$labelsToPrint = [];
foreach ($items as $item) {
    for ($i = 0; $i < $qty; $i++) {
        $labelsToPrint[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Barcode Label</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- JsBarcode CDN -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        /* Pengaturan Kertas Print */
        @page {
            margin: 0; 
        }
        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        .print-container {
            background: white;
            padding: 20px;
            max-width: 210mm; /* Lebar maksimal A4 */
            margin: 0 auto;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        
        /* Grid Layout fleksibel (Menggunakan flex untuk kompatibilitas Print Multi-Halaman) */
        .label-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 5mm; 
            justify-content: flex-start;
        }

        /* Desain per Stiker Label */
        .label-item {
            width: 50mm; /* Ukuran fixed agar stabil di berbagai ukuran kertas */
            border: 1px solid #e2e8f0;
            padding: 6px;
            text-align: center;
            border-radius: 6px;
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: white;
            min-height: 35mm;
            overflow: hidden;
            box-sizing: border-box;
        }
        
        .label-title {
            font-size: 10px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 3px;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            width: 100%;
        }

        .barcode-svg {
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        /* Hilangkan elemen non-print saat dicetak */
        @media print {
            body { background: white; padding: 0; }
            .print-container { box-shadow: none; padding: 0; max-width: 100%; width: 100%; }
            .no-print { display: none !important; }
            .label-item { border: 1px solid #000; border-radius: 0; }
        }
    </style>
</head>
<body>

    <div class="max-w-3xl mx-auto mb-6 flex items-center justify-between no-print">
        <button onclick="window.close()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-300">Tutup Halaman</button>
        <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold shadow hover:bg-indigo-700 flex items-center gap-2">
            <svg class="w-4 h-4 fill-current" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M128 0C92.7 0 64 28.7 64 64v96h64V64H354.7L384 93.3V160h64V93.3c0-17-6.7-33.3-18.7-45.3L400 18.7C388 6.7 371.7 0 354.7 0H128zM384 352v32 64H128V384 368 352H384zm64 80c0-17.7-14.3-32-32-32H400V448H112V400H96c-17.7 0-32 14.3-32 32v64c0 17.7 14.3 32 32 32H416c17.7 0 32-14.3 32-32V432zM43.7 211.8C34.1 216.1 28 225.7 28 236V368c0 26.5 21.5 48 48 48h28v-64H76c-8.8 0-16-7.2-16-16V236c0-1.8 .3-3.5 1-5.1c1.4-3.2 4.1-5.7 7.4-6.8l38.2-12.7c6.1-2 12.8-2 18.9 0l38.2 12.7c3.3 1.1 6 3.6 7.4 6.8c.7 1.6 1 3.3 1 5.1V352c0 8.8-7.2 16-16 16H128v64h64V352c0-26.5-21.5-48-48-48V250.3l-24.8-8.3c-15.6-5.2-32.3-5.2-47.9 0L43.7 211.8zM245.9 220.8c-1.2-3.1-4.2-5.1-7.5-5.1h-48c-3.3 0-6.3 2-7.5 5.1l-14.4 36.1c-1.7 4.1-3.1 8.3-4.1 12.6v45.1c0 8.8-7.2 16-16 16H128v64h64V352c0-26.5-21.5-48-48-48V270.8l10.5-26.3c1.5-3.8 2.7-7.6 3.4-11.6h51.3c.8 4 2 7.8 3.4 11.6l10.5 26.3v33.2c0 26.5-21.5 48-48 48h-16v64h64V352c0-8.8-7.2-16-16-16V275.5c-1-4.3-2.4-8.5-4.1-12.6l-14.4-36.1zM344 215.7h-48c-4.4 0-8 3.6-8 8v160c0 4.4 3.6 8 8 8h48c22.1 0 40-17.9 40-40V255.7c0-22.1-17.9-40-40-40zm-16 136h-16V247.7h16v104zM456 215.7h-48c-4.4 0-8 3.6-8 8v160c0 4.4 3.6 8 8 8h48c22.1 0 40-17.9 40-40V255.7c0-22.1-17.9-40-40-40zm-16 136h-16V247.7h16v104z"/></svg>
            Print Label Sekarang
        </button>
    </div>

    <div class="print-container">
        <div class="label-grid">
            <?php foreach ($labelsToPrint as $index => $label): ?>
            <div class="label-item">
                <div class="label-title"><?= htmlspecialchars($label['nama_barang']) ?></div>
                <svg id="barcode-<?= $index ?>" class="barcode-svg"></svg>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Data barcode disuntikkan dari PHP ke JS
        const labelsData = <?= json_encode($labelsToPrint) ?>;

        document.addEventListener("DOMContentLoaded", function() {
            labelsData.forEach((item, index) => {
                // Render Barcode
                JsBarcode("#barcode-" + index, item.barcode, {
                    format: "CODE128",
                    lineColor: "#000",
                    width: 1.2,
                    height: 30,
                    displayValue: true,
                    fontSize: 10,
                    fontOptions: "bold",
                    textMargin: 2,
                    margin: 0
                });
            });

            // Tunda print otomatis selama 1 detik agar JS selesai merender gambar SVG
            setTimeout(() => {
                window.print();
            }, 1000);
        });
    </script>
</body>
</html>
