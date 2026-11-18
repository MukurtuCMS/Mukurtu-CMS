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
 * This remembers the button and focuses it again after the dialog closes,
 * when nothing else has focus. AJAX can replace the button, so it's found
 * again by its data-drupal-selector. When a dialog's own action already
 * placed focus, such as inserting media, it's left alone.
 */
(() => {
  const openers = '.js-media-library-open-button, [data-drupal-selector$="-entity-browser-open-modal"]';
  let openerSelector = null;

  ['mousedown', 'keydown', 'click'].forEach((type) => {
    document.addEventListener(type, (event) => {
      const button = event.target.closest?.(openers);
      if (button) {
        openerSelector = button.getAttribute('data-drupal-selector');
      }
    }, true);
  });

  window.addEventListener('dialog:afterclose', () => {
    const selector = openerSelector;
    openerSelector = null;
    if (!selector) {
      return;
    }
    setTimeout(() => {
      const active = document.activeElement;
      if (active && active !== document.body) {
        return;
      }
      const button = document.querySelector(`[data-drupal-selector="${CSS.escape(selector)}"]`);
      if (button && !button.disabled) {
        button.focus();
      }
    }, 0);
  });
})();
