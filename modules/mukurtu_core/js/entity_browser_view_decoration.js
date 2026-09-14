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
   * attribute. Values are returned in the same "entity_type:id" format used by
   * entity_browser_select checkboxes so indexOf() comparisons match directly.
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
   * Update the class and ARIA checked state of a col based on the status of
   * a checkbox or radio input (WCAG 4.1.2).
   *
   * @param {object} $col
   * @param {object} $input
   */
  function updateClasses($col, $input) {
    var checked = $input.prop('checked');
    // Check if the input is a radio and toggle the class accordingly.  Radio
    // can only have check at a time.
    if ($input.is(':radio')) {
      if (checked) {
        // Remove all the check class and only check the one that is checked.
        // Going up two parents will cover both grid (table) and html view.
        // [role] scopes the aria-checked reset to actual selectable
        // rows/cols, skipping header rows and any row without one.
        $col.parent().parent().find('tr, .views-col').removeClass('checked')
          .filter('[role]').attr('aria-checked', 'false');
        $col.addClass('checked');
      }
      else {
        $col.removeClass('checked');
      }
    }
    else {
      $col[checked ? 'addClass' : 'removeClass']('checked');
    }
    // Re-assert this col's own state last: the radio branch above may have
    // just reset the whole group's aria-checked to false.
    $col.attr('aria-checked', String(checked));
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
        if (!$input.length) {
          return;
        }
        updateClasses($col, $input);
      });

      // Disable rows for items already present in the field widget.
      disableAlreadySelected(context, getAlreadySelectedIds());

      // Add a checked class when clicked or activated by keyboard.
      var $cols = $(once('viewsCol', '.views-col', context));
      $cols.each(function () {
        var $col = $(this);
        var $input = $col.find('.views-field-entity-browser-select input');
        if (!$input.length) {
          return;
        }
        // Expose selection state and role to assistive technology
        // (WCAG 4.1.2); the underlying input is removed from the tab order
        // and accessibility tree since the row is the sole interactive
        // control. Always role="checkbox", even for the (currently unused
        // by any configured view - use_field_cardinality is off everywhere
        // this attaches) radio/single-select case: a proper role="radio"
        // requires a radiogroup ancestor with roving-tabindex/arrow-key
        // navigation per WAI-ARIA, which is a materially bigger widget
        // pattern than this file implements. role="checkbox" still reports
        // accurate Name/Role/Value - each row's checked state is correctly
        // synced either way - just not the more specific radio semantics.
        $col.attr('role', 'checkbox');
        $input.attr({tabindex: '-1', 'aria-hidden': 'true'});
      });
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
        // For clicks, skip if the click was directly on the input to avoid
        // double-toggling (browser already handled it).
        if (e.type === 'keydown' || e.target.tagName !== 'INPUT') {
          $input.prop('checked', !$input.prop('checked'));
        }
        updateClasses($col, $input);
      });

      // Select/unselect the row with a click or keyboard activation anywhere inside the row.
      var $rows = $(once('viewsTable', '.view .views-table tr', context));
      $rows.each(function () {
        var $row = $(this);
        var $input = $row.find('.views-field-entity-browser-select input');
        if (!$input.length) {
          // Header rows and any row without a selectable entity have no
          // selection state to expose.
          return;
        }
        // Expose selection state and role to assistive technology
        // (WCAG 4.1.2); the underlying input is removed from the tab order
        // and accessibility tree since the row is the sole interactive
        // control. See the .views-col loop above for why this is always
        // role="checkbox", even for the radio/single-select case.
        $row.attr('role', 'checkbox');
        $input.attr({tabindex: '-1', 'aria-hidden': 'true'});
        // Unlike the .views-col loop above, table rows never had their
        // initial checked state synced on attach - only on interaction -
        // so a previously-selected row's aria-checked (and "checked" class)
        // would otherwise be missing until first click.
        updateClasses($row, $input);
      });
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
