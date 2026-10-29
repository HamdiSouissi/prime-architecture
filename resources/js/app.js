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

// Only the public offline shell is cached, never authenticated pages or data.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js').catch(() => {}));
}
