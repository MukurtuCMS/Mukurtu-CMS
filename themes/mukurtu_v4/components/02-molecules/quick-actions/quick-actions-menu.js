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
