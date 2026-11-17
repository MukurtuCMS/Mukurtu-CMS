/**
 * @file
 * Adds buttons to reorder paragraphs without dragging (#2738).
 *
 * Paragraphs are reordered by dragging a handle, as in any Drupal table of
 * multiple values. Each row gets the same pair of arrow buttons as Entity
 * Browser items (#2498), beside the handle, to move it one place earlier or
 * later. The move goes through tabledrag's own row swap and drop, as a drag
 * does, so the weights and the "changed" marker are updated the same way.
 */
((Drupal, once) => {
  const TABLE = '.field--widget-paragraphs table.field-multiple-table';

  // The same chevrons as entity-browser-reorder.js. CSS turns them to point
  // up and down.
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
   * The table's own rows, in order, not those of paragraphs nested in them.
   */
  function rowsOf(table) {
    return Array.from(table.tBodies[0]?.rows || []).filter((row) =>
      row.classList.contains('draggable'),
    );
  }

  /**
   * The row's own paragraph type label, not one of a paragraph nested in it.
   */
  function typeLabelOf(row) {
    return Array.from(row.querySelectorAll('.paragraph-type-label')).find(
      (label) => label.closest('tr') === row,
    );
  }

  /**
   * The paragraph's name: its Title field if it has one, or its type.
   */
  function titleOf(row) {
    const title = Array.from(
      row.querySelectorAll('input[name$="[subform][field_title][0][value]"]'),
    ).find((input) => input.closest('tr') === row);
    const value = title?.value.trim();
    if (value) {
      return value;
    }
    const type = typeLabelOf(row);
    return type ? type.textContent.trim() : '';
  }

  /**
   * Names the buttons after their paragraph, and disables the ones that
   * would move it off either end of the table.
   */
  function refresh(table) {
    const rows = rowsOf(table);
    rows.forEach((row, index) => {
      const cell = row.querySelector(':scope > td');
      const earlier = cell?.querySelector('.mukurtu-paragraphs-move__earlier');
      const later = cell?.querySelector('.mukurtu-paragraphs-move__later');
      if (!earlier || !later) {
        return;
      }
      const title = titleOf(row);
      earlier.setAttribute('aria-label', plain(Drupal.t('Move @title earlier', { '@title': title })));
      later.setAttribute('aria-label', plain(Drupal.t('Move @title later', { '@title': title })));
      earlier.disabled = index === 0;
      later.disabled = index === rows.length - 1;
    });
  }

  /**
   * Moves a row one place, saves the order and tells screen readers.
   */
  function move(table, row, button, direction) {
    const tableDrag = Drupal.tableDrag?.[table.id];
    const rows = rowsOf(table);
    const neighbour = rows[rows.indexOf(row) + direction];
    if (!tableDrag || !neighbour) {
      return;
    }

    tableDrag.rowObject = new tableDrag.row(
      row,
      'keyboard',
      tableDrag.indentEnabled,
      tableDrag.maxDepth,
      true,
    );
    tableDrag.rowObject.swap(direction < 0 ? 'before' : 'after', neighbour);
    // Saves the weights and marks the row changed, then calls onDrop(),
    // which refreshes the arrows.
    tableDrag.dropRow(null, tableDrag);
    tableDrag.restripeTable();

    // Moving the row takes focus off the button that was pressed. Put it
    // back, or on the other arrow if this one just hit the end of the table.
    const other = button.closest('td').querySelector(
      button.classList.contains('mukurtu-paragraphs-move__earlier')
        ? '.mukurtu-paragraphs-move__later'
        : '.mukurtu-paragraphs-move__earlier',
    );
    (button.disabled ? other : button).focus();

    // Drupal.announce() writes with innerHTML, so it gets the escaped text.
    Drupal.announce(
      Drupal.t('@title moved to position @position of @total.', {
        '@title': titleOf(row),
        '@position': rowsOf(table).indexOf(row) + 1,
        '@total': rows.length,
      }),
    );
  }

  /**
   * Builds one arrow button.
   */
  function arrow(table, row, direction) {
    const button = document.createElement('button');
    const name = direction < 0 ? 'earlier' : 'later';
    button.type = 'button';
    button.className = `mukurtu-paragraphs-move__button mukurtu-paragraphs-move__${name}`;
    button.innerHTML = ICONS[name];
    button.addEventListener('click', () => move(table, row, button, direction));
    return button;
  }

  Drupal.behaviors.mukurtuParagraphsReorder = {
    attach(context) {
      once('mukurtu-paragraphs-reorder', TABLE, context).forEach((table) => {
        const rows = rowsOf(table);
        // One paragraph has nowhere to move. Plain multiple-value fields
        // inside a paragraph use the same table, but their rows aren't
        // paragraphs and have no name to give the buttons.
        if (rows.length < 2 || !rows.every(typeLabelOf)) {
          return;
        }
        rows.forEach((row) => {
          const handle = row.querySelector(':scope > td .tabledrag-handle');
          if (!handle) {
            return;
          }
          // Beside the handle, inside the wrapper Claro and Gin style.
          handle.parentElement.classList.add('mukurtu-paragraphs-move');
          handle.before(arrow(table, row, -1));
          handle.after(arrow(table, row, 1));
        });
        refresh(table);

        // A drag, or a move with the handle and arrow keys, changes the order
        // too. Tabledrag calls onDrop() when either finishes.
        const tableDrag = Drupal.tableDrag?.[table.id];
        if (tableDrag) {
          const { onDrop } = tableDrag;
          tableDrag.onDrop = function (...args) {
            const result = onDrop.apply(this, args);
            refresh(table);
            return result;
          };
        }
        // A title the editor types changes the row's name.
        table.addEventListener('change', (event) => {
          if (event.target.matches('input[name$="[field_title][0][value]"]')) {
            refresh(table);
          }
        });
      });
    },
  };
})(Drupal, once);
