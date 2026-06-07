(function (Drupal) {
  Drupal.behaviors.exportListDropdown = {
    attach: function (context, settings) {
      // Close the export list dropdown when clicking outside of it.
      // Only attach once on the document.
      if (context !== document && context.ownerDocument !== document) {
        return;
      }
      if (document._exportListDropdownBound) {
        return;
      }
      document._exportListDropdownBound = true;
      document.addEventListener('click', function (e) {
        document.querySelectorAll('.export-list-dropdown[open]').forEach(function (details) {
          if (!details.contains(e.target)) {
            details.removeAttribute('open');
          }
        });
      });
    }
  };
})(Drupal);
