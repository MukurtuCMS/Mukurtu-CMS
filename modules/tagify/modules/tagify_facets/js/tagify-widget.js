/**
 * @file
 * Init tagify widget.
 */

// eslint-disable-next-line func-names
(function ($, Drupal, once) {
  Drupal.facets = Drupal.facets || {};

  // eslint-disable-next-line func-names
  Drupal.facets.initTagify = function (context, settings) {
    const links = $(once('tagify-widget', '.js-facets-tagify'));

    if (links.length > 0) {
      // eslint-disable-next-line func-names
      links.each(function (index, widget) {
        const $widget = $(widget);
        const $widgetLinks = $widget.find('.facet-item > a');
        const $whitelist = [];
        const $selected = [];

        // eslint-disable-next-line func-names
        $widgetLinks.each(function (key, link) {
          const href = link.getAttribute('href');
          const valueEl = link.querySelector('.facet-item__value');
          const value = valueEl ? valueEl.textContent.trim() : '';
          const countEl = link.querySelector('.facet-item__count');
          const count = countEl ? countEl.textContent.trim() : null;

          // Create whitelist for Tagify suggestions with values coming from links.
          $whitelist.push({
            value: href,
            text: count ? `${value} ${count}` : value,
          });

          // If link is active, add to the input (which will be used on Tagify).
          if (link.classList.contains('is-active')) {
            $selected.push({
              value: href,
              text: value,
              count,
            });
          }
        });

        // Check if an input element with the specified class exists.
        let input = document.querySelector('input.js-facets-tagify');
        if (!input) {
          input = document.createElement('input');
          input.setAttribute('class', 'tagify-input');
          this.before(input);
        }
        input.value = JSON.stringify($selected);
        this.before(input);

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
         * Generates HTML markup for a tag based on the provided tagData.
         * @param {Object} tagData - Data for the tag, including value, entity_id, class, etc.
         * @return {string} - HTML markup for the generated tag.
         */
        function tagTemplate(tagData) {
          const entityIdDiv =
            parseInt(input.dataset.showEntityId, 10) && tagData.entity_id
              ? `<div id="tagify__tag-items" class="tagify__tag_with-entity-id"><div class='tagify__tag__entity-id-wrap'><span class='tagify__tag-entity-id'>${tagData.entity_id}</span></div><span class='tagify__tag-text'>${tagData.value}</span></div>`
              : `<div id="tagify__tag-items"><span class='tagify__tag-facets-text'>${tagData.text}</span>
                    ${tagData.count ? `<span class="tagify__tag-facets-count"> ${tagData.count}</span>` : ''}
                 </div>`;

          return `<tag title="${tagData.text}"
            contenteditable='false'
            spellcheck='false'
            tabIndex="0"
            class="tagify__tag ${tagData.class ? tagData.class : ''}"
            ${this.getAttributes(tagData)}>
              <x id="tagify__tag-remove-button" class='tagify__tag__removeBtn' role='button' aria-label='remove tag'></x>
              ${entityIdDiv}
          </tag>`;
        }

        /**
         * Generates the HTML template for a suggestion item in the Tagify dropdown based on the provided tagData.
         * @param {Object} tagData - The data representing the suggestion item.
         * @return {string} - The HTML template for the suggestion item.
         */
        function suggestionItemTemplate(tagData) {
          return `<div ${this.getAttributes(
            tagData,
          )} class='tagify__dropdown__item ${
            tagData.class ? tagData.class : ''
          }' tabindex="0" role="option">${highlightMatchingLetters(
            tagData.text,
            this.state.inputText,
          )}</div>`;
        }

        // eslint-disable-next-line no-undef
        const tagify = new Tagify(input, {
          dropdown: {
            enabled: 0,
            highlightFirst: true,
            searchKeys: ['text'],
            fuzzySearch: !!parseInt(
              settings.tagify.tagify_facets_widget.match_operator,
              10,
            ),
            maxItems:
              settings.tagify.tagify_facets_widget.max_items ?? Infinity,
          },
          templates: {
            tag: tagTemplate,
            dropdownItem: suggestionItemTemplate,
            dropdownFooter() {
              return '';
            },
          },
          whitelist: $whitelist,
          enforceWhitelist: true,
          editTags: false,
          placeholder: settings.tagify.tagify_facets_widget.placeholder,
        });

        /**
         * Binds Sortable to Tagify's main element and specifies draggable items.
         */
        Sortable.create(tagify.DOM.scope, {
          draggable: `.${tagify.settings.classNames.tag}:not(tagify__input)`,
          forceFallback: true,
          onEnd() {
            tagify.updateValueByDOMTags();
          },
        });

        /**
         * Listens to add tag event and updates facets values accordingly.
         */

        // eslint-disable-next-line func-names
        tagify.on('add', function (e) {
          const value = e.detail?.data?.value;
          if (!value) return;
          e.preventDefault();
          $widget.trigger('facets_filter', [value]);
        });

        /**
         * Listens to remove tag event and updates facets values accordingly.
         */

        // eslint-disable-next-line func-names
        tagify.on('remove', function (e) {
          const value = e.detail?.data?.value;
          if (!value) return;
          e.preventDefault();
          $widget.trigger('facets_filter', [value]);
        });
      });
    }
  };

  /**
   * Behavior to register tagify widget to be used for facets.
   */
  Drupal.behaviors.facetsTagifyWidget = {
    attach(context, settings) {
      Drupal.facets.initTagify(context, settings);
    },
  };
})(jQuery, Drupal, once);
