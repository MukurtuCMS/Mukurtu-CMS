/**
 * @file
 * Initialize media content warnings.
 */

((Drupal, once) => {
  Drupal.behaviors.contentWarnings = {
    attach: function (context) {

      once('mukurtu-content-warnings', '.mukurtu-content-warnings', context).forEach((element) => {
        element.addEventListener('click', (event) => {
          event.preventDefault();
          this.dismissContentWarning(event.target.closest('.mukurtu-content-warnings'));
        });
      });
    },
    dismissContentWarning: function (element) {
      // Exit early if element has a .splide__track--nav parent.
      if (element.closest('.splide__track--nav')) {
        return;
      }

      const mid = element.dataset.mid;
      // Don't dismiss if whats clicked is the splide track nav item.
      document.querySelectorAll(`.mukurtu-content-warnings[data-mid="${mid}"]`).forEach((contentWarnings) => contentWarnings.classList.add('dismissed'));
    },
  };
})(Drupal, once);
