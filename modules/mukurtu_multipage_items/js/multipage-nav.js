(function ($, Drupal) {
  Drupal.behaviors.mukurtu_multipage_nav = {
    attach: function (context, settings) {
      $(document).ready(function () {
        function loadPage(pageId) {
          let url = document.URL;
          let urlComponents = url.split('/');
          let mpiId = urlComponents[urlComponents.length - 3];
          try {
            Drupal.ajax({ url: `/multipageitem/${mpiId}/${pageId}/ajax` }).execute();
            urlComponents[urlComponents.length - 1] = pageId;
            window.history.replaceState({}, '', urlComponents.join('/'));
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
            let pageId = $($(slide.slide)[0].children[0]).attr("data-history-node-id");
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
