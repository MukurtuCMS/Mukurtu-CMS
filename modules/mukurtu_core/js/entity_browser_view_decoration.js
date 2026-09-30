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

      // Table rows: the native checkbox is the control. It keeps its own
      // label ("Select item <title>"), focus, and keyboard handling, and the
      // row keeps its table semantics. Making the row itself a
      // role="checkbox" would nest the title and author links inside
      // another control (WCAG 4.1.2). Clicking elsewhere in the row still
      // toggles the checkbox, as a larger mouse target.
      //
      // The header row holds tableselect's select-all input. Its change
      // events reach the row inputs, so the handler below keeps rows in
      // sync with it too.
      // Core's tableselect names select-all only by a title, which it swaps
      // for "Deselect all" as the state changes. Give it a stable name; its
      // checked state already says which way it is. Same string as core's.
      once('eb-select-all-name', '.view .views-table thead th.select-all input[type="checkbox"]', context).forEach(function (input) {
        input.setAttribute('aria-label', Drupal.t('Select all rows in this table'));
      });

      var $rows = $(once('viewsTable', '.view .views-table tbody tr', context));
      $rows.each(function () {
        var $row = $(this);
        var $input = $row.find('.views-field-entity-browser-select input');
        if (!$input.length) {
          return;
        }
        $row.toggleClass('checked', $input.prop('checked'));
        $input.on('change', function () {
          if ($input.is(':radio')) {
            $row.closest('tbody').children('tr').removeClass('checked');
          }
          $row.toggleClass('checked', $input.prop('checked'));
        });
        $row.on('click', function (e) {
          // Leave links, labels, and the input itself to the browser.
          if ($input.prop('disabled') || $(e.target).closest('a, label, input').length) {
            return;
          }
          if ($input.is(':radio') && $input.prop('checked')) {
            return;
          }
          $input.prop('checked', !$input.prop('checked')).trigger('change');
        });
      });
    }
  };

}(Drupal, jQuery, once));
