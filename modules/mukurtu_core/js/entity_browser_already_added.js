/**
 * @file
 * Marks items the field already references in any entity browser.
 *
 * The browser opens in an iframe from a field widget that lists the current
 * items with data-entity-id="type:id". Matching choices in the browser are
 * disabled and get a visible "Already added" badge, so it is clear why they
 * cannot be selected.
 *
 * Attached to every entity browser form (see FormHooks), so it has to handle
 * each way a browser lists its choices:
 * - views with the entity_browser_select field, where the checkbox value is
 *   "type:id", in table rows or list rows;
 * - Mukurtu's own taxonomy and user widgets, where the checkbox is named
 *   "term:id" or "user:id".
 */

(function (Drupal, $, once) {

  'use strict';

  /**
   * Returns the "type:id" keys of the items in the field that opened us.
   *
   * Only that field's items count: a form can have several entity browser
   * fields, and an item added to one is still selectable in another.
   */
  function getAlreadyAddedIds() {
    var uuid = new URLSearchParams(window.location.search).get('uuid');
    var parentDoc;
    try {
      parentDoc = window.parent !== window ? window.parent.document : null;
    }
    catch (e) {
      // Cross-origin parent: there is no widget to read.
      parentDoc = null;
    }
    if (!uuid || !parentDoc) {
      return [];
    }
    // The button that opened this browser carries its uuid. Its widget is
    // the nearest ancestor that holds the widget's current-items list.
    var opener = parentDoc.querySelector('[data-uuid="' + CSS.escape(uuid) + '"]');
    var widget = opener && opener.parentElement;
    while (widget && !widget.querySelector('.entities-list')) {
      widget = widget.parentElement;
    }
    if (!widget) {
      return [];
    }
    return Array.prototype.map.call(widget.querySelectorAll('[data-entity-id]'), function (el) {
      return el.getAttribute('data-entity-id');
    });
  }

  /**
   * Returns the "type:id" key a checkbox selects, or null.
   */
  function keyFor(input) {
    if (/^[a-z_]+:\d+$/.test(input.value)) {
      return input.value;
    }
    var match = /^(term|user):(\d+)$/.exec(input.name);
    if (match) {
      return (match[1] === 'term' ? 'taxonomy_term' : 'user') + ':' + match[2];
    }
    return null;
  }

  /**
   * Returns where the badge goes: after the item's name.
   */
  function badgeTarget($input, $item) {
    if ($item.is('tr, .views-row, .views-col')) {
      var $name = $item.find('.views-field-title, .views-field-name').first();
      if ($name.length) {
        return $name.find('.field-content').first().length ? $name.find('.field-content').first() : $name;
      }
      return $item.children().not('.views-field-entity-browser-select').first();
    }
    // A plain checkbox: its own label, so the badge is part of its name.
    return $item.find('label[for="' + $input.attr('id') + '"]');
  }

  Drupal.behaviors.mukurtuEntityBrowserAlreadyAdded = {
    attach: function (context) {
      var alreadyAdded = getAlreadyAddedIds();
      if (!alreadyAdded.length) {
        return;
      }
      once('eb-already-added', 'form input[type="checkbox"]', context).forEach(function (input) {
        var key = keyFor(input);
        if (!key || alreadyAdded.indexOf(key) === -1) {
          return;
        }
        var $input = $(input);
        // A view row first: the view's checkbox also sits in a .form-item.
        var $item = $input.closest('tr, .views-row, .views-col');
        if (!$item.length) {
          $item = $input.closest('.form-item');
        }
        $input.prop('checked', false).prop('disabled', true);
        // Core's select-all collects its checkboxes when it attaches, before
        // this runs, so it would still check this one. Undo that.
        $input.on('change', function () {
          if (input.checked) {
            input.checked = false;
            $item.removeClass('checked');
          }
        });
        $item.addClass('eb-already-selected');
        // Rows that act as the checkbox themselves (the community browser).
        if ($item.attr('role') === 'checkbox') {
          $item.attr('aria-disabled', 'true').removeAttr('tabindex');
        }
        badgeTarget($input, $item).append(' ', $('<span class="gin-status eb-already-added"></span>').text(Drupal.t('Already added')));
      });
    }
  };

}(Drupal, jQuery, once));
