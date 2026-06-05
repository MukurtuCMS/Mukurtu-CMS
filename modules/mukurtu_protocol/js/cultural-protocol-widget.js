(function (Drupal, once) {
  'use strict';

  /**
   * Reset protocol checkboxes to their server-sent default state.
   *
   * Browsers (especially Firefox) restore previously-checked checkbox states
   * via form autocomplete/restoration, even across AJAX updates. This behavior
   * runs after each DOM attachment and explicitly enforces the server-sent
   * default value using the data-protocol-checkbox-default attribute.
   *
   * setTimeout(0) defers execution until after synchronous browser form
   * restoration completes, ensuring our reset takes effect last.
   */
  Drupal.behaviors.mukurtuProtocolCheckboxReset = {
    attach: function (context) {
      var elements = once(
        'protocol-checkbox-reset',
        '[data-protocol-checkbox-default]',
        context
      );
      if (elements.length) {
        setTimeout(function () {
          elements.forEach(function (el) {
            el.checked = el.getAttribute('data-protocol-checkbox-default') === '1';
          });
        }, 0);
      }
    }
  };

}(Drupal, once));
