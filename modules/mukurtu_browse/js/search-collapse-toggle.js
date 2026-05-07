((Drupal, once) => {
  'use strict';

  Drupal.behaviors.searchCollapseToggle = {
    attach(context) {
      once('searchCollapseToggle', '.search-collapse-toggle__input', context).forEach(input => {
        input.addEventListener('change', function (e) {
          e.stopPropagation();
          const url = new URL(window.location.href);
          const value = this.checked ? this.dataset.onValue : this.dataset.offValue;
          url.searchParams.set(this.dataset.param, value);
          window.location.href = url.toString();
        });
      });
    },
  };

})(Drupal, once);
