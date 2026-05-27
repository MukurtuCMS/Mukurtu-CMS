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

    lightbox.on('open', () => { lightboxOpen = true; });
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
