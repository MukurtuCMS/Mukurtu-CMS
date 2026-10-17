/**
 * @file
 * Keeps the media library dialog usable on short screens and after filtering.
 *
 * Height (WCAG 1.4.10): core's dialog.position.js sizes dialogs against the
 * window minus Drupal.displace's top and bottom offsets, and subtracts them
 * once per percentage height option. The media library has two (height: 75%
 * and the default maxHeight: 95%). When the dialog opens, Gin registers its
 * sticky Save/Preview header as a top offset, and at a 320px width that header
 * wraps to 245px tall. At 320x640 the dialog was capped at 167px, leaving the
 * library itself 11px. The dialog is modal and covers that header anyway, so
 * this sizes it against the whole window instead.
 *
 * Focus (WCAG 2.4.3): after "Apply filters", core tries to refocus the button
 * by its data-drupal-selector. The replacement button's selector has a new
 * random suffix, so core falls back to its parent's, "edit-actions", and
 * document.querySelector() finds the node form's own hidden actions first.
 * Focus leaves the dialog, and core's dialog.ajax.js then moves it to the
 * dialog's first focusable element, the "All media" link. This turns core's
 * refocus off for those buttons and focuses the replacement button instead.
 */
((Drupal, once, $) => {
  const modal = '.media-library-widget-modal';
  // Not scoped to the modal: behaviors attach to the library content before
  // jQuery UI wraps it in the dialog, so that ancestor isn't there yet.
  const filterButtons = 'form[data-drupal-selector^="views-exposed-form-media-library"] input[type="submit"]';

  // Set when a filter button is used, so the next attach knows to refocus.
  let refocusFilters = false;

  document.addEventListener('dialogContentResize', (event) => {
    const $content = $(event.target);
    if (!event.target.closest(modal) || !$content.dialog('instance')) {
      return;
    }
    // The same percentages core applies: MediaLibraryUiBuilder's height and
    // dialog.position.js's default maxHeight.
    const windowHeight = window.innerHeight;
    const height = Math.floor(windowHeight * 0.75);
    $content.dialog('option', {
      maxHeight: Math.floor(windowHeight * 0.95),
      // Like core, don't make the dialog taller than its content needs.
      height: $content.dialog('widget').outerHeight() < height ? 'auto' : height,
    });
  });

  Drupal.behaviors.mukurtuMediaLibraryDialog = {
    attach(context) {
      once('mukurtu-media-library-dialog', filterButtons, context).forEach((button) => {
        // Only when the old button took focus with it, so a stale flag (say,
        // from a failed request) never pulls focus from wherever it is now.
        const focusLost = !document.activeElement || document.activeElement === document.body;
        if (refocusFilters && focusLost) {
          button.focus();
        }
        refocusFilters = false;
        button.setAttribute('data-disable-refocus', 'true');
        button.addEventListener('click', () => {
          refocusFilters = true;
        });
      });
    },
  };
})(Drupal, once, jQuery);
