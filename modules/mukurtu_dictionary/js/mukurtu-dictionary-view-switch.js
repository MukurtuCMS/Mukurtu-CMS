// Toggle list/map view on the /dictionary page.
// Uses a separate localStorage key from the /browse page toggle.

((Drupal, once) => {
  "use strict";

  const browseModes = ["list", "map"];
  const DEFAULT_BROWSE_MODE = "list";
  const STORAGE_KEY = "dictionaryBrowseMode";
  let listLink, mapLink, listContent, mapContent, browseLinks;

  function getBrowseMode() {
    const browseMode = localStorage.getItem(STORAGE_KEY);
    if (browseModes.includes(browseMode)) {
      return browseMode;
    }
    return DEFAULT_BROWSE_MODE;
  }

  function setBrowseMode(mode) {
    if (!browseModes.includes(mode)) {
      mode = DEFAULT_BROWSE_MODE;
    }
    localStorage.setItem(STORAGE_KEY, mode);
  }

  function handleToggleBrowseMode(e) {
    const mode = e.target.dataset.browseMode;
    if (mode) {
      toggleBrowseMode(mode);
    }
  }

  function toggleBrowseMode(browseMode) {
    setBrowseMode(browseMode);

    if (getBrowseMode() === "list") {
      listLink.classList.add("active-toggle");
      mapLink?.classList.remove("active-toggle");
      listContent.hidden = false;
      mapContent.hidden = true;
    } else {
      mapLink.classList.add("active-toggle");
      listLink?.classList.remove("active-toggle");
      mapContent.hidden = false;
      listContent.hidden = true;
    }
  }

  function init(container) {
    listContent = container.querySelector(".list");
    mapContent = container.querySelector(".map");
    browseLinks = container.querySelector(".browse-links");
    listLink = container.querySelector("[data-browse-mode=list]");
    mapLink = container.querySelector("[data-browse-mode=map]");

    browseLinks.addEventListener("click", handleToggleBrowseMode);
    toggleBrowseMode(getBrowseMode());
  }

  Drupal.behaviors.mukurtuDictionaryViewSwitch = {
    attach(context) {
      once("dictionaryBrowseContainer", ".dictionary-view-container", context).forEach(init);
    },
  };

})(Drupal, once);
