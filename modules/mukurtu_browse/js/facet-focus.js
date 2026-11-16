/**
 * @file
 * Keeps keyboard focus on a browse control after it reloads the page.
 *
 * Filters, sort, the Display options toggles and the search box reload the
 * page as soon as they change (Better Exposed Filters auto-submit), which
 * sends focus back to the top. This remembers the control that changed and
 * focuses it again once the page loads, so keyboard and screen reader users
 * carry on where they were, and a parent's newly shown nested terms come
 * next in tab order (#2522, #2619).
 */
((Drupal, once) => {
  'use strict';

  const KEY = 'mukurtuFacetFocus';

  // A reload follows within moments of a change. Anything older was left by
  // a visit that never reloaded, such as leaving the page mid-submit, and
  // mustn't move focus on some later visit.
  const MAX_AGE_MS = 10000;

  const FORMS = 'form[id^="views-exposed-form-"]';

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
        // small screens, so focus the copy that's visible.
        const match = Array.from(document.querySelectorAll(`${FORMS} ${selector}`))
          .find((element) => element.offsetParent !== null);
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
        // Capture, so a control's own handler can't stop this seeing the
        // change. The Display options toggles stop it bubbling.
        form.addEventListener('change', (event) => {
          if (event.target.matches('input[type="checkbox"], input[type="radio"], select')) {
            save(event.target);
          }
        }, true);
        // The search box submits while you type, or on Enter.
        form.addEventListener('submit', () => {
          const active = document.activeElement;
          if (active && form.contains(active)
            && active.matches('input[type="text"], input[type="search"]')) {
            save(active);
          }
        });
      });
    },
  };

})(Drupal, once);
