import './bootstrap';
import './dashboard';
import Alpine from 'alpinejs';
import Collapse from '@alpinejs/collapse';
import ApexCharts from 'apexcharts';
import { formBuilder } from './components/form-builder';
import { pdfTemplateEditor } from './components/pdf-template-editor';
import { signatureField } from './components/signature-field';
import { idTemplateEditor } from './components/id-template-editor';
import { idScanWizard } from './components/id-scan-wizard';
import { scoringRuleEditor } from './components/scoring-rule-editor';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
// FullCalendar
import { Calendar } from '@fullcalendar/core';



window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;

Alpine.plugin(Collapse);
Alpine.data('formBuilder', formBuilder);
Alpine.data('pdfTemplateEditor', pdfTemplateEditor);
Alpine.data('signatureField', signatureField);
Alpine.data('idTemplateEditor', idTemplateEditor);
Alpine.data('idScanWizard', idScanWizard);
Alpine.data('scoringRuleEditor', scoringRuleEditor);
Alpine.start();

// Initialize components on DOM ready

// Alpine.data('clicked', () => ({
//     clicked(){
//         console.log("Javascript is so goddamn ugly holy shit.");
//     }
// }))

document.addEventListener('DOMContentLoaded', () => {
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
});
