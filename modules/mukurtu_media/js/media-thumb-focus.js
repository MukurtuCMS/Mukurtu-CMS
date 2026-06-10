/**
 * @file
 * Sets data-disable-refocus on thumbnail upload/remove buttons as a secondary
 * guard against ajax.js ancestor-focus scrolling in the external embed modal.
 *
 * The primary fix is PHP-side: mukurtu_media_ext_embed_thumb_ajax() appends
 * an InvokeCommand(selector, 'focus') to the ManagedFile AJAX response, which
 * sets focusChanged=true in ajax.js and skips the ancestor-focus walk entirely.
 *
 * This JS sets the data-disable-refocus HTML attribute on the buttons so that
 * even if the custom callback is bypassed, ajax.js will not walk ancestors.
 * The attribute is used (not jQuery .data()) so it survives jQuery.cleanData()
 * when ReplaceCommand removes the button from the DOM.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.mukurtuThumbFocus = {
    attach: function (context) {
      context.querySelectorAll('[id*="field-thumbnail"] input[type="submit"]').forEach(function (btn) {
        btn.setAttribute('data-disable-refocus', 'true');
      });
      if (context.id && context.id.indexOf('field-thumbnail') !== -1) {
        context.querySelectorAll('input[type="submit"]').forEach(function (btn) {
          btn.setAttribute('data-disable-refocus', 'true');
        });
      }
    },
  };

}(Drupal));
