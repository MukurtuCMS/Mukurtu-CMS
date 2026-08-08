/**
 * @file
 * Listens for a new-person BroadcastChannel message and directly selects the
 * new person on the field_related_person entity_browser widget that opened
 * the "Create a new person record" link.
 *
 * Flow:
 *  1. User clicks the "Create a new person record" link — the click handler
 *     finds the field widget's own hidden "target_id" input and remembers
 *     its element id in sessionStorage, so we know which field to update
 *     when the message arrives.
 *  2. User creates the person in the new tab; mukurtu-person-broadcast.js
 *     fires a BroadcastChannel message with { nid, title }.
 *  3. The onmessage handler finds the stored target_id input, sets its
 *     value to "node:<nid>" and fires the widget's own AJAX-trigger event -
 *     the same mechanism a normal picker selection uses - so the widget
 *     shows the new person as selected without ever opening the picker.
 *
 * The picker itself is deliberately skipped, rather than reopened: a
 * freshly-submitted person record has no cultural protocol assigned yet, so
 * it isn't independently viewable, and the picker's own view would show no
 * results for it. What actually authorizes referencing it despite that is
 * server-side - see \Drupal\mukurtu_submissions\Form\PublicSubmissionForm::
 * isReferenceToSessionCreatedEntity() - this only saves the visitor from
 * having to search a picker for a record they can't see yet.
 */
(function (Drupal, $, once) {
  'use strict';

  Drupal.behaviors.mukurtuPersonListener = {
    attach: function (context, settings) {

      // Step 1: when the create-link is clicked, remember which field's
      // hidden target_id input to update once the broadcast arrives.
      $(once('mukurtu-person-create-link', '.mukurtu-person-create-link', context))
        .on('click', function () {
          // The create link and the field's own hidden target_id input
          // share a common ancestor (the entity_browser_entity_reference
          // widget's own wrapper) but the exact wrapper class varies by
          // context (paragraph subform, field item table, etc.). Walk up
          // the DOM until we find an ancestor that contains it, stopping
          // before the full form.
          let $targetId = $();
          let $ancestor = $(this).parent();
          let depth = 0;
          while ($ancestor.length && !$targetId.length && depth < 10) {
            $targetId = $ancestor.find('input[type="hidden"][name*="[target_id]"]').first();
            if (!$targetId.length) {
              $ancestor = $ancestor.parent();
              depth++;
            }
          }

          if ($targetId.length) {
            sessionStorage.setItem('mukurtu_person_target_id_selector', '#' + $targetId.attr('id'));
          }
        });

      // Step 2: open a single persistent channel per page. Use document.body
      // directly (not scoped to context) so this runs exactly once even when
      // the create link first appears via an AJAX paragraph-add response.
      $(once('mukurtu-person-channel', document.body)).each(function () {
        if (!('BroadcastChannel' in window)) {
          return;
        }

        const channel = new BroadcastChannel('mukurtu_person_created');

        channel.onmessage = function (event) {
          const nid = event.data && event.data.nid;
          const title = event.data && event.data.title;
          const selector = sessionStorage.getItem('mukurtu_person_target_id_selector');

          if (!nid || !selector) {
            return;
          }

          sessionStorage.removeItem('mukurtu_person_target_id_selector');

          const $targetId = $(selector);
          if (!$targetId.length) {
            return;
          }

          // Announce to screen readers that the new person has been
          // selected. The live region is injected, read aloud, then
          // removed after 3s.
          const $announcement = $('<div>', {
            'aria-live': 'polite',
            'aria-atomic': 'true',
            'class': 'visually-hidden',
            'text': Drupal.t('@title was created and selected as the related person.', { '@title': title }),
          }).appendTo('body');
          setTimeout(function () { $announcement.remove(); }, 3000);

          // Set the widget's value and fire its own AJAX-trigger event -
          // EntityReferenceBrowserWidget deliberately uses a custom event
          // ("entity_browser_value_updated"), not a plain "change", for
          // this hidden field (see its own formElement()) - so the widget
          // rebuilds and shows the new person as the current selection,
          // exactly as if it had been chosen through the picker.
          $targetId.val('node:' + nid).trigger('entity_browser_value_updated');
        };
      });
    }
  };

})(Drupal, jQuery, once);
