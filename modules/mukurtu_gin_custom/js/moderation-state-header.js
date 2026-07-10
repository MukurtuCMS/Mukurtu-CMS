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
    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      // Drupal calls detachBehaviors(context) with the trigger defaulted to
      // 'unload' for unrelated AJAX operations too (e.g. opening a dialog),
      // passing that dialog's own element as context. Only react when the
      // sticky header is actually part of what's being detached -- e.g. a
      // genuine full-page/form teardown -- otherwise this fires on every
      // modal open anywhere on the page and deletes the moved widget.
      const sticky = document.querySelector('.gin-sticky-form-actions');
      if (!sticky || !context.contains || !context.contains(sticky)) return;
      sticky.querySelectorAll('.field--name-moderation-state').forEach((w) => w.remove());
      once.remove('moderation-state-header', sticky);
    },
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

        // Capture the parent form ID before moving the widget. Gin's sticky
        // header is rendered outside the <form> element (gin_sticky_actions is
        // unset from the form render array in GinContentFormHelper::formAfterBuild
        // and placed in the page template). Without form="<id>" the select
        // would not be submitted and the node would save with the workflow's
        // default_moderation_state instead of the user's selection.
        const formEl = widget.closest('form');
        const formId = formEl ? formEl.id : null;

        statusSlot.appendChild(widget);

        if (formId) {
          widget.querySelectorAll('select, input, textarea').forEach((input) => {
            if (!input.getAttribute('form')) {
              input.setAttribute('form', formId);
            }
          });
        }

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
