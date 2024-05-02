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
      main = new Splide( '.splide.media-carousel', {
        type: 'fade',
        rewind: true,
        pagination: false,
        arrows: false,
      } );
    
      thumbnails = new Splide( '.splide.thumbnail-carousel', {
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
