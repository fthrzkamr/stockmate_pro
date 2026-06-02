<?php
global $conn, $nama, $sistem;

// Stats
try { $totalBarang = (int)$conn->query("SELECT COUNT(*) FROM barang")->fetchColumn(); } catch(Exception $e) { $totalBarang = 0; }
try { $totalOutlet = (int)$conn->query("SELECT COUNT(*) FROM outlet WHERE is_active = 1")->fetchColumn(); } catch(Exception $e) { $totalOutlet = 0; }
try { 
    $hampirHabis = (int)$conn->query("
        SELECT COUNT(*) FROM (
            SELECT b.id_barang, b.min_stok,
                   (COALESCE((SELECT SUM(qty) FROM barang_masuk WHERE id_barang = b.id_barang), 0) - 
                    COALESCE((SELECT SUM(qty) FROM barang_keluar WHERE id_barang = b.id_barang), 0)) as current_stok
            FROM barang b
        ) t WHERE current_stok <= min_stok AND min_stok > 0
    ")->fetchColumn(); 
} catch(Exception $e) { $hampirHabis = 0; }

// Data Keluar — dikelompokkan per TAHUN per BULAN
$currentYear = (int)date('Y');
$years = [$currentYear - 2, $currentYear - 1, $currentYear];
$bulanLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

// Init data per tahun
$chartSeries = [];
foreach ($years as $y) {
    $chartSeries[$y] = array_fill(0, 12, 0); // index 0-11
}

try {
    $rows = $conn->query("
        SELECT YEAR(tanggal) as thn, MONTH(tanggal) as bln, SUM(qty) as total
        FROM barang_keluar
        WHERE YEAR(tanggal) >= " . ($currentYear - 2) . "
        GROUP BY thn, bln
        ORDER BY thn, bln
    ")->fetchAll();

    foreach ($rows as $r) {
        $y = (int)$r['thn'];
        $m = (int)$r['bln'] - 1; // 0-index
        if (isset($chartSeries[$y])) {
            $chartSeries[$y][$m] = (int)$r['total'];
        }
    }
} catch(Exception $e) {}

$isAdmin = isAdmin();
?>

<div class="fade-up space-y-6">

    <!-- Page Header -->
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
        <p class="text-slate-500 text-sm mt-1">
            Selamat datang, <span class="text-sky-600 font-semibold"><?= htmlspecialchars($nama) ?></span>
            <span class="text-slate-300 mx-2">•</span>
            <span class="text-slate-500"><?= date('d F Y') ?></span>
        </p>
    </div>

    <!-- Alert -->
    <?php if ($hampirHabis > 0): ?>
    <div class="flex items-center gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 flex-shrink-0"></i>
        <span><strong><?= $hampirHabis ?> barang</strong> hampir habis / di bawah stok minimum.</span>
        <a href="<?= $sistem ?>/barang" class="ml-auto text-amber-600 font-bold text-xs bg-amber-100 hover:bg-amber-200 px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
            Cek Barang &rarr;
        </a>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Total Barang -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow flex items-center justify-between gap-4">
            <div>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-2">Total Barang</p>
                <p class="text-3xl font-black text-slate-800"><?= number_format($totalBarang) ?></p>
                <p class="text-slate-500 text-xs mt-1">Item terdaftar</p>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 bg-sky-500 text-white shadow-md shadow-sky-100">
                <i class="fa-solid fa-boxes-stacked text-xl"></i>
            </div>
        </div>
        <!-- Total Outlet -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow flex items-center justify-between gap-4">
            <div>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-2">Total Outlet</p>
                <p class="text-3xl font-black text-slate-800"><?= number_format($totalOutlet) ?></p>
                <p class="text-slate-500 text-xs mt-1">Lokasi aktif</p>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 bg-emerald-500 text-white shadow-md shadow-emerald-100">
                <i class="fa-solid fa-store text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Grafik -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <div>
                <h3 class="text-slate-800 font-bold text-sm">Grafik Penjualan</h3>
                <p class="text-slate-400 text-xs mt-0.5">Perbandingan 3 tahun terakhir per bulan</p>
            </div>
            <div class="flex items-center gap-3">
                <?php $colors = ['#0ea5e9','#10b981','#f59e0b']; $ci = 0; ?>
                <?php foreach ($years as $y): ?>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm" style="background:<?= $colors[$ci] ?>"></span>
                    <span class="text-slate-500 text-xs font-semibold"><?= $y ?></span>
                </div>
                <?php $ci++; endforeach; ?>
            </div>
        </div>
        <div class="p-4">
            <div id="chartPenjualan"></div>
        </div>
    </div>

</div>

<script>
const bulanLabels = <?= json_encode($bulanLabels) ?>;
const seriesData  = <?= json_encode(array_values(array_map('array_values', $chartSeries))) ?>;
const seriesYears = <?= json_encode(array_values($years)) ?>;
const seriesColors = ['#0ea5e9', '#10b981', '#f59e0b'];

const series = seriesYears.map((y, i) => ({ name: String(y), data: seriesData[i] }));

function buildOptions(w) {
    return {
        chart: {
            type: 'bar',
            height: 320,
            width: w || '100%',
            toolbar: { show: false },
            zoom:    { enabled: false },
            fontFamily: 'Poppins, system-ui, sans-serif',
            animations: { enabled: true }
        },
        plotOptions: {
            bar: { columnWidth: '55%', borderRadius: 3 }
        },
        dataLabels: { enabled: false },
        colors: seriesColors,
        series: series,
        xaxis: {
            categories: bulanLabels,
            labels: {
                style: { fontSize: '11px', fontWeight: 500, colors: '#94a3b8' },
                rotate: 0,
                hideOverlappingLabels: false,
                trim: false
            },
            axisBorder: { show: false },
            axisTicks:  { show: false }
        },
        yaxis: {
            min: 0,
            labels: {
                style: { fontSize: '11px', colors: '#94a3b8' },
                formatter: v => Math.floor(v)
            }
        },
        legend: { show: false },
        tooltip: {
            theme: 'light',
            y: { formatter: v => v + ' item' }
        },
        grid: {
            borderColor: '#f1f5f9',
            strokeDashArray: 4,
            yaxis: { lines: { show: true } },
            xaxis: { lines: { show: false } },
            padding: { left: 4, right: 4 }
        }
    };
}

let chartInstance = null;

function renderChart() {
    const el = document.getElementById('chartPenjualan');
    if (!el) return;
    const parentW = el.parentElement ? el.parentElement.offsetWidth : 0;
    if (chartInstance) { chartInstance.destroy(); }
    chartInstance = new ApexCharts(el, buildOptions(parentW > 0 ? parentW : '100%'));
    chartInstance.render();
}

// Render setelah semua CSS & sidebar transition selesai
window.addEventListener('load', () => setTimeout(renderChart, 200));

// Update saat sidebar dibuka/tutup atau resize window
window.addEventListener('resize', () => {
    clearTimeout(window._chartResizeTimer);
    window._chartResizeTimer = setTimeout(renderChart, 150);
});

// Pantau perubahan lebar kontainer (Alpine sidebar toggle)
const chartParent = document.getElementById('chartPenjualan');
if (chartParent && window.ResizeObserver) {
    new ResizeObserver(() => {
        clearTimeout(window._chartObsTimer);
        window._chartObsTimer = setTimeout(renderChart, 200);
    }).observe(chartParent.closest('.space-y-6') || document.body);
}
</script>
