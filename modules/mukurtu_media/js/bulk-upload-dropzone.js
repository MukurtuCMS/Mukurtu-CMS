(function (Drupal, once) {

  'use strict';

  Drupal.behaviors.mukurtuBulkUploadDropzone = {
    attach: function (context) {
      once('bulk-upload-dropzone', '.mukurtu-bulk-dropzone', context).forEach(function (dropzone) {
        var fileInput = dropzone.querySelector('input[type="file"]');
        if (!fileInput) { return; }

        var dragDepth = 0;

        dropzone.addEventListener('dragenter', function (e) {
          e.preventDefault();
          dragDepth++;
          dropzone.classList.add('is-drag-over');
        });

        dropzone.addEventListener('dragover', function (e) {
          e.preventDefault();
        });

        dropzone.addEventListener('dragleave', function () {
          dragDepth--;
          if (dragDepth <= 0) {
            dragDepth = 0;
            dropzone.classList.remove('is-drag-over');
          }
        });

        dropzone.addEventListener('drop', function (e) {
          e.preventDefault();
          dragDepth = 0;
          dropzone.classList.remove('is-drag-over');

          var files = e.dataTransfer.files;
          if (!files || files.length === 0) { return; }

          // Transfer dragged files to the managed_file input.
          try {
            var dt = new DataTransfer();
            Array.from(files).forEach(function (file) { dt.items.add(file); });
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
          } catch (err) {
            // DataTransfer not available; user can use the Browse button instead.
          }
        });

        // Clicking anywhere on the dropzone background opens the file browser.
        dropzone.addEventListener('click', function (e) {
          if (e.target === dropzone || e.target.classList.contains('mukurtu-bulk-dropzone__hint')) {
            fileInput.click();
          }
        });
      });
    }
  };

}(Drupal, once));
