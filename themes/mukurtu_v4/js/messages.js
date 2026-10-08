/**
 * @file
 * Lets users dismiss status, warning, and error messages.
 *
 * A single delegated listener on document covers both server-rendered
 * messages (status-messages.html.twig) and AJAX-inserted ones
 * (message.theme.js), without needing to re-attach per AJAX response.
 */

((Drupal) => {
  if (Drupal.mukurtuMessagesDismiss) {
    return;
  }
  Drupal.mukurtuMessagesDismiss = true;

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.messages__close');
    if (!button) {
      return;
    }
    const message = button.closest('.messages-list__item');
    if (!message) {
      return;
    }

    message.remove();

    // WCAG 2.4.3: the focused button is gone, so keep keyboard and screen
    // reader users in place instead of dropping them to <body>. Move to the
    // next remaining message's button, or else to the main content.
    const next = document.querySelector('.messages-list__item .messages__close');
    const main = document.getElementById('main-content');
    if (next) {
      next.focus();
    } else if (main) {
      main.focus();
    }
  });
})(Drupal);
