/* To inject this as early as possible
 * we use native JS instead of Drupal's behaviors.
*/

// Legacy Check: Transform old localStorage items to newer ones.
function checkLegacy() {
  if (localStorage.getItem('GinDarkMode')) {
    localStorage.removeItem('GinDarkMode');
  }
  if (localStorage.getItem('Drupal.gin.darkmode')) {
    localStorage.removeItem('Drupal.gin.darkmode');
  }
  if (localStorage.getItem('GinSidebarOpen')) {
    localStorage.setItem('Drupal.gin.toolbarExpanded', localStorage.getItem('GinSidebarOpen'));
    localStorage.removeItem('GinSidebarOpen');
  }
}

checkLegacy();

// Darkmode Check.
function ginInitDarkmode() {
  const darkModeClass = 'gin--dark-mode';

  const darkmodeSetting = document.getElementById('gin-setting-darkmode')?.textContent;
  // Set window variable.
  window.ginDarkmode = darkmodeSetting ? JSON.parse(darkmodeSetting)?.ginDarkmode : 'auto';

  if (
    window.ginDarkmode == 1 ||
    window.ginDarkmode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches
  ) {
    document.documentElement.classList.add(darkModeClass);
  } else {
    document.documentElement.classList.contains(darkModeClass) === true && document.documentElement.classList.remove(darkModeClass);
  }
}

ginInitDarkmode();

// Toolbar Check.
if (localStorage.getItem('Drupal.gin.toolbarExpanded')) {
  const style = document.createElement('style');
  const className = 'gin-toolbar-inline-styles';
  style.className = className;

  if (localStorage.getItem('Drupal.gin.toolbarExpanded') === 'true') {
    style.innerHTML = `
    @media (min-width: 976px) {
      /* Small CSS hack to make sure this has the highest priority */
      body.gin--vertical-toolbar.gin--vertical-toolbar.gin--vertical-toolbar {
        padding-inline-start: 256px !important;
        transition: none !important;
      }

      .gin--vertical-toolbar .toolbar-menu-administration {
        min-width: var(--gin-toolbar-width, 256px);
        transition: none;
      }

      .gin--vertical-toolbar .toolbar-menu-administration > .toolbar-menu > .menu-item > .toolbar-icon,
      .gin--vertical-toolbar .toolbar-menu-administration > .toolbar-menu > .menu-item > .toolbar-box > .toolbar-icon {
        min-width: calc(var(--gin-toolbar-width, 256px) - 16px);
      }
    }
    `;

    const scriptTag = document.querySelector('script');
    scriptTag.parentNode.insertBefore(style, scriptTag);
  } else if (document.getElementsByClassName(className).length > 0) {
    document.getElementsByClassName(className)[0].remove();
  }
}

// Sidebar checks.
if (localStorage.getItem('Drupal.gin.sidebarWidth')) {
  const sidebarWidth = localStorage.getItem('Drupal.gin.sidebarWidth');
  document.documentElement.style.setProperty('--gin-sidebar-width', sidebarWidth);
}

if (localStorage.getItem('Drupal.gin.sidebarExpanded.desktop')) {
  const style = document.createElement('style');
  const className = 'gin-sidebar-inline-styles';
  style.className = className;

  if (window.innerWidth < 1024 || localStorage.getItem('Drupal.gin.sidebarExpanded.desktop') === 'false') {
    style.innerHTML = `
    body {
      --gin-sidebar-offset: 0px;
      padding-inline-end: 0;
      transition: none;
    }

    .layout-region-node-secondary {
      transform: translateX(var(--gin-sidebar-width, 360px));
      transition: none;
    }

    .meta-sidebar__overlay {
      display: none;
    }
    `;

    const scriptTag = document.querySelector('script');
    scriptTag.parentNode.insertBefore(style, scriptTag);
  } else if (document.getElementsByClassName(className).length > 0) {
    document.getElementsByClassName(className)[0].remove();
  }
}
