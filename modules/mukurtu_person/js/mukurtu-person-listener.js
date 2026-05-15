/**
 * @file
 * Listens for a new-person BroadcastChannel message and re-opens the
 * field_related_person entity browser pre-filtered to the new person's title.
 *
 * Flow:
 *  1. User clicks the "Create a new person record" link — the click handler
 *     stores the entity browser button's UUID in sessionStorage so we know
 *     which browser to reopen when the message arrives.
 *  2. User creates the person in the new tab; mukurtu-person-broadcast.js
 *     fires a BroadcastChannel message with { nid, title }.
 *  3. The onmessage handler finds the stored button, appends &title=<name>
 *     to its sibling hidden path input (which becomes the iframe src), and
 *     programmatically clicks the button to open the modal.
 *  4. The entity browser iframe loads with the view pre-filtered, showing
 *     only the newly created person. One click selects them.
 */
(function (Drupal, $, once) {
  'use strict';

  Drupal.behaviors.mukurtuPersonListener = {
    attach: function (context, settings) {

      // Step 1: when the create-link is clicked, remember which entity browser
      // it belongs to so we can reopen the right one after the broadcast.
      $(once('mukurtu-person-create-link', '.mukurtu-person-create-link', context))
        .on('click', function () {
          // The create link and entity browser button share a common ancestor
          // but the exact wrapper class varies by context (paragraph subform,
          // field item table, etc.). Walk up the DOM until we find an ancestor
          // that contains a [data-uuid] button, stopping before the full form.
          let $button = $();
          let $ancestor = $(this).parent();
          let depth = 0;
          while ($ancestor.length && !$button.length && depth < 10) {
            $button = $ancestor.find('[data-uuid]').first();
            if (!$button.length) {
              $ancestor = $ancestor.parent();
              depth++;
            }
          }

          if ($button.length) {
            sessionStorage.setItem('mukurtu_person_eb_uuid', $button.attr('data-uuid'));
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
          const title = event.data && event.data.title;
          const uuid = sessionStorage.getItem('mukurtu_person_eb_uuid');

          if (!title || !uuid) {
            return;
          }

          sessionStorage.removeItem('mukurtu_person_eb_uuid');

          // Announce to screen readers that the entity browser is opening.
          // The live region is injected, read aloud, then removed after 3s.
          const $announcement = $('<div>', {
            'aria-live': 'polite',
            'aria-atomic': 'true',
            'class': 'visually-hidden',
            'text': Drupal.t('@title was created. Opening the browser to select them.', { '@title': title }),
          }).appendTo('body');
          setTimeout(function () { $announcement.remove(); }, 3000);

          // Find the entity browser open button by its UUID.
          const $button = $('[data-uuid="' + uuid + '"]');
          if (!$button.length) {
            return;
          }

          // The entity browser Display/Modal renders a hidden 'path' input and
          // the open_modal submit button as siblings inside a container div.
          // Modifying the path value before clicking causes openModal() to use
          // the updated URL as the iframe src, so Views picks up ?title=... as
          // an exposed filter GET parameter.
          const $path = $button.parent().find('input[type="hidden"]').first();
          if ($path.length) {
            let src = $path.val();
            // Strip any existing title param to avoid duplicates.
            src = src.replace(/([?&])title=[^&]*/g, '$1').replace(/[?&]$/, '');
            src += (src.includes('?') ? '&' : '?') + 'title=' + encodeURIComponent(title);
            $path.val(src);
          }

          $button.trigger('click');
        };
      });
    }
  };

})(Drupal, jQuery, once);
