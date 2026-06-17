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
      autoplayVideos: false
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

    lightbox.on('open', () => {
      lightboxOpen = true;
      // GLightbox renders nav buttons with SVG only — no text. Inject accessible
      // names so screen readers can identify the controls.
      const container = document.querySelector('.glightbox-container');
      if (container) {
        container.querySelector('.gprev')?.setAttribute('aria-label', 'Previous');
        container.querySelector('.gnext')?.setAttribute('aria-label', 'Next');
        container.querySelector('.gclose')?.setAttribute('aria-label', 'Close');
      }
    });

    lightbox.on('close', () => { lightboxOpen = false; });

    // GLightbox copies slide HTML (including data-once attributes) so
    // once()-guarded behaviors never attach to the cloned nodes. Delegated
    // capture-phase listeners on document sidestep this entirely.
    function dismissWarningInLightbox(e) {
      if (!lightboxOpen) return;
      const warning = e.target.closest('.mukurtu-content-warnings');
      if (!warning) return;
      e.preventDefault();
      // Dismiss only this specific element (the lightbox clone). Calling
      // dismissContentWarning() queries by data-mid and would clear the
      // carousel warning too -- it should persist until dismissed there.
      if (warning._contentWarningObserver) {
        warning._contentWarningObserver.disconnect();
        delete warning._contentWarningObserver;
      }
      warning.classList.add('dismissed');
      Drupal.behaviors.contentWarnings?.setEmbedVisibility(warning, true);
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
