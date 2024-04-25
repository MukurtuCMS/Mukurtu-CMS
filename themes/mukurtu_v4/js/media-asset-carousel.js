/**
 * @file
 * Initialize Splide carousel for DH nodes that have multiple media assets.
 */

  ((Drupal, once) => { 
    /**
     * Initialize the carousel.
     */
    function init(el) {
      splide.refresh();

      console.log("YEP");
    }
  
    Drupal.behaviors.mediaAssetCarousel = {
      attach(context) {
        once("mediaAssets", ".field--name-field-media-assets", context).forEach(init);

        if (Drupal.behaviors.mediaAssetCarousel.mediaAssetSlider == undefined) {
         console.log("media asset slider undefined");

          Drupal.behaviors.mediaAssetCarousel.mediaAssetSlider = new Splide('.splide.media-carousel', {
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
