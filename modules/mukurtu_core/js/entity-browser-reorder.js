/**
 * @file
 * Adds buttons to reorder Entity Browser items without dragging (#2498).
 *
 * Entity Browser lists could only be reordered by dragging, which a keyboard
 * user can't do at all and WCAG 2.2 SC 2.5.7 asks to have an alternative for.
 * Each item gets a pair of arrow buttons that move it one place earlier or
 * later. The order is saved with Entity Browser's own reorder function, the
 * same one its drag handler calls, so nothing reloads.
 *
 * Appended to entity_browser/entity_reference by
 * mukurtu_core_library_info_alter(), after entity_browser.entity_reference.js
 * defines Drupal.entityBrowserEntityReference.entitiesReordered().
 */
((Drupal, once) => {
  const LIST =
    '.field--widget-entity-browser-entity-reference .entities-list.sortable';

  // Chevrons pointing toward the start and end of the line. CSS turns them
  // to point up and down in the list view, and mirrors them for
  // right-to-left languages in the grid view.
  const ICONS = {
    earlier:
      '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    later:
      '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="m6 3 5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  };

  /**
   * Decodes the HTML entities Drupal.t() adds to "@" placeholders.
   *
   * Needed for text that goes into attributes, where entities would be read
   * out literally. DOMParser doesn't run scripts.
   */
  function plain(markup) {
    return new DOMParser().parseFromString(markup, 'text/html')
      .documentElement.textContent;
  }

  /**
   * The items in a list, in order.
   *
   * Filters on the class so Sortable's drag copy, which is a clone of an
   * item, can never count as one.
   */
  function itemsOf(list) {
    return Array.from(list.children).filter(
      (element) =>
        element.classList.contains('item-container') &&
        !element.classList.contains('sortable-fallback'),
    );
  }

  /**
   * The item's title as editors see it.
   */
  function titleOf(item) {
    const heading = item.querySelector('.eb-form-selection-card__title');
    let text;
    if (heading) {
      text = heading.textContent;
    } else {
      // Label-only widgets have no card, just the label and the buttons.
      const copy = item.cloneNode(true);
      copy
        .querySelectorAll('input, button, .mukurtu-eb-move')
        .forEach((control) => control.remove());
      text = copy.textContent;
    }
    return text.replace(/\s+/g, ' ').trim();
  }

  /**
   * Disables the arrows that would move an item off either end of the list.
   */
  function refresh(list) {
    const items = itemsOf(list);
    items.forEach((item, index) => {
      const earlier = item.querySelector('.mukurtu-eb-move__earlier');
      const later = item.querySelector('.mukurtu-eb-move__later');
      if (earlier && later) {
        earlier.disabled = index === 0;
        later.disabled = index === items.length - 1;
      }
    });
  }

  /**
   * Moves an item one place, saves the order and tells screen readers.
   */
  function move(item, button, direction) {
    const list = item.parentElement;
    const items = itemsOf(list);
    const index = items.indexOf(item);
    const neighbour = items[index + direction];
    if (!neighbour) {
      return;
    }

    list.insertBefore(item, direction < 0 ? neighbour : neighbour.nextSibling);
    Drupal.entityBrowserEntityReference.entitiesReordered(list);
    refresh(list);

    // Moving the item takes focus off the button that was pressed. Put it
    // back, or on the other arrow if this one just hit the end of the list.
    const other = item.querySelector(
      button.classList.contains('mukurtu-eb-move__earlier')
        ? '.mukurtu-eb-move__later'
        : '.mukurtu-eb-move__earlier',
    );
    (button.disabled ? other : button).focus();

    // Drupal.announce() writes with innerHTML, so it gets the escaped text.
    Drupal.announce(
      Drupal.t('@title moved to position @position of @total.', {
        '@title': titleOf(item),
        '@position': itemsOf(list).indexOf(item) + 1,
        '@total': items.length,
      }),
    );
  }

  /**
   * Builds one arrow button.
   */
  function arrow(item, direction) {
    const button = document.createElement('button');
    const name = direction < 0 ? 'earlier' : 'later';
    button.type = 'button';
    button.className = `mukurtu-eb-move__button mukurtu-eb-move__${name}`;
    const title = titleOf(item);
    button.setAttribute(
      'aria-label',
      plain(
        direction < 0
          ? Drupal.t('Move @title earlier', { '@title': title })
          : Drupal.t('Move @title later', { '@title': title }),
      ),
    );
    button.innerHTML = ICONS[name];
    button.addEventListener('click', () => move(item, button, direction));
    return button;
  }

  Drupal.behaviors.mukurtuEntityBrowserReorder = {
    attach(context) {
      once('mukurtu-eb-reorder', LIST, context).forEach((list) => {
        itemsOf(list).forEach((item) => {
          const group = document.createElement('div');
          group.className = 'mukurtu-eb-move';
          group.append(arrow(item, -1), arrow(item, 1));
          item.append(group);
        });
        refresh(list);

        // A drag changes the order too, so the end arrows have to follow.
        // entity-browser-sortable.js fires this after each drag.
        list.addEventListener('mukurtu-eb-reordered', () => refresh(list));
      });
    },
  };
})(Drupal, once);
