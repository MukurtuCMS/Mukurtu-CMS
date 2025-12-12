((Drupal, once, Splide, history) => {
  Drupal.behaviors.mukurtuMultipageNav = {
    attach: function (context, settings) {
      // Initialize the multipage items carousel, only once per encountered
      // instance of .multipage-items.
      once('multipage-items', '.multipage-items', context).forEach((element) => {
        this.initMultipageItems(element);
      });
    },

    /**
     * Initialize the multipage items carousel and selector.
     *
     * @param {HTMLElement} element
     *   The multipage items wrapper element.
     */
    initMultipageItems: function(element) {
      const tocElement = element.querySelector('#multipage-item-table-of-contents');
      const initialPageId = tocElement.value;
      const multipageNavSlider = new Splide(element.querySelector('.splide.multipage-carousel'), {
        perPage: 3,
        isNavigation: true,
        pagination: false,
        updateOnMove: false,
        trimSpace: true,
        autoWidth: true,
        autoHeight: true,
        gap: '16px',
        start: this.pageIdToSlideIndex(element, initialPageId),
      }).mount();
      multipageNavSlider.on('active', (slide) => {
        const pageId = slide.slide.firstElementChild.dataset.historyNodeId;

        if (this.loadPage(element, pageId)) {
          // Change the TOC if we switched pages successfully.
          tocElement.value = pageId;
        }
      });
      element.multipageNavSlider = multipageNavSlider;

      tocElement.addEventListener('change', (event) => {
        this.jumpToPage(element, event.target.selectedIndex);
      });
    },

    /**
     * Jump to a specific page on the Splide carousel.
     *
     * @param {HTMLElement} element
     *   The multipage items wrapper element.
     * @param {string} pageId
     *   The page id to jump to.
     */
    jumpToPage: function(element, pageId) {
      const multipageNavSlider = element.multipageNavSlider;
      if (!multipageNavSlider) {
        return;
      }
      multipageNavSlider.go(pageId);
    },

    /**
     * Load a page via AJAX and replace the page contents.
     *
     * @param {HTMLElement} element
     *   The multipage items wrapper element.
     * @param {string} pageId
     *   The multipage item id to load.
     * @returns {boolean}
     *   Whether the page was successfully loaded.
     */
    loadPage: function(element, pageId) {
      try {
        Drupal.ajax({ url: `/multipageitem/${pageId}/ajax` }).execute();
        history.replaceState({}, '', `/node/${pageId}`);
      }
      catch (error) {
        return false;
      }
      return true;
    },

    /**
     * Get the slide index for a given page id.
     *
     * @param {HTMLElement} element
     *   The multipage items wrapper element.
     * @param {string} pageId
     *   The multipage item id to load.
     * @returns {number}
     *   The slide index.
     */
    pageIdToSlideIndex(element, pageId) {
      const slides = element.querySelectorAll('.splide__slide');
      for (let i = 0; i < slides.length; i++) {
        if (slides[i].dataset.id === pageId) {
          return i;
        }
      }
      return 0;
    }
  };
})(Drupal, once, Splide, history);
