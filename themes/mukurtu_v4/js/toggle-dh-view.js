((Drupal, once, jQuery) => {
  "use strict";

  // On the Digital Heritage page, change the view from list to grid to map.
  const gridLink = document.getElementById("mukurtu-browse-grid-switch-link");
  const listLink = document.getElementById("mukurtu-browse-list-switch-link");
  const mapLink = document.getElementById("mukurtu-browse-mode-switch-link");

  gridLink.addEventListener("click", (event) => {
    // Toggle class
    // gridLink.toggle
    classes.toggle("active-toggle");
  });

  listLink.addEventListener("click", (event) => {
    // Toggle class
    classes.toggle("active-toggle");
  });

  mapLink.addEventListener("click", (event) => {
    // Toggle class
    classes.toggle("active-toggle");
  });

})(Drupal, once, jQuery);
