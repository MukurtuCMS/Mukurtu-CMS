(function (Drupal, drupalSettings) {
  /**
   * Tell screen reader users why Enter did nothing.
   *
   * Sighted users see the loading text or the "No matching suggestions" row.
   * Reuses Tagify's own strings, which are already translated.
   */
  function announceBlockedEnter(instance) {
    const announce = instance.DOM.originalInput.__mukurtuAnnounce;
    const text = instance.DOM.input.textContent.trim();
    if (!announce || !text) {
      return;
    }
    if (instance.DOM.scope.querySelector('.tagify--loading-text')) {
      announce(Drupal.t('Loading...'));
    }
    else if (instance.DOM.dropdown.isConnected) {
      const noMatch = drupalSettings.tagify?.information_message?.no_matching_suggestions;
      if (noMatch) {
        // announce() decodes HTML entities, so escape the typed text first.
        announce(`${noMatch} ${Drupal.checkPlain(text)}`);
      }
    }
  }

  /**
   * Update a Tagify instance to stop creating terms on delimiter input.
   *
   * We intentionally disable delimiter-based tokenization so commas can be
   * part of a term value (e.g. "Wynne, Michael"), while Enter still commits.
   *
   * On fields that can't create terms, Enter or blur before the suggestions
   * load, or with no matching suggestion, turned the typed text into a red
   * invalid tag that was then dropped on submit. Keep that text in the input
   * instead: Enter only adds a highlighted suggestion, and blur adds nothing.
   */
  function patchTagifyInstance(instance) {
    if (!instance || instance.__mukurtuPatched) {
      return;
    }

    instance.settings.delimiters = null;
    if (instance.settings.enforceWhitelist) {
      instance.settings.addTagOnBlur = false;
      // Capture on the wrapper so this runs before Tagify's own Enter
      // handlers on the input.
      instance.DOM.scope.addEventListener('keydown', (event) => {
        // Let Enter through when it confirms an IME composition.
        if (event.key !== 'Enter' || event.isComposing
          || event.target !== instance.DOM.input
          || !instance.settings.enforceWhitelist) {
          return;
        }
        // A closed list keeps its last highlight, so only trust an open one.
        const { dropdown } = instance.DOM;
        const active = dropdown.isConnected
          && dropdown.querySelector('.tagify__dropdown__item--active');
        if (!active || active.getAttribute('value') === 'noMatch') {
          event.preventDefault();
          event.stopImmediatePropagation();
          announceBlockedEnter(instance);
        }
      }, true);
    }
    if (instance.DOM.originalInput?.classList.contains('tagify--autocreate')) {
      highlightExactMatchOnly(instance);
    }
    instance.__mukurtuPatched = true;
  }

  /**
   * Compares names the way Tagify does: trimmed and ignoring case.
   */
  function sameName(a, b) {
    return String(a ?? '').trim().toLowerCase() === String(b ?? '').trim().toLowerCase();
  }

  /**
   * Let Enter create a name that is the start of an existing one (#2661).
   *
   * On a field that can create terms, typing "Mary Jane" and pressing Enter
   * added "Mary Jane Smith" instead of creating "Mary Jane", because:
   * - Tagify highlights the first suggestion. Keep the highlight only when
   *   the suggestion matches the typed name. Arrow keys and clicks still
   *   pick any suggestion.
   * - Up Arrow on the first suggestion went round to the last, so there was
   *   no way back to the typed name. Up Arrow on the first suggestion, and
   *   Down Arrow on the last, now clear the highlight.
   * - With nothing highlighted, Enter adds the typed name, but first looks
   *   for a suggestion it matches exactly. The Tagify module stores the
   *   typed text on every suggestion it loads ("input") and searches that
   *   too, so the typed name matched a lone suggestion. Match names only,
   *   which still adds the existing term for a name typed in full.
   */
  function highlightExactMatchOnly(instance) {
    const { show, filterListItems } = instance.dropdown;
    instance.dropdown.show = (...args) => {
      const result = show(...args);
      const typed = args[0] === undefined ? instance.state.inputText : args[0];
      const data = instance.state.ddItemData;
      if (data && !sameName(data.label ?? data.value, typed)) {
        instance.dropdown.highlightOption();
      }
      return result;
    };

    instance.dropdown.filterListItems = (value, options, ...rest) => {
      const { dropdown } = instance.settings;
      const { searchKeys } = dropdown;
      if (!options?.exact || !Array.isArray(searchKeys)) {
        return filterListItems(value, options, ...rest);
      }
      dropdown.searchKeys = searchKeys.filter((key) => key !== 'input');
      try {
        return filterListItems(value, options, ...rest);
      }
      finally {
        dropdown.searchKeys = searchKeys;
      }
    };

    // Capture on the wrapper so this runs before Tagify's own arrow key
    // handler, which listens on the window while the list is open.
    instance.DOM.scope.addEventListener('keydown', (event) => {
      const up = event.key === 'ArrowUp' || event.key === 'Up';
      const down = event.key === 'ArrowDown' || event.key === 'Down';
      const active = instance.state.ddItemElm;
      // Leave arrow keys alone while they choose characters in an IME.
      if ((!up && !down) || event.isComposing || !active
        || !instance.DOM.dropdown.isConnected) {
        return;
      }
      const options = instance.dropdown.getAllSuggestionsRefs();
      if ((up && active === options[0]) || (down && active === options[options.length - 1])) {
        event.preventDefault();
        event.stopImmediatePropagation();
        instance.dropdown.highlightOption();
      }
    }, true);
  }

  /**
   * Find Tagify instances in the current context and patch them.
   */
  function patchTagifyInContext(context) {
    const root = context && context.querySelectorAll ? context : document;
    const inputs = root.querySelectorAll('input, textarea');

    inputs.forEach((element) => {
      // Tagify stores the instance reference on the original element.
      if (element.__tagify) {
        patchTagifyInstance(element.__tagify);
      }
    });
  }

  /**
   * Give the visible Tagify input an accessible name and expose the
   * original field's required/invalid/description state.
   *
   * Tagify hides the original labelled <input> and inserts a new <tags>
   * wrapper containing the actual interactive contenteditable element, but
   * doesn't copy the field's label or ARIA state over to it, leaving it
   * unlabelled (and its required/invalid state unexposed) for screen
   * reader/keyboard users. Copy the original label's text onto it as
   * aria-label, and mirror aria-required/aria-invalid/aria-describedby.
   */
  function labelTagifyInputs(context) {
    const root = context && context.querySelectorAll ? context : document;
    const originals = root.querySelectorAll('input.tagify-widget[id]');

    originals.forEach((original) => {
      const tagsElement = original.previousElementSibling;
      if (!tagsElement || tagsElement.tagName !== 'TAGS') {
        return;
      }
      const textbox = tagsElement.querySelector('.tagify__input');
      if (!textbox) {
        return;
      }

      if (!original.__mukurtuLabelled) {
        const label = document.querySelector(`label[for="${original.id}"]`);
        if (label && !textbox.hasAttribute('aria-label')) {
          textbox.setAttribute('aria-label', label.textContent.trim());
          original.__mukurtuLabelled = true;
        }
      }

      ['aria-required', 'aria-invalid', 'aria-describedby'].forEach((attribute) => {
        if (original.hasAttribute(attribute)) {
          textbox.setAttribute(attribute, original.getAttribute(attribute));
        }
        else {
          textbox.removeAttribute(attribute);
        }
      });
    });
  }

  /**
   * Make the "show all options" click behavior reachable by keyboard.
   *
   * When #suggestions_dropdown=0, Tagify's own JS shows every eligible
   * option on a native click of the input (see tagify.js's
   * handleClickEvent), but never fires for keyboard-only users. Rather than
   * synthesizing that click on focus - which would reopen the full list on
   * every Tab-in or refocus (e.g. after removing a tag), an unexpected
   * context change per WCAG 3.2.1 - use the same key (Down Arrow) the ARIA
   * combobox pattern already uses to open a listbox, so the list only
   * appears in response to an explicit keyboard action.
   */
  function makeShowAllKeyboardReachable(context) {
    const root = context && context.querySelectorAll ? context : document;
    const inputs = root.querySelectorAll('.tagify__input');

    inputs.forEach((input) => {
      if (input.__mukurtuFocusPatched) {
        return;
      }
      const tagsElement = input.closest('tags');
      if (!tagsElement || !tagsElement.classList.contains('tagify-select')) {
        return;
      }
      input.__mukurtuFocusPatched = true;
      input.addEventListener('keydown', (event) => {
        // Only open the list when it is closed. Re-clicking while it is open
        // refetches the suggestions and resets the highlight, so Down Arrow
        // could never move past the first option.
        if ((event.key === 'ArrowDown' || event.key === 'Down')
          && tagsElement.getAttribute('aria-expanded') !== 'true') {
          input.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        }
      });
    });
  }

  /**
   * Announce added/removed tags to screen reader users.
   *
   * The native <select> this widget replaced announced selection changes
   * for free; Tagify's contenteditable widget does not, so add a live
   * region and announce each tag as it's added or removed (WCAG 4.1.3).
   */
  function announceTagChanges(context) {
    const root = context && context.querySelectorAll ? context : document;
    const originals = root.querySelectorAll('input.tagify-widget[id]');

    originals.forEach((original) => {
      if (original.__mukurtuAnnouncePatched || !original.__tagify) {
        return;
      }
      const tagsElement = original.previousElementSibling;
      if (!tagsElement || tagsElement.tagName !== 'TAGS') {
        return;
      }
      original.__mukurtuAnnouncePatched = true;

      const liveRegion = document.createElement('div');
      liveRegion.setAttribute('role', 'status');
      liveRegion.setAttribute('aria-live', 'polite');
      liveRegion.setAttribute('aria-atomic', 'true');
      liveRegion.className = 'visually-hidden';
      // Insert after the original input, not after tagsElement - tagify.css
      // hides the original input via a "tags + input.tagify-widget" adjacent-
      // sibling selector, which stops matching (making the input visible
      // again) if anything is inserted between them.
      original.insertAdjacentElement('afterend', liveRegion);

      const announce = (message) => {
        // Drupal.t() HTML-escapes "@" placeholders, but this is written as
        // textContent, so decode it or "Arts & Crafts" is read as "&amp;".
        const text = new DOMParser().parseFromString(message, 'text/html').documentElement.textContent;
        // Clear first so repeated identical messages still trigger a re-read.
        liveRegion.textContent = '';
        setTimeout(() => { liveRegion.textContent = text; }, 50);
      };
      original.__mukurtuAnnounce = announce;

      original.__tagify.on('add', (event) => {
        const data = event.detail?.data;
        // Entity tags store the entity ID as their value; announce the label.
        const value = data?.label || data?.value;
        if (value) {
          announce(Drupal.t('Added @tag', { '@tag': value }));
        }
      });
      original.__tagify.on('remove', (event) => {
        const data = event.detail?.data;
        // Entity tags store the entity ID as their value; announce the label.
        const value = data?.label || data?.value;
        if (value) {
          announce(Drupal.t('Removed @tag', { '@tag': value }));
        }
      });
    });
  }

  Drupal.behaviors.mukurtuTagifyOverride = {
    attach(context) {
      // Run immediately and again at end-of-tick in case Tagify initializes
      // later in the same attach cycle.
      const runAll = (ctx) => {
        patchTagifyInContext(ctx);
        labelTagifyInputs(ctx);
        makeShowAllKeyboardReachable(ctx);
        announceTagChanges(ctx);
      };
      runAll(context);
      setTimeout(() => runAll(context), 0);
    },
  };
})(Drupal, drupalSettings);
