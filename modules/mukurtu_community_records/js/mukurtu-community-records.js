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

        // Show / hide the Community Record task based on the initial pane.
        const activePane = element.querySelector('[data-initial-cr=true]');
        this.toggleCrTask(activePane.dataset.isCr === 'true');
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
      const isCr = element.dataset.isCr === 'true';

      if (!paneNid) {
        return;
      }

      // Update the local tasks to reflect the active tab. Take the approach of
      // a search / replace on known patterns.
      this.updateTaskLinks(paneNid);
      this.toggleCrTask(isCr);
    },

    /**
     * Update the local tasks to reflect the active tab.
     *
     * @param {int|string} nid
     *   The nid of the active pane.
     */
    updateTaskLinks: function(nid) {
      const patterns = [
        /node\/(\d+)/
      ];
      const localTasksElements = document.querySelectorAll('.local-tasks');
      localTasksElements.forEach((localTasks) => {
        const anchors = localTasks.querySelectorAll('a');
        anchors.forEach((anchor) => {
          let href = anchor.getAttribute('href');
          if (href) {
            patterns.forEach((pattern) => {
              href = href.replace(pattern, (match, anchorNid) => {
                return match.replace(anchorNid, nid);
              });
            });
            anchor.setAttribute('href', href);
          }
        });
      });
    },

    /**
     * Toggle the Community Record task based on the active pane.
     *
     * @param {boolean} isCr
     *   Whether the active pane is a Community Record pane.
     */
    toggleCrTask: function(isCr) {
      const localTasksElements = document.querySelectorAll('.local-tasks');
      localTasksElements.forEach((localTasks) => {
        localTasks.querySelectorAll('li:has([href*=community-record\\/add]), li:has([href*=new-multipage])')
          .forEach((element) => {
            if (isCr) {
              element.setAttribute('hidden', '');
            }
            else {
              element.removeAttribute('hidden');
            }
          });
      });
    }
  }
})(Drupal, once);
