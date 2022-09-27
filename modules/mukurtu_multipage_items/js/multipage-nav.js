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

        // On click method to trigger the ajax call to view a specific page.
        var selectPage = function (event) {
          let pageId = $(event.currentTarget.children[0]).attr("data-history-node-id");
          if (loadPage(pageId)) {
            // Change the TOC if we switched pages successfully.
            $('#multipage-item-table-of-contents').val(pageId);
          }
        };

        // TOC On change method to trigger the ajax call to view a specific page.
        var jumpToPage = function (event) {
          loadPage($('#multipage-item-table-of-contents').val());
        };

        // Attach the handler to the page nav.
        $('#mukurtu-multipage-item-page-nav ul li').once().click(selectPage);

        // Attach the table of contents handler.
        $('#multipage-item-table-of-contents').once().change(jumpToPage);
      });
    }
  };
})(jQuery, Drupal);
