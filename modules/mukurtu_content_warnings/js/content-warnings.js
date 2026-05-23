/**
 * @file
 * Initialize media content warnings.
 */

((Drupal, once) => {
  Drupal.behaviors.contentWarnings = {
    attach: function (context) {
      once('mukurtu-content-warnings', '.mukurtu-content-warnings', context).forEach((element) => {
        // Hide any windowed embeds (object, iframe) inside the same .media
        // container so they don't paint over the warning overlay.
        this.setEmbedVisibility(element, false);

        element.addEventListener('click', (event) => {
          event.preventDefault();
          this.dismissContentWarning(event.target.closest('.mukurtu-content-warnings'));
        });
      });
    },
    // Show or hide <object>/<iframe> siblings inside the parent .media element.
    setEmbedVisibility: function (warningEl, visible) {
      const mediaEl = warningEl.closest('.media');
      if (!mediaEl) {
        return;
      }
      mediaEl.querySelectorAll('object, iframe').forEach((embed) => {
        embed.style.visibility = visible ? '' : 'hidden';
      });
    },
    dismissContentWarning: function (element) {
      // Exit early if element has a .splide__track--nav parent.
      if (element.closest('.splide__track--nav')) {
        return;
      }

      const mid = element.dataset.mid;
      document.querySelectorAll(`.mukurtu-content-warnings[data-mid="${mid}"]`).forEach((contentWarnings) => {
        contentWarnings.classList.add('dismissed');
        // Restore any hidden embeds now that the warning is dismissed.
        this.setEmbedVisibility(contentWarnings, true);
      });
    },
  };
})(Drupal, once);
