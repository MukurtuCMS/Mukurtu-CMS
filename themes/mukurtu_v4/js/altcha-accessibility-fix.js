/**
 * @file
 * Fixes an accessibility bug in the third-party ALTCHA widget's own markup
 * (modules/contrib/altcha), which we don't control: its decorative
 * "altcha.org" logo link is aria-hidden="true" but has no tabindex="-1", so
 * it remains keyboard-focusable -- a screen reader user can tab to a link
 * the accessibility tree says doesn't exist. A real, accessible link to the
 * same destination already exists in the widget's footer text, so this
 * logo link is meant to be fully hidden, not exposed.
 */

((Drupal, once) => {

  Drupal.behaviors.altchaAccessibilityFix = {
    attach(context) {
      once('altchaA11yFix', 'altcha-widget', context).forEach((widget) => {
        // The widget builds this link asynchronously after the custom
        // element upgrades, and may rebuild it later (e.g. on verification
        // state changes), so keep watching rather than fixing it once.
        const fix = () => {
          widget.querySelectorAll('.altcha-logo[aria-hidden="true"]').forEach((logo) => {
            logo.tabIndex = -1;
          });
        };
        fix();
        // attributes/attributeFilter as a defensive measure: the current
        // ALTCHA build bakes aria-hidden="true" into the link's initial
        // markup (so childList alone is sufficient today), but this also
        // catches it if a future version sets/toggles the attribute after
        // insertion instead.
        new MutationObserver(fix).observe(widget, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ['aria-hidden'],
        });
      });
    },
  };

})(Drupal, once);
