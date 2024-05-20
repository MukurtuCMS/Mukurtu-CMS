((Drupal, once) => {
  "use strict";
   let gridLink, listLink, mapLink, content;
  // On the Digital Heritage page, change the view from list to grid to map.

  function switchToList(event) {
    listLink.classList.add("active-toggle");
    gridLink.classList.remove("active-toggle");
    // mapLink.classList.remove("active-toggle");

    if (content.classList.contains("list")) {
      return;
    } else {
      content.classList.remove("grid");
      content.classList.add("list");
    }
  }

  function switchToGrid(event) {
    gridLink.classList.add("active-toggle");
    listLink.classList.remove("active-toggle");
    // mapLink.classList.remove("active-toggle");
    content.classList.remove("list");
    content.classList.add("grid");
  }


  function init() {
    gridLink = document.getElementById("mukurtu-browse-grid");
    listLink = document.getElementById("mukurtu-browse-list");
    // mapLink = document.getElementById("mukurtu-browse-map");
    content = document.querySelector(".browse-content");

    listLink.addEventListener("click", switchToList);
    gridLink.addEventListener("click", switchToGrid);
  }


  Drupal.behaviors.mukurtuBrowseSwitch = {
    attach(context) {
      once("browseLinks", ".browse-links", context).forEach(init);
    },
  };
  
})(Drupal, once);

  // mapLink?.addEventListener("click", (event) => {
  //   // Toggle button style class.
  //   mapLink.classList.toggle("active-toggle");
  //   gridLink.className = gridLink.className.replace("active-toggle", "");
  //   listLink.className = listLink.className.replace("active-toggle", "");
  // });

   // toggles.forEach((toggle) => {
      //  Check page url, if the map is showing, set it to active.  
      //   const url = new URL(window.location);
      //   if (url.searchParams.get('active') === 'grid') {
      //     gridLink.classList.toggle("active-toggle");
      //     mapLink.className = mapLink.className.replace("active-toggle", "");
      //     listLink.className = listLink.className.replace("active-toggle", "");
      //     content.className = content.className.replace(
      //       "browse-dh-list",
      //       "browse-dh-grid"
      //     );
      //   }
      // });