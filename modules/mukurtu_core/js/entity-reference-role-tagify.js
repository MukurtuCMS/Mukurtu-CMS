/**
 * @file
 * Shows a "Tagify with roles" field as one row per person.
 *
 * Tagify stays the source of truth for the names: its input (below the rows)
 * adds people, with its usual suggestions and auto-create, and its own chips
 * are hidden. Each row shows a name chip, a remove button, a role box and
 * move buttons. Removing or moving a row acts on Tagify's chips, so Tagify
 * submits the names exactly as it always does.
 *
 * Rows are rebuilt from the Tagify input's own value (a JSON array, in chip
 * order) whenever Tagify fires its native "change" event, so this relies on
 * nothing in Tagify beyond its public methods. Roles are written to a hidden
 * field as a JSON array in the same order, which the widget pairs back up on
 * the server.
 */
(function (Drupal, once, Sortable) {
  /**
   * Parses a JSON array, returning an empty array for anything else.
   */
  function parseList(value) {
    try {
      const list = JSON.parse(value || '[]');
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  /**
   * Turns a translated string into plain text for textContent or attributes.
   *
   * Drupal.t() HTML-escapes "@" placeholders, which is right for markup but
   * would show a name like O'Brien as "O&#39;Brien" here. DOMParser decodes
   * the entities without running scripts. Callers keep literal Drupal.t()
   * calls so the strings are still found for translation.
   */
  function plain(translated) {
    return new DOMParser().parseFromString(translated, 'text/html')
      .documentElement.textContent;
  }

  /**
   * A stable key for a chip, so its role follows it when chips move.
   */
  function chipKey(tag) {
    return tag.entity_id ? `id:${tag.entity_id}` : `new:${tag.value}`;
  }

  /**
   * Builds a small icon button with an accessible name and tooltip.
   */
  function iconButton(className, icon, label) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `mukurtu-role-tagify__button ${className}`;
    button.setAttribute('aria-label', label);
    button.title = label;
    const glyph = document.createElement('span');
    glyph.setAttribute('aria-hidden', 'true');
    glyph.textContent = icon;
    button.appendChild(glyph);
    return button;
  }

  Drupal.behaviors.mukurtuRoleTagify = {
    attach(context) {
      once('mukurtu-role-tagify', '.mukurtu-role-tagify', context).forEach(
        (wrapper) => {
          const names = wrapper.querySelector('input.tagify-widget');
          const rolesField = wrapper.querySelector('.mukurtu-role-tagify__roles');
          const people = wrapper.querySelector('.mukurtu-role-tagify__people');
          const prototype = people?.querySelector(
            '.mukurtu-role-tagify__prototype input',
          );
          if (!names || !rolesField || !people || !prototype) {
            return;
          }

          // With roles turned off for this field, Tagify shows its own chips
          // and only the hidden roles field is kept in step, so each
          // remaining person's existing role survives a save.
          const namesOnly = wrapper.classList.contains(
            'mukurtu-role-tagify--names-only',
          );

          const rows = document.createElement('ul');
          rows.className = 'mukurtu-role-tagify__rows';
          people.appendChild(rows);
          // Name the rows after the field, so "Remove Annie James" in Creator
          // and in People are distinguishable to screen reader users.
          const fieldLabel = wrapper.querySelector(`label[for="${names.id}"]`);
          if (fieldLabel) {
            fieldLabel.id = fieldLabel.id || `${names.id}--label`;
            people.setAttribute('role', 'group');
            people.setAttribute('aria-labelledby', fieldLabel.id);
          }
          // Show the rows between the field label and the Tagify input.
          // Tagify inserts its <tags> box just before the original input.
          const tagifyBox = names.previousElementSibling?.matches('tags')
            ? names.previousElementSibling
            : names;
          if (!namesOnly) {
            tagifyBox.parentNode.insertBefore(people, tagifyBox);
          }
          people.hidden = true;

          // Seed each chip's role from the server-rendered value.
          const roles = new Map();
          const initialRoles = parseList(rolesField.value);
          parseList(names.value).forEach((tag, index) => {
            roles.set(chipKey(tag), initialRoles[index] || '');
          });

          let uid = 0;
          // Which control to focus after the next rebuild, as
          // [row index, control class]. Rebuilding replaces every row, so
          // keyboard users would otherwise lose their place.
          let pendingFocus = null;

          const tagify = () => names.__tagify;

          function serialize() {
            const ordered = parseList(names.value).map(
              (tag) => roles.get(chipKey(tag)) || '',
            );
            rolesField.value = JSON.stringify(ordered);
          }

          /**
           * Reorders Tagify's chips: order[i] is the old index of new chip i.
           */
          function reorder(order) {
            const instance = tagify();
            if (!instance) {
              return;
            }
            const elements = instance.getTagElms();
            const anchor = elements[elements.length - 1].nextSibling;
            order.forEach((from) => {
              anchor.parentNode.insertBefore(elements[from], anchor);
            });
            instance.updateValueByDOMTags();
          }

          function move(index, offset, buttonClass) {
            const count = parseList(names.value).length;
            const target = index + offset;
            if (target < 0 || target >= count) {
              return;
            }
            const order = [...Array(count).keys()];
            [order[index], order[target]] = [order[target], order[index]];
            pendingFocus = [target, buttonClass];
            const tag = parseList(names.value)[index];
            reorder(order);
            // Drupal.announce() writes with innerHTML, so it gets Drupal.t()'s
            // escaped output, not plain().
            Drupal.announce(
              Drupal.t('@name moved to position @position of @count.', {
                '@name': tag.label || tag.value || '',
                '@position': target + 1,
                '@count': count,
              }),
            );
          }

          function remove(index) {
            const instance = tagify();
            if (!instance) {
              return;
            }
            pendingFocus = [index, 'mukurtu-role-tagify__remove'];
            const tag = parseList(names.value)[index];
            instance.removeTags(instance.getTagElms()[index]);
            Drupal.announce(
              Drupal.t('@name removed.', { '@name': tag.label || tag.value || '' }),
            );
          }

          function buildRow(tag, index, count) {
            const key = chipKey(tag);
            const name = tag.label || tag.value || '';
            const row = document.createElement('li');
            row.className = 'mukurtu-role-tagify__row';

            const handle = document.createElement('span');
            handle.className = 'mukurtu-role-tagify__handle';
            handle.setAttribute('aria-hidden', 'true');
            handle.textContent = '⠿';

            const chip = document.createElement('span');
            chip.className = 'mukurtu-role-tagify__chip';
            const chipText = document.createElement('span');
            chipText.className = 'mukurtu-role-tagify__name';
            chipText.textContent = name;
            const removeButton = iconButton(
              'mukurtu-role-tagify__remove',
              '×',
              plain(Drupal.t('Remove @name', { '@name': name })),
            );
            removeButton.addEventListener('click', () => remove(index));
            chip.append(chipText, removeButton);

            const id = `${prototype.id}--${(uid += 1)}`;
            const role = document.createElement('span');
            role.className = 'mukurtu-role-tagify__role';
            const label = document.createElement('label');
            label.className = 'form-item__label';
            label.htmlFor = id;
            label.textContent = plain(Drupal.t('Role'));
            const input = prototype.cloneNode(false);
            input.id = id;
            input.removeAttribute('name');
            input.removeAttribute('data-drupal-selector');
            // A clone carries the prototype's once() marker, which would stop
            // Drupal's autocomplete from attaching to it.
            input.removeAttribute('data-once');
            // The visible label is "Role"; the accessible name says whose.
            input.setAttribute(
              'aria-label',
              plain(Drupal.t('Role for @name', { '@name': name })),
            );
            input.value = roles.get(key) || '';
            const update = () => {
              roles.set(key, input.value);
              serialize();
            };
            input.addEventListener('input', update);
            input.addEventListener('change', update);
            // jQuery UI autocomplete sets the value without an input event,
            // and in Drupal's "Name (ID)" format. Show just the name, as
            // Tagify does; the server matches it within the role vocabulary.
            if (window.jQuery) {
              window.jQuery(input).on('autocompleteclose', () => {
                input.value = input.value.replace(/\s\(\d+\)$/, '');
                update();
              });
            }
            role.append(label, input);

            const up = iconButton(
              'mukurtu-role-tagify__up',
              '↑',
              plain(Drupal.t('Move @name up', { '@name': name })),
            );
            up.disabled = index === 0;
            up.addEventListener('click', () =>
              move(index, -1, 'mukurtu-role-tagify__up'),
            );
            const down = iconButton(
              'mukurtu-role-tagify__down',
              '↓',
              plain(Drupal.t('Move @name down', { '@name': name })),
            );
            down.disabled = index === count - 1;
            down.addEventListener('click', () =>
              move(index, 1, 'mukurtu-role-tagify__down'),
            );
            const moves = document.createElement('span');
            moves.className = 'mukurtu-role-tagify__moves';
            moves.append(up, down);

            row.append(handle, chip, role, moves);
            return row;
          }

          function render() {
            if (namesOnly) {
              serialize();
              return;
            }
            const tags = parseList(names.value);
            rows.replaceChildren(
              ...tags.map((tag, index) => buildRow(tag, index, tags.length)),
            );
            people.hidden = tags.length === 0;
            Drupal.attachBehaviors(rows);
            serialize();

            if (pendingFocus) {
              const [index, buttonClass] = pendingFocus;
              pendingFocus = null;
              const row = rows.children[Math.min(index, tags.length - 1)];
              // Moving to the first or last row disables the button just
              // used, so fall back to the opposite move button. Never fall
              // back to Remove: pressing Enter again would delete the person.
              const fallback = {
                'mukurtu-role-tagify__up': 'mukurtu-role-tagify__down',
                'mukurtu-role-tagify__down': 'mukurtu-role-tagify__up',
              }[buttonClass];
              const target =
                row?.querySelector(`.${buttonClass}:not(:disabled)`) ||
                (fallback && row?.querySelector(`.${fallback}:not(:disabled)`));
              (target || tagify()?.DOM.input)?.focus();
            }
          }

          if (!namesOnly) {
            Sortable.create(rows, {
              handle: '.mukurtu-role-tagify__handle',
              animation: 150,
              onEnd(event) {
                if (event.oldIndex === event.newIndex) {
                  return;
                }
                const order = [...Array(rows.children.length).keys()];
                const [moved] = order.splice(event.oldIndex, 1);
                order.splice(event.newIndex, 0, moved);
                reorder(order);
              },
            });
          }

          names.addEventListener('change', render);
          // Catch anything typed but not yet committed by a change event.
          names.form?.addEventListener('submit', serialize, true);
          render();
        },
      );
    },
  };
})(Drupal, once, Sortable);
