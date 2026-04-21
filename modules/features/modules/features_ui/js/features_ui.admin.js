/**
 * @file
 * JQuery.fn.sortElements
 * --------------.
 * @param Function comparator:
 *   Exactly the same behaviour as [1,2,3].sort(comparator)
 * @param Function getSortable
 *   A function that should return the element that is
 *   to be sorted. The comparator will run on the
 *   current collection, but you may want the actual
 *   resulting sort to occur on a parent or another
 *   associated element.
 *
 *   E.g. $('td').sortElements(comparator, function(){
 *      return this.parentNode;
 *   })
 *
 *   The <td>'s parent (<tr>) will be sorted instead
 *   of the <td> itself.
 *
 * Credit: http://james.padolsey.com/javascript/sorting-elements-with-jquery/
 */

jQuery.fn.sortElements = (function () {

  "use strict";

  var sort = [].sort;

  return function (comparator, getSortable) {

    getSortable = getSortable || function () {return this;};

    var placements = this.map(function () {

      var sortElement = getSortable.call(this);
      var parentNode = sortElement.parentNode;

      // Since the element itself will change position, we have
      // to have some way of storing its original position in
      // the DOM. The easiest way is to have a 'flag' node:
      var nextSibling = parentNode.insertBefore(
          document.createTextNode(''),
          sortElement.nextSibling
        );

      return function () {

        if (parentNode === this) {
          throw new Error(
            "You can't sort elements if any one is a descendant of another."
          );
        }

        // Insert before flag:
        parentNode.insertBefore(this, nextSibling);
        // Remove flag:
        parentNode.removeChild(nextSibling);

      };

    });

    return sort.call(this, comparator).each(function (i) {
      placements[i].call(getSortable.call(this));
    });

  };

})();

