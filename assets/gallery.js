import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

const czech = document.documentElement.lang === 'cs';
const lightbox = new PhotoSwipeLightbox({
    gallery: 'article.gallery',
    children: 'a',
    pswpModule: () => import('photoswipe'),
    closeTitle: czech ? 'Zavřít' : 'Close',
    zoomTitle: czech ? 'Přiblížit' : 'Zoom',
    arrowPrevTitle: czech ? 'Předchozí fotografie' : 'Previous photo',
    arrowNextTitle: czech ? 'Další fotografie' : 'Next photo',
    errorMsg: czech ? 'Fotografii se nepodařilo načíst.' : 'The photo could not be loaded.',
});

lightbox.init();
