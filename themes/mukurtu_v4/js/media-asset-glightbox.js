/**
 * @file
 * Initialize GLightbox.
 */

/* global GLightbox */

((Drupal, once) => {
  "use strict";

  const ZOOM_SELECTOR = '.ginlined-content .media--image img';

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function panBounds(img) {
    // The visible viewport to pan within is the outer .gslide-inline box
    // (fixed by max-width/max-height + overflow: hidden, see _glightbox.scss).
    // .ginlined-content itself is NOT a stable reference: it's auto-sized to
    // fit its content, so once the image grows to native size on zoom, that
    // element grows right along with it, making pan bounds computed against
    // it collapse toward zero and leaving parts of the image unreachable.
    const container = img.closest('.gslide-inline');
    const cRect = container.getBoundingClientRect();
    const iRect = img.getBoundingClientRect();
    // The image's untransformed position isn't flush with the container's
    // edges - .media--image and .ginlined-content each add their own
    // padding/centering, and the exact offset isn't worth hardcoding here.
    // Recover it by subtracting the currently-applied pan (translate3d only
    // shifts position, never size) from the current rect, then measure how
    // far the image can move in each direction before its edge would pass
    // the container's edge and start exposing blank space.
    const x = parseFloat(img.dataset.zoomX) || 0;
    const y = parseFloat(img.dataset.zoomY) || 0;
    const naturalLeft = iRect.left - x;
    const naturalTop = iRect.top - y;
    return {
      minX: cRect.right - (naturalLeft + iRect.width),
      maxX: cRect.left - naturalLeft,
      minY: cRect.bottom - (naturalTop + iRect.height),
      maxY: cRect.top - naturalTop,
    };
  }

  function setPan(img, x, y) {
    img.style.transform = `translate3d(${x}px, ${y}px, 0)`;
    img.dataset.zoomX = x;
    img.dataset.zoomY = y;
  }

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
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
      const focused = document.activeElement;
      if (!focused) return;

      // A zoomed-in image handles its own arrow-key panning below; stop the
      // event here (capture phase, before GLightbox's own bubble-phase
      // handler sees it) so panning doesn't also change slides.
      if (focused.matches?.(ZOOM_SELECTOR) && focused.classList.contains('media-asset--zoomed')) {
        e.preventDefault();
        e.stopPropagation();
        const step = 40;
        let dx = 0;
        let dy = 0;
        if (e.key === 'ArrowLeft') dx = step;
        else if (e.key === 'ArrowRight') dx = -step;
        else if (e.key === 'ArrowUp') dy = step;
        else if (e.key === 'ArrowDown') dy = -step;
        const bounds = panBounds(focused);
        const x = clamp((parseFloat(focused.dataset.zoomX) || 0) + dx, bounds.minX, bounds.maxX);
        const y = clamp((parseFloat(focused.dataset.zoomY) || 0) + dy, bounds.minY, bounds.maxY);
        setPan(focused, x, y);
        return;
      }

      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      // Only block when a video/iframe element actually has focus.
      // For native <video>, document.activeElement is the video itself.
      // For cross-origin <iframe>, document.activeElement is the iframe.
      if (focused.tagName === 'VIDEO' || focused.tagName === 'IFRAME') {
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

    setupImageZoom(lightbox);
  }

  // Click-to-zoom for lightbox images.
  //
  // GLightbox's own zoom only attaches to native `type:"image"` slides.
  // Mukurtu uses `data-type="inline"` for every media type so the content
  // warning overlay can be shared across images/audio/video/documents, so
  // GLightbox never wires its zoom up here. This reimplements click-to-zoom
  // to native size; while zoomed, moving the mouse anywhere over the slide
  // pans proportionally to cursor position (no need to hold a button down).
  // Disabled below the same 768px breakpoint GLightbox itself uses for zoom,
  // since hover-to-pan has no touch equivalent and pinch-zoom isn't
  // implemented.
  function setupImageZoom(lightbox) {
    const MOBILE_BREAKPOINT = 768;
    let zoomed = null;

    function zoomIn(img) {
      if (window.innerWidth <= MOBILE_BREAKPOINT) return;
      if (zoomed && zoomed !== img) zoomOut(zoomed);
      img.classList.add('media-asset--zoomed');
      setPan(img, 0, 0);
      img.setAttribute('aria-pressed', 'true');
      zoomed = img;
    }

    function zoomOut(img) {
      img.classList.remove('media-asset--zoomed');
      img.style.transform = '';
      delete img.dataset.zoomX;
      delete img.dataset.zoomY;
      img.setAttribute('aria-pressed', 'false');
      if (zoomed === img) zoomed = null;
    }

    function toggleZoom(img) {
      if (img.classList.contains('media-asset--zoomed')) {
        zoomOut(img);
      } else {
        zoomIn(img);
      }
    }

    document.addEventListener('click', (e) => {
      const img = e.target.closest(ZOOM_SELECTOR);
      if (!img) return;
      toggleZoom(img);
    });

    // Arrow-key panning while zoomed is handled by blockArrowsOnMediaSlide
    // above (capture phase, so it can also stop GLightbox's own bubble-phase
    // slide navigation from firing on the same arrow-key press).
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const img = document.activeElement;
      if (!img || typeof img.matches !== 'function' || !img.matches(ZOOM_SELECTOR)) return;
      e.preventDefault();
      toggleZoom(img);
    });

    document.addEventListener('mousemove', (e) => {
      if (!zoomed) return;
      // Map cursor position within the slide's visible viewport to a pan
      // offset: cursor at the left/top edge aligns the image's left/top edge
      // with the container (bounds.maxX/maxY), cursor at the right/bottom
      // edge aligns the image's right/bottom edge (bounds.minX/minY).
      const container = zoomed.closest('.gslide-inline');
      const rect = container.getBoundingClientRect();
      const fracX = clamp((e.clientX - rect.left) / rect.width, 0, 1);
      const fracY = clamp((e.clientY - rect.top) / rect.height, 0, 1);
      const bounds = panBounds(zoomed);
      setPan(
        zoomed,
        bounds.maxX - fracX * (bounds.maxX - bounds.minX),
        bounds.maxY - fracY * (bounds.maxY - bounds.minY)
      );
    });

    function resetZoom() {
      if (zoomed) zoomOut(zoomed);
    }
    lightbox.on('slide_changed', resetZoom);
    lightbox.on('close', resetZoom);
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

      // Mark the source images as zoomable so GLightbox's cloneNode(true)
      // carries these attributes into the lightbox clone automatically.
      once('mediaAssetZoomable', '.media-asset--glightbox-inline .media--image img', context).forEach((img) => {
        img.setAttribute('tabindex', '0');
        img.setAttribute('role', 'button');
        img.setAttribute('aria-pressed', 'false');
        img.setAttribute('aria-label', 'Zoom image');
      });
    }
  };

})(Drupal, once);
