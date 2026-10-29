import Alpine from 'alpinejs';
import documentMultiUpload from './document-upload';

Alpine.data('documentMultiUpload', documentMultiUpload);
window.Alpine = Alpine;
Alpine.start();
