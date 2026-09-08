// JS functionality to toggle active class on map/grid/list buttons
// and toggle visibility of map/list/grid contents.

((Drupal, once) => {
  "use strict";

  const browseModes = ["list", "grid", "map"];
  const DEFAULT_BROWSE_MODE = "list";

  function getBrowseMode() {
    const browseMode = localStorage.getItem("browseMode");
    if (browseModes.includes(browseMode)) {
      return browseMode;
    }
    return DEFAULT_BROWSE_MODE;
  }

  function setBrowseMode(mode) {
    if (!browseModes.includes(mode)) {
      mode = DEFAULT_BROWSE_MODE;
    }
    localStorage.setItem("browseMode", mode);
  }

  // Toggling `hidden` off a column-count masonry grid doesn't reliably make
  // the browser recompute column balance (especially once the grid has
  // already been balanced once before, e.g. on a prior grid->list->grid
  // switch), leaving stale, overlapping column layout. Briefly collapsing to
  // a single column and forcing a reflow before restoring it fixes that.
  function forceMasonryReflow(el) {
    const previousColumnCount = el.style.columnCount;
    el.style.columnCount = "1";
    void el.offsetHeight;
    el.style.columnCount = previousColumnCount;
  }

  // Each `.browse-container` on the page (e.g. the /browse page and a
  // Leaflet map popup rendering a Collection's own item browser) gets its
  // own independent set of elements and state, so switching modes in one
  // container never affects another container elsewhere on the same page.
  function init(browseContainer) {
    const listContent = browseContainer.querySelector(".list");
    const mapContent = browseContainer.querySelector(".map");
    const gridContent = browseContainer.querySelector(".grid");
    const browseLinks = browseContainer.querySelector(".browse-links");

    const gridLink = browseContainer.querySelector("[data-browse-mode=grid]");
    const listLink = browseContainer.querySelector("[data-browse-mode=list]");
    const mapLink = browseContainer.querySelector("[data-browse-mode=map]");

    function toggleBrowseMode(browseMode) {
      setBrowseMode(browseMode);
      const mode = getBrowseMode();

      if (mode === "list") {
        listLink.classList.add("active-toggle");
        gridLink?.classList.remove("active-toggle");
        mapLink?.classList.remove("active-toggle");

        listContent.hidden = false;
        mapContent.hidden = true;
        gridContent.hidden = true;
      }

      if (mode === "grid") {
        gridLink.classList.add("active-toggle");
        listLink?.classList.remove("active-toggle");
        mapLink?.classList.remove("active-toggle");

        gridContent.hidden = false;
        mapContent.hidden = true;
        listContent.hidden = true;
        forceMasonryReflow(gridContent);
      }

      if (mode === "map") {
        mapLink.classList.add("active-toggle");
        listLink?.classList.remove("active-toggle");
        gridLink?.classList.remove("active-toggle");

        mapContent.hidden = false;
        listContent.hidden = true;
        gridContent.hidden = true;
      }
    }

    browseLinks.addEventListener("click", (e) => {
      if (e.target.dataset.browseMode) {
        toggleBrowseMode(e.target.dataset.browseMode);
      }
    });
    toggleBrowseMode(getBrowseMode());
  }

  Drupal.behaviors.mukurtuBrowseSwitch = {
    attach(context) {
      once("browseContainer", ".browse-container", context).forEach(init);
    },
  };

})(Drupal, once);
