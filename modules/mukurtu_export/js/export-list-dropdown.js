(function (Drupal) {
  Drupal.behaviors.exportListDropdown = {
    attach: function (context, settings) {
      // Close the export list dropdown when clicking outside of it or pressing
      // Escape. Only attach once on the document.
      if (context !== document && context.ownerDocument !== document) {
        return;
      }
      if (document._exportListDropdownBound) {
        return;
      }
      document._exportListDropdownBound = true;

      // Click-outside: close any open dropdown not containing the click target.
      document.addEventListener('click', function (e) {
        document.querySelectorAll('.export-list-dropdown[open]').forEach(function (details) {
          if (!details.contains(e.target)) {
            details.removeAttribute('open');
          }
        });
      });

      // Escape key: close the open dropdown and return focus to its summary.
      // WCAG 2.1.1 – all functionality must be operable via keyboard.
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
          return;
        }
        document.querySelectorAll('.export-list-dropdown[open]').forEach(function (details) {
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
