/**
 * @file
 * Names select-all table checkboxes with aria-label, not only title (#2397).
 *
 * Core's tableselect.js adds the "Select all rows in this table" checkbox with
 * only a title, and Gin's sticky table header copies it the same way. A title
 * on its own is a weak accessible name: it is not shown to keyboard or touch
 * users and some assistive technology skips it. This copies the title into
 * aria-label and keeps it in step as core switches the title between "Select
 * all" and "Deselect all".
 *
 * This file is appended to core/drupal.tableselect in
 * mukurtu_gin_custom_library_info_alter(), so it only loads with select-all
 * tables. Gin replaces tableselect.js with its own copy, which can end up after
 * this file, so the work waits until every behavior has attached and the
 * checkbox exists.
 */
((Drupal, once) => {
  Drupal.behaviors.mukurtuSelectAllName = {
    attach(context) {
      setTimeout(() => {
        once('mukurtu-select-all-name', 'th.select-all input[type="checkbox"][title]', context).forEach((checkbox) => {
          const sync = () => checkbox.setAttribute('aria-label', checkbox.title);
          sync();
          new MutationObserver(sync).observe(checkbox, { attributes: true, attributeFilter: ['title'] });
        });
      });
    },
  };
})(Drupal, once);
