((Drupal, once) => {
  Drupal.behaviors.ginDropbutton = {
    attach: function(context) {
      once("ginDropbutton", ".dropbutton-multiple:has(.dropbutton--gin)", context).forEach((el => {
        el.querySelector(".dropbutton__toggle").addEventListener("click", (() => {
          this.updatePosition(el), window.addEventListener("scroll", (() => Drupal.debounce(this.updatePositionIfOpen(el), 100))), 
          window.addEventListener("resize", (() => Drupal.debounce(this.updatePositionIfOpen(el), 100)));
        }));
      }));
    },
    updatePosition: function(el) {
      const preferredDir = document.documentElement.dir ?? "ltr", secondaryAction = el.querySelector(".secondary-action"), dropMenu = el.querySelector(".dropbutton__items"), toggleHeight = el.offsetHeight, dropMenuWidth = dropMenu.offsetWidth, dropMenuHeight = dropMenu.offsetHeight, boundingRect = secondaryAction.getBoundingClientRect(), spaceBelow = window.innerHeight - boundingRect.bottom, spaceLeft = boundingRect.left, spaceRight = window.innerWidth - boundingRect.right;
      dropMenu.style.position = "fixed";
      const leftAlignStyles = {
        left: `${boundingRect.left}px`,
        right: "auto"
      }, rightAlignStyles = {
        left: "auto",
        right: window.innerWidth - boundingRect.right + "px"
      };
      "ltr" === preferredDir ? spaceRight >= dropMenuWidth ? Object.assign(dropMenu.style, leftAlignStyles) : Object.assign(dropMenu.style, rightAlignStyles) : spaceLeft >= dropMenuWidth ? Object.assign(dropMenu.style, rightAlignStyles) : Object.assign(dropMenu.style, leftAlignStyles), 
      dropMenu.style.top = spaceBelow >= dropMenuHeight ? `${boundingRect.bottom}px` : boundingRect.top - toggleHeight - dropMenuHeight + "px";
    },
    updatePositionIfOpen: function(el) {
      el.classList.contains("open") && this.updatePosition(el);
    }
  };
})(Drupal, once);