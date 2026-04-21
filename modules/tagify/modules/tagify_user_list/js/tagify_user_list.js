// eslint-disable-next-line func-names
(function ($, Drupal, drupalSettings, Sortable) {
  Drupal.behaviors.tagifyAutocompleteUserList = {
    attach: function attach() {
      // see https://github.com/yairEO/tagify#ajax-whitelist
      const elements = $(
        once('tagify-user-list-widget', 'input.tagify-user-list-widget'),
      );

      // eslint-disable-next-line func-names
      elements.each(function () {
        const input = this;
        const { identifier } = input.dataset;
        const { cardinality } = input.dataset;

        /**
         * Counts the number of selected tags.
         * @return {int} - The number of selected tags.
         */
        function countSelectedTags() {
          const tagsElement = document.querySelector(`.${identifier}`);
          const tagElements = tagsElement.querySelectorAll('.tagify__tag');
          return tagElements.length;
        }

        /**
         * Checks if the tag limit has been reached.
         * @return {boolean} - True if the tag limit has been reached, otherwise false.
         */
        function isTagLimitReached() {
          return cardinality > 0 && countSelectedTags() >= cardinality;
        }

        /**
         * Creates loading text markup.
         */
        function createLoadingTextMarkup() {
          const tagsElement = document.querySelector(`.${identifier}`);
          // Create the loading text div element
          const loadingText = document.createElement('div');
          loadingText.className = 'tagify--loading-text hidden';
          loadingText.textContent = 'Loading...';
          // Add the loading text div inside the tags element
          tagsElement.appendChild(loadingText);
        }

        /**
         * Removes loading text markup.
         */
        function removeLoadingTextMarkup() {
          const tagsElement = document.querySelector(`.${identifier}`);
          if (tagsElement) {
            const loadingText = tagsElement.querySelector(
              '.tagify--loading-text',
            );
            if (loadingText) {
              loadingText.remove();
            }
          }
        }

        /**
         * Highlights matching letters in a given input string by wrapping them in <strong> tags.
         * @param {string} inputTerm - The input string for matching letters.
         * @param {string} searchTerm - The term to search for within the input string.
         * @return {string} The input string with matching letters wrapped in <strong> tags.
         */
        function highlightMatchingLetters(inputTerm, searchTerm) {
          // Escape special characters in the search term.
          const escapedSearchTerm = searchTerm.replace(
            /[.*+?^${}()|[\]\\]/g,
            '\\$&',
          );
          // Create a regular expression to match the search term globally and case insensitively.
          const regex = new RegExp(`(${escapedSearchTerm})`, 'gi');
          // Check if there are any matches.
          if (!escapedSearchTerm) {
            // If no matches found, return the original input string.
            return inputTerm;
          }
          // Replace matching letters with the same letters wrapped in <strong> tags.
          return inputTerm.replace(regex, '<strong>$1</strong>');
        }

        /**
         * Generates the HTML template for a Tagify tag based on the provided
         * tagData.
         * @param {Object} tagData - The data representing the tag, including
         * info label, class, avatar, and value.
         * @return {string} - The HTML template for the tag.
         */
        function tagTemplate(tagData) {
          // Avoid 'undefined' values on paste event.
          const label = tagData.label ?? tagData.value;

          return `<tag title="${label}"
            contenteditable='false'
            spellcheck='false'
            tabIndex="-1"
            class="tagify__tag ${tagData.class ? tagData.class : ''}"
            ${this.getAttributes(tagData)}>
            <x id="tagify__tag-remove-button"
              title='Remove ${label}'
              class='tagify__tag__removeBtn'
              role='button'
              aria-label='remove ${label} tag'
              tabIndex="0">
            </x>
            <div id="tagify__tag-items">
              <div class='tagify__tag__avatar-wrap'>
                <img onerror="this.style.visibility='hidden'"
                  alt="${label}"
                  src="${tagData.avatar}"
                >
              </div>
              <span class='tagify__tag-text'>${label}</span>
            </div>
          </tag>`;
        }

        /**
         * Generates the HTML template for a suggestion item in the Tagify dropdown based on the provided tagData.
         * @param {Object} tagData - The data representing the suggestion item, including info label, class, avatar, and value.
         * @return {string} - The HTML template for the suggestion item.
         */
        function suggestionItemTemplate(tagData) {
          return !isTagLimitReached()
            ? `<div ${this.getAttributes(
                tagData,
              )} class='tagify__dropdown__item tagify__dropdown__item-center ${
                tagData.class ? tagData.class : ''
              }' tabindex="0" role="option"> ${
                tagData.avatar
                  ? `<div class='tagify__dropdown__item__avatar-wrap'><img onerror="this.style.visibility='hidden'" src="${tagData.avatar}"></div>`
                  : ''
              }<div class="tagify__dropdown-user-info"><div class="tagify__dropdown-user-info-name">${highlightMatchingLetters(
                tagData.label,
                this.state.inputText,
              )}</div>${
                tagData.info_label
                  ? `<div class="tagify__dropdown-user-info-label"><span>${
                      tagData.info_label
                    }</span></div>`
                  : ''
              }</div></div>`
            : '';
        }

        /**
         * Generates the HTML template for the header section of the Tagify dropdown, displaying the count of suggestions.
         * @param {Array} suggestions - An array of suggestions to be displayed in the dropdown.
         * @return {string} - The HTML template for the dropdown header.
         */
        function dropdownHeaderTemplate(suggestions) {
          // Fallback to 'en' if the locale is not available.
          const userLocale = navigator.language || 'en';
          const pluralRule = new Intl.PluralRules(userLocale);
          const pluralSuffix =
            pluralRule.select(suggestions.length) === 'one' ? '' : 's';

          return !isTagLimitReached()
            ? `<div
            class="tagify__dropdown__count">
                <span>${suggestions.length} member${pluralSuffix}</span>
            </div>`
            : '';
        }

        /**
         * Generates the HTML template for a suggestion footer in the Tagify dropdown based on the provided tagData.
         * @return {string} - The HTML template for the suggestion footer.
         */
        function suggestionFooterTemplate() {
          // Returns empty dropdown footer when field cardinality is unlimited or
          // field cardinality is bigger than the number of selected tags.
          return isTagLimitReached()
            ? `<footer
          data-selector='tagify-suggestions-footer'
          class="${this.settings.classNames.dropdownFooter}">
            <p>${drupalSettings.tagify.information_message.limit_tag} <strong>${cardinality}</strong></p>
         </footer>`
            : '';
        }

        // eslint-disable-next-line no-undef
        const tagify = new Tagify(input, {
          dropdown: {
            enabled: parseInt(input.dataset.suggestionsDropdown, 10),
            highlightFirst: true,
            fuzzySearch: !!parseInt(input.dataset.matchOperator, 10),
            maxItems: input.dataset.maxItems ?? Infinity,
            closeOnSelect: true,
            searchKeys: ['label', 'input'],
            mapValueTo: 'label',
            classname: 'users-list',
          },
          templates: {
            tag: tagTemplate,
            dropdownItem: suggestionItemTemplate,
            dropdownHeader: dropdownHeaderTemplate,
            dropdownFooter: suggestionFooterTemplate,
            // Modify dropdownItemNoMatch to respect debounce.
            dropdownItemNoMatch: Drupal.debounce((data) => {
              if (!isTagLimitReached()) {
                return `
                  <div class='${tagify.settings.classNames.dropdownItem} tagify--dropdown-item-no-match'
                    value="noMatch"
                    tabindex="0"
                    role="option">
                    <p>${drupalSettings.tagify.information_message.no_matching_suggestions}</p>
                    <strong class="tagify--value">${data.value}</strong>
                  </div>`;
              }
              // Don't show the no match item immediately.
              return '';
            }, 250),
          },
          whitelist: [],
          placeholder: parseInt(input.dataset.placeholder, 10),
          editTags: false,
          maxTags: cardinality > 0 ? cardinality : Infinity,
        });

        let controller;

        // Avoid creating tag when 'Create referenced entities if they don't
        // already exist' is disallowed and when tag limit is reached.
        tagify.settings.enforceWhitelist =
          isTagLimitReached() && cardinality > 1
            ? false
            : !$(this).hasClass('tagify--autocreate');
        tagify.settings.skipInvalid = isTagLimitReached()
          ? false
          : $(this).hasClass('tagify--autocreate');

        /**
         * Binds Sortable to Tagify's main element and specifies draggable items.
         */
        Sortable.create(tagify.DOM.scope, {
          // See: (https://github.com/SortableJS/Sortable#options)
          draggable: `.${tagify.settings.classNames.tag}:not(tagify__input)`,
          forceFallback: true,
          onEnd() {
            // Must update Tagify's value according to the re-ordered nodes
            // in the DOM.
            tagify.updateValueByDOMTags();
          },
        });

        /**
         * Handles autocomplete functionality for the input field using Tagify
         * User List widget.
         * @param {string} value - The current value of the input field.
         */
        function handleAutocomplete(value) {
          if (controller) {
            controller.abort();
          }

          controller = new AbortController();

          // Create Loading text markup.
          createLoadingTextMarkup();

          // eslint-disable-next-line no-unused-expressions
          value !== '' ? tagify.loading(true) : tagify.loading(false);

          // Check if url already contains query params and provide operator accordingly.
          const autocompleteUrl = new URL(
            $(input).attr('data-autocomplete-url'),
            window.location.origin,
          );
          const operator = autocompleteUrl.search ? '&' : '?';

          // Make the fetch request.
          fetch(
            `${$(input).attr('data-autocomplete-url')}${operator}q=${encodeURIComponent(value)}`,
            { signal: controller.signal },
          )
            .then((res) => res.json())
            // eslint-disable-next-line func-names
            .then(function (newWhitelist) {
              const newWhitelistData = [];
              // eslint-disable-next-line func-names
              newWhitelist.forEach(function (current) {
                newWhitelistData.push({
                  value: current.entity_id,
                  entity_id: current.entity_id,
                  avatar: current.avatar,
                  label: current.label,
                  info_label: current.info_label,
                  editable: current.editable,
                  input: tagify.state.inputText,
                  ...current.attributes,
                });
              });
              // Build the whitelist with the values coming from the fetch.
              if (newWhitelistData) {
                tagify.whitelist = newWhitelistData;
                if (identifier) {
                  removeLoadingTextMarkup();
                }
              }
              // Show dropdown suggestion if the input is or not matching.
              tagify.loading(false).dropdown.show(value);
            })
            .catch((error) => {
              if (error instanceof Error && error.name === 'AbortError') {
                // Ignore abort errors.
              } else {
                // eslint-disable-next-line no-console
                console.error('Error fetching data:', error);
              }
            });
        }

        // If 'On click' dropdown suggestions is enabled (Simulated 'Select').
        if (!tagify.settings.dropdown.enabled) {
          const tagsElement = document.querySelector(`.${identifier}`);
          tagsElement.classList.add('tagify-select');
        }

        // Tagify input event with debounce.
        // eslint-disable-next-line func-names
        const onInput = Drupal.debounce(function (e) {
          const { value } = e.detail;
          handleAutocomplete(
            value,
            tagify.value.map((item) => item.entity_id),
          );
        }, 250);

        // Tagify change event.
        // eslint-disable-next-line func-names
        const onChange = Drupal.debounce(function () {
          if (isTagLimitReached() && cardinality > 1) {
            tagify.settings.enforceWhitelist = false;
            tagify.settings.skipInvalid = false;
          }
        });

        // Input event (when a tag is being typed/edited. e.detail exposes
        // value, inputElm & isValid).
        tagify.on('input', onInput);
        // Change event (any change to the value has occurred. e.detail.value
        // callback listener argument is a String).
        tagify.on('change', onChange);

        /**
         * Handles click events on Tagify's input, triggering autocomplete if
         * conditions are met.
         * @param {Event} e - The click event object.
         */
        function handleClickEvent(e) {
          const containerClass = `.${identifier}`;
          const isTagifyInput = e.target.classList.contains('tagify__input');
          const isDesiredContainer = e.target.closest(containerClass);
          if (isTagifyInput && isDesiredContainer) {
            handleAutocomplete(
              '',
              tagify.value.map((item) => item.entity_id),
            );
          }
        }
        // If 'On click' dropdown suggestions is enabled.
        if (!tagify.settings.dropdown.enabled) {
          document.addEventListener('click', handleClickEvent);
        }
      });
    },
  };

  /**
   * Behaviors for tabs in entity edit forms.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior for tabs in entity edit forms.
   */
  Drupal.behaviors.entityDetailsSummaries = {
    attach: function attach(context) {
      const $context = $(context);

      // eslint-disable-next-line no-shadow,func-names
      const element = $context.find('.node-form-author, .media-form-author');
      if (element.length) {
        element.drupalSetSummary((authorContext) => {
          const $authorContext = $(authorContext);
          const $authorInput = $authorContext.find('.field--name-uid input');
          const $createdInput = $authorContext.find(
            '.field--name-created input',
          );

          let name = null;
          if ($authorInput.length) {
            const value = $authorInput.val();
            if (value) {
              try {
                const parsed = JSON.parse(value);
                if (Array.isArray(parsed) && parsed[0]?.label) {
                  name = parsed[0].label;
                }
              } catch (e) {
                console.error('Invalid JSON in $authorInput:', value, e);
              }
            }
          }

          let date = null;
          if ($createdInput.length) {
            date = $createdInput.val();
          }

          if (name && date) {
            return Drupal.t('By @name on @date', {
              '@name': name,
              '@date': date,
            });
          }
          if (name) {
            return Drupal.t('By @name', { '@name': name });
          }
          if (date) {
            return Drupal.t('Authored on @date', { '@date': date });
          }
        });
      }
    },
  };
})(jQuery, Drupal, drupalSettings, Sortable);
