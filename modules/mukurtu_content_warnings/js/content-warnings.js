/**
 * @file
 * Initialize media content warnings.
 */

((Drupal, once) => {
  Drupal.behaviors.contentWarnings = {
    attach: function (context) {

      once('mukurtu-content-warnings', '.mukurtu-content-warnings', context).forEach((element) => {
        element.addEventListener('click', (event) => {
          this.dismissContentWarning(event.target);
        });
      });
    },
    dismissContentWarning: function (element) {
      const mid = element.dataset.mid;
      document.querySelectorAll(`.mukurtu-content-warnings[data-mid="${mid}"]`).forEach((contentWarnings) => contentWarnings.classList.add('dismissed'));
    },
  };
})(Drupal, once);
