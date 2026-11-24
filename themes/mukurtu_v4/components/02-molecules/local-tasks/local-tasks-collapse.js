/**
 * @file
 * Collapses the local tasks tabs into a "more actions" menu on phones.
 *
 * Below md, an item's tabs and export links took up to four rows above its
 * content. This keeps the first tab (View), the active tab, and the first
 * Edit tab in the row, and moves the rest into a menu opened by a "..."
 * toggle. At md and up, or without JavaScript, every tab stays in the row.
 *
 * The toggle comes from a <template> in block--mukurtu-v4-local-tasks.html.twig
 * so its accessible name is translated server-side.
 */
((Drupal, once) => {
  const MD = window.matchMedia('(min-width: 48rem)');

  /**
   * Whether a tab stays in the row when collapsed.
   */
  function keepsItsPlace(li, index, editSeen) {
    const link = li.querySelector('a');
    if (!link) {
      return true;
    }
    if (index === 0 || link.classList.contains('is-active')) {
      return true;
    }
    const path = link.getAttribute('data-drupal-link-system-path') || '';
    return !editSeen && /\/edit$/.test(path);
  }

  function init(root) {
    const template = root.querySelector('template.local-tasks__more-template');
    const list = root.querySelector(':scope > ul, :scope .local-tasks__tabs > ul');
    if (!template || !list) {
      return;
    }
    const exportArea = root.querySelector('.local-tasks__export');

    // Everything that moves, with where it came from, so it can go back.
    const moves = [];
    let editSeen = false;
    Array.from(list.children).forEach((li, index) => {
      const link = li.querySelector('a');
      const keep = keepsItsPlace(li, index, editSeen);
      if (keep && link && /\/edit$/.test(link.getAttribute('data-drupal-link-system-path') || '')) {
        editSeen = true;
      }
      if (!keep) {
        moves.push({ el: li, wrap: false });
      }
    });
    if (exportArea) {
      Array.from(exportArea.children).forEach((el) => moves.push({ el, wrap: true }));
    }
    // Not worth a menu for a single item.
    if (moves.length < 2) {
      return;
    }

    const more = template.content.firstElementChild.cloneNode(true);
    const menu = more.querySelector('.local-tasks__more-menu');
    const moreItem = document.createElement('li');
    moreItem.className = 'local-tasks__more-item';
    moreItem.appendChild(more);

    function collapse() {
      if (moreItem.isConnected) {
        return;
      }
      moves.forEach((move) => {
        move.parent = move.el.parentNode;
        move.next = move.el.nextSibling;
        if (move.wrap) {
          const li = document.createElement('li');
          li.appendChild(move.el);
          menu.appendChild(li);
        }
        else {
          menu.appendChild(move.el);
        }
      });
      list.appendChild(moreItem);
      if (exportArea) {
        exportArea.hidden = true;
      }
      root.classList.add('local-tasks--collapsed');
    }

    function expand() {
      if (!moreItem.isConnected) {
        return;
      }
      more.open = false;
      moreItem.remove();
      // Last first, so each item's original next sibling is already back.
      moves.slice().reverse().forEach((move) => {
        const wrapper = move.wrap ? move.el.parentNode : null;
        move.parent.insertBefore(move.el, move.next);
        if (wrapper) {
          wrapper.remove();
        }
      });
      if (exportArea) {
        exportArea.hidden = false;
      }
      root.classList.remove('local-tasks--collapsed');
    }

    const update = () => (MD.matches ? expand() : collapse());
    MD.addEventListener('change', update);
    update();

    // Close on a click outside, and on Escape with focus back on the toggle
    // (WCAG 2.1.1).
    document.addEventListener('click', (event) => {
      if (more.open && !more.contains(event.target)) {
        more.open = false;
      }
    });
    // Also close once focus moves to something outside the menu, or Tab
    // past its last link would land underneath it (WCAG 2.4.11). Not when
    // focus goes nowhere (relatedTarget null): Safari and Firefox on macOS
    // don't focus a clicked link, so closing then would swallow the click.
    more.addEventListener('focusout', (event) => {
      if (more.open && event.relatedTarget && !more.contains(event.relatedTarget)) {
        more.open = false;
      }
    });
    more.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && more.open) {
        more.open = false;
        more.querySelector('summary').focus();
      }
    });
  }

  Drupal.behaviors.mukurtuLocalTasksCollapse = {
    attach(context) {
      once('local-tasks-collapse', '.local-tasks', context)
        .filter((el) => !el.closest('.glb-body'))
        .forEach(init);
    },
  };
})(Drupal, once);
