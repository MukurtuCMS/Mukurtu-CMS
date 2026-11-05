/**
 * @file
 * Keeps keyboard focus on a facet after it reloads the page.
 *
 * Checking a facet submits the browse form, which reloads the page and sends
 * focus back to the top. This remembers the control that changed and focuses
 * it again once the page loads, so keyboard and screen reader users carry on
 * where they were, and a parent's newly shown nested terms come next in tab
 * order (#2522).
 */
((Drupal, once) => {
  'use strict';

  const KEY = 'mukurtuFacetFocus';

  /**
   * Reads and clears the remembered control, if it was on this page.
   *
   * @return {string|null}
   *   The control's data-drupal-selector.
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
    return saved && saved.path === window.location.pathname ? saved.selector : null;
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
        const match = Array.from(document.querySelectorAll(`form[id^="views-exposed-form-"] [data-drupal-selector="${CSS.escape(selector)}"]`))
          .find((element) => element.offsetParent !== null);
        if (match) {
          match.focus();
        }
      });

      once('mukurtuFacetFocus', 'form[id^="views-exposed-form-"]', context).forEach((form) => {
        form.addEventListener('change', (event) => {
          const target = event.target;
          if (!target.matches('input[type="checkbox"], input[type="radio"], select') || !target.dataset.drupalSelector) {
            return;
          }
          try {
            window.sessionStorage.setItem(KEY, JSON.stringify({
              path: window.location.pathname,
              selector: target.dataset.drupalSelector,
            }));
          }
          catch (e) {
            // Without storage, focus goes back to the top as before.
          }
        });
      });
    },
  };

})(Drupal, once);
