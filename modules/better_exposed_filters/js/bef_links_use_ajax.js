/**
 * @file
 * bef_links_use_ajax.js
 *
 * Allows to use ajax with Bef links.
 */

(function (once) {

  // This is only needed to provide ajax functionality
  Drupal.behaviors.better_exposed_filters_select_as_links = {
    attach: function (context) {
      once('bef-links-use-ajax', '.bef-links.bef-links-use-ajax', context).forEach(function (element) {
        const links_name = element.getAttribute('data-name');
        const links_multiple = element.getAttribute('data-multiple');
        const form = element.closest('form');

        element.querySelectorAll('a').forEach((el) => el.addEventListener('click', (event) => {
          // Prevent following the link URL.
          event.preventDefault();

          const target = event.target;

          const link_name = links_multiple ? target.name : links_name;
          const link_value = target.name.substring(links_multiple ? links_name.length - 1 : links_name.length).replace(/^\[|\]$/g, '');
          const filter = form.querySelector('input[name="' + link_name + '"]');
          const filters = form.querySelectorAll('input[name^="' + links_name + '"]');

          if (target.classList.contains('bef-link--selected')) {
            // The previously selected link is selected again. Deselect it.
            target.classList.remove('bef-link--selected');
            if (!links_multiple || link_value === 'All') {
              filters.forEach((el) => el.remove());
            }
            else {
              filter.remove();
            }
          }
          else {
            if (!links_multiple || link_value === 'All') {
              element.querySelectorAll('.bef-link--selected').forEach((el) => el.classList.remove('bef-link--selected'));
            }
            target.classList.add('bef-link--selected');

            if (!filter) {
              const newFilter = document.createElement("input");
              newFilter.type = "hidden";
              newFilter.name = link_name;
              newFilter.value = link_value;
              element.appendChild(newFilter);
            }
 else {
              filter.value = link_value;
            }
          }

          // Submit the form.
          form.querySelector('.form-submit:not([data-drupal-selector*=edit-reset])').click();
        }));
      });
    }
  };
})(once);
