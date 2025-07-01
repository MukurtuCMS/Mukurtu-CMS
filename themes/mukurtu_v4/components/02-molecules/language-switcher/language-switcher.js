(function (Drupal, once) {
  "use strict";

  /**
   * Ensure the language-switcher dropdown never overflows the viewport.
   */
  Drupal.behaviors.languageSwitcherPosition = {
    attach(context) {
      once("language-switcher", ".language-switcher", context).forEach((wrapper) => {
        const dropdown = wrapper.querySelector(".links");
        if (!dropdown) return;

        const details = wrapper.closest("details") || wrapper;

        const adjust = () => {

          if (details instanceof HTMLDetailsElement && !details.open) return;

          const position = dropdown.getBoundingClientRect();
          // If the dropdown is overflowing to the right, move it to the left.
          if(position.right > window.innerWidth) {
            dropdown.style.left = "auto";
            dropdown.style.right = "0";
            dropdown.style.transform = "none";
          }
        };

        details.addEventListener("toggle", adjust);
        window.addEventListener("resize", adjust);
        window.addEventListener("orientationchange", adjust);
      });
    },
  };
})(Drupal, once);
