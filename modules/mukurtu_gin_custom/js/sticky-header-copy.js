/**
 * @file
 * Keeps Gin's sticky table header copy out of the Tab order (#2741).
 *
 * Gin renders a copy of a table's header row above the table, which stays
 * pinned while the page scrolls, and lays it over the real header row. Mouse
 * users click the copy. Keyboard users reached every header control twice:
 * once in the copy, and once in the real row hidden underneath it.
 *
 * The real header row keeps the table's header semantics, so it stays the one
 * for keyboard and screen reader users. The copy is hidden from assistive
 * technology and its controls leave the Tab order, but still take clicks. CSS
 * in mukurtu-gin-custom.css hides the copy while focus is in the real row, so
 * its focus indicator shows.
 *
 * This file is appended to Gin's tableheader library in
 * mukurtu_gin_custom_library_info_alter(), so it only loads with sticky
 * headers. Core's tableselect adds the select-all checkbox to both rows after
 * the page loads, so new controls in the copy are caught as they appear.
 */
((Drupal, once) => {
  const focusable = 'a[href], button, input, select, textarea, [tabindex]';

  const removeTabStops = (copy) => {
    copy.querySelectorAll(focusable).forEach((element) => {
      if (element.getAttribute('tabindex') !== '-1') {
        element.setAttribute('tabindex', '-1');
      }
    });
  };

  Drupal.behaviors.mukurtuStickyHeaderCopy = {
    attach(context) {
      once('mukurtu-sticky-header-copy', 'table.gin--sticky-table-header', context).forEach((copy) => {
        copy.setAttribute('aria-hidden', 'true');
        removeTabStops(copy);
        new MutationObserver(() => removeTabStops(copy)).observe(copy, { childList: true, subtree: true });
      });
    },
  };
})(Drupal, once);
