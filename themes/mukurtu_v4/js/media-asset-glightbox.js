/**
 * @file
 * Initialize GLightbox.
 */

/* global GLightbox */

((Drupal, once) => {
  "use strict";

  function initGLightbox() {
    const lightbox = new GLightbox({
      selector: 'a.media-asset--link',
      loop: true,
      autoplayVideos: false,
      width: '92vw',
      height: '92vh',
    });

    // When a video or remote-video (iframe) slide is active, block arrow keys
    // from reaching GLightbox's document-level bubble-phase handler. We use
    // the capture phase so the event is intercepted before it bubbles at all.
    // Cross-origin iframes prevent attaching listeners inside them, so a
    // capture-phase document listener is the only reliable approach for both
    // native <video> and <iframe> embeds.
    let lightboxOpen = false;

    function blockArrowsOnMediaSlide(e) {
      if (!lightboxOpen) return;
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      // Only block when a video/iframe element actually has focus.
      // For native <video>, document.activeElement is the video itself.
      // For cross-origin <iframe>, document.activeElement is the iframe.
      const focused = document.activeElement;
      if (focused && (focused.tagName === 'VIDEO' || focused.tagName === 'IFRAME')) {
        e.stopPropagation();
      }
    }

    document.addEventListener('keydown', blockArrowsOnMediaSlide, true);

    // GLightbox renders nav buttons with SVG only — no text. Inject accessible
    // names so screen readers can identify the controls. Called on both open
    // and slide_changed because some GLightbox builds re-render nav buttons
    // between slides, which would clear previously injected labels.
    function injectLightboxLabels() {
      const container = document.querySelector('.glightbox-container');
      if (!container) return;
      container.querySelector('.gprev')?.setAttribute('aria-label', 'Previous');
      container.querySelector('.gnext')?.setAttribute('aria-label', 'Next');
      container.querySelector('.gclose')?.setAttribute('aria-label', 'Close');
    }

    lightbox.on('open', () => {
      lightboxOpen = true;
      injectLightboxLabels();
    });

    lightbox.on('slide_changed', injectLightboxLabels);

    lightbox.on('close', () => { lightboxOpen = false; });

    // GLightbox copies slide HTML (including data-once attributes) so
    // once()-guarded behaviors never attach to the cloned nodes. Delegated
    // capture-phase listeners on document sidestep this entirely.
    function dismissWarningInLightbox(e) {
      if (!lightboxOpen) return;
      const warning = e.target.closest('.mukurtu-content-warnings');
      if (!warning) return;

      // Only handle warnings inside the lightbox itself. Carousel warnings
      // behind the overlay must not be dismissed by lightbox interactions.
      const lightboxContainer = document.querySelector('.glightbox-container');
      if (!lightboxContainer?.contains(warning)) return;

      // Stop propagation so content-warnings.js's bubble-phase listener does
      // not also call dismissContentWarning() and create a double-dismiss.
      e.stopPropagation();
      e.preventDefault();

      // Dismiss the lightbox clone AND all carousel/inline copies for this
      // media item. The user explicitly acknowledged the warning in the lightbox.
      Drupal.behaviors.contentWarnings?.dismissContentWarning(warning);
    }
    document.addEventListener('click', dismissWarningInLightbox, true);
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      dismissWarningInLightbox(e);
    }, true);
  }

  // Drupal behavior
  Drupal.behaviors.mediaAssetGLightbox = {
    attach(context) {
      // Initialize GLightbox globally once
      once("mediaGLightboxGlobal", "body", context).forEach(() => {
        // Small delay to ensure media is fully rendered
        setTimeout(() => {
          initGLightbox();
        }, 100);
      });

    }
  };

})(Drupal, once);
