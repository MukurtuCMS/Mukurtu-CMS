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
      loop: true
    });

    // When a video slide is active, stop arrow key events from bubbling up
    // to Glightbox's document-level keyboard handler. The video element still
    // receives the event (for seeking) since we stop propagation in the
    // bubble phase after the target has already handled it.
    let videoBlockers = [];

    function blockVideoArrows(e) {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        e.stopPropagation();
      }
    }

    function attachVideoBlockers() {
      videoBlockers.forEach(({el, fn}) => el.removeEventListener('keydown', fn));
      videoBlockers = [];
      const activeSlide = lightbox.getActiveSlide();
      if (!activeSlide) return;
      activeSlide.querySelectorAll('video').forEach(video => {
        video.addEventListener('keydown', blockVideoArrows);
        videoBlockers.push({el: video, fn: blockVideoArrows});
      });
    }

    lightbox.on('open', () => setTimeout(attachVideoBlockers, 200));
    lightbox.on('slide_changed', () => setTimeout(attachVideoBlockers, 200));
    lightbox.on('close', () => {
      videoBlockers.forEach(({el, fn}) => el.removeEventListener('keydown', fn));
      videoBlockers = [];
    });
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
