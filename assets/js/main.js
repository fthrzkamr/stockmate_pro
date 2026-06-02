// StockMate Pro — main.js

// ── SweetAlert Modal Berhasil/Gagal ─────────────────────────
function showAlert(title, text, icon = 'success') {
    Swal.fire({
        title: title,
        text: text,
        icon: icon, // 'success', 'error', 'warning', 'info'
        background: '#0f172a',
        color: '#f8fafc',
        confirmButtonColor: '#0ea5e9',
        customClass: {
            popup: 'rounded-2xl border border-sky-900/50',
            confirmButton: 'rounded-lg font-semibold px-6'
        }
    });
}

// ── Toast notification (Small popup di pojok) ─────────────────────────
function toast(msg, type = 'success') {
    const colors = {
        success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300',
        error:   'bg-red-500/15 border-red-500/30 text-red-300',
        info:    'bg-sky-500/15 border-sky-500/30 text-sky-300',
        warning: 'bg-amber-500/15 border-amber-500/30 text-amber-300',
    };
    const icons = { success:'fa-circle-check', error:'fa-circle-exclamation', info:'fa-circle-info', warning:'fa-triangle-exclamation' };

    const div = document.createElement('div');
    div.className = `fixed top-5 right-5 z-[9999] flex items-center gap-3 px-4 py-3 rounded-xl border text-sm font-medium
        shadow-xl ${colors[type]} transition-all duration-300`;
    div.style.animation = 'fadeUp .3s ease-out';
    div.innerHTML = `<i class="fa-solid ${icons[type]}"></i><span>${msg}</span>`;
    document.body.appendChild(div);
    setTimeout(() => { div.style.opacity = '0'; setTimeout(() => div.remove(), 300); }, 3500);
}

// ── Confirm delete ─────────────────────────────
function confirmDelete(url, callback) {
    Swal.fire({
        title: 'Hapus Data?',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#334155',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        customClass: {
            popup: 'rounded-2xl border border-slate-700/50',
            confirmButton: 'rounded-lg font-semibold',
            cancelButton: 'rounded-lg font-semibold'
        }
    }).then(result => {
        if (result.isConfirmed) {
            if (url) {
                fetch(url, { method: 'POST' })
                    .then(r => r.json())
                    .then(res => {
                        if (res.status === 'success') {
                            showAlert('Berhasil!', res.msg || 'Data berhasil dihapus', 'success');
                            if (callback) callback();
                        } else {
                            showAlert('Gagal!', res.msg || 'Gagal menghapus data', 'error');
                        }
                    }).catch(() => {
                        showAlert('Error!', 'Terjadi kesalahan sistem', 'error');
                    });
            } else if (callback) callback();
        }
    });
}

// ── Breadcrumb from URL ────────────────────────
(function() {
    const bc = document.getElementById('breadcrumb');
    if (!bc) return;
    const path = window.location.pathname.replace('/project_work/', '').split('/').filter(Boolean);
    const map = {
        home:'Dashboard', barang:'Master Barang', supplier:'Supplier', outlet:'Outlet',
        barangmasuk:'Barang Masuk', barangkeluar:'Barang Keluar', terimabarang:'Terima Barang',
        pemakaian:'Pemakaian', stokoutlet:'Stok Outlet', stockopname:'Stock Opname',
        approvalso:'Approval SO', laporan:'Laporan', administrator:'Administrator',
        hakakses:'Hak Akses', scanner:'Scanner', account:'Profil',
    };
    if (path.length === 0) { bc.textContent = 'Dashboard'; return; }
    bc.innerHTML = `<span class="text-sky-400">${map[path[0]] || path[0]}</span>`;
    if (path[1]) bc.innerHTML += ` <span class="text-slate-600 mx-1">/</span> <span>${path[1]}</span>`;
})();

// ── Auto-hide HTML alerts ───────────────────────────
document.querySelectorAll('[data-autohide]').forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 4000);
});

// ── Update Notification Badge ──────────────────────
function updateNotification() {
    fetch(usuper + '/api_notif.php')
        .then(res => res.json())
        .then(data => {
            // Update lonceng header
            const bellBadge = document.getElementById('notifBadge');
            if (bellBadge) {
                if (data.total > 0) {
                    bellBadge.classList.remove('hidden');
                    bellBadge.classList.add('animate-pulse');
                } else {
                    bellBadge.classList.add('hidden');
                }
            }

            // Update badge di sidebar menu (bisa ditaruh berdasarkan ID jika ingin lebih spesifik)
            const soMenuBadge = document.getElementById('badge-so');
            if (soMenuBadge) {
                if (data.pending_so > 0) {
                    soMenuBadge.textContent = data.pending_so;
                    soMenuBadge.classList.remove('hidden');
                } else {
                    soMenuBadge.classList.add('hidden');
                }
            }
        }).catch(console.error);
}

// Jalankan update notifikasi pertama kali & set interval tiap 30 detik
if (typeof usuper !== 'undefined') {
    updateNotification();
    setInterval(updateNotification, 30000);
}
