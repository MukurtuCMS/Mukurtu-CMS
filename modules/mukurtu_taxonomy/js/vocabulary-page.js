/**
 * @file
 * Filters a long vocabulary page as you type, collapses nested terms, and
 * shortens long letter cards.
 *
 * The script adds the buttons itself, so with JavaScript off every term stays
 * visible.
 */

(function (Drupal, once) {
  'use strict';

  // As on the term page's side panel (term-panel.js).
  const VISIBLE_TERMS = 10;

  // Matches ignore case and accents, so "e" finds "é".
  function normalize(text) {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase();
  }

  // Drupal.t() escapes placeholders, which an aria-label would show as text.
  function plain(text) {
    return new DOMParser().parseFromString(text, 'text/html').documentElement.textContent;
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
   * Adds a button to a parent term, and starts it closed.
   *
   * @return {object}
   *   The branch, with its nested list, button and whether it's open.
   */
  function addToggle(term) {
    const name = term.querySelector(':scope > [data-vocabulary-term-name]').textContent;
    const branch = {
      nested: term.querySelector(':scope > ul'),
      button: document.createElement('button'),
      name: name,
      open: false,
    };
    branch.button.type = 'button';
    branch.button.className = 'vocabulary-page__toggle';
    branch.button.addEventListener('click', function () {
      branch.open = !branch.open;
      showBranch(branch);
      // Opening a branch rebalances the columns, which can move the card, and
      // focus has to stay in view (WCAG 2.4.11).
      branch.button.scrollIntoView({block: 'nearest'});
    });
    term.prepend(branch.button);
    showBranch(branch);
    return branch;
  }

  function showBranch(branch) {
    const button = branch.button;
    branch.nested.hidden = !branch.open;
    button.setAttribute('aria-expanded', String(branch.open));
    button.setAttribute('aria-label', plain(branch.open
      ? Drupal.t('Hide terms under @term', {'@term': branch.name})
      : Drupal.t('Show terms under @term', {'@term': branch.name})));
    button.textContent = branch.open ? '−' : '+';
  }

  function addButton(label, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button';
    button.textContent = label;
    button.addEventListener('click', onClick);
    return button;
  }

  /**
   * Shortens a long letter card to its first terms, with a See more button.
   *
   * Matches the term page's side panel (term-panel.js).
   *
   * @return {object|null}
   *   The card's hidden terms, button and whether it's been opened, or null
   *   if the card is short enough already.
   */
  function shortenCard(group) {
    const list = group.querySelector(':scope > ul');
    const extra = list ? Array.from(list.children).slice(VISIBLE_TERMS) : [];
    if (!extra.length) {
      return null;
    }
    const card = {extra: extra, open: false};
    card.button = addButton(Drupal.t('See more'), function () {
      card.open = true;
      showCard(card);
      // The button goes, so focus moves to the first term it revealed.
      extra[0].querySelector('a').focus();
      card.button.remove();
    });
    card.button.classList.add('vocabulary-page__more');
    // Says which letter, since every long card has one of these buttons.
    const heading = group.querySelector(':scope > h2');
    if (heading && heading.id) {
      card.button.setAttribute('aria-describedby', heading.id);
    }
    list.after(card.button);
    showCard(card);
    return card;
  }

  function showCard(card) {
    card.extra.forEach(function (term) {
      term.hidden = !card.open;
    });
  }

  /**
   * Adds the filter and the expand and collapse controls to a vocabulary page.
   */
  Drupal.behaviors.mukurtuVocabularyPage = {
    attach: function (context) {
      once('mukurtu-vocabulary-page', '[data-vocabulary-page]', context).forEach(function (page) {
        const wrapper = page.querySelector('[data-vocabulary-filter]');
        const input = wrapper.querySelector('input');
        const status = page.querySelector('[data-vocabulary-status]');
        const letters = page.querySelector('[data-vocabulary-letters]');
        const groups = page.querySelectorAll('[data-vocabulary-group]');
        const total = page.querySelectorAll('[data-vocabulary-term], [data-vocabulary-card-term]').length;

        const branches = Array.from(page.querySelectorAll('[data-vocabulary-term]'))
          .filter(function (term) { return term.querySelector(':scope > ul'); })
          .map(addToggle);

        let expandControls = null;
        if (branches.length) {
          page.classList.add('vocabulary-page--collapsible');
          const setAll = function (open) {
            branches.forEach(function (branch) {
              branch.open = open;
              showBranch(branch);
            });
          };
          expandControls = document.createElement('div');
          expandControls.className = 'vocabulary-page__expand';
          expandControls.append(
            addButton(Drupal.t('Expand all'), function () { setAll(true); }),
            addButton(Drupal.t('Collapse all'), function () { setAll(false); })
          );
          letters.after(expandControls);
        }

        // Letter cards have no term of their own in the heading. Cards in a
        // nested vocabulary collapse their branches instead.
        const cards = Array.from(groups)
          .filter(function (group) { return !group.querySelector('[data-vocabulary-card-term]'); })
          .map(shortenCard)
          .filter(Boolean);

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
            const list = group.querySelector(':scope > ul');
            const heading = group.querySelector('[data-vocabulary-card-term]');
            let groupMatches = list ? filterList(list, query) : 0;
            if (list) {
              list.hidden = groupMatches === 0;
            }
            if (heading && normalize(heading.textContent).includes(query)) {
              groupMatches++;
            }
            group.hidden = groupMatches === 0;
            matches += groupMatches;
          });
          letters.hidden = query !== '';
          if (expandControls) {
            expandControls.hidden = query !== '';
          }
          // Filtering opens every branch with a match. Clearing the filter puts
          // them back as the visitor left them.
          branches.forEach(function (branch) {
            branch.button.hidden = query !== '';
            if (!query) {
              showBranch(branch);
            }
          });
          // Filtering shows every match, however far down its card. Clearing
          // it shortens the cards the visitor hadn't opened again.
          cards.forEach(function (card) {
            card.button.hidden = query !== '';
            if (!query) {
              showCard(card);
            }
          });
          announce(query, matches);
        });

        wrapper.hidden = false;
      });
    }
  };

})(Drupal, once);
