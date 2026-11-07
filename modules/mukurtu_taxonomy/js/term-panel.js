/**
 * @file
 * Shortens long lists in a term page's side panel.
 *
 * The script does the shortening, so with JavaScript off every term shows.
 */

(function (Drupal, once) {
  'use strict';

  const VISIBLE_TERMS = 10;

  /**
   * Adds a See more button to each long list.
   */
  Drupal.behaviors.mukurtuTermPanel = {
    attach: function (context) {
      once('mukurtu-term-panel', '.taxonomy-term-panel__list', context).forEach(function (list) {
        const extra = Array.from(list.children).slice(VISIBLE_TERMS);
        if (!extra.length) {
          return;
        }
        extra.forEach(function (term) {
          term.hidden = true;
        });

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'taxonomy-term-panel__more';
        button.textContent = Drupal.t('See more');
        // Says which list, since a panel can have two of these buttons.
        button.setAttribute('aria-describedby', list.previousElementSibling.id);
        button.addEventListener('click', function () {
          extra.forEach(function (term) {
            term.hidden = false;
          });
          // The button goes, so focus moves to the first term it revealed.
          extra[0].querySelector('a').focus();
          button.remove();
        });
        list.after(button);
      });
    }
  };

})(Drupal, once);
