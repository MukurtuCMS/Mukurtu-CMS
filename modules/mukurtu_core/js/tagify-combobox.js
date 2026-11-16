/**
 * @file
 * Exposes Tagify's input and suggestion list as an ARIA combobox (#2631).
 *
 * Tagify draws its input as a plain textbox and never ties it to the
 * suggestion list, so arrowing through suggestions announced nothing. This
 * makes the input a combobox that controls the list, points
 * aria-activedescendant at the highlighted suggestion so screen readers read
 * it, and announces how many suggestions there are, or that none match.
 *
 * Adding and removing tags is announced by mukurtu-tagify-override.js.
 * See https://www.w3.org/WAI/ARIA/apg/patterns/combobox/.
 */
((Drupal, drupalSettings) => {
  /**
   * Gives an element an ID, if it has none.
   */
  let nextId = 0;
  function ensureId(element, prefix) {
    if (!element.id) {
      nextId += 1;
      element.id = `${prefix}-${nextId}`;
    }
    return element.id;
  }

  /**
   * The suggestions shown, without the "no match" row.
   */
  function suggestionsOf(tagify) {
    return tagify.dropdown
      .getAllSuggestionsRefs()
      .filter((option) => option.getAttribute('value') !== 'noMatch');
  }

  /**
   * Points the input at the highlighted suggestion, or at none.
   */
  function syncActive(tagify, listId) {
    const { input } = tagify.DOM;
    const active = tagify.state.ddItemElm;
    if (active && active.isConnected) {
      input.setAttribute('aria-activedescendant', ensureId(active, `${listId}-option`));
    }
    else {
      input.removeAttribute('aria-activedescendant');
    }
  }

  /**
   * Says how many suggestions there are, or that none match.
   *
   * Called once the suggestions settle. While the module fetches matches,
   * Tagify filters its previous list and can briefly report no match, so
   * announcing every change would say "no match" and then a count.
   */
  function announceState(tagify, noMatch) {
    const { scope, input } = tagify.DOM;
    // The Tagify module shows a loading row while it fetches suggestions.
    if (scope.querySelector('.tagify--loading-text')) {
      return;
    }
    const count = tagify.state.dropdown.visible ? suggestionsOf(tagify).length : 0;
    let message;
    if (count) {
      message = Drupal.formatPlural(
        count,
        '1 suggestion available.',
        '@count suggestions available.',
      );
    }
    else if (noMatch) {
      const text = drupalSettings.tagify?.information_message?.no_matching_suggestions;
      const typed = input.textContent.trim();
      if (!text || !typed) {
        return;
      }
      // Drupal.announce() writes with innerHTML, so escape the typed text.
      message = `${text} ${Drupal.checkPlain(typed)}`;
    }
    // Typing on with the same result doesn't repeat it.
    if (message && message !== tagify.__mukurtuLastAnnounced) {
      tagify.__mukurtuLastAnnounced = message;
      Drupal.announce(message);
    }
  }

  /**
   * Turns one Tagify field into a combobox.
   */
  function setUp(original) {
    const tagify = original.__tagify;
    const { input, dropdown } = tagify.DOM;
    const listId = ensureId(dropdown, `${original.id || 'tagify'}--suggestions`);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);

    // Tagify labels its list by an element with the ID "dropdown", which
    // doesn't exist. Name it after the field instead.
    const label = document.querySelector(`label[for="${original.id}"]`);
    dropdown.removeAttribute('aria-labelledby');
    if (label) {
      dropdown.setAttribute('aria-label', label.textContent.trim());
    }

    const { highlightOption } = tagify.dropdown;
    tagify.dropdown.highlightOption = (...args) => {
      const result = highlightOption.apply(tagify, args);
      syncActive(tagify, listId);
      return result;
    };

    // Announce the count once the suggestions settle (see announceState()).
    let timer;
    let noMatch = false;
    const settle = () => {
      clearTimeout(timer);
      timer = setTimeout(() => announceState(tagify, noMatch), 400);
    };
    tagify.on('input', () => {
      noMatch = false;
    });
    tagify.on('dropdown:noMatch', () => {
      noMatch = true;
      settle();
    });
    tagify.on('dropdown:show', () => {
      input.setAttribute('aria-expanded', 'true');
      syncActive(tagify, listId);
      if (suggestionsOf(tagify).length) {
        noMatch = false;
      }
      settle();
    });
    tagify.on('dropdown:updated', () => {
      syncActive(tagify, listId);
      settle();
    });
    tagify.on('dropdown:hide', () => {
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      // A list that closes because nothing matches still says so. One that
      // closes because a suggestion was picked or Escape was pressed doesn't.
      if (!noMatch) {
        clearTimeout(timer);
      }
      tagify.__mukurtuLastAnnounced = null;
    });
  }

  /**
   * Sets up each ready Tagify field in a context.
   */
  function setUpAll(context) {
    const root = context && context.querySelectorAll ? context : document;
    root.querySelectorAll('input.tagify-widget').forEach((original) => {
      // Marked only once set up, so a field whose Tagify starts later is
      // picked up on the next pass.
      if (!original.__tagify || original.__mukurtuCombobox) {
        return;
      }
      original.__mukurtuCombobox = true;
      setUp(original);
    });
  }

  Drupal.behaviors.mukurtuTagifyCombobox = {
    attach(context) {
      // Tagify can start later in the same attach cycle, so run again at the
      // end of the tick, as mukurtu-tagify-override.js does.
      setUpAll(context);
      setTimeout(() => setUpAll(context), 0);
    },
  };
})(Drupal, drupalSettings);
