(function (Drupal, once) {

  'use strict';

  Drupal.behaviors.mukurtuBulkUploadDropzone = {
    attach: function (context) {
      once('bulk-upload-dropzone', '.mukurtu-bulk-dropzone', context).forEach(function (dropzone) {
        var fileInput = dropzone.querySelector('input[type="file"]');
        if (!fileInput) { return; }

        // Live region element for screen reader announcements (WCAG 4.1.3).
        var liveRegion = dropzone.querySelector('.mukurtu-bulk-dropzone__status');

        var dragDepth = 0;

        /**
         * Announce a message to screen readers via the live region.
         */
        function announce(message) {
          if (!liveRegion) { return; }
          // Clear then set to ensure repeated identical messages are announced.
          liveRegion.textContent = '';
          // Use a short timeout so the DOM mutation is detected as a change.
          setTimeout(function () {
            liveRegion.textContent = message;
          }, 50);
        }

        dropzone.addEventListener('dragenter', function (e) {
          e.preventDefault();
          dragDepth++;
          dropzone.classList.add('is-drag-over');
        });

        dropzone.addEventListener('dragover', function (e) {
          e.preventDefault();
          // Keep dropEffect consistent for assistive technologies.
          e.dataTransfer.dropEffect = 'copy';
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

          // Transfer dragged files to the managed_file input, then trigger
          // Drupal's AJAX upload pipeline by clicking the Upload button.
          try {
            var dt = new DataTransfer();
            Array.from(files).forEach(function (file) { dt.items.add(file); });
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));

            var fileCount = files.length;
            announce(Drupal.formatPlural(
              fileCount,
              '1 file added. Processing upload.',
              '@count files added. Processing upload.',
              { '@count': fileCount }
            ));

            // Click the managed_file Upload submit button so Drupal processes
            // the files through its AJAX pipeline and stores the FIDs.
            var managedFileWrapper = fileInput.closest('.js-form-managed-file');
            if (managedFileWrapper) {
              var uploadBtn = managedFileWrapper.querySelector('input[type="submit"], button[type="submit"]');
              if (uploadBtn) {
                setTimeout(function () { uploadBtn.click(); }, 100);
              }
            }
          } catch (err) {
            // DataTransfer not available; user can use the Browse button instead.
            announce(Drupal.t('File drop is not supported in this browser. Please use the Choose Files button.'));
          }
        });

        // Clicking anywhere on the dropzone background opens the file browser.
        dropzone.addEventListener('click', function (e) {
          if (e.target === dropzone || e.target.classList.contains('mukurtu-bulk-dropzone__hint')) {
            fileInput.click();
          }
        });

        // Focus management: move focus to the Step 2 heading after a Drupal
        // AJAX form rebuild so keyboard and screen reader users are oriented
        // (WCAG 2.4.3). The heading has tabindex="-1" to allow programmatic
        // focus without entering the tab order.
        document.addEventListener('drupalAjaxSuccess', function () {
          var stepHeading = document.getElementById('bulk-upload-step-heading');
          if (stepHeading) {
            stepHeading.focus();
          }
        });
      });
    }
  };

}(Drupal, once));
