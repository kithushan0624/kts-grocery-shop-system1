// assets/js/app.js — Global JS utilities for K.T.S Grocery

/* ===== SIDEBAR TOGGLE ===== */
const sidebar = document.getElementById('sidebar');
const mainContent = document.querySelector('.main-content');
const sidebarToggle = document.getElementById('sidebarToggle');

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
        } else {
            sidebar.classList.toggle('collapsed');
        }
    });
}

// Close sidebar on mobile overlay click
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('mobile-open')) {
        if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
            sidebar.classList.remove('mobile-open');
        }
    }
});

/* ===== LIVE CLOCK ===== */
function updateClock() {
    const el = document.getElementById('headerClock');
    if (!el) return;
    const now = new Date();
    const options = { weekday:'short', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' };
    el.textContent = now.toLocaleDateString('en-LK', options);
}
setInterval(updateClock, 1000);

/* ===== DROPDOWN MENUS ===== */
document.querySelectorAll('[id$="Btn"]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const menuId = this.id.replace('Btn', 'Menu');
        const menu = document.getElementById(menuId);
        if (!menu) return;
        const isOpen = menu.classList.contains('show');
        // Close all
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
        if (!isOpen) menu.classList.add('show');
    });
});
document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
});

/* ===== TOAST NOTIFICATIONS ===== */
function showToast(message, type = 'success', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
        document.body.appendChild(container);
    }
    const icons = { success:'<i class="bi bi-check-circle-fill"></i>', error:'<i class="bi bi-x-circle-fill"></i>', warning:'<i class="bi bi-exclamation-triangle-fill"></i>', info:'<i class="bi bi-info-circle-fill"></i>' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span style="font-size:16px;line-height:1;display:flex;align-items:center;">${icons[type]||'<i class="bi bi-info-circle-fill"></i>'}</span><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/* ===== API HELPER ===== */
async function apiCall(url, method = 'GET', data = null) {
    const opts = { method, headers: {} };
    if (data) {
        if (data instanceof FormData) {
            opts.body = data;
        } else {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
    }
    try {
        const res = await fetch(url, opts);
        if (res.status === 401) {
            window.location.href = '../login.php';
            return null;
        }
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('API Parse Error. Raw Response:', text);
            showToast('Network error. Please try again.', 'error');
            return null;
        }
    } catch(e) {
        console.error('API Fetch Error:', e);
        showToast('Network error. Please try again.', 'error');
        return null;
    }
}

/* ===== MODAL HELPERS ===== */
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('active');
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('active');
}
// Close on backdrop click or close button
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
    if (e.target.classList.contains('close-modal') || e.target.closest('.close-modal')) {
        const modal = e.target.closest('.modal-overlay');
        if (modal) modal.classList.remove('active');
    }
});
// Close on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

/* ===== CURRENCY FORMATTER ===== */
function formatLKR(amount) {
    const symbol = window.APP_CURRENCY || 'රු';
    return symbol + ' ' + parseFloat(amount || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ===== CONFIRM DELETE ===== */
function confirmDelete(msg, callback) {
    if (confirm(msg || 'Are you sure you want to delete this item? This cannot be undone.')) {
        callback();
    }
}

/* ===== DEBOUNCE ===== */
function debounce(fn, delay = 300) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

/* ===== TABLE SEARCH ===== */
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', debounce(function() {
        const q = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }));
}

/* ===== DATE HELPERS ===== */
function isExpiringSoon(dateStr, days = 30) {
    if (!dateStr) return false;
    const exp = new Date(dateStr);
    const now = new Date();
    const diff = (exp - now) / (1000 * 60 * 60 * 24);
    return diff >= 0 && diff <= days;
}
function isExpired(dateStr) {
    if (!dateStr) return false;
    return new Date(dateStr) < new Date();
}
function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-LK', { day:'2-digit', month:'short', year:'numeric' });
}
function escHtml(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
