(Drupal => {
  Drupal.behaviors.formDescriptionToggle = {
    attach: context => {
      context.querySelectorAll(".help-icon__description-toggle").forEach(((elem, index) => {
        if (elem.dataset.formDescriptionToggleAttached) return;
        elem.dataset.formDescriptionToggleAttached = !0;
        const a11yLabel = "help-icon-label--" + Math.floor(1e4 * Math.random());
        elem.setAttribute("id", a11yLabel), elem.setAttribute("aria-expanded", "false"), 
        elem.setAttribute("aria-controls", "target"), elem.closest(".help-icon__description-container").querySelectorAll(".claro-details__description, .fieldset__description, .form-item__description").forEach((description => {
          description.setAttribute("aria-labelledby", a11yLabel);
        })), elem.addEventListener("click", (event => {
          event.preventDefault(), event.stopPropagation(), "SUMMARY" === event.currentTarget.parentElement.tagName && !1 === event.currentTarget.parentElement.parentElement.open && (event.currentTarget.parentElement.parentElement.open = !0), 
          event.currentTarget.focus(), event.currentTarget.closest(".help-icon__description-container").querySelectorAll(".claro-details__description, .fieldset__description, .form-item__description").forEach(((description, index) => {
            if (index > 1) return;
            const setStatus = description.classList.contains("visually-hidden");
            event.currentTarget.setAttribute("aria-expanded", setStatus), description.classList.toggle("visually-hidden"), 
            description.setAttribute("aria-hidden", !setStatus);
          }));
        }));
      }));
    }
  };
})(Drupal);