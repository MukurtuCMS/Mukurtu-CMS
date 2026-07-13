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
   * Give the visible Tagify input an accessible name.
   *
   * Tagify hides the original labelled <input> and inserts a new <tags>
   * wrapper containing the actual interactive contenteditable element, but
   * doesn't copy the field's label over to it, leaving it unlabelled for
   * screen reader/keyboard users. Copy the original label's text onto it as
   * aria-label.
   */
  function labelTagifyInputs(context) {
    const root = context && context.querySelectorAll ? context : document;
    const originals = root.querySelectorAll('input.tagify-widget[id]');

    originals.forEach((original) => {
      if (original.__mukurtuLabelled) {
        return;
      }
      const tagsElement = original.previousElementSibling;
      if (!tagsElement || tagsElement.tagName !== 'TAGS') {
        return;
      }
      const textbox = tagsElement.querySelector('.tagify__input');
      const label = document.querySelector(`label[for="${original.id}"]`);
      if (!textbox || !label || textbox.hasAttribute('aria-label')) {
        return;
      }
      textbox.setAttribute('aria-label', label.textContent.trim());
      original.__mukurtuLabelled = true;
    });
  }

  /**
   * Make the "show all options" click behavior reachable by keyboard.
   *
   * When #suggestions_dropdown=0, Tagify's own JS shows every eligible
   * option on a native click of the input (see tagify.js's
   * handleClickEvent), but never fires for keyboard-only Tab focus.
   * Synthesize the same click event on focus so keyboard users get the
   * same "browse everything" experience as a mouse click.
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
      input.addEventListener('focus', () => {
        input.dispatchEvent(new MouseEvent('click', { bubbles: true }));
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
      };
      runAll(context);
      setTimeout(() => runAll(context), 0);
    },
  };
})(Drupal);
