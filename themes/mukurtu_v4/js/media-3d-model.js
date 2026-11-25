/**
 * @file
 * 3D model viewer (model-viewer) settings and controls.
 *
 * Runs before model-viewer, which is a module script and so deferred.
 */
((Drupal, drupalSettings, once) => {
  // Without these, model-viewer downloads its Draco and Basis decoders from
  // gstatic.com, a third-party request the consent manager would have to gate.
  const paths = drupalSettings.mukurtuModelViewer || {};
  self.ModelViewerElement = self.ModelViewerElement || {};
  if (paths.dracoDecoderLocation) {
    self.ModelViewerElement.dracoDecoderLocation = paths.dracoDecoderLocation;
  }
  if (paths.ktx2TranscoderLocation) {
    self.ModelViewerElement.ktx2TranscoderLocation = paths.ktx2TranscoderLocation;
  }

  // Each rotate button does what an arrow key does with the viewer focused,
  // so a press turns by the same step, quick presses add up, and the
  // viewer's own limits apply.
  const KEYS = {
    'rotate-left': 'ArrowLeft',
    'rotate-right': 'ArrowRight',
    'rotate-up': 'ArrowUp',
    'rotate-down': 'ArrowDown',
  };
  // Each press of a zoom button counts as this many mouse wheel steps.
  // model-viewer's own Page Up and Page Down handling doesn't zoom.
  const ZOOM_STEPS = 3;

  const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function hasWebGL() {
    try {
      const canvas = document.createElement('canvas');
      return !!(canvas.getContext('webgl2') || canvas.getContext('webgl'));
    }
    catch {
      return false;
    }
  }

  function showUnsupported(wrapper) {
    wrapper.classList.add('media-3d-model--unsupported');
    const message = wrapper.querySelector('.media-3d-model__unsupported');
    if (message) {
      message.hidden = false;
    }
  }

  /**
   * Moves the camera for one control button press.
   *
   * The buttons are a single-pointer and keyboard alternative to dragging
   * and pinching the model (WCAG 2.5.1, 2.5.7).
   */
  function act(viewer, action) {
    if (action === 'reset') {
      viewer.cameraOrbit = viewer.getAttribute('camera-orbit') || 'auto auto auto';
      viewer.fieldOfView = viewer.getAttribute('field-of-view') || 'auto';
      if (reducedMotion()) {
        viewer.jumpCameraToGoal();
      }
      return;
    }
    if (action === 'zoom-in' || action === 'zoom-out') {
      viewer.zoom(action === 'zoom-in' ? ZOOM_STEPS : -ZOOM_STEPS);
      if (reducedMotion()) {
        viewer.jumpCameraToGoal();
      }
      return;
    }
    const input = viewer.shadowRoot && viewer.shadowRoot.querySelector('.userInput');
    if (!input || !KEYS[action]) {
      return;
    }
    input.dispatchEvent(new KeyboardEvent('keydown', { key: KEYS[action], bubbles: true, composed: true }));
    if (reducedMotion()) {
      viewer.jumpCameraToGoal();
    }
  }

  // Delegated, because GLightbox shows a clone of the hidden copy of the
  // viewer, made after behaviors have run.
  if (once('media-3d-model-controls', 'html').length) {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-model-viewer-action]');
      if (!button) {
        return;
      }
      const viewer = button.closest('.media-3d-model')?.querySelector('model-viewer');
      if (viewer && viewer.shadowRoot) {
        act(viewer, button.dataset.modelViewerAction);
      }
    });

    // model-viewer's load errors don't bubble.
    document.addEventListener('error', (event) => {
      if (event.target instanceof Element && event.target.matches('model-viewer')) {
        const wrapper = event.target.closest('.media-3d-model');
        if (wrapper) {
          showUnsupported(wrapper);
        }
      }
    }, true);
  }

  // The controls start hidden, so they don't show without JavaScript, when
  // they would do nothing. This also runs on the hidden lightbox copy, so
  // GLightbox's clone of it is ready too.
  Drupal.behaviors.media3dModel = {
    attach(context) {
      const supported = hasWebGL();
      once('media-3d-model', '.media-3d-model', context).forEach((wrapper) => {
        if (!supported) {
          showUnsupported(wrapper);
          return;
        }
        const controls = wrapper.querySelector('.media-3d-model__controls');
        if (controls) {
          controls.hidden = false;
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
