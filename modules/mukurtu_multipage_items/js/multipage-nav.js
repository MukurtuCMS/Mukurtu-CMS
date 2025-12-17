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
        autoWidth: true,
        fixedHeight: '106px',
        gap: '10px',
        perPage: 3,
        isNavigation: true,
        pagination: false,
        updateOnMove: false,
        start: this.pageIdToSlideIndex(element, initialPageId),
      }).mount();
      multipageNavSlider.on('active', (slide) => {
        const nid = slide.slide.dataset.id;

        if (this.loadPage(element, nid)) {
          // Change the TOC if we switched pages successfully.
          tocElement.value = nid;
          this.updateTaskLinks(nid);
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
    },

    /**
     * Update the local tasks to reflect the active tab.
     *
     * @param {int|string} nid
     *   The nid of the active pane.
     */
    updateTaskLinks: function(nid) {
      const patterns = [
        /node\/(\d+)/
      ];
      const localTasksElements = document.querySelectorAll('.local-tasks');
      localTasksElements.forEach((localTasks) => {
        const anchors = localTasks.querySelectorAll('a');
        anchors.forEach((anchor) => {
          let href = anchor.getAttribute('href');
          if (href) {
            patterns.forEach((pattern) => {
              href = href.replace(pattern, (match, anchorNid) => {
                return match.replace(anchorNid, nid);
              });
            });
            anchor.setAttribute('href', href);
          }
        })
      })
    }
  };
})(Drupal, once, Splide, history);
