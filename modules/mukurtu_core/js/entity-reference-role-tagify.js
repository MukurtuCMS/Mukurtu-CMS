/**
 * @file
 * Keeps one role box per Tagify chip for the "Tagify with roles" widget.
 *
 * Reads the chips from the Tagify input's own value (a JSON array, in chip
 * order) rather than from Tagify internals, and listens for the native
 * "change" event Tagify fires on that input whenever chips are added,
 * removed or dragged. Roles are written to a hidden field as a JSON array in
 * the same order, which the widget pairs back up on the server.
 */
(function (Drupal, once) {
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
   * A stable key for a chip, so its role follows it when chips move.
   */
  function chipKey(tag) {
    return tag.entity_id ? `id:${tag.entity_id}` : `new:${tag.value}`;
  }

  Drupal.behaviors.mukurtuRoleTagify = {
    attach(context) {
      once('mukurtu-role-tagify', '.mukurtu-role-tagify', context).forEach(
        (wrapper) => {
          const names = wrapper.querySelector('input.tagify-widget');
          const rolesField = wrapper.querySelector('.mukurtu-role-tagify__roles');
          const list = wrapper.querySelector('.mukurtu-role-tagify__list');
          const prototype = list?.querySelector(
            '.mukurtu-role-tagify__prototype input',
          );
          if (!names || !rolesField || !list || !prototype) {
            return;
          }
          const labelPattern = list.dataset.roleLabel || '@name';
          const rowsContainer = document.createElement('div');
          rowsContainer.className = 'mukurtu-role-tagify__rows';
          list.appendChild(rowsContainer);

          // Seed each chip's role from the server-rendered value.
          const roles = new Map();
          const initialRoles = parseList(rolesField.value);
          parseList(names.value).forEach((tag, index) => {
            roles.set(chipKey(tag), initialRoles[index] || '');
          });

          let uid = 0;

          function serialize() {
            const ordered = parseList(names.value).map(
              (tag) => roles.get(chipKey(tag)) || '',
            );
            rolesField.value = JSON.stringify(ordered);
          }

          function buildRow(tag) {
            const key = chipKey(tag);
            const id = `${prototype.id}--${(uid += 1)}`;
            const row = document.createElement('div');
            row.className = 'mukurtu-role-tagify__row form-item';

            const label = document.createElement('label');
            label.className = 'form-item__label';
            label.htmlFor = id;
            label.textContent = labelPattern.replace(
              '@name',
              tag.label || tag.value || '',
            );

            const input = prototype.cloneNode(false);
            input.id = id;
            input.removeAttribute('name');
            input.removeAttribute('data-drupal-selector');
            // A clone carries the prototype's once() marker, which would stop
            // Drupal's autocomplete from attaching to it.
            input.removeAttribute('data-once');
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

            row.append(label, input);
            return row;
          }

          function render() {
            const tags = parseList(names.value);
            rowsContainer.replaceChildren(...tags.map(buildRow));
            list.hidden = tags.length === 0;
            Drupal.attachBehaviors(rowsContainer);
            serialize();
          }

          names.addEventListener('change', render);
          // Catch anything typed but not yet committed by a change event.
          names.form?.addEventListener('submit', serialize, true);
          render();
        },
      );
    },
  };
})(Drupal, once);
