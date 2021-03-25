(function ($, Drupal) {
  Drupal.behaviors.importTable = {
    attach: function (context, settings) {
      $('#edit-import-table', context).once('makeImportTable').each(function () {
        var importTable = new Tabulator(this, {
          cellEdited: function(cell) {
            // Disable the submit button, the file needs to be validated again.
            if (document.getElementById("edit-submitforimport") != undefined) {
              document.getElementById("edit-submitforimport").disabled = true;
            }

            // Disable the validate button, it will be re-enabled once the server
            // gets the updated data.
            document.getElementById('edit-submitforvalidation').disabled = true;

            // Get FID.
            let fid = $('#mukurtu-import-from-file #edit-import-file input[name*=\'import_file[fids]\']')[0].value;

            // Get the table data.
            let rows = importTable.getData();

            // Send to the server to update.
            Drupal.ajax({
              url: '/mukurtu-ajax/tabulator/file-update',
              type: 'POST',
              submit: { 'fid': fid, 'rows': JSON.stringify(rows) },
            }).execute().done(
              function (commands, statusString, ajaxObject) {

              });
          },
        });

        let columns = importTable.getColumnDefinitions();
        // Temporary. Make all columns editable.
        let newColumns = columns.map(column => {
          column.editor = "input";
          return column;
        });

        importTable.setColumns(newColumns);
      });
    }
  };
})(jQuery, Drupal);
