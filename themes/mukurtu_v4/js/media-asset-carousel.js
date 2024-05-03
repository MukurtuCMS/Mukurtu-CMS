/**
 * @file
 * Initialize Splide carousel for DH nodes that have multiple media assets.
 */

  ((Drupal) => { 
    let main, thumbnails, tabs;

    /**
     * Refresh carousels on click of tab, if tabs exist.
     */
    function tabRefresh(e) {
      // console.log("tab has been CLICKED");
      // main.sync( thumbnails );
      // main.mount();
      // thumbnails.mount();
      main.refresh();
      thumbnails.refresh();
    }
    
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

      tabs = document.querySelector('.horizontal-tabs-list');
      tabs.addEventListener("click", tabRefresh);
    }
  
    Drupal.behaviors.mediaAssetCarousel = {
      attach(context) {
        once("mediaAssets", ".media-carousels", context).forEach(init);
      }
    };
  })(Drupal);
