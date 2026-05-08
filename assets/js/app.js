/**
 * HydroLogic OS – Main Application JS
 * ======================================
 * Global JavaScript utilities used across all pages.
 */

// ── Modal Management ────────────────────────────────────────
/**
 * Open a modal dialog by its ID.
 * @param {string} id - The modal element's ID
 */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
}

/**
 * Close a modal dialog by its ID.
 * @param {string} id - The modal element's ID
 */
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}

// Close modals when clicking the backdrop
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id$="-modal"]').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });

    // Close on ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('[id$="-modal"]').forEach(function (m) {
                m.classList.add('hidden');
            });
        }
    });

    // ── Flash message auto-dismiss ──────────────────────────
    const flash = document.getElementById('flash-msg');
    if (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity 0.5s';
            flash.style.opacity = '0';
            setTimeout(function () { flash.remove(); }, 500);
        }, 5000); // Auto-dismiss after 5 seconds
    }

    // ── Global Search (client-side table filter) ─────────────
    const searchInput = document.getElementById('global-search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(function (row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // ── Confirm delete on data-confirm buttons ───────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // ── Auto-calculate total cost in electricity form ────────
    const unitsInput = document.querySelector('[name="units_consumed"]');
    const rateInput  = document.querySelector('[name="cost_per_unit"]');
    const totalDisplay = document.getElementById('calc-total-cost');

    if (unitsInput && rateInput && totalDisplay) {
        function calcTotal() {
            const units = parseFloat(unitsInput.value) || 0;
            const rate  = parseFloat(rateInput.value)  || 0;
            totalDisplay.textContent = 'PKR ' + (units * rate).toLocaleString('en-PK', { minimumFractionDigits: 2 });
        }
        unitsInput.addEventListener('input', calcTotal);
        rateInput.addEventListener('input', calcTotal);
    }
});