(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.features = {
    attach: function (context) {

      // Mark any conflicts with a class.
      if ((typeof drupalSettings.features !== 'undefined') && (typeof drupalSettings.features.conflicts !== 'undefined')) {
      // For (var configType in drupalSettings.features.conflicts) {.
          if (drupalSettings.features.conflicts) {
            var configConflicts = drupalSettings.features.conflicts;
            $('.js-features-export-wrapper .features-export-parent input[type=checkbox]:not(.js-features-filter)', context).each(function () {
              var key = $(this).attr('name');
              var matches = key.match(/^([^\[]+)(\[.+\])?\[(.+)\]\[(.+)\]$/);
              var component = matches[1];
              var item = matches[4];
              if ((component in configConflicts) && (item in configConflicts[component])) {
                $(this).parent().addClass('component-conflict');
              }
            });
          }
        // }
      }

      function _checkAll(value) {
        if (value) {
          $('.js-components-select input[type=checkbox]:visible', context).each(function () {
            var move_id = $(this).attr('id');
            $(this).click();
            $('#' + move_id).prop('checked', true);
          });
        }
        else {
          $('.js-components-added input[type=checkbox]:visible', context).each(function () {
            var move_id = $(this).attr('id');
            $(this).click();
            $('#' + move_id).prop('checked', false);
          });
        }
      }

      function updateComponentCountInfo(item, section) {
        var parent;

        switch (section) {
          case 'select':
            parent = $(item).closest('.js-features-export-list').siblings('.js-features-export-component');
            $('.js-component-count', parent).text(function (index, text) {
                return +text + 1;
              }
            );
            break;

          case 'added':
          case 'detected':
            parent = $(item).closest('.js-features-export-component');
            $('.js-component-count', parent).text(function (index, text) {
              return text - 1;
            });
        }
      }

      function moveCheckbox(item, section, value) {
        updateComponentCountInfo(item, section);
        var curParent = item;
        if ($(item).hasClass('js-form-type-checkbox')) {
          item = $(item).children('input[type=checkbox]');
        }
        else {
          curParent = $(item).parents('.js-form-type-checkbox');
        }
        var newParent = $(curParent).parents('.js-features-export-parent').find('.js-components-' + section + ' .form-checkboxes');
        $(curParent).detach();
        $(curParent).appendTo(newParent);
        var list = ['select', 'added', 'detected', 'included'];
        for (var i in list) {
          if (list[i]) {
            $(curParent).removeClass('component-' + list[i]);
            $(item).removeClass('component-' + list[i]);
          }
        }
        $(curParent).addClass('component-' + section);
        $(item).addClass('component-' + section);
        if (value) {
          $(item).attr('checked', 'checked');
        }
        else {
          $(item).removeAttr('checked');
        }
        var $newParents = $(newParent);
        $newParents.parents('.js-features-export-list').removeClass('features-export-empty');
        // Unhide the config type group.
        $newParents.parents('.features-export-parent').removeClass('features-filter-hidden');

        // re-sort new list of checkboxes based on labels.
        $newParents.find('label').sortElements(
          function (a, b) {
            return $(a).text() > $(b).text() ? 1 : -1;
          },
          function () {
            return this.parentNode;
          }
        );
      }

      // Provide timer for auto-refresh trigger.
      var timeoutID = 0;
      var inTimeout = 0;
      function _triggerTimeout() {
        timeoutID = 0;
        _updateDetected();
      }
      function _resetTimeout() {
        inTimeout++;
        // If timeout is already active, reset it.
        if (timeoutID !== 0) {
          window.clearTimeout(timeoutID);
          if (inTimeout > 0) {
            inTimeout--;
          }
        }
        timeoutID = window.setTimeout(_triggerTimeout, 500);
      }

      function _updateDetected() {
        if (!drupalSettings.features.autodetect) {
          return;
        }
        // Query the server for a list of components/items in the feature and update
        // the auto-detected items.
        var items = [];  // Will contain a list of selected items exported to feature.
        var components = {};  // Contains object of component names that have checked items.
        $('.js-features-export-wrapper .features-export-parent input[type=checkbox]:not(.js-features-filter):checked', context).each(function () {
          var key = $(this).attr('name');
          var matches = key.match(/^([^\[]+)(\[.+\])?\[(.+)\]\[(.+)\]$/);
          components[matches[1]] = matches[1];
          if (!$(this).hasClass('component-detected')) {
            items.push(key);
          }
        });
        var featureName = $('#edit-machine-name').val();
        if (featureName === '') {
          featureName = '*';
        }

        var url = Drupal.url('features/api/detect/' + featureName);
        var excluded = drupalSettings.features.excluded;
        var required = drupalSettings.features.required;
        var postData = {'items': items, 'excluded': excluded, 'required': required};
        jQuery.post(url, postData, function (data) {
          if (inTimeout > 0) {
inTimeout--; }
          // If we have triggered another timeout then don't update with old results.
          if (inTimeout === 0) {
            // Data is an object keyed by component listing the exports of the feature.
            for (var component in data) {
              if (data[component]) {
                var itemList = data[component];
                $('.js-component--name-' + component + ' input[type=checkbox]', context).each(function () {
                  var key = $(this).attr('value');
                  // First remove any auto-detected items that are no longer in component.
                  if ($(this).hasClass('component-detected')) {
                    if (!(key in itemList)) {
                      moveCheckbox(this, 'select', false);
                    }
                  }
                  // Next, add any new auto-detected items.
                  else if ($(this).hasClass('component-select')) {
                    if (key in itemList) {
                      moveCheckbox(this, 'detected', itemList[key]);
                      $(this).prop('checked', true);
                      $(this).parent().show(); // Make sure it's not hidden from filter.
                    }
                  }
                });
              }
            }
            // Loop over all selected components and check for any that have been completely removed.
            for (var selectedComponent in components) {
              if ((data == null) || !(selectedComponent in data)) {
                $('.js-component--name-' + selectedComponent + ' input[type=checkbox].component-detected', context).each(function () {
                  moveCheckbox(this, 'select', false);
                });
              }
            }
          }
        }, "json");
      }

      // Handle component selection UI.
      $('.js-features-export-wrapper .features-export-parent input[type=checkbox]', context).click(function () {
        _resetTimeout();
        if ($(this).hasClass('component-select')) {
          moveCheckbox(this, 'added', true);
        }
        else if ($(this).hasClass('component-included')) {
          moveCheckbox(this, 'added', false);
        }
        else if ($(this).hasClass('component-added')) {
          if ($(this).is(':checked')) {
            moveCheckbox(this, 'included', true);
          }
          else {
            moveCheckbox(this, 'select', false);
          }
        }
      });

      // Handle select/unselect all.
      $('.js-features-checkall', context).click(function () {
        let $text = $(this).next();
        if ($(this).prop('checked')) {
          _checkAll(true);
          $text.text(Drupal.t('Deselect all'))
            .attr('title', Drupal.t('Deselect all currently expanded configurations'));
        }
        else {
          _checkAll(false);
          $text.text(Drupal.t('Select all'))
            .attr('title', Drupal.t('Select all currently expanded configurations'));
        }
        _resetTimeout();
      });

      // Handle hide/show components.
      $('.js-features-filter .features-hide-component.form-select', context).change(function () {
        var $exportWrapper = $('.js-features-export-wrapper', context);
        var componentType = $(this).val();
        $exportWrapper
            .find('.js-features-filter-hidden')
            .removeClass('js-features-filter-hidden');
        if (componentType) {
          if (componentType === 'included+groups') {
            componentType = 'included';
            // Hide empty config components.
            $exportWrapper.find('.js-component-count').filter(function() {
              return $(this).text() === '0';
            }).parents('.features-export-parent').addClass('js-features-filter-hidden');
          }
          $exportWrapper.find('.js-features-export-parent .js-components-' + componentType).addClass('js-features-filter-hidden');
        }
      });

      // Collapse/Expand components.
      $('.js-features-filter .features-toggle-components', context).click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        var expandAll = Drupal.t('Expand all');
        var collapseAll = Drupal.t('Collapse all');
        var $this = $(this);
        var $components = $('.features-export-component', context);
        if (expandAll == $this.text()) {
          $components.attr('open', true);
          $this.text(collapseAll);
        } else {
          $components.attr('open', false);
          $this.text(expandAll);
        }
      });

      // Handle filtering.
      // Provide timer for auto-refresh trigger.
      var filterTimeoutID = 0;
      function _triggerFilterTimeout() {
        filterTimeoutID = 0;
        _updateFilter();
      }
      function _resetFilterTimeout() {
        // If timeout is already active, reset it.
        if (filterTimeoutID !== 0) {
          window.clearTimeout(filterTimeoutID);
          filterTimeoutID = null;
        }
        filterTimeoutID = window.setTimeout(_triggerFilterTimeout, 200);
      }
      function _updateFilter() {
        var filter = $('.js-features-filter-input').val();
        var regex = new RegExp(filter, 'i');
        // Collapse fieldsets.
        var newState = {};
        var currentState = {};
        $('.js-features-export-component', context).each(function () {
          // Expand parent fieldset.
          var section = $(this).attr('id');
          var details = $(this);

          currentState[section] = details.prop('open');
          if (!(section in newState)) {
            newState[section] = false;
          }

          details.find('.form-checkboxes label').each(function () {
            if (filter === '') {
              // Collapse the section, but make checkbox visible.
              if (currentState[section]) {
                details.prop('open', false);
                currentState[section] = false;
              }
              $(this).parent().show();
            }
            else if ($(this).text().match(regex)) {
              $(this).parent().show();
              newState[section] = true;
            }
            else {
              $(this).parent().hide();
            }
          });
        });
        for (var section in newState) {
          if (currentState[section] !== newState[section]) {
            if (newState[section]) {
              $('#' + section).prop('open', true);
            }
            else {
              $('#' + section).prop('open', false);
            }
          }
        }
      }
      $('.js-features-filter-input', context).bind("input", function () {
        _resetFilterTimeout();
      });
      $('.js-features-filter-clear', context).click(function () {
        $('.js-features-filter-input').val('');
        _updateFilter();
      });

      // Show the filter bar.
      $('.js-features-filter', context).removeClass('visually-hidden');

      // Handle Package selection checkboxes in the Differences page.
      $('.features-diff-listing .features-diff-header input.form-checkbox', context).click(function () {
        var value = $(this).prop('checked');
        $('.features-diff-listing .diff-' + $(this).prop('value') + ' input.form-checkbox', context).each(function () {
          $(this).prop('checked', value);
          if (value) {
            $(this).parents('tr').addClass('selected');
          }
          else {
            $(this).parents('tr').removeClass('selected');
          }
        });
      });

      // Handle special theming of headers in tableselect.
      $('td.features-export-header-row', context).each(function () {
        var row = $(this).parent('tr');
        row.addClass('features-export-header-row');
        var checkbox = row.find('td input:checkbox');
        if (checkbox.length) {
          checkbox.hide();
        }
      });

      // Handle clicking anywhere in row on Differences page.
      $('.features-diff-listing tr td:nth-child(2)', context).click(function () {
        var checkbox = $(this).parent().find('td input:checkbox');
        checkbox.prop('checked', !checkbox.prop('checked')).triggerHandler('click');
        if (checkbox.prop('checked')) {
          $(this).parents('tr').addClass('selected');
        }
        else {
          $(this).parents('tr').removeClass('selected');
        }
      });
      // Show/Hide components.
      $('.features-diff-header-action-link', context).click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        var showAll = Drupal.t('Show all');
        var hideAll = Drupal.t('Hide all');
        var $this = $(this);
        var $checkbox = $this.closest('tr').find('td:nth-child(1) input:checkbox');
        var $elements = $this.closest('table').find('tr.diff-' + $checkbox.prop('value'));
        if (hideAll == $this.text()) {
          $this.text(showAll);
          $elements.addClass('js-features-diff-hidden');
        }
        else {
          $this.text(hideAll);
          $elements.removeClass('js-features-diff-hidden');
        }
      });

      $('.features-diff-listing thead th:nth-child(2)', context).click(function () {
        var checkbox = $(this).parent().find('th input:checkbox');
        checkbox.click();
      });
    }
  };

})(jQuery, Drupal);
