(($, Drupal, once) => {
  Drupal.behaviors.ginLbLb = {
    attach: (context) => {
      once('gin-lb-lb', '.layout-builder-block', context).forEach((elm) => {
        const $div = $(elm);
        const activeClass = 'gin-lb--disable-section-focus';
        const observer = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
              if ($(mutation.target).hasClass('focus')) {
                $(mutation.target)
                  .parents('.layout-builder__section')
                  .addClass(activeClass);
              } else {
                $(mutation.target)
                  .parents('.layout-builder__section')
                  .removeClass(activeClass);
              }
            }
          });
        });
        observer.observe($div[0], {
          attributes: true,
        });
      });
    },
  };
})(jQuery, Drupal, once);
