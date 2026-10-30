/**
 * @file
 * The character palette, floating version (#502 experiment).
 *
 * One Á̱ button sits in a corner of the page. It opens a floating panel of
 * the characters site managers listed on the Character palette settings
 * page, and each character goes into whichever marked field had focus last:
 * a text field, a rich text editor or a Tagify box. Ctrl+Shift+Space jumps
 * from a field to the panel, and Escape goes back with the caret where it
 * was. "Keep open" leaves the panel up while people type.
 *
 * Mouse and touch insertion keep focus in the field, so the caret stays put
 * and a phone keyboard stays open.
 *
 * Fields are marked server side with data-mukurtu-character-palette (see
 * CharacterPalette.php). Each marked field gets a target here that knows how
 * to insert into it:
 * - A plain input or textarea: at the remembered selection, then an input
 *   event so other scripts see the change.
 * - A rich text editor: through CKEditor's model at its own selection, so
 *   undo works.
 * - A Tagify box: into its contenteditable box at the caret, then an input
 *   event so its suggestions update. Tagify turns typed text into a tag on
 *   blur; that waits while focus is in the palette, and happens when focus
 *   moves on from the palette instead.
 */
(function (Drupal, drupalSettings, once) {

  const PIN_KEY = 'mukurtu.characterPalette.pinned';
  const LANGUAGE_KEY = 'mukurtu.characterPalette.language';
  const SHORTCUT = 'Ctrl+Shift+Space';

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
   * Returns the Tagify instance managing an input, if there is one now.
   */
  function tagifyFor(input) {
    return input.classList.contains('tagify-widget') && input.__tagify ? input.__tagify : null;
  }

  /**
   * Whether a textarea can have a rich text editor: its text format select
   * points at it.
   */
  function hasEditorFormat(input) {
    return Boolean(input.id && document.querySelector('[data-editor-for="' + CSS.escape(input.id) + '"]'));
  }

  function announceInsert(item) {
    Drupal.announce(Drupal.t('Inserted @character.', {'@character': item.name || item.character}));
  }

  // The page's one palette, built on first use.
  let palette = null;
  // Every marked field, and the one that had focus last.
  const targets = [];
  let current = null;

  /**
   * Whether an element is part of the palette.
   */
  function inPalette(element) {
    return Boolean(palette && element && (palette.button.contains(element) || palette.panel.contains(element)));
  }

  /**
   * Makes the target for one marked field.
   */
  function makeTarget(input) {
    let selection = {start: input.value.length, end: input.value.length};
    // Where the caret was in a Tagify box, as offsets into its text.
    let tagifySelection = null;

    function rememberInput() {
      if (typeof input.selectionStart === 'number') {
        selection = {start: input.selectionStart, end: input.selectionEnd};
      }
    }
    ['select', 'keyup', 'mouseup', 'input', 'blur'].forEach(function (type) {
      input.addEventListener(type, rememberInput);
    });

    function rememberTagify() {
      const tagify = tagifyFor(input);
      const range = window.getSelection().rangeCount ? window.getSelection().getRangeAt(0) : null;
      if (!tagify || !range || !tagify.DOM.input.contains(range.startContainer)) {
        return;
      }
      const before = document.createRange();
      before.selectNodeContents(tagify.DOM.input);
      before.setEnd(range.startContainer, range.startOffset);
      const start = before.toString().length;
      tagifySelection = {start: start, end: start + range.toString().length};
    }

    function placeTagifyCaret(tagify) {
      const text = tagify.DOM.input.firstChild;
      if (!text || !tagifySelection) {
        return;
      }
      const range = document.createRange();
      range.setStart(text, Math.min(tagifySelection.end, text.length));
      range.collapse(true);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
    }

    const target = {
      input: input,

      contains(element) {
        const tagify = tagifyFor(input);
        if (tagify) {
          return tagify.DOM.scope.contains(element);
        }
        const editor = editorFor(input);
        if (editor) {
          return editor.ui.view.editable.element.contains(element);
        }
        return element === input;
      },

      hasFocus() {
        return target.contains(document.activeElement);
      },

      focus() {
        const tagify = tagifyFor(input);
        const editor = editorFor(input);
        if (tagify) {
          tagify.DOM.input.focus();
          placeTagifyCaret(tagify);
        }
        else if (editor) {
          editor.editing.view.focus();
        }
        else {
          input.focus();
          input.setSelectionRange(selection.start, selection.end);
        }
      },

      insert(item) {
        const tagify = tagifyFor(input);
        const editor = editorFor(input);
        if (tagify) {
          guardTagifyDropdown(tagify);
          const box = tagify.DOM.input;
          if (document.activeElement === box) {
            rememberTagify();
          }
          const text = box.textContent;
          const start = tagifySelection ? Math.min(tagifySelection.start, text.length) : text.length;
          const end = tagifySelection ? Math.min(tagifySelection.end, text.length) : text.length;
          box.textContent = text.slice(0, start) + item.character + text.slice(end);
          tagifySelection = {start: start + item.character.length, end: start + item.character.length};
          if (document.activeElement === box) {
            placeTagifyCaret(tagify);
          }
          box.dispatchEvent(new Event('input', {bubbles: true}));
          if (!target.hasFocus()) {
            tagify.dropdown.hide();
          }
        }
        else if (editor) {
          // The editor keeps its own selection while focus is elsewhere.
          editor.model.change(function (writer) {
            editor.model.insertContent(writer.createText(item.character));
          });
        }
        else {
          if (document.activeElement === input) {
            rememberInput();
          }
          const length = input.value.length;
          input.setRangeText(item.character, Math.min(selection.start, length), Math.min(selection.end, length), 'end');
          selection = {start: input.selectionStart, end: input.selectionEnd};
          input.dispatchEvent(new Event('input', {bubbles: true}));
        }
        announceInsert(item);
      },
    };

    // The Tagify module shows suggestions whenever a lookup comes back, and
    // an insertion starts one, so they would open over the page while focus
    // is in the palette (WCAG 2.4.11). Suggestions only show while the field
    // itself has focus.
    function guardTagifyDropdown(tagify) {
      if (tagify.__mukurtuPaletteGuard) {
        return;
      }
      tagify.__mukurtuPaletteGuard = true;
      tagify.on('dropdown:show', function () {
        if (!target.hasFocus()) {
          tagify.dropdown.hide();
        }
      });
    }

    if (input.classList.contains('tagify-widget')) {
      // Tagify turns typed text into a tag on blur. A capturing listener on
      // the document runs before Tagify's own, so the setting can be held
      // off for a blur that only moves into the palette.
      document.addEventListener('blur', function (event) {
        const tagify = tagifyFor(input);
        if (!tagify || event.target !== tagify.DOM.input || !inPalette(event.relatedTarget)) {
          return;
        }
        rememberTagify();
        guardTagifyDropdown(tagify);
        tagify.dropdown.hide();
        const setting = tagify.settings.addTagOnBlur;
        tagify.settings.addTagOnBlur = false;
        target.heldText = setting;
        window.setTimeout(function () {
          tagify.settings.addTagOnBlur = setting;
        });
      }, true);
      document.addEventListener('selectionchange', rememberTagify);

      // Leaving the palette for anywhere but the field does what the blur
      // would have done.
      target.commitHeldText = function (to) {
        const tagify = tagifyFor(input);
        if (!target.heldText || !tagify || inPalette(to) || tagify.DOM.scope.contains(to)) {
          return;
        }
        target.heldText = false;
        const text = tagify.DOM.input.textContent.trim();
        if (text && tagify.settings.addTagOn.includes('blur')) {
          tagify.addTags(text, true);
        }
        tagify.dropdown.hide();
      };
    }

    return target;
  }

  /**
   * Builds the page's palette: the corner button and its panel.
   */
  function buildPalette(groups) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'mukurtu-character-palette__toggle mukurtu-character-palette__toggle--floating';
    // A letter with marks above and below reads as "special letters" in any
    // language. It is decoration; the button's name says what it does.
    const icon = document.createElement('span');
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = 'Á̱';
    button.appendChild(icon);
    button.setAttribute('aria-label', Drupal.t('Character palette'));
    button.title = plain(Drupal.t('Character palette (@shortcut)', {'@shortcut': SHORTCUT}));
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', 'mukurtu-character-palette');

    const description = document.createElement('span');
    description.id = 'mukurtu-character-palette-description';
    description.className = 'visually-hidden';
    description.textContent = plain(Drupal.t('Inserts characters into the field you were last typing in. Shortcut: @shortcut.', {'@shortcut': SHORTCUT}));
    button.setAttribute('aria-describedby', description.id);

    const panel = document.createElement('div');
    panel.id = 'mukurtu-character-palette';
    panel.className = 'mukurtu-character-palette mukurtu-character-palette--floating';
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

    const header = document.createElement('div');
    header.className = 'mukurtu-character-palette__language';
    if (groups.length > 1) {
      const label = document.createElement('label');
      label.htmlFor = 'mukurtu-character-palette-language';
      label.className = 'mukurtu-character-palette__label';
      label.textContent = Drupal.t('Language');
      const select = document.createElement('select');
      select.id = 'mukurtu-character-palette-language';
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
      header.append(label, select);
    }

    // "Keep open" leaves the panel up while focus moves between fields.
    const pin = document.createElement('input');
    pin.type = 'checkbox';
    pin.id = 'mukurtu-character-palette-pin';
    pin.checked = Boolean(read(PIN_KEY, false));
    const pinLabel = document.createElement('label');
    pinLabel.htmlFor = pin.id;
    pinLabel.className = 'mukurtu-character-palette__pin';
    pinLabel.prepend(pin);
    pinLabel.append(' ', Drupal.t('Keep open'));
    pin.addEventListener('change', function () {
      write(PIN_KEY, pin.checked);
    });
    header.append(pinLabel);

    panel.append(header, list);

    function renderCharacters() {
      list.textContent = '';
      groups[groupIndex].characters.forEach(function (item) {
        const character = document.createElement('button');
        character.type = 'button';
        character.className = 'mukurtu-character-palette__character';
        character.textContent = item.character;
        if (item.name) {
          character.setAttribute('aria-label', item.name);
          character.title = item.name;
        }
        character.addEventListener('click', function () {
          if (!current || !current.input.isConnected) {
            Drupal.announce(Drupal.t('Choose a field to type in first.'));
            return;
          }
          current.insert(item);
        });
        list.appendChild(character);
      });
    }
    renderCharacters();

    // A mouse or a finger would otherwise take focus from the field, which
    // loses the caret and closes a phone's keyboard.
    [button, panel].forEach(function (element) {
      element.addEventListener('pointerdown', function (event) {
        if (current && current.hasFocus() && event.target.closest('button')) {
          event.preventDefault();
        }
      });
    });

    function open(moveFocus) {
      panel.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      if (moveFocus) {
        const first = panel.querySelector('.mukurtu-character-palette__character');
        (first || panel.querySelector('select, input')).focus();
      }
    }

    function close() {
      panel.hidden = true;
      button.setAttribute('aria-expanded', 'false');
    }

    button.addEventListener('click', function () {
      if (panel.hidden) {
        open(false);
      }
      else {
        close();
      }
    });

    // Escape goes back to the field, closing the panel unless it's kept
    // open. It belongs to the palette here, not to whatever holds the field,
    // such as a map popup that would close too.
    [button, panel].forEach(function (element) {
      element.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
          return;
        }
        event.preventDefault();
        event.stopPropagation();
        if (!pin.checked) {
          close();
        }
        if (current && current.input.isConnected) {
          current.focus();
        }
      });
    });

    // Focus and clicks outside the palette close it unless it's kept open,
    // because it floats over the page (WCAG 2.4.11).
    document.addEventListener('pointerdown', function (event) {
      if (!panel.hidden && !pin.checked && !inPalette(event.target) && !(current && current.contains(event.target))) {
        close();
      }
    });
    [button, panel].forEach(function (element) {
      element.addEventListener('focusout', function (event) {
        targets.forEach(function (target) {
          if (target.commitHeldText) {
            target.commitHeldText(event.relatedTarget);
          }
        });
        if (!panel.hidden && !pin.checked && event.relatedTarget && !inPalette(event.relatedTarget)
          && !(current && current.contains(event.relatedTarget))) {
          close();
        }
      });
    });

    document.body.append(button, description, panel);
    return {button: button, panel: panel, open: open, close: close};
  }

  // The field people were last typing in is where characters go.
  document.addEventListener('focusin', function (event) {
    if (inPalette(event.target)) {
      return;
    }
    for (let i = targets.length - 1; i >= 0; i--) {
      if (!targets[i].input.isConnected) {
        targets.splice(i, 1);
      }
      else if (targets[i].contains(event.target)) {
        current = targets[i];
        return;
      }
    }
  });

  // Ctrl+Shift+Space: from a field to the palette, and back again.
  document.addEventListener('keydown', function (event) {
    if (!palette || event.code !== 'Space' || !event.ctrlKey || !event.shiftKey || event.altKey || event.metaKey) {
      return;
    }
    event.preventDefault();
    if (inPalette(document.activeElement)) {
      if (current && current.input.isConnected) {
        current.focus();
      }
      return;
    }
    palette.open(true);
  });

  /**
   * Registers marked fields and builds the palette.
   */
  Drupal.behaviors.mukurtuCharacterPalette = {
    attach(context) {
      const groups = (drupalSettings.mukurtuCharacterPalette || {}).groups || [];
      if (!groups.length) {
        return;
      }
      if (!palette) {
        palette = buildPalette(groups);
      }
      once('mukurtu-character-palette', '[data-mukurtu-character-palette]', context).forEach(function (input) {
        if (input.disabled || input.readOnly || input.type === 'hidden' || typeof input.setRangeText !== 'function') {
          return;
        }
        // Widgets such as the map keep their data in a textarea hidden on
        // purpose, and typing into it would be lost. A rich text editor hides
        // its textarea too, but the palette writes to the editor instead.
        if (window.getComputedStyle(input).display === 'none' && !hasEditorFormat(input)) {
          return;
        }
        const target = makeTarget(input);
        targets.push(target);
        if (target.hasFocus()) {
          current = target;
        }
      });
    },
  };

})(Drupal, drupalSettings, once);
