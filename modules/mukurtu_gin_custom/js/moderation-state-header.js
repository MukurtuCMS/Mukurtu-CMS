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

        // Remove the "Current state: X" label -- redundant in the header.
        const currentState = widget.querySelector('.js-form-item:first-child .form-item__label');
        if (currentState && currentState.textContent.trim() === Drupal.t('Current state')) {
          currentState.closest('.js-form-item')?.remove();
        }

        statusSlot.appendChild(widget);

        // Hide any remaining copy of the widget in the sidebar footer.
        document.querySelectorAll('.gin-sidebar .field--name-moderation-state').forEach((dup) => {
          dup.style.display = 'none';
        });
      });
    },
  };
})(jQuery, Drupal, once);
