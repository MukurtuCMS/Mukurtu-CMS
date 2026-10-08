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

    // WCAG 2.4.3: the focused button is about to go, so keep keyboard and
    // screen reader users in place instead of dropping them to <body>. Prefer
    // the neighboring message in the same list, then the dialog the message
    // was in (so focus stays inside the modal), then the main content.
    const sibling = [message.nextElementSibling, message.previousElementSibling]
      .find((el) => el && el.matches('.messages-list__item'));
    const target =
      (sibling && sibling.querySelector('.messages__close')) ||
      message.closest('.ui-dialog') ||
      document.getElementById('main-content');

    message.remove();

    if (target) {
      target.focus();
    }
  });
})(Drupal);
