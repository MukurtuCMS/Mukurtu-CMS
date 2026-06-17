/**
 * @file
 * Initialize GLightbox.
 */

/* global GLightbox */

((Drupal, once) => {
  "use strict";

  // GLightbox clones innerHTML, which copies data-once attributes verbatim.
  // Strip the once keys from cloned elements so attachBehaviors() treats them
  // as unprocessed and re-attaches handlers (e.g. content warning dismiss).
  function attachBehaviorsToSlide(slideContent) {
    const warnings = slideContent.querySelectorAll('.mukurtu-content-warnings');
    if (warnings.length) {
      once.remove('mukurtu-content-warnings', warnings);
    }
    Drupal.attachBehaviors(slideContent, drupalSettings);
  }

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
      // GLightbox clones inline content (including data-once attributes) into
      // the lightbox DOM. Strip the once keys so Drupal.attachBehaviors() can
      // re-initialise behaviors (e.g. content warning click handlers) on the
      // clones.
      const activeContent = container?.querySelector('.current .gslide-content');
      if (activeContent) {
        attachBehaviorsToSlide(activeContent);
      }
    });

    // Re-attach behaviors whenever the slide changes so that cloned content
    // (e.g. content warning overlays) gets its event handlers.
    lightbox.on('slide_changed', ({ current }) => {
      const slideContent = current.slideNode?.querySelector('.gslide-content');
      if (slideContent) {
        attachBehaviorsToSlide(slideContent);
      }
    });

    lightbox.on('close', () => { lightboxOpen = false; });
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
