// JS functionality to toggle active class on map/grid/list buttons
// and toggle visibility of map/list/grid contents.

((Drupal, once) => {
  "use strict";

  const browseModes = ["list", "grid", "map"];
  const DEFAULT_BROWSE_MODE = "list";
  let gridLink, listLink, mapLink, mapContent, listContent, gridContent, browseLinks;

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

  function listCheck() {
    return getBrowseMode() === "list";
  }

  function gridCheck() {
    return getBrowseMode() === "grid";
  }

  function mapCheck() {
    return getBrowseMode() === "map";
  }

  function handleToggleBrowseMode(e) {
    toggleBrowseMode(e.target.dataset.browseMode);
  }

  function toggleBrowseMode(browseMode) {
    setBrowseMode(browseMode);

    // Then perform class toggling/content visibility toggling based on which
    // browse mode was set.
    if (listCheck()) {
      listLink.classList.add("active-toggle");
      gridLink?.classList.remove("active-toggle");
      mapLink?.classList.remove("active-toggle");

      listContent.hidden = false;
      mapContent.hidden = true;
      gridContent.hidden = true;
    }

    if (gridCheck()) {
      gridLink.classList.add("active-toggle");
      listLink?.classList.remove("active-toggle");
      mapLink?.classList.remove("active-toggle");

      gridContent.hidden = false;
      mapContent.hidden = true;
      listContent.hidden = true;
    }

    if (mapCheck()) {
      mapLink.classList.add("active-toggle");
      listLink?.classList.remove("active-toggle");
      gridLink?.classList.remove("active-toggle");

      mapContent.hidden = false;
      listContent.hidden = true;
      gridContent.hidden = true;
    }
  }

  function init() {
    listContent = document.querySelector(".list");
    mapContent = document.querySelector(".map");
    gridContent = document.querySelector(".grid");
    browseLinks = document.querySelector(".browse-links");

    gridLink = document.querySelector("[data-browse-mode=grid]");
    listLink = document.querySelector("[data-browse-mode=list]");
    mapLink = document.querySelector("[data-browse-mode=map]");

    browseLinks.addEventListener("click", handleToggleBrowseMode);
    toggleBrowseMode(getBrowseMode());
  }

  Drupal.behaviors.mukurtuBrowseSwitch = {
    attach(context) {
      once("browseContainer", ".browse-container", context).forEach(init);
    },
  };

})(Drupal, once);
