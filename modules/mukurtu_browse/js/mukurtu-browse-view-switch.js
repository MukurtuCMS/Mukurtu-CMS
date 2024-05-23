// JS functionality to toggle active class on map/grid/list buttons
// and toggle visibility of map/list/grid contents. 

((Drupal, once) => {
  "use strict";
   let gridLink, listLink, mapLink, mapContent, listContent, gridContent;
   
   function switchToMap() {
    mapLink.classList.add("active-toggle");
    listLink.classList.remove("active-toggle");
    gridLink.classList.remove("active-toggle");

    mapContent.hidden = false;
    listContent.hidden = true;
    gridContent.hidden = true;
  }

  function switchToList() {
    listLink.classList.add("active-toggle");
    gridLink.classList.remove("active-toggle");
    mapLink.classList.remove("active-toggle");

    listContent.hidden = false;
    mapContent.hidden = true;
    gridContent.hidden = true;
  }

  function switchToGrid() {
    gridLink.classList.add("active-toggle");
    listLink.classList.remove("active-toggle");
    mapLink.classList.remove("active-toggle");

    gridContent.hidden = false;
    mapContent.hidden = true;
    listContent.hidden = true;
  }


  function init() {
    listContent = document.querySelector(".list");
    listContent.hidden = false;
    mapContent = document.querySelector(".map");
    mapContent.hidden = true;
    gridContent = document.querySelector(".grid");
    gridContent.hidden = true;

    gridLink = document.getElementById("mukurtu-browse-grid");
    listLink = document.getElementById("mukurtu-browse-list");
    mapLink = document.getElementById("mukurtu-browse-map");
    mapContent = document.querySelector(".browse-content.map");
    listContent = document.querySelector(".browse-content.list");
    gridContent = document.querySelector(".browse-content.grid");

    listLink.addEventListener("click", switchToList);
    gridLink.addEventListener("click", switchToGrid);
    mapLink.addEventListener("click", switchToMap);
  }


  Drupal.behaviors.mukurtuBrowseSwitch = {
    attach(context) {
      once("browseLinks", ".browse-links", context).forEach(init);
    },
  };
  
})(Drupal, once);
