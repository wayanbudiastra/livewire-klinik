import './bootstrap';

import { initFlowbite } from 'flowbite';
import Swal from 'sweetalert2';
import { Chart, registerables } from 'chart.js';

// ── Chart.js global ──────────────────────────────────────
Chart.register(...registerables);
window.Chart = Chart;

// ── Flowbite init ────────────────────────────────────────
document.addEventListener('livewire:navigated', () => initFlowbite());
document.addEventListener('DOMContentLoaded', () => initFlowbite());

// ── SweetAlert2 Global Config ────────────────────────────
window.Swal = Swal;

// Konfigurasi default tema SweetAlert2 sesuai warna app
const SwalEMR = Swal.mixin({
    customClass: {
        confirmButton: 'swal-btn-confirm',
        cancelButton:  'swal-btn-cancel',
        popup:         'swal-popup',
    },
    buttonsStyling: false,
    reverseButtons: true,
});

window.SwalEMR = SwalEMR;

// ── Helper: konfirmasi aksi berbahaya ────────────────────
window.confirmAction = async function ({
    title      = 'Yakin?',
    text       = '',
    icon       = 'warning',
    confirmText= 'Ya, Lanjutkan',
    cancelText = 'Batal',
    confirmColor = 'danger',  // 'danger' | 'primary' | 'warning' | 'success'
    callback,
}) {
    const result = await SwalEMR.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText:  cancelText,
        customClass: {
            confirmButton: confirmColor === 'danger'  ? 'swal-btn-danger' :
                           confirmColor === 'warning' ? 'swal-btn-warning' :
                           confirmColor === 'success' ? 'swal-btn-success' :
                                                        'swal-btn-confirm',
            cancelButton: 'swal-btn-cancel',
            popup:        'swal-popup',
        },
        buttonsStyling: false,
        reverseButtons: true,
    });

    if (result.isConfirmed && typeof callback === 'function') {
        callback();
    }

    return result.isConfirmed;
};

// ── Livewire: intercept wire:confirm → SweetAlert2 ───────
// Mengganti dialog browser bawaan wire:confirm
document.addEventListener('livewire:init', () => {
    Livewire.hook('message.sent', () => {});
});

// Override window.confirm yang dipakai wire:confirm
const originalConfirm = window.confirm;
window.confirm = function (message) {
    // Biarkan hanya jika bukan dari Livewire (fallback)
    return originalConfirm.call(window, message);
};
