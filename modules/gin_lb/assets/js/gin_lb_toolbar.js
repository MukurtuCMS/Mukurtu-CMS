((Drupal, once) => {
  Drupal.behaviors.ginLbToolbar = {
    attach: (context) => {
      once('glb-primary-save', '.js-glb-primary-save', context).forEach(
        (item) => {
          item.addEventListener('click', () => {
            document
              .querySelector(
                '#gin_sidebar .form-actions .js-glb-button--primary',
              )
              .click();
          });
        },
      );
    },
  };
})(Drupal, once);
