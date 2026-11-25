
import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js';
import { registerQzPrinting } from './printing/qz.js';

import permissionSelector from './components/permission-selector.js';
import salesNotePos from './components/sales-note-pos.js';

import './../../vendor/power-components/livewire-powergrid/dist/powergrid';

import flatpickr from 'flatpickr';

// Exponer dependencias globales utilizadas por SAF.
window.Livewire = Livewire;
window.Alpine = Alpine;
window.flatpickr = flatpickr;

// Registrar los componentes Alpine antes de iniciar Livewire.
Alpine.data('permissionSelector', permissionSelector);
Alpine.data('salesNotePos', salesNotePos);

registerQzPrinting(Livewire);

Livewire.start();

