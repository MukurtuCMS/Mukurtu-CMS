(Drupal => {
  Drupal.behaviors.ginSticky = {
    attach: () => {
      once("ginSticky", ".region-sticky-watcher").forEach((() => {
        const observer = new IntersectionObserver((_ref => {
          let [e] = _ref;
          const regionSticky = document.querySelector(".region-sticky");
          regionSticky.classList.toggle("region-sticky--is-sticky", e.intersectionRatio < 1), 
          regionSticky.toggleAttribute("data-offset-top", e.intersectionRatio < 1), Drupal.displace(!0);
        }), {
          threshold: [ 1 ]
        }), element = document.querySelector(".region-sticky-watcher");
        element && observer.observe(element);
      }));
    }
  };
})(Drupal);