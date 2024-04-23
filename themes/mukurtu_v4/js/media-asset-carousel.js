/**
 * @file
 * Initialize Splide carousel for DH nodes that have multiple media assets.
 */

  ((Drupal, once) => { 
    // let splide = new Splide( '.media-splide', {
    //   perPage: 3,
    //   isNavigation: true,
    //   pagination: false,
    //   updateOnMove: false,
    //   trimSpace: true,
    //   autoWidth: true,
    //   autoHeight: true,
    //   gap: '16px',
    //   start: 0,
    // } );

    /**
     * Initialize the carousel.
     */
    function init(el) {
      console.log("YEP");
      // splide.mount();
    }
  
    Drupal.behaviors.mediaAssetCarousel = {
      attach(context) {
        once("mediaAssets", ".field--name-field-media-assets", context).forEach(init);

        if (Drupal.behaviors.mediaAssetCarousel.mediaAssetSlider == undefined) {
          Drupal.behaviors.mediaAssetCarousel.mediaAssetSlider = new Splide('.splide.media-splide', {
            perPage: 3,
            isNavigation: true,
            pagination: false,
            updateOnMove: false,
            trimSpace: true,
            autoWidth: true,
            autoHeight: true,
            gap: '16px',
            start: 0,
          }).mount();
        }
      }
    };
  })(Drupal, once);
