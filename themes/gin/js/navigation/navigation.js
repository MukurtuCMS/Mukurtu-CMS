((Drupal, once, { computePosition, offset, arrow, shift, flip }) => {
  /**
   * The wrapping element for the navigation sidebar.
   */
  let sidebar;

  // Gin Custom start ---------------------
  const breakpointLarge = 1280;
  // Gin Custom end ------------------------

  /**
   * Collapsed toolbar keydown handling vars.
   */
  let firstFocusableEl;
  const firstLevelToolbarItems = Array.from(document.querySelectorAll('.navigation__logo, .toolbar-menu > .toolbar-menu__item--level-1 > .toolbar-link')); // 1st level menu items.
  const keys = {
    tab:     9,
    esc:    27,
    space:  32,
  };
  let currentIndex, subIndex;

  /**
   * Informs in the navigation is expanded.
   *
   * @returns {boolean} - If the navigation is expanded.
   */
  function isNavExpanded() {
    return document.documentElement.classList.contains('admin-toolbar-expanded');
  }

  /**
   * If active item is in the menu trail, then expand the navigation so it is
   * open, and then scroll to it. This happens on page load and when
   * transitioning between expanded and collapsed states.
   */
  function autoExpandToActiveMenuItem() {
    const activeItems = sidebar.querySelectorAll('.is-active');
    closeAllSubmenus();
    activeItems.forEach(activeItem => {
      activeItem?.closest('.toolbar-menu__item.toolbar-menu__item--level-2')?.classList.add('toolbar-menu__item--expanded');
      activeItem?.closest('.toolbar-menu__item.toolbar-menu__item--level-2')?.classList.add('active-path');

      // Only expand level one if sidebar is in expanded state.
      // Gin Custom start ---------------------
      activeItem?.closest('.toolbar-menu__item.toolbar-menu__item--level-1')?.classList.add('active-path');
      // Gin Custom end ------------------------
    });
    // Scroll to the open trays so they're in view.
    const expandedTray = sidebar.querySelector('.toolbar-menu__item.toolbar-menu__item--expanded');
    expandedTray?.scrollIntoView({ behavior: 'smooth' });

    // Gin Custom start ---------------------
    // checkOverflow();
    // Gin Custom end -----------------------
  }

  /**
   * Searches the sidebar for all menu items, and when it finds the menu item
   * for the link that it's currently on, it will add relevant CSS classes to it
   * so it can be styled and opened to.
   *
   * @todo once we move into the Drupal core menu system, we should be able to
   * remove this.
   */
  function markCurrentPageInMenu() {
    // Check all links on the sidebar (that are not in the shortcutsNav
    // <div>) to see if they are the current page. If so, set a `current`
    // and `is-active` CSS class on the parent <li>.
    const sidebarLinks = sidebar.querySelectorAll('a.toolbar-link:not(.menu--shortcuts *):not(.toolbar-link--create)');
    sidebarLinks.forEach(link => {
      if (link.href === document.URL) {
        link.parentElement.classList.add('current', 'is-active');
      }
    });

    // Gin Custom start ---------------------
    // Mark overview pages as active.
    const sidebarTitles = sidebar.querySelectorAll('.toolbar-menu__item--level-1[data-url]');
    sidebarTitles.forEach(title => {
      if (title.getAttribute('data-url') === window.location.pathname) {
        title.querySelector('a.toolbar-link')?.classList.add('current', 'is-active');
      }
    });
    // Gin Custom end ------------------------
  }

  /**
   * Expand / collapse sidebar
   *
   * @param {boolean} toState - the state which the sidebar will be
   *   transitioning to (true if expanded, false if collapsed).
   */
  function expandCollapseSidebar(toState) {
    const expandCollapseButton = sidebar.querySelector('[aria-controls="admin-toolbar"]');
    if (toState) closeTooltip();
    document.documentElement.classList.toggle('admin-toolbar-expanded', toState);
    Drupal.displace(true);
    sidebar.querySelector('#sidebar-state').textContent = toState ? Drupal.t('Collapse sidebar') : Drupal.t('Expand sidebar');
    expandCollapseButton.setAttribute('aria-expanded', toState);
    localStorage.setItem('Drupal.navigation.sidebarExpanded', toState);
    autoExpandToActiveMenuItem();

    if (toState) {
      flyoutTooltipDetach();
    }
    else {
      flyoutTooltipInit();
    }

    // Gin Custom start ---------------------
    if (toState === true && window.innerWidth < breakpointLarge) {
      Drupal.ginSidebar?.collapseSidebar();
    }
    // Gin Custom end ------------------------
  }

  /**
   * Show shadow on sticky section only when expanded and content is
   * overflowing.
   *
   * @todo this should be using either CSS only or Intersection Observer instead
   * of getBoundingClientRect().
   */
  // Gin Custom start ---------------------
  // function checkOverflow() {
  //   if (isNavExpanded()) {
  //     const stickyMenu = sidebar.querySelector('.admin-toolbar__sticky-section');
  //     const mainMenu = sidebar.querySelector('#menu-builder'); // @todo why are we using ID here?
  //     const stickyMenuTopPos = stickyMenu?.getBoundingClientRect().top;
  //     const mainMenuBottomPos = mainMenu?.getBoundingClientRect().bottom;
  //     stickyMenu?.classList.toggle('shadow', mainMenuBottomPos > stickyMenuTopPos);
  //   }
  // }
  // Gin Custom end ------------------------

  /**
   * Calculate and place flyouts relative to their parent links using the
   * floating UI library.
   *
   * @param {Element} hoveredEl - <li> element that was hovered over.
   */
  function positionFlyout(hoveredEl) {
    const anchorEl = hoveredEl.querySelector('.toolbar-link'); // This is the <button> element within the <li>.
    const flyoutEl = document.getElementById(anchorEl.getAttribute('aria-controls')); // Top level <ul> that contains menu items to be shown.
    const arrowEl = flyoutEl?.querySelector('.toolbar-menu__arrow-ref'); // Empty <div> that is styled as an arrow.
    computePosition(anchorEl, flyoutEl, {
      placement: 'right',
      middleware: [
        offset(6),
        flip({ padding: 16 }),
        shift({ padding: 16 }),
        arrow({ element: arrowEl }),
      ],
    }).then(({ x, y, placement, middlewareData }) => {
      Object.assign(flyoutEl.style, {
        left: `${x}px`,
        top: `${y}px`,
      });

      // Accessing the data
      const { x: arrowX, y: arrowY } = middlewareData.arrow;

      const staticSide = {
        top: 'bottom',
        right: 'left',
        bottom: 'top',
        left: 'right',
      }[placement.split('-')[0]];

      Object.assign(arrowEl.style, {
        left: arrowX != null ? `${arrowX}px` : '',
        top: arrowY != null ? `${arrowY}px` : '',
        right: '',
        bottom: '',
        [staticSide]: '-4px',
      });
    });
  }

  /**
   * Calculate and place tooltips relative to their parent links using the
   * floating UI library.
   *
   * @param {Element} anchorEl - <a> element within the navigation link
   *   that was hovered over.
   * @param {Element} tooltipEl - Tooltip span
   *   shown.
   */
  function positionTooltip(hoveredEl) {
    const anchorEl = hoveredEl.querySelector('.toolbar-link'); // This is the <a> element within the navigation link.
    const tooltipEl = document.querySelector('.gin-tooltip-navigation'); // This is the tooltip span.
    computePosition(anchorEl, tooltipEl, {
      placement: 'right',
      middleware: [
        offset(6),
        flip({ padding: 16 }),
        shift({ padding: 16 }),
      ],
    }).then(({ x, y }) => {
      Object.assign(tooltipEl.style, {
        left: `${x}px`,
        top: `${y}px`,
      });
    });
  }

  /**
   * When flyouts are active, any click outside of the flyout should close the
   * flyout.
   *
   * @param {Event} e - The click event.
   *
   * @todo can we refactor this to something like blur or focusout? It's only
   * called from one place.
   */
  function closeFlyoutOnClickOutside(e) {
    // This can trigger when expand/collapse button is clicked. We need to
    // ensure this only runs when navigation is collapsed.
    if (isNavExpanded()) return;

    if (!e.target.closest('.cloned-flyout')) {
      closeFlyout();
    }
  }

  /**
   * Open the flyout when in collapsed mode.
   *
   * @param {Event || Element} e - Either the  mouseenter event from the parentListItem or an element.
   */
  function openFlyout(e) {
    // Only one flyout can be open at once, so close currently
    // open flyouts.
    const hoveredEl = e.target ? e.target : e.parentElement; // This is the <li> that was hovered over. Check if it's an event object or not.
    const buttonEl = hoveredEl.querySelector('.toolbar-link'); // The level-1 list item <button>.
    const clonedFlyout = hoveredEl.querySelector('.toolbar-menu__submenu').cloneNode(true); // Flyout clone.
    const clonedFlyoutId = `${hoveredEl.id}--flyout-clone`; // ID for flyout aria-controls.
    // if (hoveredEl.classList.contains('toolbar-menu__item--expanded')) return;
    closeFlyout();
    closeTooltip();
    // Add aria attributes to the flyout and <button>.
    // Add a class to easily remove flyout in closeFlyout().
    // Append the cloned flyout to the body to fix overflow issues with
    // vertical scrolling on the collapsed sidebar.
    buttonEl.setAttribute('aria-controls', clonedFlyoutId);
    buttonEl.setAttribute('aria-expanded', true);
    clonedFlyout.setAttribute('id', clonedFlyoutId);
    clonedFlyout.classList.add('cloned-flyout');
    document.querySelector('body').append(clonedFlyout);

    // Add click event listeners to all buttons and then contains the callback
    // to expand / collapse the button's menus.
    clonedFlyout.querySelectorAll('.toolbar-menu__item--has-dropdown > button').forEach(el => el.addEventListener('click', (e) => {
      // Gin Custom start ---------------------
      const dataUrl = el.getAttribute('data-url');
      if ((e.ctrlKey || e.shiftKey || e.altKey || e.metaKey) && dataUrl) {
        window.open(dataUrl, '_blank');
      }
      // Gin Custom end ------------------------
      else {
        openCloseSubmenu(e.currentTarget.parentElement);
      }
    }));

    // Gin Custom start ---------------------
    // Add the event listeners for focus handling on flyout clone.
    const flyoutEls = document.querySelectorAll('.cloned-flyout .toolbar-menu__item--level-2 > .toolbar-link, .cloned-flyout .toolbar-menu__item--level-3 > .toolbar-link');
    flyoutEls?.forEach(el => {
      el.addEventListener('keydown', handleKeydownFlyout, false);
    });
    // Gin Custom end ------------------------

    // If the active submenu is not yet open (it might be open if it has
    // focus-within and the user did a  mouseleave and mouseenter).
    // if (!hoveredEl.classList.contains('toolbar-menu__item--expanded')) {
      // Only position if the submenu is not already open. This prevents the
      // flyout from unexpectedly shifting.
      positionFlyout(hoveredEl);
    // }
    hoveredEl.classList.add('toolbar-menu__item--expanded');

    // When a level-1 item hover ends, check if the flyout has focus and if
    // not, close it.
    clonedFlyout.addEventListener('mouseleave', delayedFlyoutClose, false);

    // When a flyout is open, listen for clicks outside the flyout.
    document.addEventListener('click', closeFlyoutOnClickOutside, false);
    // Auto expand to the active menu item on the cloned flyout.
    autoExpandToActiveMenuItem();
  }

  /**
   * Open the tooltip when in collapsed mode.
   * Note: JS solution needed due to requirement for vertical scrolling.
   * CSS solution of overflow-y:scroll and overflow-x:visible on sidebar is not possible.
   *
   * @param {Event} e - The mouseenter event from the parentListItem.
   */
  function openTooltip(e) {
    closeFlyout();
    closeTooltip();
    const hoveredEl = e.target; // This is the <li> that was hovered over.
    const clonedTooltip = hoveredEl.querySelector('.toolbar-link > span').cloneNode(true); // Tooltip clone.
    // Add a class to easily remove flyout in closeTooltip().
    // Append the cloned tooltip to the body to fix overflow issues with
    // vertical scrolling on the collapsed sidebar.
    clonedTooltip.classList.add('gin-tooltip-navigation');
    document.querySelector('body').append(clonedTooltip);
    if (!hoveredEl.classList.contains('toolbar-menu__item--expanded')) {
      positionTooltip(hoveredEl);
    }
  }

  /**
   * Close the flyout.
   */
  function closeFlyout() {
    // Remove expanded class if sidebar is collapsed.
    // Remove cloned flyout element.
    if (!isNavExpanded()) {
      // Remove the event listeners for focus handling on flyout clone.
      const flyoutEls = document.querySelectorAll('.cloned-flyout .toolbar-menu__item--level-2, .cloned-flyout .toolbar-menu__item--level-3');
      flyoutEls?.forEach(el => {
        el.removeEventListener('keydown', handleKeydownFlyout);
      });

      const clonedFlyout = document.querySelector('.cloned-flyout');
      const clonedFlyoutControl = document.querySelector(`[aria-controls=${clonedFlyout?.id}]`);
      clonedFlyoutControl?.removeAttribute('aria-controls');
      clonedFlyoutControl?.setAttribute('aria-expanded', false);
      closeAllSubmenus();
      clonedFlyout?.removeEventListener('mouseleave', delayedFlyoutClose);
      clonedFlyout?.remove();
      document.removeEventListener('click', closeFlyoutOnClickOutside);
    }
  }

  /**
   * Close the tooltip.
   */
  function closeTooltip() {
    if (!isNavExpanded()) {
      const clonedTooltip = document.querySelector('.gin-tooltip-navigation');
      clonedTooltip?.remove();
    }
  }

  /**
   * Close the flyout after timer (if not hovered over again).
   *
   * @param {e} - mouseleave event.
   */
  function delayedFlyoutClose(e) {
    const parentListItem = e.currentTarget;
    const currentFlyout = document.querySelector('.cloned-flyout');

    // Do not close flyout if it contains focus.
    if (currentFlyout.contains(document.activeElement)) return;

    timer = setTimeout(() => {
      closeFlyout();
      parentListItem.removeEventListener('mouseover', () => clearTimeout(timer), { once: true });
    }, 400);
    parentListItem.addEventListener('mouseover', () => clearTimeout(timer), { once: true });
  }

  /**
   * Keyboard navigation for the collapsed toolbar top level items.
   * This is particularly complex because of the flyout positioning outside of
   * the sidebar markup.
   *
   * @param {Event} event - The keydown event.
   *
   */
  function handleKeydownTopLevel(event) {
    // Reset the currentIndex so it's always accurate.
    currentIndex = firstLevelToolbarItems.indexOf(event.target);
    switch (event.keyCode) {
      case keys.tab:
        if (event.shiftKey) {
          // Focus the previous menu item.
          currentIndex--;
          firstLevelToolbarItems[currentIndex]?.focus();
        } else {
          // Focus the next menu item.
          currentIndex++;
          if (firstLevelToolbarItems[currentIndex]) {
            firstLevelToolbarItems[currentIndex].focus();
          } else {
            firstFocusableEl.focus();
          }
        }
        event.preventDefault();
        break;
      case keys.space:
        // Open the flyout and focus the first item in the flyout.
        if (this.parentElement.classList.contains('toolbar-menu__item--has-dropdown')) {
          openFlyout(this);
          window.setTimeout(() => document.querySelector('.cloned-flyout .toolbar-menu__item--level-2 .toolbar-link').focus(), 0);
        }
        event.preventDefault();
        break;
      case keys.esc:
        // Leave the menu, focus goes to first focusable element in the page content.
        firstFocusableEl.focus();
        event.preventDefault();
        break;
    }
  }

  /**
   * Keyboard navigation for the collapsed toolbar flyouts.
   *
   * @param {Event} event - The keydown event.
   *
   */
  function handleKeydownFlyout(event) {
    let flyoutEls = Array.from(document.querySelectorAll('.cloned-flyout .toolbar-menu__item--level-2 > .toolbar-link, .cloned-flyout .toolbar-menu__item--expanded .toolbar-menu__item--level-3 .toolbar-link'));;
    subIndex = flyoutEls.indexOf(event.target);
    switch (event.keyCode) {
      case keys.tab:
        if (event.shiftKey) {
          // Tab & shift.
          if (document.activeElement == event.target.parentElement.querySelector('li:nth-child(2) .toolbar-link') &&
          document.activeElement.parentElement.classList.contains('toolbar-menu__item--level-2')) {
            // Focus previous element but this was the first element in the flyout.
            // Close the flyout and focus the parent element.
            currentIndex--;
            window.setTimeout(() => firstLevelToolbarItems[currentIndex].focus(), 0);
            closeFlyout();
            subIndex = 1;
          } else {
            subIndex--;
            window.setTimeout(() => flyoutEls[subIndex].focus(), 0);
          }
        } else {
          // Tab.
          if (document.activeElement == event.target.parentElement.querySelector('li:last-of-type .toolbar-link') &&
          document.activeElement.parentElement.classList.contains('toolbar-menu__item--level-2')) {
            // Focus next element but there are no more elements in the flyout.
            // Close the flyout and focus the parent element.
            // Timeout needed for Firefox.
            currentIndex++;
            window.setTimeout(() => firstLevelToolbarItems[currentIndex].focus(), 0);
            closeFlyout();
            subIndex = 1;
          } else {
            subIndex++;
            window.setTimeout(() => flyoutEls[subIndex].focus(), 0);
          }
        }
        event.preventDefault();
        break;
      case keys.space:
        const thirdLevel = event.target.parentElement.querySelectorAll('.toolbar-menu__item--level-3 .toolbar-link');
        let indexToAdd = flyoutEls.indexOf(event.target) + 1;
        thirdLevel.forEach(item => {
          flyoutEls.splice(indexToAdd, 0, item);
          indexToAdd++;
        });
        subIndex++;
        window.setTimeout(() => flyoutEls[subIndex].focus(), 0);
        break;
      case keys.esc:
        if (document.querySelector('.cloned-flyout')) {
          currentIndex++;
          firstLevelToolbarItems[currentIndex].focus();
          closeFlyout();
          subIndex = 1;
        }
        event.preventDefault();
        break;
    }
  }

  /**
   * Flyout and tooltip setup in the collapsed toolbar state. This gets called when toolbar
   * is put into a collapsed state.
   */
  function flyoutTooltipInit() {
    // Flyouts.
    sidebar.querySelectorAll('.toolbar-menu__item--level-1 > .toolbar-menu__submenu')?.forEach(flyoutEl => {
      const parentListItem = flyoutEl.parentElement;
      // when a level-1 list item with children is hovered, open the flyout
      parentListItem.addEventListener('mouseenter', openFlyout, false);
    });

    // Tooltips.
    sidebar.querySelectorAll('.toolbar-menu__item--level-1:not(.toolbar-menu__item--has-dropdown) > .toolbar-link')?.forEach(tooltipEl => {
      const parentListItem = tooltipEl.parentElement;
      // when a childless level-1 list item is hovered, open the tooltip
      parentListItem.addEventListener('mouseenter', openTooltip, false);
      parentListItem.addEventListener('mouseleave', closeTooltip, false);
    });

    // Handle focus and keyboard nav in the collapse toolbar.
    // Needed due to flyout markup repositioning in the DOM.
    currentIndex = 0;
    subIndex = 1;
    firstFocusableEl = getFirstFocusableEl();
    firstLevelToolbarItems?.forEach(firstLevelEl => {
      firstLevelEl.addEventListener('keydown', handleKeydownTopLevel, false);
    });
  }

  /**
   * Remove all flyout and tooltip related event listeners. This gets called when toolbar is
   * put into an expanded state.
   */
  function flyoutTooltipDetach() {
    // Flyouts.
    sidebar.querySelectorAll('.toolbar-menu__item--level-1 > .toolbar-menu__submenu')?.forEach(flyoutEl => {
      const parentListItem = flyoutEl.parentElement;
      parentListItem.removeEventListener('mouseenter', openFlyout);
    });

    // Tooltips.
    sidebar.querySelectorAll('.toolbar-menu__item--level-1:not(.toolbar-menu__item--has-dropdown) > .toolbar-link')?.forEach(tooltipEl => {
      const parentListItem = tooltipEl.parentElement;
      parentListItem.removeEventListener('mouseenter', openTooltip);
      parentListItem.removeEventListener('mouseleave', closeTooltip);
    });

    // Keyboard navigation for collapsed toolbar and flyouts.
    firstLevelToolbarItems?.forEach(firstLevelEl => {
      firstLevelEl.removeEventListener('keydown', handleKeydownTopLevel);
    });
  }

  /**
   * Close all submenus that are underneath the optional element parameter.
   *
   * @param {Element} [Element] - Optional element under which to close all
   * submenus.
   */
  function closeAllSubmenus(Element) {
    const submenuParentElement = Element ?? sidebar;
    const selectorsToIgnore = '.sidebar-toggle';
    let itemsToClose = submenuParentElement.querySelectorAll('.toolbar-menu__item--expanded');
    // Don't remove expanded class from active trail when toolbar is collapsed.
    if (!isNavExpanded()) {
      itemsToClose = submenuParentElement.querySelectorAll('.toolbar-menu__item--expanded:not(.active-path)');
    }
    itemsToClose.forEach(el => el.classList.remove('toolbar-menu__item--expanded'));
    submenuParentElement.querySelectorAll(`.toolbar-link[aria-expanded="true"]:not(:is(${selectorsToIgnore}))`).forEach(el => {
      el.setAttribute('aria-expanded', false);
      el.querySelector('.toolbar-link__action').textContent = Drupal.t('Extend');
    });
  }

  /**
   * Open or close the submenu. This can happen in both the open and closed
   * state.
   *
   * @param {Element} parentListItem - the parent <li> that needs to be opened
   *   or closed
   * @param {boolean} [state] - optional state where it will end up (true if
   *   opened, or false if closed). If omitted, state will be toggled.
   */
  function openCloseSubmenu(parentListItem, state) {
    toState = state ?? parentListItem.classList.contains('toolbar-menu__item--expanded');
    const buttonEl = parentListItem.querySelector('button.toolbar-link');

    // If we're clicking on a top level menu item, ensure that all other menu
    // items close. Otherwise just close any other sibling menu items.
    if (buttonEl.matches('.toolbar-menu__item.toolbar-menu__item--level-1 > *')) {
      closeAllSubmenus()
    }
    else {
      closeAllSubmenus(parentListItem.parentElement);
    }

    parentListItem.classList.toggle('toolbar-menu__item--expanded', !toState);
    buttonEl.setAttribute('aria-expanded', toState);
    buttonEl.querySelector('.toolbar-link__action').textContent = toState ? Drupal.t('Extend') : Drupal.t('Collapse');
    // Gin Custom start ---------------------
    // checkOverflow();
    // Gin Custom end ------------------------
  }

  /**
   * Initialize Drupal.displace()
   *
   * We add the displace attribute to a separate full width element because we
   * don't want this element to have transitions. Note that this element and the
   * navbar share the same exact width.
   */
  function initDisplace() {
    const displaceElement = sidebar.querySelector('.admin-toolbar__displace-placeholder');
    const edge = document.documentElement.dir === 'rtl' ? 'right' : 'left';
    displaceElement.setAttribute(`data-offset-${edge}`, '');
    Drupal.displace(true);
  }

  /**
   * Get the first focusable element in the page content (not drupal toolbar).
   * This is used for the focus handling in the toolbar.
   */
  function getFirstFocusableEl() {
    const nextEl = sidebar.nextElementSibling.tagName == 'SCRIPT' ? sidebar.nextElementSibling.nextElementSibling : sidebar.nextElementSibling;
    const focusableEls = nextEl.querySelectorAll('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, [href], button, [tabindex="-1"]');
    return focusableEls[0];
  }

  /**
   * Initialize everything.
   */
  function init(el) {
    sidebar = el;
    firstFocusableEl = getFirstFocusableEl();
    const expandCollapseButton = sidebar.querySelector('[aria-controls="admin-toolbar"]');

    markCurrentPageInMenu();
    expandCollapseSidebar(localStorage.getItem('Drupal.navigation.sidebarExpanded') !== 'false');
    initDisplace();

    // Event listener to expand/collapse the sidebar.
    expandCollapseButton.addEventListener('click', () => expandCollapseSidebar(!isNavExpanded()));

    // Safari does not give focus to <button> elements on click (other browsers
    // do). This event listener normalizes the behavior across browsers.
    sidebar.addEventListener('click', e => {
      if (e.target.matches('button, button *')) {
        e.target.closest('button').focus();
      }
    });

    // Add click event listeners to all buttons and then contains the callback
    // to expand / collapse the button's menus.
    sidebar.querySelectorAll('.toolbar-menu__item--has-dropdown > button').forEach(el => el.addEventListener('click', (e) => {
      openCloseSubmenu(e.currentTarget.parentElement);
    }));

    // Gin Custom start ---------------------
    // Show toolbar navigation with shortcut:
    // OPTION + T (Mac) / ALT + T (Windows)
    document.addEventListener('keydown', e => {
      if (e.altKey === true && e.code === 'KeyT') {
        expandCollapseSidebar(!isNavExpanded());
      }
    });
    // Gin Custom end ------------------------
  }

  Drupal.behaviors.ginNavigation = {
    attach(context) {
      once('navigation', '.admin-toolbar', context).forEach(init);
    },
    // Gin Custom start ---------------------
    collapseSidebar() {
      expandCollapseSidebar(false);
    },
    // Gin Custom end ------------------------
  };
})(Drupal, once, FloatingUIDOM);
