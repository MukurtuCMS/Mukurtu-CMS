((Drupal, drupalSettings, once) => {
  Drupal.behaviors.ginEscapeAdmin = {
    attach: context => {
      once("ginEscapeAdmin", "[data-gin-toolbar-escape-admin]", context).forEach((el => {
        const escapeAdminPath = sessionStorage.getItem("escapeAdminPath");
        drupalSettings.path.currentPathIsAdmin && null !== escapeAdminPath && el.setAttribute("href", escapeAdminPath);
      }));
    }
  };
})(Drupal, drupalSettings, once);