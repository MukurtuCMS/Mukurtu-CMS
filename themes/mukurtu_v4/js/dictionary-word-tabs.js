/**
 * @file
 * Tab switching behavior for dictionary word entries.
 */

((Drupal, once) => {
  Drupal.behaviors.dictionaryWordTabs = {
    attach(context) {
      once('dictionary-word-tabs', '.dictionary-word-tabs', context).forEach(tabs => {
        const tabLinks = Array.from(tabs.querySelectorAll('.horizontal-tabs-list a'));

        function activateTab(index) {
          tabLinks.forEach((l, i) => {
            l.closest('li').classList.toggle('selected', i === index);
            l.setAttribute('aria-selected', i === index ? 'true' : 'false');
            l.setAttribute('tabindex', i === index ? '0' : '-1');
          });
          // Use href-to-id matching so order in the DOM doesn't matter.
          tabLinks.forEach(l => {
            const pane = tabs.querySelector(l.getAttribute('href'));
            if (pane) pane.hidden = true;
          });
          const activePane = tabs.querySelector(tabLinks[index].getAttribute('href'));
          if (activePane) activePane.hidden = false;
        }

        // Set initial roving tabindex state.
        tabLinks.forEach((link, i) => link.setAttribute('tabindex', i === 0 ? '0' : '-1'));

        tabLinks.forEach((link, index) => {
          link.addEventListener('click', e => {
            e.preventDefault();
            activateTab(index);
          });

          link.addEventListener('keydown', e => {
            let newIndex = index;
            if (e.key === 'ArrowRight') newIndex = (index + 1) % tabLinks.length;
            else if (e.key === 'ArrowLeft') newIndex = (index - 1 + tabLinks.length) % tabLinks.length;
            else if (e.key === 'Home') newIndex = 0;
            else if (e.key === 'End') newIndex = tabLinks.length - 1;
            else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activateTab(index); return; }
            else return;
            e.preventDefault();
            activateTab(newIndex);
            tabLinks[newIndex].focus();
          });
        });
      });
    },
  };
})(Drupal, once);
