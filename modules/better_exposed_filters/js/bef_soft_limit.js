/**
 * @file
 * Provides the soft limit functionality.
 */

(function ($, once) {
  Drupal.behaviors.better_exposed_filters_soft_limit = {
    attach: function (context, settings) {
      if (settings.better_exposed_filters.soft_limit !== 'undefined') {
        $.each(settings.better_exposed_filters.soft_limit, function (bef) {
          Drupal.better_exposed_filters.applySoftLimit(bef, settings.better_exposed_filters.soft_limit[bef]);
        });
      }
    }
  };

  Drupal.better_exposed_filters = Drupal.better_exposed_filters || {};

  /**
   * Applies the soft limit UI feature to a specific filter.
   *
   * @param {string} filter_id
   *   The filter id.
   * @param {object} settings
   *   The maximum amount of items to show.
   */
  Drupal.better_exposed_filters.applySoftLimit = function (filter_id, settings) {
    var filter_selector = filter_id.replace(/_/g, '-');
    var zero_based_limit = (settings.limit - 1);
    var bef_list = $('[data-drupal-selector^="edit-' + filter_selector + '"] ' + settings.list_selector);

    // In case of multiple instances of a filter, we need to key them.
    if (bef_list.length > 1) {
      bef_list.each(function (key, $value) {
        $(this).attr('data-drupal-filter-id', filter_selector + '-' + key);
      });
    }

    // Hide befs over the limit.
    bef_list.each(function () {
      var allLiElements = $(this).find(settings.item_selector);
      $(once('applySoftLimit', allLiElements.slice(zero_based_limit + 1))).hide();
    });

    // Add "Show more" / "Show less" links.
    $(once('applySoftLimit', bef_list.filter(function () {
      return $(this).find(settings.item_selector).length > settings.limit;
    }))).each(function () {
      var bef = $(this);
      var showLessLabel = settings.show_less;
      var showMoreLabel = settings.show_more;
      $('<a href="#" class="bef-soft-limit-link"></a>')
        .text(showMoreLabel)
        .on('click', function () {
          if (bef.find(settings.item_selector + ':hidden').length > 0) {
            bef.find(settings.item_selector + ':gt(' + zero_based_limit + ')').slideDown();
            bef.find(settings.item_selector + ':lt(' + (zero_based_limit + 2) + ') a, ' + settings.item_selector +':lt(' + (zero_based_limit + 2) + ') input').focus();
            $(this).addClass('open').text(showLessLabel);
          }
          else {
            bef.find(settings.item_selector + ':gt(' + zero_based_limit + ')').slideUp();
            $(this).removeClass('open').text(showMoreLabel);
          }
          return false;
        }).insertAfter($(this));
    });
  };

})(jQuery, once);
