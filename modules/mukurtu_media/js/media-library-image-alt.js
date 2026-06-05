/**
 * @file
 * Autofills the alt text field in the media library upload modal from the
 * media name (which is already set to the filename without extension by the
 * PHP form alter). Runs on every Drupal behaviors attach so it fires after
 * each upload AJAX cycle.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.mukurtuMediaLibraryImageAlt = {
    attach: function (context) {
      once('mukurtu-media-alt-autofill', '.media-library-add-form__fields', context).forEach(function (fieldsEl) {
        // Name field: media[N][fields][name][0][value]
        var nameInput = fieldsEl.querySelector('input[name$="[fields][name][0][value]"]');
        // Alt field: media[N][fields][field_media_image][0][alt]
        var altInput = fieldsEl.querySelector('input[name$="[fields][field_media_image][0][alt]"]');

        if (nameInput && altInput && !altInput.value && nameInput.value) {
          altInput.value = nameInput.value;
        }
      });
    }
  };

}(Drupal));
