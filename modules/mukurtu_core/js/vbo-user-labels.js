/**
 * @file
 * Replaces "item(s) selected" with "user(s) selected" in the Claro bulk-action
 * status element on user-management views.
 *
 * The status text is set by claro/js/tableselect.js after every checkbox
 * change. A MutationObserver watches for those updates so the label stays
 * consistent without patching core or contrib code.
 */
(function (Drupal, once) {
  'use strict';

  function fixStatusText(el) {
    const text = el.textContent;
    const fixed = text
      .replace(/\bitem\b/g, 'user')
      .replace(/\bitems\b/g, 'users');
    if (text !== fixed) {
      el.textContent = fixed;
    }
  }

  Drupal.behaviors.mukurtuVboUserLabels = {
    attach(context) {
      once(
        'vbo-user-labels',
        '[data-drupal-views-bulk-actions-status]',
        context,
      ).forEach(function (el) {
        fixStatusText(el);
        new MutationObserver(function () {
          fixStatusText(el);
        }).observe(el, { childList: true, characterData: true, subtree: true });
      });
    },
  };
}(Drupal, once));
