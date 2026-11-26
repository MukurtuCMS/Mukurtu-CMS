/**
 * @file
 * Filters a long vocabulary page as you type.
 */

(function (Drupal, once) {
  'use strict';

  // Matches ignore case and accents, so "e" finds "é".
  function normalize(text) {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase();
  }

  /**
   * Shows the terms that match, and the terms they're nested under.
   *
   * @return {number}
   *   How many terms in this list match.
   */
  function filterList(list, query) {
    let matches = 0;
    list.querySelectorAll(':scope > [data-vocabulary-term]').forEach(function (term) {
      const name = term.querySelector(':scope > [data-vocabulary-term-name]').textContent;
      const nested = term.querySelector(':scope > ul');
      const nestedMatches = nested ? filterList(nested, query) : 0;
      if (nested) {
        // An empty list is invalid for assistive technology.
        nested.hidden = nestedMatches === 0;
      }
      const isMatch = normalize(name).includes(query);
      term.hidden = !isMatch && nestedMatches === 0;
      matches += nestedMatches + (isMatch ? 1 : 0);
    });
    return matches;
  }

  /**
   * Adds the filter to a vocabulary page.
   */
  Drupal.behaviors.mukurtuVocabularyPage = {
    attach: function (context) {
      once('mukurtu-vocabulary-page', '[data-vocabulary-page]', context).forEach(function (page) {
        const wrapper = page.querySelector('[data-vocabulary-filter]');
        const input = wrapper.querySelector('input');
        const status = page.querySelector('[data-vocabulary-status]');
        const letters = page.querySelector('[data-vocabulary-letters]');
        const groups = page.querySelectorAll('[data-vocabulary-group]');
        const total = page.querySelectorAll('[data-vocabulary-term]').length;

        // Waits for a pause in typing, so screen readers aren't interrupted by
        // a new count for every letter.
        const announce = Drupal.debounce(function (query, matches) {
          status.textContent = query
            ? Drupal.formatPlural(matches, '1 of @total terms matches.', '@count of @total terms match.', {'@total': total})
            : '';
        }, 400);

        input.addEventListener('input', function () {
          const query = normalize(input.value.trim());
          let matches = 0;
          groups.forEach(function (group) {
            const groupMatches = filterList(group.querySelector('ul'), query);
            group.hidden = groupMatches === 0;
            matches += groupMatches;
          });
          letters.hidden = query !== '';
          announce(query, matches);
        });

        wrapper.hidden = false;
      });
    }
  };

})(Drupal, once);
