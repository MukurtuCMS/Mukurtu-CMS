/**
 * @file
 * Captures a 3D model's thumbnail from the viewer on its add and edit forms.
 *
 * The capture, a PNG data URL, goes in the form's hidden thumbnail_capture
 * field, and becomes the thumbnail when the form is saved.
 */
((Drupal, once) => {
  /**
   * Shows a capture and says what will happen to it.
   */
  function show(wrapper, dataUrl) {
    const preview = wrapper.querySelector('.mukurtu-3d-capture__preview');
    const status = wrapper.querySelector('.mukurtu-3d-capture__status');
    preview.src = dataUrl;
    preview.hidden = false;
    // Emptied first, so a second capture is announced too.
    status.textContent = '';
    setTimeout(() => {
      status.textContent = Drupal.t('The thumbnail will be this view when you save.');
    }, 100);
  }

  function readAsDataUrl(blob) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => reject(reader.error);
      reader.readAsDataURL(blob);
    });
  }

  // model-viewer lowers its resolution when the device is slow, and a
  // capture then holds only part of the view. Full resolution here, where
  // the view is captured.
  customElements.whenDefined('model-viewer').then(() => {
    customElements.get('model-viewer').minimumRenderScale = 1;
  });

  Drupal.behaviors.mukurtu3dModelThumbnailCapture = {
    attach(context) {
      once('mukurtu-3d-capture', '[data-mukurtu-3d-capture]', context).forEach((wrapper) => {
        const form = wrapper.closest('form');
        const input = form && form.querySelector('[data-mukurtu-3d-capture-value]');
        const viewer = wrapper.querySelector('model-viewer');
        const actions = wrapper.querySelector('.mukurtu-3d-capture__actions');
        const button = wrapper.querySelector('[data-mukurtu-3d-capture-button]');
        if (!input || !viewer || !actions || !button) {
          return;
        }

        // A capture kept when the form is shown again, for example with an
        // error on another field, is shown again.
        if (input.value) {
          show(wrapper, input.value);
        }

        // The button only works once the model has loaded.
        const ready = () => {
          actions.hidden = false;
        };
        if (viewer.loaded) {
          ready();
        }
        else {
          viewer.addEventListener('load', ready, { once: true });
        }

        button.addEventListener('click', async () => {
          try {
            const blob = await viewer.toBlob({ mimeType: 'image/png' });
            const dataUrl = await readAsDataUrl(blob);
            input.value = dataUrl;
            show(wrapper, dataUrl);
          }
          catch {
            const status = wrapper.querySelector('.mukurtu-3d-capture__status');
            status.textContent = Drupal.t("The captured thumbnail couldn't be saved. Try again, or upload a thumbnail.");
          }
        });
      });
    },

    // Removing or replacing the model removes its viewer, and with it any
    // capture of it.
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('mukurtu-3d-capture', '[data-mukurtu-3d-capture]', context).forEach((wrapper) => {
        const input = wrapper.closest('form')?.querySelector('[data-mukurtu-3d-capture-value]');
        if (input) {
          input.value = '';
        }
      });
    },
  };
})(Drupal, once);
