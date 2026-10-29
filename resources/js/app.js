import Alpine from 'alpinejs';
import documentMultiUpload from './document-upload';
import billingEditor from './billing-editor';

Alpine.data('documentMultiUpload', documentMultiUpload);
Alpine.data('billingEditor', billingEditor);
window.Alpine = Alpine;
Alpine.start();

if (document.getElementById('planning-gantt')) {
    import('./planning-gantt');
}

if (document.querySelector('[data-dashboard-chart]')) {
    import('./dashboard-charts');
}
