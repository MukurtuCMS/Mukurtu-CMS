/**
 * @file protocol-community-browser.js
 *
 * Auto-triggers member list refresh when the community entity browser
 * selection is completed.
 */

(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.mukurtuProtocolCommunityBrowser = {
    attach: function (context) {
      // Attach exactly one listener to the document.  Use document.body as
      // the once() sentinel: it is stable across AJAX rebuilds, so repeated
      // calls to attach() after the wrapper is replaced are no-ops.
      once('mukurtu-community-auto-update', document.body).forEach(function () {
        $(document).on('entities-selected', function (event) {
          // Only react when the triggering entity browser button is inside
          // our communities wrapper (guards against other browsers on page).
          if (!$(event.target).closest('#communities-and-members-wrapper').length) {
            return;
          }

          var $btn = $('#communities-and-members-wrapper').find('.js-communities-auto-update');

          // entity_browser.modal_selection.js fires entities-selected once
          // (via selectionCompleted, which populates entity_ids), then unbinds
          // the handler. entity_browser.modal.js fires it again via the
          // select_entities AJAX command — by then entity_ids is empty.
          // Skip the second fire to avoid wiping protocol_communities.
          // eb-target is entity_browser's internal hidden input that holds the
          // selected entity IDs after the first (real) selection event and is
          // cleared by the second (AJAX command) event — intentional coupling.
          var entityIds = $btn.closest('form').find('input.eb-target').val();
          if (!entityIds || !entityIds.trim()) {
            return;
          }

          // The entities-selected event is triggered synchronously from within
          // the entity browser iframe via parent.jQuery.trigger(). XHRs started
          // inside a cross-frame synchronous event can be aborted by the browser.
          // Defer to the next task so we're outside that frame context.
          var btnId = $btn.attr('id');
          setTimeout(function () {
            var ajaxInstance = (Drupal.ajax.instances || []).find(function (inst) {
              return inst && inst.element && inst.element.id === btnId;
            });
            if (ajaxInstance && !ajaxInstance.ajaxing) {
              ajaxInstance.execute();
            }
          }, 0);
        });
      });
    }
  };

}(jQuery, Drupal, once));
