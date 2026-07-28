((Drupal, once) => {
  "use strict";

  Drupal.behaviors.localContextsTabs = {
    attach(context) {
      once('local-contexts-tabs', '.local-contexts-dialog__tabs', context).forEach((tablist) => {
        const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));

        const selectTab = (tab) => {
          tabs.forEach((candidate) => {
            const selected = candidate === tab;
            candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
            candidate.tabIndex = selected ? 0 : -1;
            const panel = document.getElementById(candidate.getAttribute('aria-controls'));
            if (panel) {
              panel.hidden = !selected;
            }
          });
        };

        tabs.forEach((tab, index) => {
          tab.addEventListener('click', () => selectTab(tab));

          tab.addEventListener('keydown', (e) => {
            let newIndex = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
              newIndex = (index + 1) % tabs.length;
            }
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
              newIndex = (index - 1 + tabs.length) % tabs.length;
            }
            else if (e.key === 'Home') {
              newIndex = 0;
            }
            else if (e.key === 'End') {
              newIndex = tabs.length - 1;
            }

            if (newIndex !== null) {
              e.preventDefault();
              tabs[newIndex].focus();
              selectTab(tabs[newIndex]);
            }
          });
        });
      });
    },
  };
})(Drupal, once);
