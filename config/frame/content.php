<?php
$menu = $menu ?? '';
switch ($menu) {
    default:
    case '':
    case 'home':
        require_once('content/home/home.php');
    break;

    // ── MASTER BARANG ──────────────────────────────
    case 'barang':      require_once('content/barang/barang.php'); break;
    case 'ibarang':     require_once('content/barang/input.php');  break;
    case 'ebarang':     require_once('content/barang/edit.php');   break;
    
    // ── INVENTORY ──────────────────────────────────
    case 'inventory':    require_once('content/inventory/inventory.php'); break;
    case 'einventory':   require_once('content/inventory/edit.php');      break;

    case 'vbarang':     require_once('content/barang/view.php');   break;

    // ── SUPPLIER ───────────────────────────────────
    case 'supplier':    require_once('content/supplier/supplier.php'); break;
    case 'isupplier':   require_once('content/supplier/input.php');    break;
    case 'esupplier':   require_once('content/supplier/edit.php');     break;
    case 'vsupplier':   require_once('content/supplier/view.php');     break;

    // ── OUTLET ─────────────────────────────────────
    case 'outlet':      require_once('content/outlet/outlet.php'); break;
    case 'ioutlet':     require_once('content/outlet/input.php');  break;
    case 'eoutlet':     require_once('content/outlet/edit.php');   break;
    case 'voutlet':     require_once('content/outlet/view.php');   break;

    // ── BARANG MASUK (Admin Gudang) ────────────────
    case 'barangmasuk':  require_once('content/barangmasuk/barangmasuk.php'); break;
    case 'ibarangmasuk': require_once('content/barangmasuk/input.php');       break;
    case 'vbarangmasuk': require_once('content/barangmasuk/view.php');        break;

    // ── BARANG KELUAR (Admin Gudang) ───────────────
    case 'barangkeluar':  require_once('content/barangkeluar/barangkeluar.php'); break;
    case 'ibarangkeluar': require_once('content/barangkeluar/input.php');        break;
    case 'vbarangkeluar': require_once('content/barangkeluar/view.php');         break;

    // ── TERIMA BARANG (Stock Keeper) ───────────────
    case 'terimabarang':  require_once('content/terimabarang/terimabarang.php'); break;
    case 'iterimabarang': require_once('content/terimabarang/input.php');        break;
    case 'vterimabarang': require_once('content/terimabarang/view.php');         break;

    // ── PEMAKAIAN BARANG (Stock Keeper) ───────────
    case 'pemakaian':  require_once('content/pemakaian/pemakaian.php'); break;
    case 'ipemakaian': require_once('content/pemakaian/input.php');     break;
    case 'vpemakaian': require_once('content/pemakaian/view.php');      break;

    // ── STOK OUTLET ────────────────────────────────
    case 'stokoutlet': require_once('content/stokoutlet/stokoutlet.php'); break;

    // ── STOCK OPNAME (Staff) ───────────────────────
    case 'stockopname':  require_once('content/stockopname/stockopname.php'); break;
    case 'istockopname': require_once('content/stockopname/input.php');       break;
    case 'estockopname': require_once('content/stockopname/edit.php');        break;
    case 'vstockopname': require_once('content/stockopname/view.php');        break;

    // ── APPROVAL STOCK OPNAME (SPV) ────────────────
    case 'approvalso':  require_once('content/approvalso/approvalso.php'); break;
    case 'vapprovalso': require_once('content/approvalso/view.php');       break;

    // ── LAPORAN ────────────────────────────────────
    case 'laporan':        require_once('content/laporan/laporan.php');        break;
    case 'laporanstok':    require_once('content/laporan/stok.php');           break;
    case 'laporanmasuk':   require_once('content/laporan/masuk.php');          break;
    case 'laporankeluar':  require_once('content/laporan/keluar.php');         break;
    case 'laporanselisih': require_once('content/laporan/selisih.php');        break;

    // ── ADMINISTRATOR ──────────────────────────────
    case 'administrator':  require_once('content/administrator/administrator.php'); break;
    case 'iadministrator': require_once('content/administrator/input.php');         break;
    case 'eadministrator': require_once('content/administrator/edit.php');          break;

    // ── MASTER BAGIAN ──────────────────────────────
    case 'bagianmaster':  require_once('content/bagianmaster/bagianmaster.php'); break;
    case 'ibagianmaster': require_once('content/bagianmaster/input.php');        break;
    case 'ebagianmaster': require_once('content/bagianmaster/edit.php');         break;

    // ── HAK AKSES ──────────────────────────────────
    case 'hakakses': require_once('content/hakakses/hakakses.php'); break;

    // ── SCANNER ────────────────────────────────────
    case 'scanner': require_once('content/scanner/scanner.php'); break;

    // ── ACCOUNT ────────────────────────────────────
    case 'account': require_once('content/account/account.php'); break;
}
