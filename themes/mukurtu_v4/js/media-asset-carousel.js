/**
 * @file
 * Initialize Splide carousel for DH nodes that have multiple media assets.
 */

  ((Drupal, once) => { 
    /**
     * Initialize the carousel.
     */
    function init(el) {
      document.addEventListener( 'DOMContentLoaded', function() {
        let splide = new Splide( '.media-splide' );
        splide.mount();
        console.log("YEP");
      } );
    }
  
    Drupal.behaviors.mediaAssetCarousel = {
      attach(context) {
        once("mediaAssets", ".field--name-field-media-assets", context).forEach(init);
      },
    };
  })(Drupal, once);