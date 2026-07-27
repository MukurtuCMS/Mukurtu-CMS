/**
 * Adds inline edit buttons to Layout Builder blocks on front-end page views.
 *
 * Edit URLs are passed via drupalSettings.mukurtuLbEditUrls (a UUID->URL map
 * computed fresh per-request in hook_page_attachments). The UUID is stamped on
 * each block element as data-layout-block-uuid by hook_layout_builder_block_alter.
 */
(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuLbFrontendEdit = {
    attach: function (context, settings) {
      var editUrls = settings.mukurtuLbEditUrls;
      if (!editUrls) {
        return;
      }

      once('lb-frontend-edit', '[data-layout-block-uuid]', context).forEach(function (block) {
        var uuid = block.getAttribute('data-layout-block-uuid');
        var url = editUrls[uuid];
        if (!url) {
          return;
        }

        block.classList.add('lb-editable');

        // Use a <button> rather than <a> so the implicit ARIA role (button) matches
        // the action -- clicking opens a dialog, not navigating to another page.
        // Drupal's core AJAX binding (Drupal.ajax.bindAjaxLinks) only ever reads
        // the URL from the element's href attribute, regardless of tag name, so
        // href is set here even though it's not semantically meaningful on a
        // <button> -- the browser ignores it for navigation, but jQuery's
        // .attr('href') still reads it, which is all Drupal's AJAX binding needs.
        var blockLabel = block.getAttribute('data-lb-block-label');
        var ariaLabel = blockLabel
          ? Drupal.t('Edit @label', {'@label': blockLabel})
          : Drupal.t('Edit block');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lb-edit-btn use-ajax';
        btn.setAttribute('href', url);
        btn.setAttribute('data-dialog-type', 'dialog');
        btn.setAttribute('data-dialog-renderer', 'off_canvas');
        btn.setAttribute('data-dialog-options', JSON.stringify({ width: 400 }));
        // aria-haspopup informs assistive technologies that activating this
        // button opens a dialog rather than performing a navigation or submission.
        btn.setAttribute('aria-haspopup', 'dialog');
        btn.setAttribute('aria-label', ariaLabel);
        btn.innerHTML =
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
            '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>' +
          '</svg>';

        block.appendChild(btn);

        // Let Drupal process the use-ajax class on the new button.
        Drupal.attachBehaviors(block, settings);
      });
    },
  };

})(jQuery, Drupal, once);
