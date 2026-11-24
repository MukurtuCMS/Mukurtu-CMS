(function (Drupal) {
  Drupal.behaviors.quickActionsMenu = {
    attach: function (context) {
      // Close the quick-actions overflow menu when clicking outside of it or
      // pressing Escape. Only attach once on the document.
      if (context !== document && context.ownerDocument !== document) {
        return;
      }
      if (document._quickActionsMenuBound) {
        return;
      }
      document._quickActionsMenuBound = true;

      // Click-outside: close any open menu not containing the click target.
      document.addEventListener('click', function (e) {
        document.querySelectorAll('.quick-actions__overflow[open]').forEach(function (details) {
          if (!details.contains(e.target)) {
            details.removeAttribute('open');
          }
        });
      });

      // Focus moving outside a menu: close it, or Tab past its last link
      // would land on content the open menu covers (WCAG 2.4.11). Not when
      // focus goes nowhere (relatedTarget null): Safari and Firefox on macOS
      // don't focus a clicked link, so closing then would swallow the click.
      document.addEventListener('focusout', function (e) {
        var details = e.target.closest && e.target.closest('.quick-actions__overflow[open]');
        if (details && e.relatedTarget && !details.contains(e.relatedTarget)) {
          details.removeAttribute('open');
        }
      });

      // Escape key: close the open menu and return focus to its summary.
      // WCAG 2.1.1 - all functionality must be operable via keyboard.
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
          return;
        }
        document.querySelectorAll('.quick-actions__overflow[open]').forEach(function (details) {
          details.removeAttribute('open');
          var summary = details.querySelector('summary');
          if (summary) {
            summary.focus();
          }
        });
      });
    }
  };
})(Drupal);
