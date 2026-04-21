(function ($, Drupal) {
  Drupal.behaviors.layoutBuilderRestrictionsByRegion = {
    attach: function (context, settings) {
      // On page load.
      $( 'input.restriction-type:checked').each(function () {
        displayToggle(this);
      });

      // On change of restrictions radios.
      $( 'input.restriction-type', context ).change(function (e) {
        displayToggle(this);
      });

      function displayToggle(element) {
        var layoutPlugin = $(element).attr('data-layout-plugin');

        if ($(element).val() == 'all') {
          $( 'details[data-layout-plugin="' + layoutPlugin + '"] tbody tr[data-region="all_regions"]', context ).removeClass('hidden');
          $( 'details[data-layout-plugin="' + layoutPlugin + '"] tbody tr:not([data-region="all_regions"])', context ).addClass('hidden');
        }
        else if ($(element).val() == 'per-region') {
          $( 'details[data-layout-plugin="' + layoutPlugin + '"] tbody tr[data-region="all_regions"]', context ).addClass('hidden');
          $( 'details[data-layout-plugin="' + layoutPlugin + '"] tbody tr:not([data-region="all_regions"])', context ).removeClass('hidden');
        }
      }
    }
  };
})(jQuery, Drupal);
