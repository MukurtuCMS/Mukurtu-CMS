/**
 * @file
 * Initialize Splide carousel for DH nodes that have multiple media assets.
 */

  ((Drupal, once) => { 
    let main, thumbnails;

    /**
     * Initialize the carousels.
     */
    function init(el) {
      const id = el.dataset.id;
      const mediaSelector =`[data-id="${id}"] .splide.media-carousel`;
      const thumbSelector =`[data-id="${id}"] .splide.thumbnail-carousel`;

      main = new Splide(mediaSelector, {
        type: 'fade',
        rewind: true,
        pagination: false,
        arrows: false,
      } );

      thumbnails = new Splide(thumbSelector, {
        autoWidth: true,
        fixedHeight: '106px',
        gap: '10px',
        rewind: true,
        pagination: false,
        isNavigation: true,
        breakpoints: {
          600: {
            fixedWidth : 60,
            fixedHeight: 44,
          },
        },
      } );

      main.sync( thumbnails );
      main.mount();
      thumbnails.mount();
    }
  
    Drupal.behaviors.mediaAssetCarousel = {
      attach(context) {
        once("mediaAssets", ".media-carousels", context).forEach(init);
      }
    };
  })(Drupal, once);
