/**
 * @file
 * Select-All Button functionality.
 */

(function ($, Drupal, drupalSettings) {
  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.views_bulk_operations = {
    attach(context, settings) {
      once('vbo-init', '.vbo-view-form', context).forEach(
        Drupal.viewsBulkOperationsFrontUi,
      );
    },
  };

  /**
   * VBO selection handling class.
   */
  const viewsBulkOperationsSelection = class {
    constructor(vboForm) {
      this.vboForm = vboForm;
      this.$actionSelect = $('select[name="action"]', vboForm);
      this.view_id = '';
      this.display_id = '';
      this.$summary = null;
      this.totalCount = 0;
      this.ajaxing = false;
    }

    /**
     * Bind event handlers to an element.
     *
     * @param {jQuery} $element
     * @param {string} elementType
     * @param {int} index
     */
    bindEventHandlers($element, elementType, index = 0) {
      if ($element.length) {
        const selectionObject = this;
        $element.on('keypress', function (event) {
          // Emulate click action for enter key.
          if (event.which === 13) {
            event.preventDefault();
            event.stopPropagation();
            selectionObject.update(this, elementType, index);
            $(this).trigger('click');
          }
          if (event.which === 32) {
            selectionObject.update(this, elementType, index);
          }
        });
        $element.on('click', function (event) {
          // Act only on left button click.
          if (event.which === 1) {
            selectionObject.update(this, elementType, index);
          }
        });
      }
    }

    bindActionSelect() {
      if (this.$actionSelect.length) {
        const selectionObject = this;
        this.$actionSelect.on('change', function (event) {
          selectionObject.toggleButtonsState();
        });
      }
    }

    bindCheckboxes() {
      const selectionObject = this;
      const checkboxes = $('.js-vbo-checkbox', this.vboForm);
      checkboxes.on('change', function (event) {
        selectionObject.toggleButtonsState();
      });
    }

    toggleButtonsState() {
      // If no rows are checked, disable any form submit actions.
      const buttons = $(
        'input[data-vbo="vbo-action"], button[data-vbo="vbo-action"]',
        this.vboForm,
      );
      let anyItemsSelected;

      if (this.view_id.length && this.display_id.length) {
        anyItemsSelected = this.totalCount;
      } else {
        anyItemsSelected = $('.js-vbo-checkbox:checked', this.vboForm).length;
      }

      if (this.$actionSelect.length) {
        const hasSelection =
          anyItemsSelected && this.$actionSelect[0].value !== '';
        buttons.prop('disabled', !hasSelection);
      } else {
        buttons.prop('disabled', !anyItemsSelected);
      }
    }

    /**
     * Perform an AJAX request to update selection.
     *
     * @param {object} element
     *   The checkbox element.
     * @param {string} elementType
     *   Which type of a checkbox is it?
     * @param {int} index
     *   Index of the checkbox, used for table select all.
     */
    update(element, elementType, index) {
      if (!this.view_id.length || !this.display_id.length) {
        this.toggleButtonsState();
        return;
      }
      if (this.ajaxing) {
        return;
      }

      const list = {};
      const selectionObject = this;
      let op = 'update';
      if (elementType === 'selection_method_change') {
        op = element.checked ? 'method_exclude' : 'method_include';
      } else {
        // Build standard list.
        $('.js-vbo-checkbox', this.vboForm).each(function () {
          const domValue = this.value;
          // All bulk form keys are quite long, it'd be safe to assume
          // anything above 10 characters to filter out other values.
          if (domValue.length < 10) {
            return;
          }
          list[domValue] = this.checked;
        });

        // If a table select all was used, update the list according to that.
        if (elementType === 'table_select_all') {
          this.list[index].forEach(function (bulkFormKey) {
            list[bulkFormKey] = element.checked;
          });
        }
      }

      const $summary = this.$summary;
      const $selectionInfo = this.$selectionInfo;
      const targetUri = `${
        drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix
      }views-bulk-operations/ajax/${this.view_id}/${this.display_id}`;

      const ajaxOptions = {
        url: targetUri,
        progress: false,
        submit: {
          list,
          op,
        },
        success(data) {
          selectionObject.totalCount = data.count;
          $selectionInfo.html(data.selection_info);
          $summary[0].textContent = Drupal.formatPlural(
            data.count,
            'Selected 1 item',
            'Selected @count items',
          );
          selectionObject.toggleButtonsState();
          selectionObject.ajaxing = false;
        },
      };

      if (
        Object.prototype.hasOwnProperty.call(drupalSettings, 'vbo') &&
        Object.prototype.hasOwnProperty.call(
          drupalSettings.vbo,
          'ajax_loader',
        ) &&
        drupalSettings.vbo.ajax_loader
      ) {
        ajaxOptions.progress = { type: 'fullscreen' };
      }

      const ajaxDrupal = Drupal.ajax(ajaxOptions);
      this.ajaxing = true;
      ajaxDrupal.execute();
    }
  };

  /**
   * Callback used in {@link Drupal.behaviors.views_bulk_operations}.
   *
   * @param {object} element
   */
  Drupal.viewsBulkOperationsFrontUi = function (element) {
    const $vboForm = $(element);
    const $viewsTables = $('.vbo-table', $vboForm);
    const $primarySelectAll = $('.vbo-select-all', $vboForm);
    const tableSelectAll = [];
    const vboSelection = new viewsBulkOperationsSelection($vboForm);

    // When grouping is enabled, there can be multiple tables.
    if ($viewsTables.length) {
      $viewsTables.each(function (index) {
        tableSelectAll[index] = $(this).find('.select-all input').first();
      });
    }

    // Add AJAX functionality to row selector checkboxes.
    const $multiSelectElement = $vboForm
      .find('.vbo-multipage-selector')
      .first();
    if ($multiSelectElement.length) {
      vboSelection.$selectionInfo = $multiSelectElement
        .find('.vbo-info-list-wrapper')
        .first();
      vboSelection.$summary = $multiSelectElement.find('summary').first();
      vboSelection.view_id = $multiSelectElement.attr('data-view-id');
      vboSelection.display_id = $multiSelectElement.attr('data-display-id');
      vboSelection.totalCount =
        drupalSettings.vbo_selected_count[vboSelection.view_id][
          vboSelection.display_id
        ];

      // Get the list of all checkbox values and add AJAX callback.
      vboSelection.list = [];

      let $contentWrappers;
      if ($viewsTables.length) {
        $contentWrappers = $viewsTables;
      } else {
        $contentWrappers = $([$vboForm]);
      }

      $contentWrappers.each(function (index) {
        vboSelection.list[index] = [];

        $(this)
          .find('input[type="checkbox"]')
          .each(function () {
            const value = this.value;
            if (!$(this).hasClass('vbo-select-all') && value !== 'on') {
              vboSelection.list[index].push(value);
              vboSelection.bindEventHandlers($(this), 'vbo_checkbox');
            }
          });

        // Bind event handlers to select all checkbox.
        if ($viewsTables.length && tableSelectAll.length) {
          vboSelection.bindEventHandlers(
            tableSelectAll[index],
            'table_select_all',
            index,
          );
        }
      });
    }
    // If we don't have multiselect and AJAX calls, we need to toggle button
    // state on click instead of on AJAX success.
    else {
      vboSelection.bindCheckboxes();
    }

    // Initialize all selector if the primary select all and
    // view table elements exist.
    if ($primarySelectAll.length) {
      $primarySelectAll.on('change', function (event) {
        const value = this.checked;

        // Select / deselect all checkboxes in the view.
        // If there are table select all elements, use that.
        if (tableSelectAll.length) {
          tableSelectAll.forEach(function (element) {
            if (element.get(0).checked !== value) {
              element.click();
            }
          });
        }

        // Also handle checkboxes that may still have different values.
        $vboForm
          .find(
            '.views-field-views-bulk-operations-bulk-form input[type="checkbox"]',
          )
          .each(function () {
            if (this.checked !== value) {
              $(this).click();
            }
          });

        // Clear the selection information if exists.
        $vboForm.find('.vbo-info-list-wrapper').each(function () {
          $(this).html('');
        });
      });

      if ($multiSelectElement.length) {
        vboSelection.bindEventHandlers(
          $primarySelectAll,
          'selection_method_change',
        );
      }
    }
    vboSelection.bindActionSelect();
    vboSelection.toggleButtonsState();
  };
})(jQuery, Drupal, drupalSettings);
