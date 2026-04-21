/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

((Drupal, drupalSettings, once) => {
  const breakpointLarge = 1280;
  const toolbarVariant = drupalSettings.gin.toolbar_variant;

  Drupal.behaviors.ginToolbar = {
    attach: (context) => {
      Drupal.ginToolbar.init(context);
      Drupal.ginToolbar.initKeyboardShortcut(context);
    },
  };

  Drupal.ginToolbar = {
    init: function (context) {
      once('ginToolbarInit', '#gin-toolbar-bar', context).forEach(() => {
        const toolbarTrigger = document.querySelector('.toolbar-menu__trigger');

        // Check for Drupal trayVerticalLocked and remove it.
        if (toolbarVariant != 'classic' && localStorage.getItem('Drupal.toolbar.trayVerticalLocked')) {
          localStorage.removeItem('Drupal.toolbar.trayVerticalLocked');
        }

        // Set sidebarState.
        if (localStorage.getItem('Drupal.gin.toolbarExpanded') === 'true') {
          document.body.setAttribute('data-toolbar-menu', 'open');
          toolbarTrigger.classList.add('is-active');
        }
        else {
          document.body.setAttribute('data-toolbar-menu', '');
          toolbarTrigger.classList.remove('is-active');
        }

        // this.initKeyboardShortcut();
        this.initDisplace();
      });

      // Toolbar toggle
      once('ginToolbarToggle', '.toolbar-menu__trigger', context).forEach(el => el.addEventListener('click', e => {
        e.preventDefault();
        this.toggleToolbar();
      }));
    },

    initKeyboardShortcut: function (context) {
      once('ginToolbarKeyboardShortcutInit', '.toolbar-menu__trigger, .admin-toolbar__expand-button', context).forEach(() => {
        // Show toolbar navigation with shortcut:
        // OPTION + T (Mac) / ALT + T (Windows)
        document.addEventListener('keydown', e => {
          if (e.altKey === true && e.code === 'KeyT') {
            this.toggleToolbar();
          }
        });
      });
    },

    initDisplace: () => {
      const toolbar = document.querySelector('#gin-toolbar-bar .toolbar-menu-administration');

      if (toolbar) {
        if (toolbarVariant === 'vertical') {
          toolbar.setAttribute('data-offset-left', '');
        } else {
          toolbar.setAttribute('data-offset-top', '');
        }
      }
    },

    toggleToolbar: function () {
      const toolbarTrigger = document.querySelector('.toolbar-menu__trigger');

      // Toggle active class.
      toolbarTrigger.classList.toggle('is-active');

      if (toolbarTrigger.classList.contains('is-active')) {
        this.showToolbar();
      }
      else {
        this.collapseToolbar();
      }
    },

    showToolbar: function () {
      const active = 'true';

      document.body.setAttribute('data-toolbar-menu', 'open');

      // Write state to localStorage.
      localStorage.setItem('Drupal.gin.toolbarExpanded', active);

      this.dispatchToolbarEvent(active);
      this.displaceToolbar();

      // Check which toolbar is active.
      if (window.innerWidth < breakpointLarge && toolbarVariant === 'vertical') {
        Drupal.ginSidebar.collapseSidebar();
      }
    },

    collapseToolbar: function () {
      const toolbarTrigger = document.querySelector('.toolbar-menu__trigger');
      const elementToRemove = document.querySelector('.gin-toolbar-inline-styles');
      const active = 'false';

      toolbarTrigger.classList.remove('is-active');
      document.body.setAttribute('data-toolbar-menu', '');

      if (elementToRemove) {
        elementToRemove.parentNode.removeChild(elementToRemove);
      }

      // Write state to localStorage.
      localStorage.setItem('Drupal.gin.toolbarExpanded', 'false');

      this.dispatchToolbarEvent(active);
      this.displaceToolbar();
    },

    dispatchToolbarEvent: (active) => {
      // Dispatch event.
      const event = new CustomEvent('toolbar-toggle', { detail: active === 'true'})
      document.dispatchEvent(event);
    },

    displaceToolbar: () => {
      ontransitionend = () => {
        Drupal.displace(true);
      };
    },
  };

})(Drupal, drupalSettings, once);
