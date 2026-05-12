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

  function init() {
    listContent = document.querySelector(".dictionary-view-container .list");
    mapContent = document.querySelector(".dictionary-view-container .map");
    browseLinks = document.querySelector(".dictionary .browse-links");
    listLink = document.querySelector(".dictionary [data-browse-mode=list]");
    mapLink = document.querySelector(".dictionary [data-browse-mode=map]");

    if (!browseLinks || !listContent || !mapContent) {
      return;
    }

    browseLinks.addEventListener("click", handleToggleBrowseMode);
    toggleBrowseMode(getBrowseMode());
  }

  Drupal.behaviors.mukurtuDictionaryViewSwitch = {
    attach(context) {
      once("dictionaryBrowseContainer", ".dictionary-view-container", context).forEach(init);
    },
  };

})(Drupal, once);
