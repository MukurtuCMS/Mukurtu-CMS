/**
 * @file
 * Tab switching behavior for dictionary word entries.
 */

((Drupal, once) => {
  Drupal.behaviors.dictionaryWordTabs = {
    attach(context) {
      once('dictionary-word-tabs', '.dictionary-word-tabs', context).forEach(tabs => {
        const tabLinks = tabs.querySelectorAll('.horizontal-tabs-list a');
        const panes = tabs.querySelectorAll('.horizontal-tabs-pane');

        tabLinks.forEach((link, index) => {
          link.addEventListener('click', e => {
            e.preventDefault();
            tabLinks.forEach(l => {
              l.closest('li').classList.remove('selected');
              l.setAttribute('aria-selected', 'false');
            });
            panes.forEach(p => { p.hidden = true; });
            link.closest('li').classList.add('selected');
            link.setAttribute('aria-selected', 'true');
            panes[index].hidden = false;
          });
        });
      });
    },
  };
})(Drupal, once);
