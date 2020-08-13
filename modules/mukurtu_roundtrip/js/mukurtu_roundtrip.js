(function ($, Drupal) {
  Drupal.behaviors.importTable = {
    attach: function (context, settings) {
      $('#edit-import-table', context).once('makeImportTable').each(function () {
        var importTable = new Tabulator(this);
        console.log(this);
      });
    }
  };
})(jQuery, Drupal);
