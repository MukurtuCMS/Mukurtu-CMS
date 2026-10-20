/**
 * @file
 * Makes the visitor map's tooltips dismissible and hoverable (WCAG 1.4.13).
 *
 * Leaflet shows a marker's tooltip on hover and on focus, but out of the box:
 * - Escape does not close it;
 * - it closes the moment the pointer leaves the marker, so it cannot be
 *   hovered (and its text cannot be magnified or selected);
 * - a hover tooltip and a focus tooltip can both be open at once, covering
 *   neighbouring circles.
 *
 * Scoped to the visitor map by its fixed id, so no other Leaflet map changes.
 *
 * @see \Drupal\mukurtu_core\Service\VisitorsCountryMap::MAP_ELEMENT_ID
 */
(function ($, Drupal) {

  const MAP_ID = 'visitors-country-map';

  /**
   * Delay before a pointer-out closes a tooltip, to let the pointer cross the
   * small gap between a circle and its tooltip.
   */
  const CLOSE_DELAY = 150;

  /**
   * Whether the pointer or keyboard focus is still on a marker or its tooltip.
   */
  function stillInUse(layer) {
    const tooltip = layer.getTooltip();
    const tooltipElement = tooltip && tooltip.getElement();
    const icon = layer.getElement && layer.getElement();
    return Boolean(
      (tooltipElement && tooltipElement.matches(':hover')) ||
      (icon && (icon.matches(':hover') || icon === document.activeElement))
    );
  }

  /**
   * Closes a marker's tooltip shortly after the pointer leaves it, unless the
   * pointer moved onto the tooltip (or focus is on the marker).
   */
  function closeSoon(layer) {
    window.setTimeout(() => {
      if (!stillInUse(layer)) {
        layer.closeTooltip();
      }
    }, CLOSE_DELAY);
  }

  /**
   * Replaces Leaflet's immediate close-on-pointer-out for one marker.
   */
  function makeHoverable(layer) {
    if (!layer.getTooltip || !layer.getTooltip() || layer.visitorsMapHoverable) {
      return;
    }
    layer.visitorsMapHoverable = true;
    // Leaflet binds closeTooltip to mouseout with no context of its own.
    layer.off('mouseout', layer.closeTooltip);
    layer.on('mouseout', () => closeSoon(layer));
  }

  $(document).on('leafletMapInit', (event, settings, lMap, mapid) => {
    if (mapid !== MAP_ID) {
      return;
    }

    let openTooltip = null;

    lMap.eachLayer(makeHoverable);
    lMap.on('layeradd', (e) => makeHoverable(e.layer));

    lMap.on('tooltipopen', (e) => {
      // One at a time: opening one, by hover or focus, closes any other.
      if (openTooltip && openTooltip !== e.tooltip && openTooltip._source) {
        openTooltip._source.closeTooltip();
      }
      openTooltip = e.tooltip;

      const element = e.tooltip.getElement();
      const source = e.tooltip._source;
      if (element && source && !element.visitorsMapHoverable) {
        element.visitorsMapHoverable = true;
        element.addEventListener('mouseleave', () => closeSoon(source));
      }
    });

    lMap.on('tooltipclose', (e) => {
      if (openTooltip === e.tooltip) {
        openTooltip = null;
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && openTooltip && openTooltip._source) {
        openTooltip._source.closeTooltip();
      }
    });
  });

})(jQuery, Drupal);
