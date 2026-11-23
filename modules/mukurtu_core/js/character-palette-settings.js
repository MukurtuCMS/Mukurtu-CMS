/**
 * @file
 * Adds arrow buttons to the Character palette settings tables (#2560).
 *
 * Rows can be dragged, and these buttons give the same move without dragging
 * (WCAG 2.5.7). Each press is a one-place drag as far as core's table drag
 * code goes, so weights and the "unsaved changes" warning work the same way.
 * Anything that moves a row also renumbers the row's labels here, since they
 * say which character it is.
 */
(($, Drupal, once) => {
  const ICONS = {
    up: '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="m3 10 5-5 5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    down: '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="m3 6 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  };

  /**
   * Decodes the HTML entities Drupal.t() adds to "@" placeholders.
   *
   * Needed for text set as plain text or attributes, where entities would be
   * shown as typed. DOMParser doesn't run scripts.
   */
  function plain(markup) {
    return new DOMParser().parseFromString(markup, 'text/html')
      .documentElement.textContent;
  }

  /**
   * The rows of a table, in order.
   */
  function rowsOf(table) {
    return Array.from(table.tBodies[0].rows);
  }

  /**
   * Sets a box's hidden label.
   */
  function label(row, name, text) {
    const input = row.querySelector(`input[name$="[${name}]"]`);
    if (input && input.labels[0]) {
      input.labels[0].textContent = plain(text);
    }
  }

  /**
   * Brings the arrows and labels in line with the rows' current order.
   */
  function refresh(table) {
    const rows = rowsOf(table);
    rows.forEach((row, index) => {
      const number = index + 1;
      const args = { '@number': number, '@group': table.dataset.group };
      const up = row.querySelector('.mukurtu-character-move__up');
      const down = row.querySelector('.mukurtu-character-move__down');
      const upName = plain(Drupal.t('Move character @number up', args));
      const downName = plain(Drupal.t('Move character @number down', args));

      up.disabled = index === 0;
      up.title = upName;
      up.setAttribute('aria-label', upName);
      down.disabled = index === rows.length - 1;
      down.title = downName;
      down.setAttribute('aria-label', downName);

      label(row, 'character', Drupal.t('Character @number in @group', args));
      label(
        row,
        'name',
        Drupal.t('Name for character @number in @group', args),
      );
      label(
        row,
        'weight',
        Drupal.t('Order for character @number in @group', args),
      );
    });
  }

  /**
   * Leaves one "unsaved changes" warning on each table that has changes.
   *
   * Gin adds the warning above every table on the page, not just the one that
   * changed. This page has one table per language, and each copy is an alert,
   * so screen readers would hear it once for each language.
   */
  function tidyWarnings(form) {
    form.querySelectorAll('.mukurtu-character-table').forEach((table) => {
      const changed = table.querySelector('.tabledrag-changed');
      table
        .closest('fieldset')
        .querySelectorAll('.tabledrag-changed-warning')
        .forEach((warning, index) => {
          if (!changed || index > 0) {
            warning.remove();
          }
        });
    });
  }

  /**
   * Moves a row one place and tells screen readers where it landed.
   */
  function move(button, direction) {
    const row = button.closest('tr');
    const table = row.closest('table');
    const drag = Drupal.tableDrag[table.id];
    const rows = rowsOf(table);
    const neighbour = rows[rows.indexOf(row) + direction];
    if (!neighbour) {
      return;
    }

    // eslint-disable-next-line new-cap
    drag.rowObject = new drag.row(row, 'keyboard', false, 0, true);
    drag.rowObject.swap(direction < 0 ? 'before' : 'after', neighbour);
    // Saves the new order and calls refresh() through onDrop.
    drag.dropRow(null, drag);

    // Moving the row takes focus off the button that was pressed. Put it
    // back, or on the other arrow if this one just hit the end of the list.
    const other = row.querySelector(
      direction < 0
        ? '.mukurtu-character-move__down'
        : '.mukurtu-character-move__up',
    );
    (button.disabled ? other : button).focus();

    Drupal.announce(
      Drupal.t('Moved to position @position of @total.', {
        '@position': rowsOf(table).indexOf(row) + 1,
        '@total': rows.length,
      }),
    );
  }

  /**
   * Builds one arrow button.
   */
  function arrow(name, direction) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `mukurtu-character-move__button mukurtu-character-move__${name}`;
    button.innerHTML = ICONS[name];
    button.addEventListener('click', () => move(button, direction));
    return button;
  }

  Drupal.behaviors.mukurtuCharacterPaletteSettings = {
    attach(context) {
      once(
        'mukurtu-character-move',
        '.mukurtu-character-table',
        context,
      ).forEach((table) => {
        rowsOf(table).forEach((row) => {
          row
            .querySelector('td.mukurtu-character-move')
            .append(arrow('up', -1), arrow('down', 1));
        });
        refresh(table);
        const drag = Drupal.tableDrag[table.id];
        // "Show row weights" is remembered site-wide and removes the handles.
        // Its toggle is hidden here, so this page must not inherit that.
        drag.hideColumns();
        drag.onDrop = () => {
          refresh(table);
          tidyWarnings(table.closest('form'));
        };

        // Add new row replaces a table. Core marks its changed rows again but
        // not the warning, and the moves are still unsaved.
        if (table.querySelector('.tabledrag-changed')) {
          // eslint-disable-next-line new-cap
          new drag.row(rowsOf(table)[0], '', false, 0, true).addChangedWarning();
          drag.changed = true;
          tidyWarnings(table.closest('form'));
        }
      });
    },
  };
})(jQuery, Drupal, once);
