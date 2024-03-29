(function ($, Drupal) {
  Drupal.behaviors.mukurtu_multipage_nav = {
    attach: function (context, settings) {
      $(document).ready(function () {
        function loadPage(pageId) {
          try {
            Drupal.ajax({ url: `/multipageitem/${pageId}/ajax` }).execute();
            let nodeUrl = $('.splide__slide.is-active > .node').attr('about');

            let newUrl = "/node/" + pageId;
            if (nodeUrl != undefined) {
              newUrl = nodeUrl;
            }
            window.history.replaceState({}, '', newUrl);
          } catch (error) {
            return false;
          }
          return true;
        }

        // TOC On change method to trigger the ajax call to view a specific page.
        var jumpToPage = function (event) {
          Drupal.behaviors.mukurtu_multipage_nav.multipageNavSlider.go(event.target.selectedIndex);
        };

        // Attach the table of contents handler.
        $('#multipage-item-table-of-contents').once().change(jumpToPage);

        if (Drupal.behaviors.mukurtu_multipage_nav.multipageNavSlider == undefined) {
          Drupal.behaviors.mukurtu_multipage_nav.multipageNavSlider = new Splide('.splide', {
            perPage: 3,
            isNavigation: true,
            pagination: true,
            updateOnMove: false,
            trimSpace: false,
            start: document.getElementById('multipage-item-table-of-contents').selectedIndex,
          }).mount();

          Drupal.behaviors.mukurtu_multipage_nav.multipageNavSlider.on('active', function (slide) {
            let pageId = slide.slide.firstElementChild.attributes["data-history-node-id"].value;
            if (loadPage(pageId)) {
              // Change the TOC if we switched pages successfully.
              $('#multipage-item-table-of-contents').val(pageId);
            }
          });
        }
      });
    }
  };
})(jQuery, Drupal);
