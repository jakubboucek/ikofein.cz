import './scss/front.scss';

if (document.querySelector('article.gallery')) {
    import('./gallery.js').catch(error => console.error('Gallery could not be loaded.', error));
}
