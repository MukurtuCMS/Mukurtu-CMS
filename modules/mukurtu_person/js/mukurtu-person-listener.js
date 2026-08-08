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
            // eslint-disable-next-line no-console
            console.debug('[mukurtu_person] create-link click: found target_id input', $targetId.attr('id'));
          }
          else {
            // eslint-disable-next-line no-console
            console.debug('[mukurtu_person] create-link click: target_id input NOT found within', depth, 'ancestor levels');
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

          // eslint-disable-next-line no-console
          console.debug('[mukurtu_person] broadcast received', { nid: nid, title: title, selector: selector });

          if (!nid || !selector) {
            // eslint-disable-next-line no-console
            console.debug('[mukurtu_person] aborting: missing nid or no stored selector (create-link was never clicked on this tab, or nothing was found for it)');
            return;
          }

          sessionStorage.removeItem('mukurtu_person_target_id_selector');

          const $targetId = $(selector);
          if (!$targetId.length) {
            // eslint-disable-next-line no-console
            console.debug('[mukurtu_person] aborting: no element found for stored selector', selector, '- the form may have been rebuilt (e.g. an AJAX re-render) since the link was clicked, changing the element\'s id');
            return;
          }

          // eslint-disable-next-line no-console
          console.debug('[mukurtu_person] setting target_id and triggering entity_browser_value_updated', $targetId.attr('id'));

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

          // The widget's own re-render (triggered below) will show a
          // generic "Content <nid>" placeholder instead of the real name:
          // entity_browser's RenderedEntity field-widget-display plugin
          // checks $entity->access('view') before rendering, and that's
          // correctly denied here - a fresh submission has no cultural
          // protocol assigned yet, so only its owner can view it, same as
          // any other pending submission. Fixing the access check itself
          // would mean touching Mukurtu's cultural-protocol access control
          // broadly for what's ultimately a one-time cosmetic label, so
          // instead: swap the placeholder for the real title (already
          // known from the broadcast message) once the widget's AJAX
          // rebuild actually lands. ajaxComplete is used rather than a
          // fixed delay since the round-trip time isn't predictable;
          // capped at 20 attempts (roughly 10s if requests average 500ms)
          // so this can't listen forever if the rebuild never arrives.
          const $scope = $targetId.closest('.field--widget-entity-browser-entity-reference');
          const nidPattern = new RegExp('(^|\\s)' + nid + '($|\\s)');
          let attemptsLeft = 20;
          const swapPlaceholderLabel = function () {
            attemptsLeft--;
            let replaced = false;
            if ($scope.length) {
              $scope.find('*').addBack().contents().each(function () {
                if (this.nodeType === 3 && nidPattern.test(this.nodeValue)) {
                  this.nodeValue = title;
                  replaced = true;
                }
              });
            }
            if (replaced || attemptsLeft <= 0) {
              $(document).off('ajaxComplete', swapPlaceholderLabel);
            }
          };
          $(document).on('ajaxComplete', swapPlaceholderLabel);

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
