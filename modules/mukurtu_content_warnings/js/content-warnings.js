/**
 * @file
 * Initialize media content warnings.
 */

((Drupal, once) => {
  Drupal.behaviors.contentWarnings = {
    attach: function (context) {

      once("content-warnings", ".mukurtu-content-warning", context).forEach((e) => {
        e.addEventListener("click", () => {
          e.classList.remove("mukurtu-content-warning");
          // Todo: prob shouldn't rely on the [1] here... need to get this value more reliably
          e.children[1].classList.add("cleared");
        });
      });
    },
  };
})(Drupal, once);
