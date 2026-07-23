/**
 * @file
 * Autofills the alt text field in the media library upload modal from the
 * media name (normally already set to the filename without extension by the
 * PHP form alter). Runs on every Drupal behaviors attach so it fires after
 * each upload AJAX cycle.
 *
 * once() is scoped directly to the alt input itself, not the surrounding
 * .media-library-add-form__fields wrapper - the wrapper can already exist
 * (and be marked processed) from an earlier, emptier AJAX round while a
 * later round replaces just the widget's inner markup with fresh alt/name
 * inputs. Scoping once() to that outer wrapper would then never re-fire,
 * leaving stale references to detached nodes. The alt input is always a
 * genuinely new node whenever it (re)appears, so once() reliably catches it
 * regardless of which ancestor Drupal.attachBehaviors() was actually called
 * with as context.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.mukurtuMediaLibraryImageAlt = {
    attach: function (context) {
      // Alt field: media[N][fields][field_media_image][0][alt]
      once('mukurtu-media-alt-autofill', 'input[name$="[fields][field_media_image][0][alt]"]', context).forEach(function (altInput) {
        var fieldsEl = altInput.closest('.media-library-add-form__fields') || document;
        // Name field: media[N][fields][name][0][value]
        var nameInput = fieldsEl.querySelector('input[name$="[fields][name][0][value]"]');

        if (!nameInput) {
          return;
        }

        function copyNameToAlt() {
          if (!altInput.value && nameInput.value) {
            altInput.value = nameInput.value;
          }
        }

        // Covers the case the name field is already populated (the PHP form
        // alter's #default_value) by the time this alt input appears.
        copyNameToAlt();

        // Covers the case the name field's value is filled in after this
        // alt input already exists.
        nameInput.addEventListener('input', copyNameToAlt);
      });
    }
  };

}(Drupal));
