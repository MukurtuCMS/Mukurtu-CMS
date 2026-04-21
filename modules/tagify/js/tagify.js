// eslint-disable-next-line func-names
(function ($, Drupal, drupalSettings, Sortable) {
  Drupal.behaviors.tagifyAutocomplete = {
    attach: function attach(context) {
      // See: https://github.com/yairEO/tagify#ajax-whitelist.
      const elements = $(once('tagify-widget', 'input.tagify-widget', context));

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
          const loadingText = document.createElement('div');
          loadingText.className = 'tagify--loading-text hidden';
          loadingText.textContent = 'Loading...';
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
         * Checks if the info label is a valid image source.
         * @param {string} infoLabel - The info label input.
         * @return {boolean} True if the info label is a valid image source.
         */
        function validImgSrc(infoLabel) {
          const pattern = new RegExp(
            '^(https?:\\/\\/)?' +
              '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|' +
              '((\\d{1,3}\\.){3}\\d{1,3}))' +
              '(\\:\\d+)?' +
              '(\\/[-a-z\\d%_.~+]*)*' +
              '(\\.(?:jpg|jpeg|png|gif|bmp|svg|webp))' +
              '(\\?[;&a-z\\d%_.~+=-]*)?' +
              '(\\#[-a-z\\d_]*)?$',
            'i',
          );
          return !!pattern.test(infoLabel);
        }

        /**
         * Highlights matching letters in a given input string by wrapping them in <strong> tags.
         * @param {string} inputTerm - The input string for matching letters.
         * @param {string} searchTerm - The term to search for within the input string.
         * @return {string} The input string with matching letters wrapped in <strong> tags.
         */
        function highlightMatchingLetters(inputTerm, searchTerm) {
          inputTerm = Drupal.checkPlain(inputTerm);
          searchTerm = Drupal.checkPlain(searchTerm);
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
         * Generates HTML markup for an entity id.
         * @param {string} entityId - The entity id.
         * @return {string} The entity id markup HTML.
         */
        function entityIdMarkup(entityId) {
          return parseInt(input.dataset.showEntityId, 10) && entityId
            ? `<div id="tagify__tag-items" class="tagify__tag_with-entity-id"><div class='tagify__tag__entity-id-wrap'><span class='tagify__tag-entity-id'>${Drupal.checkPlain(entityId)}</span></div></div>`
            : '';
        }

        /**
         * Checks if a given string contains SVG elements.
         * @param {string} infoLabel - The string to be checked for SVG elements.
         * @return {boolean} - Returns true if the string contains SVG elements, otherwise false.
         */
        function containsSVG(infoLabel) {
          // Check if infoLabel is defined and not null
          if (infoLabel && typeof infoLabel === 'string') {
            // Create a temporary div element
            const tempDiv = document.createElement('div');
            // Set the innerHTML of the div to the content of infoLabel
            tempDiv.innerHTML = infoLabel;
            // Check if the div contains any SVG elements
            const svgElements = tempDiv.querySelectorAll('svg');
            // Return true if any SVG elements are found, false otherwise
            return svgElements.length > 0;
          }
        }

        /**
         * Checks if a given string contains an img HTML tag.
         * @param {string} infoLabel - The string to be checked for an img HTML tag.
         * @return {boolean} - Returns true if the string contains an img HTML tag, otherwise false.
         */
        function containsImgTag(infoLabel) {
          // Check if infoLabel is defined and not null
          if (infoLabel && typeof infoLabel === 'string') {
            // Create a temporary div element
            const tempDiv = document.createElement('div');
            // Set the innerHTML of the div to the content of infoLabel
            tempDiv.innerHTML = infoLabel;
            // Check if the div contains any img elements
            const imgElements = tempDiv.querySelectorAll('img');
            // Return true if any img elements are found, false otherwise
            return imgElements.length > 0;
          }
          return false;
        }

        /**
         * Generates HTML markup for an info label.
         * @param {string} infoLabel - The info label information.
         * @return {string} The info label markup HTML.
         */
        function infoLabelMarkup(infoLabel) {
          if (!infoLabel) {
            return '';
          }

          // Info label markup (Image or plain text).
          // eslint-disable-next-line no-nested-ternary
          return validImgSrc(infoLabel) ||
            containsSVG(infoLabel) ||
            containsImgTag(infoLabel)
            ? `<div id='tagify__tag__info-label-wrap' class='tagify__tag__info-label-wrap'><div class='tagify__tag-info-label-image'>${
                validImgSrc(infoLabel)
                  ? `<img onerror="this.style.visibility='hidden'" src="${infoLabel}">`
                  : infoLabel
              }</div></div>`
            : infoLabel
              ? `<div id='tagify__tag__info-label-wrap' class='tagify__tag__info-label-wrap'><span class='tagify__tag-info-label'>${infoLabel}</span></div>`
              : infoLabel;
        }

        /**
         * Generates HTML markup for a tag.
         * @param {string} tagLabel - The label.
         * @param {string} tagInfoLabel - The info label information.
         * @param {string} tagEntityId - The entity id.
         * @return {string} The tag markup HTML.
         */
        function tagMarkup(tagLabel, tagInfoLabel, tagEntityId) {
          return `<div id="tagify__tag-items">${tagEntityId}
            <span class="${
              tagEntityId
                ? 'tagify__tag-text-with-entity-id'
                : 'tagify__tag-text'
            }">${tagLabel}</span>${tagInfoLabel}</div>`;
        }

        /**
         * Generates HTML markup for a tag based on the provided tagData.
         * @param {Object} tagData - Data for the tag, including value, entity_id, class, etc.
         * @return {string} - The HTML markup for the generated tag.
         */
        function tagTemplate(tagData) {
          // Avoid 'undefined' values on paste event.
          const label = Drupal.checkPlain(tagData.label ?? tagData.value);

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
                tabindex="0">
              </x>
              ${tagMarkup(
                label,
                infoLabelMarkup(tagData.info_label),
                entityIdMarkup(tagData.entity_id),
              )}
          </tag>`;
        }

        /**
         * Generates the HTML template for a suggestion item in the Tagify dropdown based on the provided tagData.
         * @param {Object} tagData - The data representing the suggestion item.
         * @return {string} - The HTML template for the suggestion item.
         */
        function suggestionItemTemplate(tagData) {
          // Returns suggestion item when the field cardinality is unlimited or
          // field cardinality is bigger than the number of selected tags.
          return !isTagLimitReached()
            ? `<div ${this.getAttributes(
                tagData,
              )} class='tagify__dropdown__item ${
                tagData.class ? tagData.class : ''
              }' tabindex="0" role="option"><div class="tagify__dropdown__item-highlighted">
            ${highlightMatchingLetters(tagData.label, this.state.inputText)}
          </div>${infoLabelMarkup(tagData.info_label)}</div>`
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

        // Check if input is inside gin sidebar.
        const isInGinSidebar = !!input.closest('#gin_sidebar');

        // Tagify initialization.
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
          },
          templates: {
            tag: tagTemplate,
            dropdownItem: suggestionItemTemplate,
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
                    <strong class="tagify--value">${Drupal.checkPlain(data.value)}</strong>
                  </div>`;
              }
              // Don't show the no match item immediately.
              return '';
            }, 250),
          },
          whitelist: [],
          placeholder: parseInt(input.dataset.placeholder, 10),
          tagTextProp: 'label',
          editTags: false,
          maxTags: cardinality > 0 ? cardinality : Infinity,
          pasteAsTags: false,
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

        // The below code is printed as escaped, so please copy this function from:
        // https://github.com/yairEO/tagify/blob/master/src/parts/helpers.js#L89-L97
        function escapeHTML(s) {
          return typeof s === 'string'
            ? s.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>')
            : s;
        }

        // Split the taxonomy terms into groups, when rendering the suggestions list dropdown:
        // (since each term also has a 'parent' property)
        tagify.dropdown.createListHTML = (suggestionsList) => {
          if (isTagLimitReached()) {
            return '';
          }
          const parentsOfTerms = suggestionsList.reduce((acc, suggestion) => {
            const parent = suggestion.parent_name || '';

            if (!acc[parent]) acc[parent] = [suggestion];
            else acc[parent].push(suggestion);

            return acc;
          }, {});

          // Build a set of parent names that have children
          const parentNamesWithChildren = new Set();
          Object.keys(parentsOfTerms).forEach((key) => {
            if (key) {
              parentNamesWithChildren.add(key);
            }
          });

          const getTermsSuggestionsHTML = (parentTerms) =>
            parentTerms
              .map((suggestion) => {
                if (
                  typeof suggestion === 'string' ||
                  typeof suggestion === 'number'
                )
                  suggestion = { value: suggestion };

                const value = tagify.dropdown.getMappedValue.call(
                  tagify,
                  suggestion,
                );

                suggestion.label =
                  value && typeof value === 'string'
                    ? escapeHTML(value)
                    : value;

                return tagify.settings.templates.dropdownItem.apply(tagify, [
                  suggestion,
                ]);
              })
              .join('');

          // Assign the item to a group.
          return Object.entries(parentsOfTerms)
            .map(([parentName, childName]) => {
              if (parentName) {
                // Find the parent suggestion to get its attributes
                const parentSuggestion = suggestionsList.find(
                  (s) => s.label === parentName && !s.parent_name,
                );

                // Create parent header (clickable only if parentSelection is enabled)
                let parentHeaderHTML = '';
                const isParentSelectionEnabled = parseInt(
                  input.dataset.parentSelection,
                  10,
                );

                if (parentSuggestion && isParentSelectionEnabled) {
                  const value = tagify.dropdown.getMappedValue.call(
                    tagify,
                    parentSuggestion,
                  );
                  parentSuggestion.label =
                    value && typeof value === 'string'
                      ? escapeHTML(value)
                      : value;

                  parentHeaderHTML = `<div class="tagify__dropdown__item tagify__dropdown__item--parent" ${
                    tagify.getAttributes
                      ? tagify.getAttributes(parentSuggestion)
                      : ''
                  } tabindex="0" role="option">
                    <div class="tagify__dropdown__item-highlighted dropdown_group">${parentName}</div>
                  </div>`;
                } else {
                  parentHeaderHTML = `<span class="dropdown_group">${parentName}</span>`;
                }

                return `<div class="tagify__dropdown__itemsGroup" data-title="${parentName}">
                ${parentHeaderHTML}
                ${getTermsSuggestionsHTML(childName)}
              </div>`;
              }
              // Filter out items that are parents with children
              const filteredChildName = childName.filter(
                (item) => !parentNamesWithChildren.has(item.label),
              );
              return getTermsSuggestionsHTML(filteredChildName);
            })
            .join('');
        };

        /**
         * Handles autocomplete functionality for the input field using Tagify.
         * @param {string} value - The current value of the input field.
         * @param {string[]} selectedEntities - An array of selected entities.
         */
        function handleAutocomplete(value, selectedEntities) {
          if (controller) {
            controller.abort();
          }

          controller = new AbortController();

          if (identifier) {
            createLoadingTextMarkup();
          }

          // Show loading animation meanwhile the dropdown suggestions are hided.
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
            `${$(input).attr('data-autocomplete-url')}${operator}q=${encodeURIComponent(value)}&selected=${selectedEntities}`,
            { signal: controller.signal },
          )
            .then((res) => res.json())
            .then(function (newWhitelist) {
              const newWhitelistData = newWhitelist.map((current) => ({
                value: current.entity_id,
                entity_id: current.entity_id,
                parent_name: current.parent_name,
                info_label: current.info_label,
                label: current.label,
                editable: current.editable,
                input: tagify.state.inputText,
                ...current.attributes,
              }));
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

        // Tagify input event with debounce.
        // eslint-disable-next-line func-names
        const onInput = Drupal.debounce(function (e) {
          const value = e.detail?.value ?? e.detail.pastedText ?? '';
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
        tagify.on('input paste cut', onInput);
        // Change event (any change to the value has occurred. e.detail.value
        // callback listener argument is a String).
        tagify.on('change', onChange);

        // Add gin-sidebar class to dropdown when shown in sidebar context.
        if (isInGinSidebar) {
          tagify.on('dropdown:show', () => {
            tagify.DOM.dropdown.classList.add('tagify__dropdown--gin-sidebar');
          });
        }

        // If 'On click' dropdown suggestions is enabled (Simulated 'Select').
        if (!tagify.settings.dropdown.enabled) {
          const tagsElement = document.querySelector(`.${identifier}`);
          tagsElement.classList.add('tagify-select');
        }

        /**
         * Handles click events on Tagify's input, triggering autocomplete if
         * conditions are met.
         * @param {Event} e - The click event object.
         */
        function handleClickEvent(e) {
          const isTagifyInput = e.target.classList.contains('tagify__input');
          const isDesiredContainer = e.target.closest(`.${identifier}`);
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

  Drupal.behaviors.tagifySelect = {
    attach: function attach(context) {
      const selectElements = $(
        once('tagify-select-widget', 'select.tagify-select-widget', context),
      );

      // eslint-disable-next-line func-names
      selectElements.each(function () {
        const select = this;
        const cardinality = parseInt(select.dataset.cardinality, 10);
        const { identifier } = select.dataset;
        const { matchOperator } = select.dataset;
        const { matchLimit } = select.dataset;
        const { mode } = select.dataset;
        const { placeholder } = select.dataset;

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
         * Highlights matching letters in a given input string by wrapping them in <strong> tags.
         * @param {string} inputTerm - The input string for matching letters.
         * @param {string} searchTerm - The term to search for within the input string.
         * @return {string} The input string with matching letters wrapped in <strong> tags.
         */
        function highlightMatchingLetters(inputTerm, searchTerm) {
          inputTerm = Drupal.checkPlain(inputTerm);
          searchTerm = Drupal.checkPlain(searchTerm);
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
         * Generates HTML markup for an entity id.
         * @param {string} entityId - The entity id.
         * @return {string} The entity id markup HTML.
         */
        function entityIdMarkup(entityId) {
          return parseInt(select.dataset.showEntityId, 10) && entityId
            ? `<div id="tagify__tag-items" class="tagify__tag_with-entity-id"><div class='tagify__tag__entity-id-wrap'><span class='tagify__tag-entity-id'>${Drupal.checkPlain(entityId)}</span></div></div>`
            : '';
        }

        /**
         * Generates HTML markup for a tag.
         * @param {string} tagLabel - The label.
         * @param {string} tagEntityId - The entity id.
         * @return {string} The tag markup HTML.
         */
        function tagMarkup(tagLabel, tagEntityId) {
          return `<div id="tagify__tag-items">${tagEntityId}
            <span class="${
              tagEntityId
                ? 'tagify__tag-text-with-entity-id'
                : 'tagify__tag-text'
            }">${tagLabel}</span>
            </div>`;
        }

        /**
         * Generates HTML markup for a tag based on the provided tagData.
         * @param {Object} tagData - Data for the tag, including value, entity_id, class, etc.
         * @return {string} - The HTML markup for the generated tag.
         */
        function tagTemplate(tagData) {
          const text = Drupal.checkPlain(tagData.text);
          // Avoid 'undefined' values on paste event.
          const label = text ?? Drupal.checkPlain(tagData.label);

          return `<tag title="${text}"
            contenteditable='false'
            spellcheck='false'
            tabIndex="-1"
            class="tagify__tag ${tagData.class ? tagData.class : ''}"
            ${this.getAttributes(tagData)}>
            <x
            id="tagify__tag-remove-button"
            class='tagify__tag__removeBtn'
            role='button'
            aria-label='remove tag'
            tabIndex="0">
            </x>
              ${tagMarkup(label, entityIdMarkup(tagData.value))}
          </tag>`;
        }

        /**
         * Generates HTML markup for a dropdown item based on the provided tagData.
         *
         * @param {Object} tagData - Data for the tag, including value, text, etc.
         * @return {string} - HTML markup for the generated dropdown item.
         */
        function dropdownItemTemplate(tagData) {
          const { classNames } = this.settings;

          if (!isTagLimitReached() || mode) {
            const dropdownItemClass = classNames.dropdownItem;
            const highlightedText = highlightMatchingLetters(
              tagData.text,
              this.state.inputText,
            );

            const event = new CustomEvent('tagifyDropDownItemTemplate', {
              bubbles: true,
              detail: {
                template: `
                  <div class='${dropdownItemClass}'
                    value="${Drupal.checkPlain(tagData.value)}"
                    tabindex="0"
                    role="option">
                    <div class="tagify__dropdown__item-highlighted">${highlightedText}</div>
                  </div>`,
                classNames,
                tagData,
                highlightedText,
                select,
              },
            });

            select.dispatchEvent(event);
            return event.detail.template;
          }

          return '';
        }

        const options = [];
        const selected = [];
        const parentStack = [];
        let hasHierarchy = false;

        [...this.options].forEach((option) => {
          if (!option.value || !option.text) {
            return;
          }

          // Extract hierarchy level based on leading dashes
          const match = option.text.match(/^(-+)\s*/); // Matches leading dashes
          const level = match ? match[1].length : 0; // Number of dashes determines depth
          const text = option.text.replace(/^(-+\s*)/, ''); // Remove leading dashes

          if (level > 0) {
            hasHierarchy = true;
          }

          // Adjust parent stack based on depth
          while (
            parentStack.length > 0 &&
            parentStack[parentStack.length - 1].level >= level
          ) {
            parentStack.pop();
          }

          // Determine parent
          const parent =
            parentStack.length > 0
              ? parentStack[parentStack.length - 1].text
              : null;

          // Store the current option
          const optionData = { value: option.value, text, label: text, parent };
          options.push(optionData);

          // Add to selected if applicable
          if (option.selected) {
            selected.push(optionData);
          }

          // Push current item as a potential parent
          parentStack.push({ text, level });
        });

        // Sorting: Ensure parents appear before children
        options.sort((a, b) => {
          if (!a.parent && b.parent) return -1;
          if (a.parent && !b.parent) return 1;
          if (a.parent === b.parent) return a.level - b.level;
          return 0;
        });

        /**
         * Generates the HTML template for a suggestion footer in the Tagify dropdown based on the provided tagData.
         * @return {string} - The HTML template for the suggestion footer.
         */
        function suggestionFooterTemplate() {
          const { classNames } = this.settings;

          if (isTagLimitReached() && !mode) {
            return `<footer data-selector='tagify-suggestions-footer'
              class="${classNames.dropdownFooter}">
              <p>${drupalSettings.tagify_select.information_message.limit_tag} <strong>${cardinality}</strong></p>
            </footer>`;
          }

          return '';
        }

        // Check if select is inside gin sidebar.
        const isInGinSidebar = !!select.closest('#gin_sidebar');

        // Insert an input element to attach Tagify. Unfortunately, it is not
        // possible to attach Tagify directly to the select element because the
        // values, which are needed for the value callback, do get messed up.
        const input = document.createElement('input');
        input.setAttribute('class', this.getAttribute('class'));
        if (this.hasAttribute('disabled')) {
          input.setAttribute('disabled', this.getAttribute('disabled'));
        }
        input.value = JSON.stringify(selected);
        this.before(input);

        // eslint-disable-next-line no-undef
        const tagify = new Tagify(input, {
          mode,
          dropdown: {
            enabled: 0,
            fuzzySearch: !!parseInt(matchOperator, 10),
            maxItems: matchLimit === '0' ? Infinity : matchLimit,
            highlightFirst: true,
            searchKeys: ['text'],
            mapValueTo: 'text',
          },
          templates: {
            tag: tagTemplate,
            dropdownItem: dropdownItemTemplate,
            dropdownFooter: suggestionFooterTemplate,
            dropdownItemNoMatch: (data) =>
              !isTagLimitReached()
                ? `<div class='${tagify.settings.classNames.dropdownItem} tagify--dropdown-item-no-match'
              value="noMatch"
              tabindex="0"
              role="option">
                <p>${drupalSettings.tagify_select.information_message.no_matching_suggestions} </p><strong class="tagify--value">${Drupal.checkPlain(data.value)}</strong>
              </div>`
                : '',
          },
          whitelist: options,
          enforceWhitelist: true,
          editTags: !!mode,
          maxTags: cardinality > 0 ? cardinality : Infinity,
          tagTextProp: 'text',
          placeholder,
        });

        // Function to escape HTML special characters
        function escapeHTML(s) {
          return typeof s === 'string'
            ? s
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
            : s;
        }

        // Custom function to group taxonomy terms in the dropdown
        tagify.dropdown.createListHTML = (suggestionsList) => {
          if (isTagLimitReached() && !mode) {
            return '';
          }

          // Grouping suggestions by their parent category
          const parentsOfTerms = suggestionsList.reduce((acc, suggestion) => {
            const parent = suggestion.parent || ''; // Default to empty if no parent

            if (!acc[parent]) acc[parent] = [suggestion];
            else acc[parent].push(suggestion);

            return acc;
          }, {});

          // Function to generate HTML for terms within a parent group
          const getTermsSuggestionsHTML = (parentTerms) =>
            parentTerms
              .map((suggestion) => {
                if (
                  typeof suggestion === 'string' ||
                  typeof suggestion === 'number'
                ) {
                  suggestion = { value: suggestion };
                }

                // Map the value properly and escape HTML
                const value = tagify.dropdown.getMappedValue.call(
                  tagify,
                  suggestion,
                );
                suggestion.label =
                  value && typeof value === 'string'
                    ? escapeHTML(value)
                    : value;

                return tagify.settings.templates.dropdownItem.apply(tagify, [
                  suggestion,
                ]);
              })
              .join('');

          // Generate final grouped dropdown list
          return Object.entries(parentsOfTerms)
            .map(([parentName, childName]) => {
              if (parentName) {
                return `<div class="tagify__dropdown__itemsGroup" data-title="${parentName}">
          <span class="dropdown_group">${parentName}</span>
          ${getTermsSuggestionsHTML(childName)}
        </div>`;
              }
              return !hasHierarchy ? getTermsSuggestionsHTML(childName) : '';
            })
            .join('');
        };

        // Remove tagify--select class to keep Tagify styles.
        if (select.dataset.mode) {
          const tagsElement = document.querySelector(`.${identifier}`);
          tagsElement.classList.remove('tagify--select');
        }

        /**
         * Binds Sortable to Tagify's main element and specifies draggable items.
         */
        Sortable.create(tagify.DOM.scope, {
          draggable: `.${tagify.settings.classNames.tag}:not(tagify__input)`,
          forceFallback: true,
          onEnd() {
            Array.from(
              tagify.DOM.scope.querySelectorAll(
                `.${tagify.settings.classNames.tag}`,
              ),
            ).forEach((tag) => {
              const value = tag.getAttribute('value');
              const option = select.querySelector(`option[value="${value}"]`);
              if (option) {
                select.removeChild(option);
                select.appendChild(option);
                option.selected = tag.classList.contains('tagify__tag');
              }
            });
          },
        });

        /**
         * Listens to add tag event and updates select values accordingly.
         */
        // eslint-disable-next-line func-names
        tagify.on('add', function (e) {
          const value = e.detail?.data?.value;
          if (!value) return;
          const option = select.querySelector(`option[value="${value}"]`);
          if (option) {
            select.removeChild(option);
            select.appendChild(option);
            option.selected = true;
          }
          // Trigger a native change event so Drupal's AJAX system responds.
          select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        /**
         * Listens to change tag event and updates select values accordingly.
         */
        // eslint-disable-next-line func-names
        tagify.on('change', function (e) {
          // Trigger a native change event so Drupal's AJAX system responds.
          JSON.parse(e.detail.value).forEach((item) => {
            const { value } = item;
            const option = select.querySelector(`option[value="${value}"]`);
            if (option) {
              option.selected = true;
              select.dispatchEvent(new Event('change', { bubbles: true }));
            }
          });
        });

        /**
         * Listens to remove tag event and updates select values accordingly.
         */
        // eslint-disable-next-line func-names
        tagify.on('remove', function (e) {
          const value = e.detail?.data?.value;
          if (!value) return;
          const option = select.querySelector(`option[value="${value}"]`);
          if (option) {
            option.selected = false;
          }
          // Trigger a native change event so Drupal's AJAX system responds.
          select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Add gin-sidebar class to dropdown when shown in sidebar context.
        if (isInGinSidebar) {
          tagify.on('dropdown:show', () => {
            tagify.DOM.dropdown.classList.add('tagify__dropdown--gin-sidebar');
          });
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings, Sortable);
