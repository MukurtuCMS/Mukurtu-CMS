<?php

namespace Drupal\geofield\Plugin\views;

/**
 * Trait class for Geofield Proximity View Handlers.
 */
trait GeofieldProximityHandlerTrait {

  /**
   * Add an Order By declaration to the View Query.
   *
   * @param string $order
   *   The order to be applied (ASC or DESC)
   */
  public function addQueryOrderBy($order) {
    $this->ensureMyTable();
    $lat_alias = $this->realField . '_lat';
    $lon_alias = $this->realField . '_lon';
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    try {
      /** @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface $source_plugin */
      $source_plugin = $this->proximitySourceManager->createInstance($this->options['source'], $this->options['source_configuration']);
      $source_plugin->setViewHandler($this);
      $source_plugin->setUnits($this->options['units']);

      if ($haversine_options = $source_plugin->getHaversineOptions()) {
        $haversine_options['destination_latitude'] = $this->tableAlias . '.' . $lat_alias;
        $haversine_options['destination_longitude'] = $this->tableAlias . '.' . $lon_alias;
        $query->addOrderBy(NULL, geofield_haversine($haversine_options), $order, $this->tableAlias . '_' . $this->field);
      }
    }
    catch (\Exception $e) {
      $this->getLogger('geofield')->error($e->getMessage());
    }
  }

}
