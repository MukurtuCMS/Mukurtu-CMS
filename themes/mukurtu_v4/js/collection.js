((Drupal, once, jQuery) => {
  var element = document.getElementById("collection-body");
  var paragraph = element.firstChild;
  let fontSize = parseFloat(getComputedStyle(element).fontSize);
  let numberOfLines = Math.ceil(element.offsetHeight / fontSize);

  if (numberOfLines > 2) {
    // Element has overflow so we truncate.
    paragraph.classList.add("line-clamp-2");
    const newButton = document.createElement("button");
    newButton.textContent = "Show More";
    newButton.style.maxWidth = 'fit-content';
    element.appendChild(newButton);

    newButton.addEventListener("click", () => {
      paragraph.classList.remove("line-clamp-2");
      element.removeChild(newButton);
    });
  } else {
    // Element does overflow so we do nothing.
  }

})(Drupal, once, jQuery);
