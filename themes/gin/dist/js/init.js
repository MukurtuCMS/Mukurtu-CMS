(() => {
  if (localStorage.getItem("GinDarkMode") && localStorage.removeItem("GinDarkMode"), 
  localStorage.getItem("Drupal.gin.darkmode") && localStorage.removeItem("Drupal.gin.darkmode"), 
  localStorage.getItem("GinSidebarOpen") && (localStorage.setItem("Drupal.gin.toolbarExpanded", localStorage.getItem("GinSidebarOpen")), 
  localStorage.removeItem("GinSidebarOpen")), function() {
    const darkmodeSetting = document.getElementById("gin-setting-darkmode")?.textContent;
    window.ginDarkmode = darkmodeSetting ? JSON.parse(darkmodeSetting)?.ginDarkmode : "auto", 
    1 == window.ginDarkmode || "auto" === window.ginDarkmode && window.matchMedia("(prefers-color-scheme: dark)").matches ? document.documentElement.classList.add("gin--dark-mode") : !0 === document.documentElement.classList.contains("gin--dark-mode") && document.documentElement.classList.remove("gin--dark-mode");
  }(), localStorage.getItem("Drupal.gin.toolbarExpanded")) {
    const style = document.createElement("style"), className = "gin-toolbar-inline-styles";
    if (style.className = className, "true" === localStorage.getItem("Drupal.gin.toolbarExpanded")) {
      style.innerHTML = "\n    @media (min-width: 976px) {\n      /* Small CSS hack to make sure this has the highest priority */\n      body.gin--vertical-toolbar.gin--vertical-toolbar.gin--vertical-toolbar {\n        padding-inline-start: 256px !important;\n        transition: none !important;\n      }\n\n      .gin--vertical-toolbar .toolbar-menu-administration {\n        min-width: var(--gin-toolbar-width, 256px);\n        transition: none;\n      }\n\n      .gin--vertical-toolbar .toolbar-menu-administration > .toolbar-menu > .menu-item > .toolbar-icon,\n      .gin--vertical-toolbar .toolbar-menu-administration > .toolbar-menu > .menu-item > .toolbar-box > .toolbar-icon {\n        min-width: calc(var(--gin-toolbar-width, 256px) - 16px);\n      }\n    }\n    ";
      const scriptTag = document.querySelector("script");
      scriptTag.parentNode.insertBefore(style, scriptTag);
    } else document.getElementsByClassName(className).length > 0 && document.getElementsByClassName(className)[0].remove();
  }
  if (localStorage.getItem("Drupal.gin.sidebarWidth")) {
    const sidebarWidth = localStorage.getItem("Drupal.gin.sidebarWidth");
    document.documentElement.style.setProperty("--gin-sidebar-width", sidebarWidth);
  }
  if (localStorage.getItem("Drupal.gin.sidebarExpanded.desktop")) {
    const style = document.createElement("style"), className = "gin-sidebar-inline-styles";
    if (style.className = className, window.innerWidth < 1024 || "false" === localStorage.getItem("Drupal.gin.sidebarExpanded.desktop")) {
      style.innerHTML = "\n    body {\n      --gin-sidebar-offset: 0px;\n      padding-inline-end: 0;\n      transition: none;\n    }\n\n    .layout-region-node-secondary {\n      transform: translateX(var(--gin-sidebar-width, 360px));\n      transition: none;\n    }\n\n    .meta-sidebar__overlay {\n      display: none;\n    }\n    ";
      const scriptTag = document.querySelector("script");
      scriptTag.parentNode.insertBefore(style, scriptTag);
    } else document.getElementsByClassName(className).length > 0 && document.getElementsByClassName(className)[0].remove();
  }
})();