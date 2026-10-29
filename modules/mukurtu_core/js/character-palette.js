/**
 * @file
 * The character palette (#2560).
 *
 * Adds a "Characters" button after every input marked with
 * data-mukurtu-character-palette. The button opens a panel of the characters
 * site managers listed on the Character palette settings page, and each
 * character button inserts its character where the cursor was.
 *
 * Mouse and touch insertion keep focus in the field, so the caret stays put
 * and a phone keyboard stays open. Keyboard insertion keeps focus on the
 * character button, so several characters can be inserted in a row; Escape
 * goes back to the field.
 *
 * A textarea with a rich text editor gets the button under the editor, and
 * characters go in through CKEditor's model at the editor's own selection,
 * so undo and the editor's change tracking work (#2561). If the text format
 * is switched to one without an editor, the same button falls back to the
 * textarea itself.
 */
(function (Drupal, drupalSettings, once) {

  const OPEN_KEY = 'mukurtu.characterPalette.open';
  const LANGUAGE_KEY = 'mukurtu.characterPalette.language';

  let counter = 0;

  /**
   * Drupal.t() escapes @ placeholders for HTML. Text set through
   * textContent or an attribute has to be decoded or it shows entities.
   * Drupal.announce() writes HTML, so it gets the escaped string instead.
   */
  function plain(html) {
    return new DOMParser().parseFromString(html, 'text/html').documentElement.textContent;
  }

  // Browser storage can be missing or blocked; the palette works without it.
  function read(key, fallback) {
    try {
      const value = window.localStorage.getItem(key);
      return value === null ? fallback : JSON.parse(value);
    }
    catch (e) {
      return fallback;
    }
  }

  function write(key, value) {
    try {
      window.localStorage.setItem(key, JSON.stringify(value));
    }
    catch (e) {
      // Nothing to remember it in.
    }
  }

  /**
   * Returns the CKEditor 5 instance managing a textarea, if there is one now.
   *
   * Looked up each time, because the editor is created after behaviors run
   * and is destroyed and recreated when the text format changes.
   */
  function editorFor(input) {
    const id = input.getAttribute('data-ckeditor5-id');
    return id && Drupal.CKEditor5Instances ? Drupal.CKEditor5Instances.get(id) : null;
  }

  /**
   * Whether a textarea can have a rich text editor: its text format select
   * points at it.
   */
  function hasEditorFormat(input) {
    return Boolean(input.id && document.querySelector('[data-editor-for="' + CSS.escape(input.id) + '"]'));
  }

  /**
   * Builds the button and panel for one input.
   */
  function attach(input, groups) {
    counter += 1;
    const panelId = 'mukurtu-character-palette-' + counter;
    const fieldKey = input.name || input.id || '';
    const fieldLabel = input.getAttribute('data-mukurtu-character-palette');
    let selection = {start: input.value.length, end: input.value.length};

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'mukurtu-character-palette__toggle';
    toggle.textContent = Drupal.t('Characters');
    if (fieldLabel) {
      toggle.setAttribute('aria-label', plain(Drupal.t('Characters for @field', {'@field': fieldLabel})));
    }
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-controls', panelId);

    const panel = document.createElement('div');
    panel.id = panelId;
    panel.className = 'mukurtu-character-palette';
    panel.setAttribute('role', 'group');
    panel.setAttribute('aria-label', Drupal.t('Character palette'));
    panel.hidden = true;

    const list = document.createElement('div');
    list.className = 'mukurtu-character-palette__characters';

    let groupIndex = 0;
    const savedLanguage = read(LANGUAGE_KEY, null);
    groups.forEach(function (group, index) {
      if (group.label === savedLanguage) {
        groupIndex = index;
      }
    });

    if (groups.length > 1) {
      const selectId = panelId + '-language';
      const wrapper = document.createElement('div');
      wrapper.className = 'mukurtu-character-palette__language';
      const label = document.createElement('label');
      label.htmlFor = selectId;
      label.textContent = Drupal.t('Language');
      const select = document.createElement('select');
      select.id = selectId;
      groups.forEach(function (group, index) {
        const option = document.createElement('option');
        option.value = String(index);
        option.textContent = group.label;
        select.appendChild(option);
      });
      select.value = String(groupIndex);
      select.addEventListener('change', function () {
        groupIndex = Number(select.value);
        write(LANGUAGE_KEY, groups[groupIndex].label);
        renderCharacters();
      });
      wrapper.appendChild(label);
      wrapper.appendChild(select);
      panel.appendChild(wrapper);
    }
    panel.appendChild(list);

    function renderCharacters() {
      list.textContent = '';
      groups[groupIndex].characters.forEach(function (item) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'mukurtu-character-palette__character';
        button.textContent = item.character;
        if (item.name) {
          button.setAttribute('aria-label', item.name);
          button.title = item.name;
        }
        // A mouse or a finger would otherwise take focus from the field,
        // which loses the caret and closes a phone's keyboard.
        button.addEventListener('pointerdown', function (event) {
          if (fieldHasFocus()) {
            event.preventDefault();
          }
        });
        button.addEventListener('click', function () {
          insert(item);
        });
        list.appendChild(button);
      });
    }

    function fieldHasFocus() {
      const editor = editorFor(input);
      if (editor) {
        return editor.ui.view.editable.element.contains(document.activeElement);
      }
      return document.activeElement === input;
    }

    function focusField() {
      const editor = editorFor(input);
      if (editor) {
        editor.editing.view.focus();
      }
      else {
        input.focus();
      }
    }

    function remember() {
      if (typeof input.selectionStart === 'number') {
        selection = {start: input.selectionStart, end: input.selectionEnd};
      }
    }
    ['select', 'keyup', 'mouseup', 'input', 'blur'].forEach(function (type) {
      input.addEventListener(type, remember);
    });

    function insert(item) {
      const editor = editorFor(input);
      if (editor) {
        // The editor keeps its own selection while focus is elsewhere.
        editor.model.change(function (writer) {
          editor.model.insertContent(writer.createText(item.character));
        });
        Drupal.announce(Drupal.t('Inserted @character.', {'@character': item.name || item.character}));
        return;
      }
      if (document.activeElement === input) {
        remember();
      }
      const length = input.value.length;
      const start = Math.min(selection.start, length);
      const end = Math.min(selection.end, length);
      input.setRangeText(item.character, start, end, 'end');
      selection = {start: input.selectionStart, end: input.selectionEnd};
      input.dispatchEvent(new Event('input', {bubbles: true}));
      Drupal.announce(Drupal.t('Inserted @character.', {'@character': item.name || item.character}));
    }

    function setOpen(open) {
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (fieldKey) {
        const openFields = read(OPEN_KEY, []).filter(function (key) {
          return key !== fieldKey;
        });
        if (open) {
          openFields.push(fieldKey);
        }
        write(OPEN_KEY, openFields);
      }
    }

    toggle.addEventListener('click', function () {
      setOpen(panel.hidden);
    });

    function closeOnEscape(event) {
      if (event.key === 'Escape' && !panel.hidden) {
        event.preventDefault();
        setOpen(false);
        focusField();
      }
    }
    panel.addEventListener('keydown', closeOnEscape);
    toggle.addEventListener('keydown', closeOnEscape);

    renderCharacters();
    // Under a rich text editor rather than between the hidden textarea and
    // the editor, whichever of the two attached first.
    const anchor = input.closest('[data-mukurtu-character-palette-anchor]')
      || (hasEditorFormat(input) && input.closest('.js-form-item'))
      || input;
    anchor.after(toggle, panel);

    if (fieldKey && read(OPEN_KEY, []).indexOf(fieldKey) !== -1) {
      panel.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
    }
  }

  /**
   * Attaches the palette to marked inputs.
   */
  Drupal.behaviors.mukurtuCharacterPalette = {
    attach(context) {
      const groups = (drupalSettings.mukurtuCharacterPalette || {}).groups || [];
      if (!groups.length) {
        return;
      }
      once('mukurtu-character-palette', '[data-mukurtu-character-palette]', context).forEach(function (input) {
        if (input.disabled || input.readOnly || input.type === 'hidden' || typeof input.setRangeText !== 'function') {
          return;
        }
        // Widgets such as the map keep their data in a textarea hidden on
        // purpose, and typing into it would be lost. A rich text editor hides
        // its textarea too, but the palette writes to the editor instead. A
        // field inside a closed section is hidden by its parent, not itself,
        // and still gets the palette.
        if (window.getComputedStyle(input).display === 'none' && !hasEditorFormat(input)) {
          return;
        }
        attach(input, groups);
      });
    },
  };

})(Drupal, drupalSettings, once);
