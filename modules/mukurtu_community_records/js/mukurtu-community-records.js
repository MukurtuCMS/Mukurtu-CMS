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
      this.updateTitle(element);
    },

    /**
     * Show the active record's title in the heading that names it.
     *
     * Inside a multipage item that is the current page heading, since the
     * page H1 names the multipage item. Otherwise it is the page H1, along
     * with the matching part of the document title.
     *
     * @param {HTMLElement} element
     *   The active pane.
     */
    updateTitle: function(element) {
      const title = element.dataset.title;
      if (!title) {
        return;
      }

      const multipage = element.closest('.multipage-items');
      const h1 = document.querySelector('.page__title h1');
      // A node H1 wraps the title in its field markup; keep that wrapper.
      const heading = multipage
        ? multipage.querySelector('[data-mukurtu-page-title]')
        : h1?.querySelector('.field--name-title') ?? h1;
      if (!heading) {
        return;
      }

      const previous = heading.textContent.trim();
      heading.textContent = title;
      if (!multipage && previous && document.title.startsWith(previous)) {
        document.title = title + document.title.slice(previous.length);
      }
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
