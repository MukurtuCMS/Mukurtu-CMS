((Drupal, once, _ref) => {
  let {computePosition, offset, shift, flip} = _ref;
  Drupal.theme.ginTooltipWrapper = (dataset, title) => `<div class="gin-tooltip ${dataset.drupalTooltipClass || ""}">\n      ${dataset.drupalTooltip || title}\n    </div>`, 
  Drupal.behaviors.ginTooltip = {
    attach: context => {
      Drupal.ginTooltip.init(context);
    }
  }, Drupal.ginTooltip = {
    init: function(context) {
      once("ginTooltipInit", "[data-gin-tooltip]", context).forEach((trigger => {
        const title = trigger.title;
        title && (trigger.title = ""), trigger.insertAdjacentHTML("afterend", Drupal.theme.ginTooltipWrapper(trigger.dataset, title));
        const tooltip = trigger.nextElementSibling, updatePosition = () => {
          this.computePosition(trigger, tooltip);
        };
        new ResizeObserver(updatePosition).observe(trigger), new MutationObserver(updatePosition).observe(trigger, {
          attributes: !0,
          childList: !0,
          subtree: !0
        }), trigger.addEventListener("mouseover", updatePosition), trigger.addEventListener("focus", updatePosition);
      }));
    },
    computePosition: function(trigger, tooltip) {
      let placement = arguments.length > 2 && void 0 !== arguments[2] ? arguments[2] : "bottom-end";
      computePosition(trigger, tooltip, {
        strategy: "absolute",
        placement: trigger.dataset.drupalTooltipPosition || placement,
        middleware: [ flip({
          padding: 16
        }), offset(6), shift({
          padding: 16
        }) ]
      }).then((_ref2 => {
        let {x, y} = _ref2;
        Object.assign(tooltip.style, {
          "inset-inline-start": `${x}px`,
          "inset-block-start": `${y}px`
        });
      }));
    }
  };
})(Drupal, once, FloatingUIDOM);