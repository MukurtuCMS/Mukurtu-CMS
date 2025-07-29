/**
 * @file
 * Account menu dropdown behavior.
 */

((Drupal, once) => {
  'use strict';

  /**
   * Updates the aria-expanded attribute and visual state.
   *
   * @param {Element} button
   *   The toggle button element.
   * @param {Element} menuItem
   *   The menu item container.
   * @param {boolean} isExpanded
   *   Whether the dropdown is expanded.
   */
  function updateDropdownState(button, menuItem, isExpanded) {
    button.setAttribute('aria-expanded', isExpanded);
  }

  /**
   * Closes all other open dropdowns in the same menu.
   *
   * @param {Element} currentMenuItem
   *   The current menu item to keep open.
   * @param {Element} menu
   *   The parent menu container.
   */
  function closeOtherDropdowns(currentMenuItem, menu) {
    const allDropdownItems = menu.querySelectorAll('.account-menu__menu-item--has-children');
    allDropdownItems.forEach((item) => {
      if (item !== currentMenuItem) {
        const button = item.querySelector('.account-menu__button-toggle');
        if (button) {
          updateDropdownState(button, item, false);
        }
      }
    });
  }

  /**
   * Initialize account menu dropdown functionality.
   *
   * @param {Element} menuItem
   *   The menu item with dropdown.
   */
  function initAccountDropdown(menuItem) {
    const toggleButton = menuItem.querySelector('.account-menu__button-toggle');
    const link = menuItem.querySelector('.account-menu__menu-link--has-children');
    const submenu = menuItem.querySelector('.menu__account-submenu');
    const parentMenu = menuItem.closest('.menu__account-menu');

    if (!toggleButton || !submenu || !link) {
      return;
    }

    // Set initial state
    updateDropdownState(toggleButton, menuItem, false);

    let wasOpenedByClick = false;

    // Handle toggle button click
    toggleButton.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';
      const newState = !isExpanded;

      // Close other dropdowns if opening this one
      if (newState && parentMenu) {
        closeOtherDropdowns(menuItem, parentMenu);
      }

      wasOpenedByClick = newState;
      updateDropdownState(toggleButton, menuItem, newState);
    });

    // Handle hover on the entire menu item (like main nav)
    menuItem.addEventListener('mouseover', () => {
      if (!wasOpenedByClick) {
        updateDropdownState(toggleButton, menuItem, true);
      }
    });

    // Handle mouse leave on the entire menu item
    menuItem.addEventListener('mouseout', () => {
      if (!wasOpenedByClick) {
        updateDropdownState(toggleButton, menuItem, false);
      }
    });
  }

  /**
   * Close all account menu dropdowns.
   */
  function closeAllAccountDropdowns() {
    const allToggleButtons = document.querySelectorAll('.account-menu__button-toggle');
    allToggleButtons.forEach((button) => {
      const menuItem = button.closest('.account-menu__menu-item--has-children');
      if (menuItem) {
        updateDropdownState(button, menuItem, false);
      }
    });
  }

  /**
   * Account menu dropdown behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach account menu dropdown functionality.
   */
  Drupal.behaviors.accountMenuDropdown = {
    attach(context) {
      once('account-menu', '.menu__account-menu', context).forEach((menu) => {
        const dropdownItems = menu.querySelectorAll('.account-menu__menu-item--has-children');

        dropdownItems.forEach(initAccountDropdown);

        // Close dropdowns when clicking outside
        document.addEventListener('click', (event) => {
          const accountMenu = event.target.closest('.menu__account-menu');
          if (!accountMenu) {
            closeAllAccountDropdowns();
          }
        });

        // Global escape key handler for account menu dropdowns
        document.addEventListener('keyup', (event) => {
          if (event.key === 'Escape') {
            closeAllAccountDropdowns();
          }
        });
      });
    },

    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        closeAllAccountDropdowns();
      }
    }
  };

})(Drupal, once);
