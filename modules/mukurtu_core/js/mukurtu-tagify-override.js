(function (Drupal) {
  /**
   * Update a Tagify instance to stop creating terms on delimiter input.
   *
   * We intentionally disable delimiter-based tokenization so commas can be
   * part of a term value (e.g. "Wynne, Michael"), while Enter still commits.
   */
  function patchTagifyInstance(instance) {
    if (!instance || instance.__mukurtuPatched) {
      return;
    }

    instance.settings.delimiters = null;
    instance.__mukurtuPatched = true;
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
        if (event.key === 'ArrowDown' || event.key === 'Down') {
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
      tagsElement.insertAdjacentElement('afterend', liveRegion);

      const announce = (message) => {
        // Clear first so repeated identical messages still trigger a re-read.
        liveRegion.textContent = '';
        setTimeout(() => { liveRegion.textContent = message; }, 50);
      };

      original.__tagify.on('add', (event) => {
        const value = event.detail?.data?.value;
        if (value) {
          announce(Drupal.t('Added @tag', { '@tag': value }));
        }
      });
      original.__tagify.on('remove', (event) => {
        const value = event.detail?.data?.value;
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
})(Drupal);
