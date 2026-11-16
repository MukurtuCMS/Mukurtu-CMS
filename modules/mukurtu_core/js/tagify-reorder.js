/**
 * @file
 * Adds buttons to reorder Tagify tags without dragging (#2710).
 *
 * Tags in a multi-value Tagify field could only be reordered by dragging,
 * which a keyboard user can't do at all and WCAG 2.2 SC 2.5.7 asks to have an
 * alternative for. Each tag gets a pair of arrow buttons that move it one
 * place earlier or later. The order is saved the same way the Tagify
 * module's drag handler saves it, by reading the tags back from the page.
 *
 * Follows the Entity Browser arrows from #2498 (entity-browser-reorder.js)
 * and reuses their strings.
 */
((Drupal) => {
  // Chevrons pointing toward the start and end of the line. CSS mirrors them
  // for right-to-left languages.
  const ICONS = {
    earlier:
      '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    later:
      '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="m6 3 5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  };

  /**
   * Decodes HTML entities, for text that goes into attributes.
   *
   * Drupal.t() escapes "@" placeholders, and Tagify escapes tag labels.
   * DOMParser doesn't run scripts.
   */
  function plain(markup) {
    return new DOMParser().parseFromString(String(markup), 'text/html')
      .documentElement.textContent;
  }

  /**
   * The tags in a field, in order.
   *
   * Skips Sortable's drag copy and tags Tagify is still removing.
   */
  function tagsOf(tagify) {
    return tagify.getTagElms().filter(
      (tag) =>
        !tag.classList.contains('sortable-fallback') &&
        !tag.classList.contains(tagify.settings.classNames.tagHide),
    );
  }

  /**
   * The tag's name as editors see it.
   */
  function titleOf(tagify, tag) {
    const data = tagify.getSetTagData(tag) || {};
    // Entity tags store the entity ID as their value; use the label.
    return plain(data.label ?? data.value ?? '').replace(/\s+/g, ' ').trim();
  }

  /**
   * Moves a tag one place, saves the order and tells screen readers.
   */
  function move(tagify, tag, button, direction) {
    const tags = tagsOf(tagify);
    const index = tags.indexOf(tag);
    const neighbour = tags[index + direction];
    if (!neighbour) {
      return;
    }

    tag.parentNode.insertBefore(
      tag,
      direction < 0 ? neighbour : neighbour.nextSibling,
    );
    // The Tagify module's drag handler saves the order the same way.
    tagify.updateValueByDOMTags();
    refresh(tagify);

    // Put focus back on the button that was pressed, or on the other arrow
    // if this one just hit the end of the list.
    const other = tag.querySelector(
      button.classList.contains('mukurtu-tagify-move__earlier')
        ? '.mukurtu-tagify-move__later'
        : '.mukurtu-tagify-move__earlier',
    );
    (button.disabled ? other : button).focus();

    // Drupal.announce() writes with innerHTML, so it gets the escaped text.
    Drupal.announce(
      Drupal.t('@title moved to position @position of @total.', {
        '@title': titleOf(tagify, tag),
        '@position': tagsOf(tagify).indexOf(tag) + 1,
        '@total': tags.length,
      }),
    );
  }

  /**
   * Builds one arrow button.
   */
  function arrow(tagify, tag, direction) {
    const button = document.createElement('button');
    const name = direction < 0 ? 'earlier' : 'later';
    button.type = 'button';
    button.className = `mukurtu-tagify-move__button mukurtu-tagify-move__${name}`;
    button.innerHTML = ICONS[name];
    button.addEventListener('click', (event) => {
      // Keep Tagify from treating the click as a click on the tag.
      event.stopPropagation();
      move(tagify, tag, button, direction);
    });
    return button;
  }

  /**
   * Gives each tag its arrows, and names and enables them for its position.
   */
  function refresh(tagify) {
    const tags = tagsOf(tagify);
    tags.forEach((tag, index) => {
      let group = tag.querySelector(':scope > .mukurtu-tagify-move');
      if (!group) {
        group = document.createElement('span');
        group.className = 'mukurtu-tagify-move';
        group.append(arrow(tagify, tag, -1), arrow(tagify, tag, 1));
        // A press on an arrow mustn't start a drag, or move focus to the
        // Tagify input.
        ['pointerdown', 'mousedown', 'touchstart'].forEach((type) => {
          group.addEventListener(type, (event) => event.stopPropagation());
        });
        tag.append(group);
      }
      // With one tag there is nothing to reorder.
      group.hidden = tags.length < 2;

      const title = titleOf(tagify, tag);
      const earlier = group.querySelector('.mukurtu-tagify-move__earlier');
      const later = group.querySelector('.mukurtu-tagify-move__later');
      earlier.setAttribute(
        'aria-label',
        plain(Drupal.t('Move @title earlier', { '@title': title })),
      );
      later.setAttribute(
        'aria-label',
        plain(Drupal.t('Move @title later', { '@title': title })),
      );
      earlier.disabled = index === 0;
      later.disabled = index === tags.length - 1;
    });
  }

  /**
   * Whether a Tagify field holds an ordered list of tags.
   */
  function isReorderable(original) {
    // Single-value fields are marked "limited" by the Tagify widget.
    if (original.classList.contains('tagify--limited')) {
      return false;
    }
    // "Tagify with roles" shows its own rows, with their own arrows, unless
    // roles are turned off for the field.
    const roles = original.closest('.mukurtu-role-tagify');
    return !roles || roles.classList.contains('mukurtu-role-tagify--names-only');
  }

  /**
   * Sets up the arrows on each ready Tagify field in a context.
   */
  function setUp(context) {
    const root = context && context.querySelectorAll ? context : document;
    root.querySelectorAll('input.tagify-widget').forEach((original) => {
      const tagify = original.__tagify;
      // Marked only once set up, so a field whose Tagify starts later is
      // picked up on the next pass.
      if (!tagify || original.__mukurtuReorder || !isReorderable(original)) {
        return;
      }
      original.__mukurtuReorder = true;
      refresh(tagify);
      // Tags are added, removed, dragged and redrawn as direct children of
      // Tagify's wrapper. The arrows live inside the tags, so adding them
      // doesn't trigger this again.
      let pending = false;
      new MutationObserver(() => {
        if (pending) {
          return;
        }
        pending = true;
        requestAnimationFrame(() => {
          pending = false;
          refresh(tagify);
        });
      }).observe(tagify.DOM.scope, { childList: true });
    });
  }

  Drupal.behaviors.mukurtuTagifyReorder = {
    attach(context) {
      // Tagify can start later in the same attach cycle, so run again at the
      // end of the tick, as mukurtu-tagify-override.js does.
      setUp(context);
      setTimeout(() => setUp(context), 0);
    },
  };
})(Drupal);
