((Drupal, once, jQuery) => {
  "use strict";

  // On the Digital Heritage page, have dropdown submit on change.
  const element = document.getElementById("edit-sort-by--3");

  element.addEventListener("change", (event) => {
    // this.form.submit();
    document
      .getElementById(
        "views-exposed-form-mukurtu-digital-heritage-browse-mukurtu-digital-heritage-browse-block"
      )
      .submit();
  });

})(Drupal, once, jQuery);
