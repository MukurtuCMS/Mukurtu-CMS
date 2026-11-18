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
    // Tagify moves focus from a tag's remove button straight to the input,
    // so Tab could never get past a tag to the remove buttons or the reorder
    // arrows (#2710). Let them keep focus, and put focus on the input
    // after a removal instead, as Tagify meant to. Only when the removed tag
    // took focus with it, so removals the editor didn't make (on load, or
    // from another widget) don't pull focus into the field.
    instance.settings.focusInputOnRemove = false;
    let lastFocused = null;
    instance.DOM.scope.addEventListener('focusin', (event) => {
      lastFocused = event.target;
    });
    instance.on('remove', () => {
      const active = document.activeElement;
      const lost = !active || active === document.body;
      if (lost && lastFocused && !lastFocused.isConnected) {
        lastFocused = null;
        instance.DOM.input.focus();
      }
    });
    keepFocusOnEscape(instance);
    reopenOnDownArrow(instance);
    removeWrapperExpanded(instance);
    instance.__mukurtuPatched = true;
  }

  /**
   * Whether a Tagify suggestion list is showing.
   *
   * Tagify's own flag stays on while suggestions load, with the list off the
   * page, and for a moment after the list closes.
   */
  function isListShowing(instance) {
    return Boolean(instance.state.dropdown.visible) && instance.DOM.dropdown.isConnected;
  }

  /**
   * Keep focus in the field when Escape is pressed with the list closed.
   *
   * Tagify blurs its input then, so focus fell to the page body and keyboard
   * and screen reader users lost their place (WCAG 2.4.3, #2719). On fields
   * that create terms, the blur also added the typed text as a new term.
   * Its handler calls the input's blur(), so stand in a no-op blur() for the
   * length of the key press, and clear the typed text instead, as the ARIA
   * combobox pattern and the browse search box (#2473) do. The first Escape
   * still just closes the list, and is marked as handled, so a dialog around
   * the field stays open (#2746). jQuery UI dialogs, like the media library,
   * close on any Escape that isn't. With the list closed, the next Escape
   * closes the dialog as usual. A tag being edited still handles Escape as
   * before.
   */
  function keepFocusOnEscape(instance) {
    const { input } = instance.DOM;
    // Set once Escape has closed the list or cleared the field, until the
    // person clicks or presses another key.
    let dismissed = false;
    instance.DOM.scope.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape' && event.key !== 'Esc') {
        dismissed = false;
        return;
      }
      if (event.target !== input || Object.prototype.hasOwnProperty.call(input, 'blur')) {
        return;
      }
      // An Escape that is closing the list leaves the text alone, and one
      // that cancels a character in an input method belongs to it.
      dismissed = true;
      if (isListShowing(instance)) {
        event.preventDefault();
      }
      if (!event.isComposing && !isListShowing(instance) && input.textContent.trim()) {
        instance.input.set.call(instance, '');
      }
      input.blur = () => {};
      setTimeout(() => {
        delete input.blur;
      }, 0);
    }, true);
    instance.DOM.scope.addEventListener('mousedown', () => {
      dismissed = false;
    });
    // Suggestions requested before Escape can still arrive, and the Tagify
    // module opens the list for them. Close it again.
    instance.on('dropdown:show', () => {
      if (dismissed) {
        setTimeout(() => instance.dropdown.hide(), 0);
      }
    });
  }

  /**
   * Let Down Arrow reopen the list straight after it closes.
   *
   * Tagify leaves its "list showing" flag on for 100ms after it closes the
   * list, and Down Arrow only opens a list that flag says is closed. So Down
   * Arrow pressed soon after Escape did nothing (#2748). Fast typists and
   * some assistive technology send keys that quickly. Clear the flag when
   * Down Arrow finds the list gone in that window. Outside it, the flag can
   * also mean suggestions are still loading, so it's left alone then.
   */
  function reopenOnDownArrow(instance) {
    let justHidden = false;
    instance.on('dropdown:hide', () => {
      justHidden = true;
      setTimeout(() => {
        justHidden = false;
      }, 150);
    });
    instance.on('dropdown:show', () => {
      justHidden = false;
    });
    instance.DOM.scope.addEventListener('keydown', (event) => {
      if ((event.key === 'ArrowDown' || event.key === 'Down') && justHidden
        && !instance.DOM.dropdown.isConnected) {
        justHidden = false;
        instance.state.dropdown.visible = false;
      }
    }, true);
  }

  /**
   * Keep aria-expanded off Tagify's <tags> wrapper.
   *
   * Tagify sets it there when the list opens and closes, but the wrapper has
   * no role, so the attribute isn't allowed (WCAG 4.1.2, #2720). The input
   * inside is the combobox and carries its own aria-expanded
   * (tagify-combobox.js).
   */
  function removeWrapperExpanded(instance) {
    const { scope } = instance.DOM;
    scope.removeAttribute('aria-expanded');
    new MutationObserver(() => {
      if (scope.hasAttribute('aria-expanded')) {
        scope.removeAttribute('aria-expanded');
      }
    }).observe(scope, { attributes: true, attributeFilter: ['aria-expanded'] });
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
      // The original input is hidden from screen readers (#2710), and a
      // plain required attribute isn't copied above, so say it here.
      if (original.required) {
        textbox.setAttribute('aria-required', 'true');
      }
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
        // Tagify inserts the wrapper just before the original element, which
        // holds the instance. The wrapper's aria-expanded is removed (#2720).
        const instance = tagsElement.nextElementSibling?.__tagify;
        if ((event.key === 'ArrowDown' || event.key === 'Down')
          && instance && !isListShowing(instance)) {
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

  /**
   * Adds or removes one ID in an element's aria-describedby list.
   */
  function toggleDescribedBy(element, id, add) {
    const ids = (element.getAttribute('aria-describedby') || '')
      .split(/\s+/)
      .filter((value) => value && value !== id);
    if (add) {
      ids.push(id);
    }
    if (ids.length) {
      element.setAttribute('aria-describedby', ids.join(' '));
    }
    else {
      element.removeAttribute('aria-describedby');
    }
  }

  /**
   * Shows the browser's "required" check as an error on the visible field.
   *
   * The original input keeps its required attribute so the browser still
   * blocks an empty submit, but it's hidden, so the browser's own message
   * would point at nothing a screen reader can reach. Show Drupal's message
   * under the field instead, tie it to the visible input, and move focus
   * there when it's the form's first problem. With browser validation
   * turned off (enable_html5_validation = FALSE), this never runs and
   * Drupal's server-side error is mirrored by labelTagifyInputs().
   */
  function showRequiredError(event, original) {
    if (!original.validity.valueMissing) {
      return;
    }
    event.preventDefault();

    let error = original.__mukurtuRequiredError;
    if (!error) {
      error = document.createElement('div');
      error.id = `${original.id}--required-error`;
      error.className = 'form-item__error-message';
      original.__mukurtuRequiredError = error;
    }
    const label = document.querySelector(`label[for="${original.id}"]`);
    // Drupal.t() escapes "@" placeholders; decode them for textContent.
    error.textContent = new DOMParser().parseFromString(
      Drupal.t('@name field is required.', {
        '@name': label ? label.textContent.trim() : '',
      }),
      'text/html',
    ).documentElement.textContent;
    if (!error.isConnected) {
      // After the original input, keeping it right after Tagify's <tags>
      // box, which tagify.css relies on to hide it.
      original.insertAdjacentElement('afterend', error);
    }
    original.setAttribute('aria-invalid', 'true');
    toggleDescribedBy(original, error.id, true);
    labelTagifyInputs(original.parentElement);

    // The browser focuses the first invalid control whose message it
    // shows. This one's message is cancelled, so focus it here, but only
    // if nothing before it in the form is invalid too.
    const first = original.form && Array.from(original.form.elements)
      .find((element) => element.willValidate && !element.validity.valid);
    const textbox = original.previousElementSibling?.querySelector('.tagify__input');
    if (first === original && textbox) {
      setTimeout(() => textbox.focus(), 0);
    }
  }

  /**
   * Clears the required error once the field has a tag.
   */
  function clearRequiredError(original) {
    const error = original.__mukurtuRequiredError;
    if (!error || !error.isConnected) {
      return;
    }
    error.remove();
    original.removeAttribute('aria-invalid');
    toggleDescribedBy(original, error.id, false);
    labelTagifyInputs(original.parentElement);
  }

  /**
   * Hides each original input from assistive technology (#2710).
   *
   * Tagify draws its own field over the original input and hides the
   * original only visually (tagify.css), so screen readers found a second
   * field with the same label and a JSON value. The original stays in the
   * form, out of the tab order, so it's still submitted and its required
   * attribute still stops an empty submit.
   */
  function hideOriginalInputs(context) {
    const root = context && context.querySelectorAll ? context : document;
    root.querySelectorAll('input.tagify-widget[id]').forEach((original) => {
      const tagsElement = original.previousElementSibling;
      if (original.__mukurtuHidden || !original.__tagify
        || !tagsElement || tagsElement.tagName !== 'TAGS') {
        return;
      }
      original.__mukurtuHidden = true;
      original.setAttribute('aria-hidden', 'true');
      original.addEventListener('invalid', (event) => showRequiredError(event, original));
      original.__tagify.on('add', () => clearRequiredError(original));
      // A click on the field's label would focus the hidden original input,
      // where nothing shows and typing goes nowhere. Focus Tagify's input.
      const label = document.querySelector(`label[for="${original.id}"]`);
      label?.addEventListener('click', (event) => {
        const textbox = tagsElement.querySelector('.tagify__input');
        if (textbox) {
          event.preventDefault();
          textbox.focus();
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
        hideOriginalInputs(ctx);
      };
      runAll(context);
      setTimeout(() => runAll(context), 0);
    },
  };
})(Drupal, drupalSettings);
