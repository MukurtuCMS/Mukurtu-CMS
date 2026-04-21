<?php

namespace Drupal\geolocation_geometry_natural_earth_us_states\Plugin\geolocation\GeolocationGeometryData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geolocation_geometry_data\GeolocationGeometryDataBase;
use Shapefile\ShapefileException;

/**
 * Import US states.
 *
 * @GeolocationGeometryData(
 *   id = "natural_earth_us_states",
 *   name = @Translation("Natural Earth US States"),
 *   description = @Translation("Geometries of all us states."),
 * )
 */
class UsStates extends GeolocationGeometryDataBase {

  /**
   * {@inheritdoc}
   */
  public $sourceUri = 'https://www.naturalearthdata.com/http//www.naturalearthdata.com/download/110m/cultural/ne_110m_admin_1_states_provinces.zip';

  /**
   * {@inheritdoc}
   */
  public $sourceFilename = 'ne_110m_admin_1_states_provinces.zip';

  /**
   * {@inheritdoc}
   */
  public $localDirectory = 'geolocation_geometry_natural_earth_us_states';

  /**
   * {@inheritdoc}
   */
  public $shapeFilename = 'ne_110m_admin_1_states_provinces.shp';

  /**
   * {@inheritdoc}
   */
  public function import(&$context): TranslatableMarkup {
    parent::import($context);
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $logger = \Drupal::logger('geolocation_us_states');

    try {
      /** @var \Shapefile\Geometry\Geometry $record */
      while ($record = $this->shapeFile->fetchRecord()) {
        if ($record->isDeleted()) {
          continue;
        }

        /** @var \Drupal\taxonomy\TermInterface $term */
        $term = $taxonomy_storage->create([
          'vid' => 'geolocation_us_states',
          'name' => $record->getData('NAME'),
        ]);
        $term->set('field_geometry_data_geometry', [
          'geojson' => $record->getGeoJSON(),
        ]);
        $term->save();
      }
      return t('Done importing US States.');
    }
    catch (ShapefileException $e) {
      $logger->warning($e->getMessage());
      return t('ERROR importing US States.');
    }
  }

}
