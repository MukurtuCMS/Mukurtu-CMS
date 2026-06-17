/**
 * Moves the content_moderation state widget into Gin's sticky header.
 *
 * content_moderation places the widget in the footer group, which Gin copies
 * into its sidebar panel. We move it into the sticky header's status slot
 * (where the core Published toggle lives when content_moderation is inactive)
 * and hide the sidebar copy to avoid duplication.
 */
(function ($, Drupal, once) {
  Drupal.behaviors.mukurtuModerationStateHeader = {
    attach(context, settings) {
      once('moderation-state-header', '.gin-sticky-form-actions', context).forEach((sticky) => {
        const widget = document.querySelector('.field--name-moderation-state');
        if (!widget) return;

        const statusSlot = sticky.querySelector('#edit-status, [data-drupal-selector="edit-status"]');
        if (!statusSlot) return;

        // Visually hide "Current state: X" in the header -- it is spatially
        // redundant there, but must remain in the accessibility tree.
        const currentStateItem = widget.querySelector('.js-form-item:first-child');
        if (currentStateItem) {
          const label = currentStateItem.querySelector('.form-item__label');
          if (label && label.textContent.trim() === Drupal.t('Current state')) {
            currentStateItem.classList.add('visually-hidden');
          }
        }

        statusSlot.appendChild(widget);

        // Remove any remaining copy of the widget from the sidebar to prevent
        // duplicate id attributes in the document, which break label/input
        // association for assistive technology.
        document.querySelectorAll('.gin-sidebar .field--name-moderation-state').forEach((dup) => {
          dup.remove();
        });
      });
    },
  };
})(jQuery, Drupal, once);
