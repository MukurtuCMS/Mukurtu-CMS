/**
 * @file
 * Returns focus to the button that opened a field's dialog when it closes.
 *
 * jQuery UI returns focus to whatever had it when a dialog opened. The media
 * library's "Add media" button and entity browsers' select buttons are AJAX
 * buttons, which Drupal disables while the dialog loads, so by then focus has
 * already fallen to the page body. Closing the dialog with Escape or its
 * Close button left focus there, and keyboard and screen reader users lost
 * their place in the form (WCAG 2.4.3, #2772).
 *
 * This remembers the button when it's activated and focuses it again after
 * the dialog closes, when nothing else has focus. Inserting or selecting
 * rebuilds the field with AJAX, which replaces the button, so this waits for
 * that to finish and finds the button again by its data-drupal-selector.
 * When something else already has focus, it's left alone.
 */
(($) => {
  const openers = '.js-media-library-open-button, [data-drupal-selector$="-entity-browser-open-modal"]';
  let openerSelector = null;

  const remember = (event) => {
    const button = event.target.closest?.(openers);
    if (button) {
      openerSelector = button.getAttribute('data-drupal-selector');
    }
  };
  // Only activation counts: Tab passing over a button doesn't.
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      remember(event);
    }
  }, true);
  document.addEventListener('mousedown', remember, true);
  document.addEventListener('click', remember, true);

  const refocus = (selector) => {
    const active = document.activeElement;
    if (active && active !== document.body) {
      return;
    }
    const button = document.querySelector(`[data-drupal-selector="${CSS.escape(selector)}"]`);
    if (button && !button.disabled) {
      button.focus();
    }
  };

  window.addEventListener('dialog:afterclose', () => {
    const selector = openerSelector;
    openerSelector = null;
    if (!selector) {
      return;
    }
    setTimeout(() => {
      if ($.active > 0) {
        $(document).one('ajaxStop', () => setTimeout(() => refocus(selector), 0));
      }
      else {
        refocus(selector);
      }
    }, 0);
  });
})(jQuery);
