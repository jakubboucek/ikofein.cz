import './scss/front.scss';
import './strip-tracking-url-params.js';

if (document.querySelector('article.gallery')) {
    import('./gallery.js').catch(error => console.error('Gallery could not be loaded.', error));
}
