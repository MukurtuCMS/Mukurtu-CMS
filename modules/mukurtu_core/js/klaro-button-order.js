/**
 * @file
 * Puts Klaro's consent settings button after the skip link (#2747).
 *
 * Klaro adds its button as the first element of <body>, so it was the first
 * Tab stop on every page, ahead of "Skip to main content". Moving it to just
 * after the skip link makes the skip link first, and keeps the button early
 * in the tab order. It's fixed in place on screen, so it doesn't move there.
 *
 * Appended to klaro/klaro by mukurtu_core_library_info_alter(), after
 * klaro.drupal.js, so this runs after Klaro has added the button.
 */
((Drupal) => {
  Drupal.behaviors.mukurtuKlaroButtonOrder = {
    attach(context) {
      if (context !== document) {
        return;
      }
      const button = document.getElementById('klaro_toggle_dialog');
      const skipLink = document.querySelector('body > a[href="#main-content"]');
      if (button && skipLink && skipLink.nextElementSibling !== button) {
        skipLink.after(button);
      }
    },
  };
})(Drupal);
