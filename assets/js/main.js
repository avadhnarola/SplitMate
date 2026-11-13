/**
 * SplitMate - Core JavaScript Application Library
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const appSidebar = document.getElementById('appSidebar');

    if (sidebarToggleBtn && appSidebar) {
        let backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop d-none';
        document.body.appendChild(backdrop);

        function toggleSidebar() {
            appSidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        }

        sidebarToggleBtn.addEventListener('click', toggleSidebar);
        backdrop.addEventListener('click', toggleSidebar);
    }

    // 2. Initialize Bootstrap Tooltips if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // 3. Auto-dismiss flash alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
});

/**
 * Toast notification dispatcher
 */
function showToast(message, type = 'success', title = '') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastId = 'toast_' + Date.now();
    const bgClass = type === 'success' ? 'bg-success text-white' : (type === 'danger' ? 'bg-danger text-white' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-primary text-white'));
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-triangle-exclamation' : 'fa-info-circle');

    const html = `
    <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="fa-solid ${icon} fs-5"></i>
                <div>
                    ${title ? `<strong>${title}</strong><br>` : ''}
                    <span>${message}</span>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>`;

    container.insertAdjacentHTML('beforeend', html);
    const toastEl = document.getElementById(toastId);
    if (typeof bootstrap !== 'undefined') {
        const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
        bsToast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }
}

/**
 * Export HTML Table directly to clean CSV
 */
function exportTableToCSV(tableId, filename = 'splitmate_export.csv') {
    const table = document.getElementById(tableId);
    if (!table) {
        alert('Table not found for export');
        return;
    }

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('th, td');
        for (let j = 0; j < cols.length; j++) {
            // Skip action columns if marked
            if (cols[j].classList.contains('no-export')) continue;
            let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s+)/g, ' ').trim();
            // Escape double quotes
            text = text.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        if (row.length > 0) {
            csv.push(row.join(','));
        }
    }

    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

/**
 * Trigger clean page print
 */
function printReport() {
    window.print();
}

