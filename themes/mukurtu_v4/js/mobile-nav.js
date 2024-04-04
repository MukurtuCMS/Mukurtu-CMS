/**
 * @file
 * Sets up show/hide interactions for mobile menu including focus trap.
 */
// import { createFocusTrap } from "../node_modules/focus-trap/dist/focus-trap.umd.min.js";

// export default (Drupal, once) => {
((Drupal, once) => {
  let mainMenu;
  let mobileNavButton;
  let mobileNavButtonState;
  // const menuFocusTrap = require('focus-trap');

  /**
   * Functionality of mobile menu.
   */
  function mobileMenuControl(e) {
    e.stopImmediatePropagation();

    mobileNavButtonState = this.getAttribute("aria-expanded");

    // If mobile nav btn is closed, open it.
    // Else, close it.
    if (mobileNavButtonState == "false") {
      // menuFocusTrap.activate();
      this.setAttribute("aria-expanded", true);
      mainMenu.classList.add("is-visible");
      document.body.classList.add("is-fixed");
      document.addEventListener("click", clickOutside);

    } else {
      // menuFocusTrap.deactivate();
      this.setAttribute("aria-expanded", false);
      mainMenu.classList.remove("is-visible");
      document.body.classList.remove("is-fixed");
      document.removeEventListener("click", clickOutside);
    }
  }

  /**
   * Ensure mobile menu closes if click outside.
   */
  function clickOutside(event) {
    if (!event.target.closest(".main-navigation")) {
      mobileNavButton.setAttribute("aria-expanded", false);
      mainMenu.classList.remove("is-visible");
      document.body.classList.remove("is-fixed");
      document.removeEventListener("click", clickOutside);
    }
  }

  /**
   * Initialize event listeners and focus trap.
   */
  function init(el) {
    console.log("we have liftoff");
    mobileNavButton = el.querySelector(".mobile-nav-button button");
    console.log(mobileNavButton);

    mainMenu = el.querySelector(".main-navigation");
    
    // menuFocusTrap = createFocusTrap([".mobile-nav-button", ".main-navigation"], {
    //   clickOutsideDeactivates: true,
    // });

    mobileNavButton.addEventListener("click", mobileMenuControl);
    document.addEventListener("keyup", (e) => {
      if (e.key === "Escape") {
        // menuFocusTrap.deactivate();
        mobileNavButton.setAttribute("aria-expanded", false);
        mainMenu.classList.remove("is-visible");
        document.body.classList.remove("is-fixed");
        document.removeEventListener("click", clickOutside);
      }
    });
  }

  Drupal.behaviors.mainMenu = {
    attach(context) {
      once("mobileNav", ".header__main-nav", context).forEach(init);
    },
  };
// };
})(Drupal, once);