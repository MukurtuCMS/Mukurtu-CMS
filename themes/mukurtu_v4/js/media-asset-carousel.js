/**
 * @file
 * Initialize Splide carousel for DH nodes that have multiple media assets.
 */

  ((Drupal, once) => { 
    let main, thumbnails;
    /**
     * Initialize the carousel.
     */
    function init(el) {
      main = new Splide( '.splide.media-carousel', {
        type      : 'fade',
        rewind    : true,
        pagination: false,
        arrows    : false,
      } );
    
      thumbnails = new Splide( '.splide.thumbnail-carousel', {
        fixedWidth  : 100,
        fixedHeight : 60,
        gap         : 10,
        rewind      : true,
        pagination  : false,
        isNavigation: true,
        breakpoints : {
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

       


          // Drupal.behaviors.mediaAssetCarousel.mediaAssetSlider = new Splide('.splide.media-carousel', {
          //   perPage: 3,
          //   isNavigation: true,
          //   pagination: false,
          //   updateOnMove: false,
          //   trimSpace: true,
          //   autoWidth: true,
          //   autoHeight: true,
          //   gap: '16px',
          //   start: 0,
          // }).mount();

      }
    };
  })(Drupal, once);
