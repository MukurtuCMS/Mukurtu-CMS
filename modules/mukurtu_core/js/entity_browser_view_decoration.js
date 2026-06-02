/**
 * @file
 * Defines the behavior that decorates Entity Browser views.
 *
 * Highly inspired on the media_entity_browser contrib module.
 *
 * Provided by Nate Lampton, see
 * https://github.com/MukurtuCMS/Mukurtu-CMS/issues/775#issuecomment-2763105092.
 */

(function (Drupal, $, once) {

  "use strict";

  /**
   * Returns entity IDs already selected in the field widget on the parent form.
   *
   * The widget renders each selected entity with a data-entity-id="node:NNN"
   * attribute. We return the bare numeric IDs so they can be compared against
   * the entity_browser_select checkbox values in the view.
   */
  function getAlreadySelectedIds() {
    var ids = [];
    // The entity browser runs in an iframe; selected items are rendered on the
    // parent page. Access window.parent.document (same-origin) to find them.
    var searchDoc = document;
    try {
      if (window.parent !== window) {
        searchDoc = window.parent.document;
      }
    }
    catch (e) {
      // Cross-origin frame — fall back to the current document.
    }
    $(searchDoc).find('[data-entity-id]').each(function () {
      ids.push($(this).attr('data-entity-id'));
    });
    return ids;
  }

  /**
   * Disables rows whose entity is already present in the field widget.
   *
   * @param {object} context
   * @param {Array} alreadySelected - Numeric entity ID strings.
   */
  function disableAlreadySelected(context, alreadySelected) {
    if (!alreadySelected.length) {
      return;
    }
    $('.view .views-table tr', context).each(function () {
      var $row = $(this);
      var $input = $row.find('.views-field-entity-browser-select input');
      if ($input.length && alreadySelected.indexOf($input.val()) !== -1) {
        $input.prop('disabled', true);
        $row.addClass('eb-already-selected').attr({'aria-disabled': 'true'}).removeAttr('tabindex');
        $row.find('td:first').append('<span class="visually-hidden"> (already added)</span>');
      }
    });
  }

  /**
   * Update the class of a col based on the status of a checkbox.
   *
   * @param {object} $col
   * @param {object} $input
   */
  function updateClasses($col, $input) {
    // Check if the input is a radio and toggle the class accordingly.  Radio
    // can only have check at a time.
    if ($input.is(':radio')) {
      if ($input.prop('checked')) {
        // Remove all the check class and only check the one that is checked.
        // Going up two parents will cover both grid (table) and html view.
        $col.parent().parent().find('tr, .views-col').removeClass('checked');
        $col.addClass('checked');
      }
      else {
        $col.removeClass('checked');
      }
    }
    else {
      $col[$input.prop('checked') ? 'addClass' : 'removeClass']('checked');
    }
  }

  /**
   * Attaches our custom behavior.
   */
  Drupal.behaviors.GaEntityBrowserDecorationBehavior = {
    attach: function (context, settings) {
      // Run through each col to add the default classes.
      $('.views-col', context).each(function () {
        var $col = $(this);
        var $input = $col.find('.views-field-entity-browser-select input');
        updateClasses($col, $input);
      });

      // Disable rows for items already present in the field widget.
      disableAlreadySelected(context, getAlreadySelectedIds());

      // Add a checked class when clicked or activated by keyboard.
      var $cols = $(once('viewsCol', '.views-col', context));
      $cols.not('.eb-already-selected').attr('tabindex', '0');
      $cols.on('click keydown', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
          return;
        }
        if (e.type === 'keydown') {
          e.preventDefault();
        }
        var $col = $(this);
        var $input = $col.find('.views-field-entity-browser-select input');
        if ($input.prop('disabled')) {
          return;
        }
        $input.prop('checked', !$input.prop('checked'));
        updateClasses($col, $input);
      });

      // Select/unselect the row with a click or keyboard activation anywhere inside the row.
      var $rows = $(once('viewsTable', '.view .views-table tr', context));
      $rows.not('.eb-already-selected').attr('tabindex', '0');
      $rows.on('click keydown', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
          return;
        }
        if (e.type === 'keydown') {
          e.preventDefault();
        }
        var $row = $(this);
        var $input = $row.find('.views-field-entity-browser-select input');
        if ($input.prop('disabled')) {
          return;
        }
        // For clicks, skip if the click was directly on the input to avoid
        // double-toggling (browser already handled it).
        if (e.type === 'keydown' || e.target.tagName !== 'INPUT') {
          if (!$input.is(':radio') || $input.is(':radio') && !$input.prop('checked')) {
            $input.prop('checked', !$input.prop('checked'));
          }
        }
        updateClasses($row, $input);
      });
    }
  };

}(Drupal, jQuery, once));
