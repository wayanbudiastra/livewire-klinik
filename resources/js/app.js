import './bootstrap';

// Alpine.js sudah di-bundle Livewire — tidak perlu import manual
// Flowbite: inisialisasi komponen
import { initFlowbite } from 'flowbite';

document.addEventListener('livewire:navigated', () => initFlowbite());
document.addEventListener('DOMContentLoaded', () => initFlowbite());
