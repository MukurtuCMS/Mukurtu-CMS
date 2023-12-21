((Drupal, once, jQuery) => {
  "use strict";

  // On the Digital Heritage page, change the view from list to grid to map.
  const gridLink = document.getElementById("mukurtu-browse-grid-switch-link");
  const listLink = document.getElementById("mukurtu-browse-list-switch-link");
  const mapLink = document.getElementById("mukurtu-browse-mode-switch-link");
  const gridContainer = document.getElementsByClassName("browse-dh-list")[0];

  gridLink.addEventListener("click", (event) => {
    // Toggle button style class.
    gridLink.classList.toggle("active-toggle");
    listLink.className = listLink.className.replace("active-toggle", "");
    mapLink.className = mapLink.className.replace("active-toggle", "");

    gridContainer.className = gridContainer.className.replace(
      "browse-dh-list",
      "browse-dh-grid"
    );
  });

  listLink.addEventListener("click", (event) => {
    // Toggle button style class.
    listLink.classList.toggle("active-toggle");
    gridLink.className = gridLink.className.replace("active-toggle", "");
    mapLink.className = mapLink.className.replace("active-toggle", "");

    gridContainer.className = gridContainer.className.replace(
      "browse-dh-grid",
      "browse-dh-list"
    );
  });

  mapLink.addEventListener("click", (event) => {
    // Toggle button style class.
    mapLink.classList.toggle("active-toggle");
    gridLink.className = gridLink.className.replace("active-toggle", "");
    listLink.className = listLink.className.replace("active-toggle", "");
  });

})(Drupal, once, jQuery);
