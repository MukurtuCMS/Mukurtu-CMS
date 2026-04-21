(function($, Drupal) {
  'use strict';
  Drupal.behaviors.facets_reset_button = {
    attach: function (context) {

      var checked = $("input[checked='checked'].facets-checkbox");
      var block = $('.facets-reset-button a.facets-reset-link');

      if(checked.length != 0){
        block.show();
      }
      else{
        block.hide();
      }

    },
  };
})(jQuery, Drupal);
