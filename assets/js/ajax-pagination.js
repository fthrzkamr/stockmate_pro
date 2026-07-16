/**
 * AJAX Pagination - Navigation TANPA reload page
 * Menggunakan History API untuk smooth transitions
 */

let currentModule = '';
let isLoading = false;

function initAjaxPagination(moduleName) {
    currentModule = moduleName;
}

/**
 * Navigate to page with smooth transition (NO full reload)
 */
function goToPage(page, limit, modul, search = '') {
    const params = new URLSearchParams();
    params.set('page', page);
    params.set('limit', limit);
    if (search) params.set('search', decodeURIComponent(search));
    
    // Maintain other URL search params (like outlet, supplier, status, lokasi, tgl_awal, tgl_akhir)
    const currentParams = new URLSearchParams(window.location.search);
    for (const [key, val] of currentParams.entries()) {
        if (!['page', 'limit', 'search', 'menu'].includes(key)) {
            params.set(key, val);
        }
    }

    const menuQuery = modul + '?' + params.toString();
    if (typeof loadcontent === 'function') {
        loadcontent(menuQuery);
    } else {
        window.location.href = usuper + '/' + menuQuery;
    }
}

function changeLimit(newLimit, modul, search = '') {
    const params = new URLSearchParams();
    params.set('page', '1');
    params.set('limit', newLimit);
    if (search) params.set('search', decodeURIComponent(search));
    
    // Maintain other URL search params
    const currentParams = new URLSearchParams(window.location.search);
    for (const [key, val] of currentParams.entries()) {
        if (!['page', 'limit', 'search', 'menu'].includes(key)) {
            params.set(key, val);
        }
    }

    const menuQuery = modul + '?' + params.toString();
    if (typeof loadcontent === 'function') {
        loadcontent(menuQuery);
    } else {
        window.location.href = usuper + '/' + menuQuery;
    }
}

// Intercept GET form submissions (Filters) to use AJAX
document.addEventListener('submit', function(e) {
    const form = e.target;
    // Intercept form filter atau form dengan method GET
    if (form.id === 'filterForm' || form.getAttribute('method')?.toUpperCase() === 'GET') {
        // Skip if it is not an internal route
        const action = form.getAttribute('action') || '';
        if (action.startsWith('http') && !action.includes(window.location.hostname)) return;

        e.preventDefault();
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        for (const [key, value] of formData.entries()) {
            // JANGAN masukkan parameter 'menu' ke query string jika routing menggunakan path /module
            if (value !== '' && key !== 'menu') {
                params.set(key, value);
            }
        }
        
        // Dapatkan nama modul
        const menuInput = form.querySelector('input[name="menu"]');
        let moduleName = menuInput ? menuInput.value : '';
        if (!moduleName) {
            const pathParts = window.location.pathname.split('/');
            moduleName = pathParts[pathParts.length - 1] || 'home';
        }
        
        const menuQuery = moduleName + '?' + params.toString();
        if (typeof loadcontent === 'function') {
            loadcontent(menuQuery);
        } else {
            window.location.href = usuper + '/' + menuQuery;
        }
    }
});

// Auto-initialize
document.addEventListener('DOMContentLoaded', function() {
    const pathParts = window.location.pathname.split('/');
    const moduleName = pathParts[pathParts.length - 1] || pathParts[pathParts.length - 2];
    if (moduleName) initAjaxPagination(moduleName);
});
