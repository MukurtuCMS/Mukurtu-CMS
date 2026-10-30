/**
 * @file
 * The character palette (#2560).
 *
 * Adds a small "Characters" button (an accented letter) beside every input
 * marked with data-mukurtu-character-palette. The button opens a panel of
 * the characters site managers listed on the Character palette settings
 * page, and each character button inserts its character where the cursor
 * was.
 *
 * Mouse and touch insertion keep focus in the field, so the caret stays put
 * and a phone keyboard stays open. Keyboard insertion keeps focus on the
 * character button, so several characters can be inserted in a row; Escape
 * goes back to the field.
 *
 * A textarea with a rich text editor gets the button in the editor's top
 * bar, and characters go in through CKEditor's model at the editor's own selection,
 * so undo and the editor's change tracking work (#2561). If the text format
 * is switched to one without an editor, the same button falls back to the
 * textarea itself.
 *
 * A Tagify field (keywords, locations, people) is typed in a contenteditable
 * box Tagify puts in place of its input. Characters go into that box at the
 * caret and Tagify is told through an input event, so its suggestions
 * update. Tagify turns typed text into a tag when the box loses focus; that
 * waits while focus is in the palette, and happens when focus moves on from
 * the palette instead (#2561).
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
   * Returns the Tagify instance managing an input, if there is one now.
   *
   * Tagify may set up after the palette does, so this is looked up each
   * time too.
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

  /**
   * Puts the button beside the field and the panel under it.
   *
   * @return {Element[]}
   *   For a panel that floats over the page, the elements that count as
   *   inside it: the field's form and the panel. Empty for a panel in the
   *   page's own layout.
   */
  function place(input, toggle, panel) {
    // The theme's header search marks its form: the button goes inside the
    // search bar, before the submit button, and the panel under the form.
    const searchForm = input.closest('[data-mukurtu-character-palette-anchor]');
    if (searchForm) {
      const submit = searchForm.querySelector('[type="submit"]');
      if (submit) {
        submit.before(toggle);
      }
      else {
        searchForm.append(toggle);
      }
      searchForm.after(panel);
      return [searchForm, panel];
    }

    // A rich text editor is created after behaviors run, and destroyed and
    // recreated when the text format changes. The button goes in the
    // editor's top bar, beside its toolbar, while there is one, and beside
    // the textarea while there isn't. Beside the toolbar rather than over
    // it, so the toolbar measures the room actually left when it decides
    // which buttons to fold into its "Show more items" menu.
    if (hasEditorFormat(input)) {
      const item = input.closest('.js-form-item') || input.parentElement;
      item.after(panel);
      const follow = function () {
        const top = input.parentElement.querySelector('.ck-editor .ck-editor__top');
        if (top) {
          if (toggle.parentElement !== top) {
            top.append(toggle);
          }
        }
        else if (toggle.previousElementSibling !== input) {
          input.after(toggle);
        }
      };
      follow();
      new MutationObserver(follow).observe(input.parentElement, {childList: true});
      return [];
    }

    const field = document.createElement('span');
    field.className = 'mukurtu-character-palette__field';
    // Tagify puts its box directly before its input, and its CSS relies on
    // the two staying side by side. If Tagify got there first, the box comes
    // into the row too.
    const tagifyBox = input.classList.contains('tagify-widget') && input.previousElementSibling;
    input.before(field);
    if (tagifyBox && tagifyBox.classList.contains('tagify')) {
      field.append(tagifyBox);
    }
    field.append(input, toggle);
    field.after(panel);
    return [];
  }

  /**
   * Builds the button and panel for one input.
   */
  function attach(input, groups) {
    counter += 1;
    const panelId = 'mukurtu-character-palette-' + counter;
    // Open panels are remembered by field name. A field with no name, such
    // as a map point's description, whose id changes on every page load,
    // isn't remembered at all, so the stored list can't grow without end.
    const fieldKey = input.name || '';
    const fieldLabel = input.getAttribute('data-mukurtu-character-palette');
    let selection = {start: input.value.length, end: input.value.length};

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'mukurtu-character-palette__toggle';
    // A letter with marks above and below reads as "special letters" in any
    // language. It is decoration; the button's name says what it does.
    const icon = document.createElement('span');
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '\u00C1\u0331';
    toggle.appendChild(icon);
    toggle.title = Drupal.t('Characters');
    toggle.setAttribute('aria-label', fieldLabel ? plain(Drupal.t('Characters for @field', {'@field': fieldLabel})) : Drupal.t('Characters'));
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
      label.className = 'mukurtu-character-palette__label';
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
        resized();
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
      const tagify = tagifyFor(input);
      if (tagify) {
        return document.activeElement === tagify.DOM.input;
      }
      const editor = editorFor(input);
      if (editor) {
        return editor.ui.view.editable.element.contains(document.activeElement);
      }
      return document.activeElement === input;
    }

    function focusField() {
      const tagify = tagifyFor(input);
      if (tagify) {
        tagify.DOM.input.focus();
        placeTagifyCaret(tagify);
        return;
      }
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

    // Where the caret was in a Tagify box, as character offsets into its
    // text, kept while focus is in the palette.
    let tagifySelection = null;

    function rememberTagify() {
      const tagify = tagifyFor(input);
      const selection = window.getSelection();
      if (!tagify || !selection.rangeCount || !tagify.DOM.input.contains(selection.anchorNode)) {
        return;
      }
      const range = selection.getRangeAt(0);
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
      const offset = Math.min(tagifySelection.end, text.length);
      range.setStart(text, offset);
      range.collapse(true);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
    }

    function insertIntoTagify(tagify, item) {
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
      Drupal.announce(Drupal.t('Inserted @character.', {'@character': item.name || item.character}));
    }

    function insert(item) {
      const tagify = tagifyFor(input);
      if (tagify) {
        insertIntoTagify(tagify, item);
        return;
      }
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

    // Tells whatever holds the palette that it changed size, such as a map
    // popup that has to move to stay in view.
    function resized() {
      toggle.dispatchEvent(new CustomEvent('mukurtu-character-palette-resize', {bubbles: true}));
    }

    // Set once placed: what counts as inside a floating panel.
    let floatingIn = [];

    function setOpen(open) {
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      resized();
      // A floating panel covers the page, so it never reopens by itself.
      if (fieldKey && !floatingIn.length) {
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
    // As with the character buttons, a mouse or a finger opening the panel
    // leaves focus, and the caret, in the field.
    toggle.addEventListener('pointerdown', function (event) {
      if (fieldHasFocus()) {
        event.preventDefault();
      }
    });

    if (input.classList.contains('tagify-widget')) {
      const inPalette = function (element) {
        return Boolean(element) && (toggle.contains(element) || panel.contains(element));
      };
      // Tagify turns typed text into a tag on blur. A capturing listener on
      // the document runs before Tagify's own, so the setting can be held
      // off for a blur that only moves into the palette.
      let heldText = false;
      let keepDropdownClosed = false;
      document.addEventListener('blur', function (event) {
        const tagify = tagifyFor(input);
        if (!tagify || event.target !== tagify.DOM.input || !inPalette(event.relatedTarget)) {
          return;
        }
        rememberTagify();
        const setting = tagify.settings.addTagOnBlur;
        tagify.settings.addTagOnBlur = false;
        heldText = setting;
        window.setTimeout(function () {
          tagify.settings.addTagOnBlur = setting;
        });
      }, true);
      document.addEventListener('selectionchange', rememberTagify);
      // Leaving the palette for anywhere but the field does what the blur
      // would have done.
      const commitHeldText = function (event) {
        const tagify = tagifyFor(input);
        if (!heldText || !tagify || inPalette(event.relatedTarget) || tagify.DOM.scope.contains(event.relatedTarget)) {
          return;
        }
        heldText = false;
        const text = tagify.DOM.input.textContent.trim();
        if (text && tagify.settings.addTagOn.includes('blur')) {
          tagify.addTags(text, true);
        }
        tagify.dropdown.hide();
        // The Tagify module shows suggestions whenever a lookup comes back,
        // focused or not, so one still on its way would reopen them over
        // the page. Keep them closed until the field has focus again.
        keepDropdownClosed = true;
        if (!tagify.__mukurtuPaletteGuard) {
          tagify.__mukurtuPaletteGuard = true;
          tagify.on('dropdown:show', function () {
            if (keepDropdownClosed && !fieldHasFocus()) {
              tagify.dropdown.hide();
            }
          });
          tagify.DOM.input.addEventListener('focus', function () {
            keepDropdownClosed = false;
          });
        }
      };
      toggle.addEventListener('focusout', commitHeldText);
      panel.addEventListener('focusout', commitHeldText);
    }

    function closeOnEscape(event) {
      if (event.key === 'Escape' && !panel.hidden) {
        // Escape belongs to the palette here, not to whatever holds it, such
        // as a map popup that would close too.
        event.preventDefault();
        event.stopPropagation();
        setOpen(false);
        focusField();
      }
    }
    panel.addEventListener('keydown', closeOnEscape);
    toggle.addEventListener('keydown', closeOnEscape);

    renderCharacters();
    floatingIn = place(input, toggle, panel);

    if (floatingIn.length) {
      // A floating panel covers whatever is under it, so it closes when
      // focus or a click moves anywhere else (WCAG 2.4.11).
      const closeIfOutside = function (event) {
        const inside = floatingIn.some(function (element) {
          return element.contains(event.target);
        });
        if (!panel.hidden && !inside) {
          setOpen(false);
        }
      };
      document.addEventListener('focusin', closeIfOutside);
      document.addEventListener('pointerdown', closeIfOutside);
    }
    else if (fieldKey && read(OPEN_KEY, []).indexOf(fieldKey) !== -1) {
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
