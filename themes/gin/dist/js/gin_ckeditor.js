((Drupal, drupalSettings, once) => {
  Drupal.behaviors.ginCKEditor = {
    attach: context => {
      Drupal.ginCKEditor.init(context);
    }
  }, Drupal.ginCKEditor = {
    init: context => {
      once("ginCKEditors", "body", context).forEach((() => {
        if (window.CKEDITOR && void 0 !== CKEDITOR) {
          if (drupalSettings.path.currentPath.indexOf("admin/config/content/formats/manage") > -1) return;
          const variablesCss = drupalSettings.gin.variables_css_path, accentCss = drupalSettings.gin.accent_css_path, contentsCss = drupalSettings.gin.ckeditor_css_path, accentColorPreset = drupalSettings.gin.preset_accent_color, accentColor = drupalSettings.gin.accent_color, darkmodeClass = drupalSettings.gin.darkmode_class;
          (1 == window.ginDarkmode || "auto" === window.ginDarkmode && window.matchMedia("(prefers-color-scheme: dark)").matches) && (CKEDITOR.config.bodyClass = darkmodeClass), 
          void 0 === CKEDITOR.config.contentsCss && CKEDITOR.config.contentsCss.push(variablesCss, accentCss, contentsCss), 
          void 0 === CKEDITOR.config.contextmenu_contentsCss && (CKEDITOR.config.contextmenu_contentsCss = new Array, 
          void 0 === CKEDITOR.skinName && (CKEDITOR.skinName = CKEDITOR.skin.name), CKEDITOR.config.contextmenu_contentsCss.push(CKEDITOR.skin.getPath("editor"), variablesCss, accentCss, contentsCss)), 
          CKEDITOR.on("instanceReady", (element => {
            const editor = element.editor;
            editor.document.$.body.setAttribute("data-gin-accent", accentColorPreset), "custom" === accentColorPreset && accentColor && Drupal.ginAccent.setCustomAccentColor(accentColor, editor.document.$.head), 
            editor.on("mode", (function() {
              "wysiwyg" == this.mode && (editor.document.$.body.setAttribute("data-gin-accent", accentColorPreset), 
              "custom" === accentColorPreset && accentColor && Drupal.ginAccent.setCustomAccentColor(accentColor, editor.document.$.head), 
              "auto" === window.ginDarkmode && (window.matchMedia("(prefers-color-scheme: dark)").matches ? editor.document.$.body.classList.add(darkmodeClass) : editor.document.$.body.classList.remove(darkmodeClass)));
            })), editor.on("menuShow", (function(element) {
              const darkModeClass = 1 == window.ginDarkmode || "auto" === window.ginDarkmode && window.matchMedia("(prefers-color-scheme: dark)").matches ? darkmodeClass : "", iframeElement = element.data[0].element.$.childNodes[0].contentWindow.document;
              darkModeClass && iframeElement.body.classList.add(darkModeClass), iframeElement.body.setAttribute("data-gin-accent", accentColorPreset), 
              "custom" === accentColorPreset && accentColor && Drupal.ginAccent.setCustomAccentColor(accentColor, iframeElement.head);
            })), window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e => {
              e.matches && "auto" === window.ginDarkmode && (editor.document.$.body.classList.add(darkmodeClass), 
              document.querySelectorAll(`.${editor.id}.cke_panel`).length > 0) && document.querySelector(`.${editor.id}.cke_panel`).childNodes[0].contentWindow.document.body.classList.add(darkmodeClass);
            })), window.matchMedia("(prefers-color-scheme: light)").addEventListener("change", (e => {
              e.matches && "auto" === window.ginDarkmode && (editor.document.$.body.classList.remove(darkmodeClass), 
              document.querySelectorAll(`.${editor.id}.cke_panel`).length > 0) && document.querySelector(`.${editor.id}.cke_panel`).childNodes[0].contentWindow.document.body.classList.remove(darkmodeClass);
            }));
          }));
        }
      }));
    }
  };
})(Drupal, drupalSettings, once);