/**
 * @file
 * Accessible replacement for facets/soft-limit.js.
 *
 * Fixes: role/semantic mismatch on toggle (WCAG 4.1.2), missing aria-expanded
 * state (WCAG 4.1.2), and focus jumping into list on expand (WCAG 2.1.1).
 */

(function ($, once) {
  Drupal.behaviors.facetSoftLimit = {
    attach: (context, settings) => {
      if (settings.facets.softLimit !== 'undefined') {
        $.each(settings.facets.softLimit, function (facet, limit) {
          Drupal.facets.applySoftLimit(facet, limit, settings);
        });
      }
    },
  };

  Drupal.facets = Drupal.facets || {};

  /**
   * Applies the soft limit UI feature to a specific facets list.
   *
   * @param {string} facet
   *   The facet id.
   * @param {string} limit
   *   The maximum amount of items to show.
   * @param {object} settings
   *   Settings.
   */
  Drupal.facets.applySoftLimit = function (facet, limit, settings) {
    const zeroBasedLimit = limit - 1;
    const facetId = facet;
    const facetsList = $(`ul[data-drupal-facet-id="${facetId}"]`);

    // In case of multiple instances of a facet, we need to key them.
    if (facetsList.length > 1) {
      facetsList.each(function (key, $value) {
        $(this).attr('data-drupal-facet-id', `${facetId}-${key}`);
      });
    }

    // Hide facets over the limit.
    facetsList.each(function () {
      const allLiElements = $(this).find('li');
      $(once('applysoftlimit', allLiElements.slice(zeroBasedLimit + 1))).hide();
    });

    // Add "Show more" / "Show less" toggle button.
    $(
      once(
        'applysoftlimit',
        facetsList.filter(function () {
          return $(this).find('> li').length > limit;
        }),
      ),
    ).each(function () {
      const facet = $(this);
      const showLessLabel =
        settings.facets.softLimitSettings[facetId].showLessLabel;
      const showMoreLabel =
        settings.facets.softLimitSettings[facetId].showMoreLabel;

      const $toggle = $(
        '<a href="#" class="facets-soft-limit-link" role="button" aria-expanded="false"></a>',
      );
      $toggle[0].textContent = showMoreLabel;

      $toggle
        .on('click', function (e) {
          e.preventDefault();
          if (facet.find('> li:hidden').length > 0) {
            facet.find(`> li:gt(${zeroBasedLimit})`).slideDown();
            $(this)
              .attr('aria-expanded', 'true')
              .addClass('open')[0].textContent = showLessLabel;
          } else {
            facet.find(`> li:gt(${zeroBasedLimit})`).slideUp();
            $(this)
              .attr('aria-expanded', 'false')
              .removeClass('open')[0].textContent = showMoreLabel;
          }
        })
        .insertAfter($(this));
    });
  };
})(jQuery, once);
