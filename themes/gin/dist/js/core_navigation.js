((Drupal, once) => {
  Drupal.behaviors.ginCoreNavigation = {
    attach: context => {
      Drupal.ginCoreNavigation.initKeyboardShortcut(context);
    }
  }, Drupal.ginCoreNavigation = {
    initKeyboardShortcut: function(context) {
      once("ginToolbarKeyboardShortcut", ".admin-toolbar__expand-button", context).forEach((() => {
        document.addEventListener("keydown", (e => {
          !0 === e.altKey && "KeyT" === e.code && this.toggleToolbar();
        }));
      })), once("ginToolbarClickHandler", ".top-bar__burger, .admin-toolbar__expand-button", context).forEach((button => {
        button.addEventListener("click", (() => {
          window.innerWidth < 1280 && button.getAttribute("aria-expanded", "false") && Drupal.ginSidebar?.collapseSidebar();
        }));
      }));
    },
    toggleToolbar() {
      let toolbarTrigger = document.querySelector(".admin-toolbar__expand-button");
      toolbarTrigger && toolbarTrigger.click();
    },
    collapseToolbar: function() {
      document.querySelectorAll(".top-bar__burger, .admin-toolbar__expand-button").forEach((button => {
        button.setAttribute("aria-expanded", "false");
      })), document.documentElement.setAttribute("data-admin-toolbar", "collapsed"), Drupal.displace(!0);
    }
  };
})(Drupal, once);