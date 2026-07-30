/**
 * @file
 * Filter behavior for the grouped Referenced Content display.
 */

((Drupal, once) => {
  Drupal.behaviors.relatedContentFilter = {
    attach(context) {
      once('related-content-filter', '[data-related-content-filter]', context).forEach(bar => {
        const container = bar.closest('.related-content');
        if (!container) return;

        const status = container.querySelector('[data-related-content-status]');
        const buttons = Array.from(bar.querySelectorAll('[data-filter-value]'));
        const items = Array.from(container.querySelectorAll('.field__item'));

        function applyFilter(value) {
          let shown = 0;
          items.forEach(item => {
            const sources = (item.dataset.source || '').split(' ');
            const vocabularies = (item.dataset.vocabulary || '').split(' ');
            const matches = value === 'all' || sources.includes(value) || vocabularies.includes(value);
            item.hidden = !matches;
            if (matches) shown += 1;
          });
          if (status) {
            status.textContent = Drupal.formatPlural(
              items.length,
              'Showing @shown of @count item',
              'Showing @shown of @count items',
              { '@shown': shown },
            );
          }
        }

        function activate(index) {
          buttons.forEach((button, i) => {
            const active = i === index;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-checked', active ? 'true' : 'false');
            button.setAttribute('tabindex', active ? '0' : '-1');
          });
          applyFilter(buttons[index].dataset.filterValue);
        }

        buttons.forEach((button, index) => {
          button.addEventListener('click', () => activate(index));

          button.addEventListener('keydown', e => {
            let newIndex = index;
            if (e.key === 'ArrowRight') newIndex = (index + 1) % buttons.length;
            else if (e.key === 'ArrowLeft') newIndex = (index - 1 + buttons.length) % buttons.length;
            else if (e.key === 'Home') newIndex = 0;
            else if (e.key === 'End') newIndex = buttons.length - 1;
            else return;
            e.preventDefault();
            activate(newIndex);
            buttons[newIndex].focus();
          });
        });

        activate(0);
      });
    },
  };
})(Drupal, once);
