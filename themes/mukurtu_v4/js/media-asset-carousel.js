/**
 * @file
 * Initialize Splide carousel for DH nodes that have multiple media assets.
 */

  ((Drupal, once) => { 
    let main, thumbnails, tabs;

    /**
     * Refresh carousels on click of tab, if tabs exist.
     */
    // function tabRefresh(e) {
      // console.log("tab has been CLICKED");

    //   main.refresh();
    //   thumbnails.refresh();
    // }
    
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

      // tabs = document.querySelector('.horizontal-tabs-list');
      // tabs.addEventListener("click", tabRefresh);
    }
  
    Drupal.behaviors.mediaAssetCarousel = {
      attach(context) {
        once("mediaAssets", ".media-carousels", context).forEach(init);
      }
    };
  })(Drupal, once);
