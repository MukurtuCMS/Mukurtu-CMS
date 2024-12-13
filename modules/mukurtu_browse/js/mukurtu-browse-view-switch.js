// JS functionality to toggle active class on map/grid/list buttons
// and toggle visibility of map/list/grid contents. 

((Drupal, once) => {
  "use strict";
   let gridLink, listLink, mapLink, mapContent, listContent, gridContent, browseLinks, button;

  function listCheck() {

    if (localStorage.getItem("browseMode") == "list") {
      return true;
    } else {
      return false;
    }
  }

  function gridCheck() {
    if (localStorage.getItem("browseMode") == "grid") {
      return true;
    } else {
      return false;
    }
  }

  function mapCheck() {
    if (localStorage.getItem("browseMode") == "map") {
      return true;
    } else {
      return false;
    }
  }

  function toggleBrowseMode(e) {

    button = e.target.getAttribute('id');

    // First set the cookie. 
    if (button == "mukurtu-browse-list") {
      localStorage.setItem("browseMode", "list");
    } else if (button == "mukurtu-browse-grid") {
      localStorage.setItem("browseMode", "grid");
    } else if (button == "mukurtu-browse-map"){
      localStorage.setItem("browseMode", "map");
    }

    // Then perform class toggling/content visibility 
    // toggling based on which cookie set.
    if (listCheck()) {
      listLink.classList.add("active-toggle");
      gridLink.classList.remove("active-toggle");
      mapLink.classList.remove("active-toggle");

      listContent.hidden = false;
      mapContent.hidden = true;
      gridContent.hidden = true;
    }

    if (gridCheck()) {
      gridLink.classList.add("active-toggle");
      listLink.classList.remove("active-toggle");
      mapLink.classList.remove("active-toggle");

      gridContent.hidden = false;
      mapContent.hidden = true;
      listContent.hidden = true;
    }

    if (mapCheck()) {
      mapLink.classList.add("active-toggle");
      listLink.classList.remove("active-toggle");
      gridLink.classList.remove("active-toggle");

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

    gridLink = document.getElementById("mukurtu-browse-grid");
    listLink = document.getElementById("mukurtu-browse-list");
    mapLink = document.getElementById("mukurtu-browse-map");

    browseLinks.addEventListener("click", toggleBrowseMode);

    let cookie = localStorage.getItem("browseMode");

    // If cookie exists already, read it and 
    // simulate a click on the corresponding button.
    if (cookie) {
      button = `mukurtu-browse-${cookie}`;
      const event = new MouseEvent("click", {
        view: window,
        bubbles: true,
        cancelable: true,
      });
      const cb = document.getElementById(button);
      cb.dispatchEvent(event);
    }
  }

  Drupal.behaviors.mukurtuBrowseSwitch = {
    attach(context) {
      once("browseContainer", ".browse-container", context).forEach(init);
    },
  };
  
})(Drupal, once);
