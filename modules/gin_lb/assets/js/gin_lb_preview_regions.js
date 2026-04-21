((Drupal, once) => {
  Drupal.behaviors.ginLbPreviewRegions = {
    attach: () => {
      if (document.getElementById('layout-builder-content-preview') === null) {
        return;
      }
      once('glb-preview-region', 'body').forEach(() => {
        const toolbarPreviewRegion = document.getElementById(
          'glb-toolbar-preview-regions',
        );
        const toolbarPreviewContent = document.getElementById(
          'glb-toolbar-preview-content',
        );
        const formPreviewContent = document.getElementById(
          'layout-builder-content-preview',
        );
        const body = document.getElementsByTagName('body')[0];
        const { contentPreviewId } = formPreviewContent.dataset;
        const isContentPreview =
          JSON.parse(localStorage.getItem(contentPreviewId)) !== false;
        toolbarPreviewContent.checked = formPreviewContent.checked;
        toolbarPreviewRegion.checked = body.classList.contains(
          'glb-preview-regions--enable',
        );

        toolbarPreviewRegion.addEventListener('change', () => {
          if (toolbarPreviewRegion.checked) {
            document
              .querySelector('.layout__region-info')
              .parentNode.classList.add('layout-builder__region');
            document
              .querySelector('body')
              .classList.add('glb-preview-regions--enable');
          } else {
            body.classList.remove('glb-preview-regions--enable');
          }
        });
        toolbarPreviewContent.addEventListener('change', () => {
          if (formPreviewContent.checked !== toolbarPreviewContent.checked) {
            formPreviewContent.click();
          }
        });
        formPreviewContent.addEventListener('change', () => {
          if (formPreviewContent.checked !== toolbarPreviewContent.checked) {
            toolbarPreviewContent.click();
          }
        });

        // Initial state.
        // By default, the checkbox is checked, and it is JS that is unchecking
        // it.
        if (!isContentPreview) {
          toolbarPreviewContent.click();
        }
      });
    },
  };
})(Drupal, once);
