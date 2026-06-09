/**
 * @file
 * Initialize Splide carousel for DH nodes that have multiple media assets.
 */

  ((Drupal, once) => {
    let main, thumbnails;

    /**
     * Set block-size on the track to match the target slide's scrollHeight.
     *
     * scrollHeight reads through the block-size: 0 rule on inactive slides,
     * so we get each slide's true content height without any DOM manipulation.
     * targetIndex defaults to the current active slide; pass the incoming
     * index on 'move' so the height animates in sync with the fade transition.
     * The track value is cleared first so a shrinking viewport can reduce the
     * height rather than being locked to a stale larger value.
     */
    function updateTrackHeight(splideInstance) {
      const track = splideInstance.root.querySelector('.splide__track');
      track.style.minBlockSize = '';

      requestAnimationFrame(() => {
        const activeSlide = splideInstance.Components.Slides.getAt(splideInstance.index);
        if (activeSlide) {
          const height = activeSlide.slide.scrollHeight;
          if (height > 0) {
            track.style.minBlockSize = height + 'px';
          }
        }
      });
    }

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

      main.on('mounted moved resized', () => updateTrackHeight(main));

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
