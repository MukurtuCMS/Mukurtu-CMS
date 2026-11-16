/**
 * @file
 * Keeps keyboard focus where it was after the browse filters reload the page.
 *
 * The filters, sort, Display options and search box wait for Apply (#2619).
 * Applying reloads the page, which sends focus back to the top, a long way
 * from the filters. This remembers what submitted the form (the Apply
 * button, Reset, or the search box on Enter) and focuses it again once the
 * page loads, so people can carry on refining. Shift+Tab from Apply reaches
 * the filters, including a parent's newly shown nested terms (#2522).
 */
((Drupal, once) => {
  'use strict';

  const KEY = 'mukurtuFacetFocus';

  // A reload follows within moments of a change. Anything older was left by
  // a visit that never reloaded, such as leaving the page mid-submit, and
  // mustn't move focus on some later visit.
  const MAX_AGE_MS = 10000;

  const FORMS = 'form[id^="views-exposed-form-"]';

  // The form's Apply button.
  const APPLY = '[data-drupal-selector^="edit-submit-"]';

  /**
   * A selector that finds the same control on the reloaded page.
   *
   * @param {HTMLElement} control
   *   The control.
   *
   * @return {string|null}
   *   The selector, or null if the control can't be found again.
   */
  function selectorFor(control) {
    if (control.dataset.drupalSelector) {
      return `[data-drupal-selector="${CSS.escape(control.dataset.drupalSelector)}"]`;
    }
    if (control.name) {
      return `[name="${CSS.escape(control.name)}"]`;
    }
    // The Display options toggles have only the URL parameter they set.
    if (control.dataset.param) {
      return `[data-param="${CSS.escape(control.dataset.param)}"]`;
    }
    return null;
  }

  /**
   * Remembers a control, to focus it again after the reload.
   *
   * @param {HTMLElement} control
   *   The control.
   */
  function save(control) {
    const selector = selectorFor(control);
    if (!selector) {
      return;
    }
    try {
      window.sessionStorage.setItem(KEY, JSON.stringify({
        path: window.location.pathname,
        selector,
        time: Date.now(),
      }));
    }
    catch (e) {
      // Without storage, focus goes back to the top as before.
    }
  }

  /**
   * Reads and clears the remembered control, if it's recent and on this page.
   *
   * @return {string|null}
   *   The control's selector.
   */
  function takeSaved() {
    let saved = null;
    try {
      saved = JSON.parse(window.sessionStorage.getItem(KEY) || 'null');
      window.sessionStorage.removeItem(KEY);
    }
    catch (e) {
      return null;
    }
    if (!saved || saved.path !== window.location.pathname
      || !(Date.now() - saved.time < MAX_AGE_MS)) {
      return null;
    }
    return saved.selector;
  }

  Drupal.behaviors.mukurtuFacetFocus = {
    attach(context) {
      once('mukurtuFacetFocusRestore', 'html', context).forEach(() => {
        const selector = takeSaved();
        if (!selector) {
          return;
        }
        // The form can be rendered more than once, for example in a drawer on
        // small screens, so focus the copy that's visible. Reset isn't shown
        // once nothing is filtered, so it falls back to Apply.
        const visible = (query) => Array.from(document.querySelectorAll(query))
          .find((element) => element.offsetParent !== null);
        const match = visible(`${FORMS} ${selector}`) || visible(`${FORMS} ${APPLY}`);
        if (!match) {
          return;
        }
        match.focus();
        // Put the cursor after the search words, ready to type more.
        if (typeof match.setSelectionRange === 'function' && typeof match.value === 'string') {
          try {
            match.setSelectionRange(match.value.length, match.value.length);
          }
          catch (e) {
            // Not a text field.
          }
        }
      });

      once('mukurtuFacetFocus', FORMS, context).forEach((form) => {
        form.addEventListener('submit', (event) => {
          // The search box on Enter, or the button that was pressed. Safari
          // doesn't focus a button on click, so fall back to the submitter.
          const active = document.activeElement;
          const control = active && active !== form && form.contains(active)
            && active.matches('input[type="text"], input[type="search"], input[type="submit"], button')
            ? active
            : event.submitter;
          if (control && form.contains(control)) {
            save(control);
          }
        });
      });

      // The dictionary's Reset is a link beside Apply rather than a button,
      // so it lands back on Apply.
      once('mukurtuFacetFocusReset', '.browse-links .filter-reset', context).forEach((link) => {
        link.addEventListener('click', () => {
          const apply = link.parentElement.querySelector(APPLY);
          if (apply) {
            save(apply);
          }
        });
      });
    },
  };

})(Drupal, once);
