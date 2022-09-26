(function ($, Drupal) {
  Drupal.behaviors.mukurtu_multipage_nav = {
    attach: function (context, settings) {
      $(document).ready(function () {
        // On click method to trigger the ajax call to view a specific page.
        var selectPage = function (event) {
          let url = document.URL;
          let urlComponents = url.split('/');
          let mpiId = urlComponents[urlComponents.length - 3];
          let pageId = $(event.currentTarget.children[0]).attr("data-history-node-id");
          try {
            Drupal.ajax({ url: `/multipageitem/${mpiId}/${pageId}/ajax` }).execute();
            urlComponents[urlComponents.length - 1] = pageId;
            window.history.replaceState({}, '', urlComponents.join('/'));
          } catch (error) {
            // Don't care about the error.
          }
        };

        // Attach the handler to the page nav.
        $('#mukurtu-multipage-item-page-nav ul li').once().click(selectPage);
      });
    }
  };
})(jQuery, Drupal);
