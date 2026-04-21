/**
 * @file
 * Transforms links into a dropdown list.
 */

(function ($, Drupal, once) {

  'use strict';

  Drupal.facets = Drupal.facets || {};
  Drupal.behaviors.facetsDropdownWidget = {
    attach: function (context, settings) {
      Drupal.facets.makeDropdown(context, settings);
    }
  };

  /**
   * Turns all facet links into a dropdown with options for every link.
   *
   * @param {object} context
   *   Context.
   * @param {object} settings
   *   Settings.
   */
  Drupal.facets.makeDropdown = function (context, settings) {
    // Find all dropdown facet links and turn them into an option.
    $(once('facets-dropdown-transform', '.js-facets-dropdown-links')).each(function () {
      var $ul = $(this);
      var $links = $ul.find('.facet-item a');
      var $dropdown = $('<select></select>');
      // Preserve all attributes of the list.
      $ul.each(function () {
        $.each(this.attributes,function (idx, elem) {
            $dropdown.attr(elem.name, elem.value);
        });
      });
      // Remove the class which we are using for .once().
      $dropdown.removeClass('js-facets-dropdown-links');

      $dropdown.addClass('facets-dropdown');
      $dropdown.addClass('js-facets-widget');
      $dropdown.addClass('js-facets-dropdown');
      $dropdown.attr('name', $ul.data('drupal-facet-filter-key') + '[]')

      var id = $(this).data('drupal-facet-id');
      // Add aria-labelledby attribute to reference label.
      $dropdown.attr('aria-labelledby', "facet_" + id + "_label");
      var default_option_label = settings.facets.dropdown_widget[id]['facet-default-option-label'];

      // Add empty text option first.
      var $default_option = $('<option></option>')
        .attr('value', '')
        .text(default_option_label);
      $dropdown.append($default_option);

      $ul.prepend('<li class="default-option"><a href="' + window.location.href.split('?')[0] + '">' + Drupal.checkPlain(default_option_label) + '</a></li>');

      var has_active = false;
      $links.each(function () {
        var $link = $(this);
        var active = $link.hasClass('is-active');
        var type = $link.data('drupal-facet-widget-element-class');
        var $option = $('<option></option>')
          .attr('value', $link.attr('href'))
          .data($link.data())
          .addClass(type)
          .val($link.data('drupal-facet-filter-value'));
        if (active) {
          has_active = true;
          // Set empty text value to this link to unselect facet.
          $default_option.attr('value', $link.attr('href'));
          $ul.find('.default-option a').attr("href", $link.attr('href'));
          $option.attr('selected', 'selected');
          $link.find('.js-facet-deactivate').remove();
        }
        $option.text(function () {
          // Add hierarchy indicator in case hierarchy is enabled.
          var $parents = $link.parent('li.facet-item').parents('li.facet-item');
          var prefix = '';
          for (var i = 0; i < $parents.length; i++) {
            prefix += '-';
          }
          return prefix + ' ' + $link.text().trim();
        });
        $dropdown.append($option);
      });

      // Go to the selected option when it's clicked.
      $dropdown.on('change.facets', function () {
        var anchor = $($ul).find("[data-drupal-facet-item-id='" + $(this).find(':selected').data('drupalFacetItemId') + "']");
        var $linkElement = (anchor.length > 0) ? $(anchor) : $ul.find('.default-option a');
        var url = $linkElement.attr('href');
        $(this).trigger('facets_filter', [ url ]);
      });

      // Append empty text option.
      if (!has_active) {
        $default_option.attr('selected', 'selected');
      }

      // Replace links with dropdown.
      $ul.after($dropdown).hide();
      Drupal.attachBehaviors($dropdown.parent()[0], Drupal.settings);
    });
  };

})(jQuery, Drupal, once);
