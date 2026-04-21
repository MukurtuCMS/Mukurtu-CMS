((Drupal, drupalSettings, once) => {
  const toolbarVariant = drupalSettings.gin.toolbar_variant;
  Drupal.behaviors.ginToolbar = {
    attach: context => {
      Drupal.ginToolbar.init(context), Drupal.ginToolbar.initKeyboardShortcut(context);
    }
  }, Drupal.ginToolbar = {
    init: function(context) {
      once("ginToolbarInit", "#gin-toolbar-bar", context).forEach((() => {
        const toolbarTrigger = document.querySelector(".toolbar-menu__trigger");
        "classic" != toolbarVariant && localStorage.getItem("Drupal.toolbar.trayVerticalLocked") && localStorage.removeItem("Drupal.toolbar.trayVerticalLocked"), 
        "true" === localStorage.getItem("Drupal.gin.toolbarExpanded") ? (document.body.setAttribute("data-toolbar-menu", "open"), 
        toolbarTrigger.classList.add("is-active")) : (document.body.setAttribute("data-toolbar-menu", ""), 
        toolbarTrigger.classList.remove("is-active")), this.initDisplace();
      })), once("ginToolbarToggle", ".toolbar-menu__trigger", context).forEach((el => el.addEventListener("click", (e => {
        e.preventDefault(), this.toggleToolbar();
      }))));
    },
    initKeyboardShortcut: function(context) {
      once("ginToolbarKeyboardShortcutInit", ".toolbar-menu__trigger, .admin-toolbar__expand-button", context).forEach((() => {
        document.addEventListener("keydown", (e => {
          !0 === e.altKey && "KeyT" === e.code && this.toggleToolbar();
        }));
      }));
    },
    initDisplace: () => {
      const toolbar = document.querySelector("#gin-toolbar-bar .toolbar-menu-administration");
      toolbar && ("vertical" === toolbarVariant ? toolbar.setAttribute("data-offset-left", "") : toolbar.setAttribute("data-offset-top", ""));
    },
    toggleToolbar: function() {
      const toolbarTrigger = document.querySelector(".toolbar-menu__trigger");
      toolbarTrigger.classList.toggle("is-active"), toolbarTrigger.classList.contains("is-active") ? this.showToolbar() : this.collapseToolbar();
    },
    showToolbar: function() {
      document.body.setAttribute("data-toolbar-menu", "open"), localStorage.setItem("Drupal.gin.toolbarExpanded", "true"), 
      this.dispatchToolbarEvent("true"), this.displaceToolbar(), window.innerWidth < 1280 && "vertical" === toolbarVariant && Drupal.ginSidebar.collapseSidebar();
    },
    collapseToolbar: function() {
      const toolbarTrigger = document.querySelector(".toolbar-menu__trigger"), elementToRemove = document.querySelector(".gin-toolbar-inline-styles");
      toolbarTrigger.classList.remove("is-active"), document.body.setAttribute("data-toolbar-menu", ""), 
      elementToRemove && elementToRemove.parentNode.removeChild(elementToRemove), localStorage.setItem("Drupal.gin.toolbarExpanded", "false"), 
      this.dispatchToolbarEvent("false"), this.displaceToolbar();
    },
    dispatchToolbarEvent: active => {
      const event = new CustomEvent("toolbar-toggle", {
        detail: "true" === active
      });
      document.dispatchEvent(event);
    },
    displaceToolbar: () => {
      ontransitionend = () => {
        Drupal.displace(!0);
      };
    }
  };
})(Drupal, drupalSettings, once);