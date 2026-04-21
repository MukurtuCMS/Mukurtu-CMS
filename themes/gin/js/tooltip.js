((Drupal, once, { computePosition, offset, shift, flip }) => {
  /**
   * Theme function for a tooltip.
   *
   * @param {object} dataset
   *   The dataset object.
   * @param {string} dataset.drupalTooltipClass
   *   Extra class for theming.
   * @param {string} dataset.drupalTooltip
   *   The text for tooltip.
   * @param {string} dataset.title
   *   Fallback text for tooltip.
   *
   * @return {HTMLElement}
   *   A DOM Node.
   */
  Drupal.theme.ginTooltipWrapper = (dataset, title) =>
    `<div class="gin-tooltip ${dataset.drupalTooltipClass || ''}">
      ${dataset.drupalTooltip || title}
    </div>`;

  /**
   * Attaches the tooltip behavior to all required triggers.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the tooltip behavior.
   */

  Drupal.behaviors.ginTooltip = {
    attach: (context) => {
      Drupal.ginTooltip.init(context);
    },
  };

  Drupal.ginTooltip = {
    init: function (context) {
      once('ginTooltipInit', '[data-gin-tooltip]', context).forEach(
        (trigger) => {
          const title = trigger.title;

          // Remove title attribute
          if (title) {
            trigger.title = '';
          }

          trigger.insertAdjacentHTML(
            'afterend',
            Drupal.theme.ginTooltipWrapper(trigger.dataset, title),
          );
          const tooltip = trigger.nextElementSibling;
          const updatePosition = () => {
            this.computePosition(trigger, tooltip);
          };

          // Small trick to avoid tooltip stays on same place when button size changed.
          const ro = new ResizeObserver(updatePosition);
          ro.observe(trigger);

          const mo = new MutationObserver(updatePosition);
          mo.observe(trigger, {
            attributes: true,
            childList: true,
            subtree: true,
          });

          trigger.addEventListener('mouseover', updatePosition);
          trigger.addEventListener('focus', updatePosition);
        },
      );
    },

    computePosition: function (trigger, tooltip, placement = 'bottom-end') {
      computePosition(trigger, tooltip, {
        strategy: 'absolute',
        placement: trigger.dataset.drupalTooltipPosition || placement,
        middleware: [
          flip({ padding: 16 }),
          offset(6),
          shift({ padding: 16 }),
        ],
      }).then(({ x, y }) => {
        Object.assign(tooltip.style, {
          'inset-inline-start': `${x}px`,
          'inset-block-start': `${y}px`,
        });
      });
    },
  };
})(Drupal, once, FloatingUIDOM);
