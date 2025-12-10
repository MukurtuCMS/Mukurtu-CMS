((Drupal, once) => {
  Drupal.behaviors.mukurtuCommunityRecordTabs = {
    attach: function (context, settings) {

      // Act on horizontal tabs only once.
      once('mukurtu-community-record-tabs', '.horizontal-tabs-panes', context).forEach((element) => {
        // Set up a mutation observer to detect changes to the active pane.
        const observer = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (mutation.attributeName !== 'class') {
              return;
            }
            if (!mutation.target.matches('.horizontal-tabs-pane')) {
              return;
            }
            if (mutation.target.matches('.horizontal-tab-hidden')) {
              return;
            }
            this.handlePaneChange(mutation.target);
          });
        });

        observer.observe(element, {
          attributes: true,
          subtree: true,
          attributeFilter: ['class']
        });
      });
    },

    /**
     * Handle a change in the active pane.
     *
     * @param {HTMLElement} element
     *   The active pane.
     */
    handlePaneChange: function(element) {
      const paneNid = element.dataset.nid;

      if (!paneNid) {
        return;
      }

      // Update the local tasks to reflect the active tab. Take the approach of
      // a search / replace on known patterns.
      const localTasksElements = document.querySelectorAll('.local-tasks');
      const patterns = [
        /node\/(\d+)/
      ];
      localTasksElements.forEach((localTasks) => {
        const anchors = localTasks.querySelectorAll('a');
        anchors.forEach((anchor) => {
          let href = anchor.getAttribute('href');
          if (href) {
            patterns.forEach((pattern) => {
              href = href.replace(pattern, (match, anchorNid) => {
                return match.replace(anchorNid, paneNid);
              });
            });
            anchor.setAttribute('href', href);
          }
        });
      });
    }
  }
})(Drupal, once);
