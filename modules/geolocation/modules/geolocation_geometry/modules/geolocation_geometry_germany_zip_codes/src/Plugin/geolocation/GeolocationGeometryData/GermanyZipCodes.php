<?php

namespace Drupal\geolocation_geometry_germany_zip_codes\Plugin\geolocation\GeolocationGeometryData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geolocation_geometry_data\GeolocationGeometryDataBase;
use Shapefile\ShapefileException;

/**
 * Import Zip Code geometries in Germany.
 *
 * @GeolocationGeometryData(
 *   id = "germany_zip_codes",
 *   name = @Translation("Germany Zip Codes"),
 *   description = @Translation("Geometries of all zip in Germany."),
 * )
 */
class GermanyZipCodes extends GeolocationGeometryDataBase {

  /**
   * {@inheritdoc}
   */
  public $sourceUri = 'https://www.suche-postleitzahl.org/download_v1/wgs84/mittel/plz-5stellig/shapefile/plz-5stellig.shp.zip';

  /**
   * {@inheritdoc}
   */
  public $sourceFilename = 'plz-5stellig.shp.zip';

  /**
   * {@inheritdoc}
   */
  public $localDirectory = 'plz';

  /**
   * {@inheritdoc}
   */
  public $shapeFilename = 'plz-5stellig.shp';

  /**
   * {@inheritdoc}
   */
  public function import(&$context): TranslatableMarkup {
    parent::import($context);
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $logger = \Drupal::logger('geolocation_geometry_germany_zip_codes');

    try {
      /** @var \Shapefile\Geometry\Geometry $record */
      while ($record = $this->shapeFile->fetchRecord()) {
        if ($record->isDeleted()) {
          continue;
        }

        /** @var \Drupal\taxonomy\TermInterface $term */
        $term = $taxonomy_storage->create([
          'vid' => 'germany_zip_codes',
          'name' => $record->getData('PLZ'),
        ]);
        $term->set('field_geometry_data_geometry', [
          'geojson' => $record->getGeoJSON(),
        ]);
        $term->set('field_city', $record->getData('NOTE'));
        $term->save();
      }
      return t('Done importing PLZ');
    }
    catch (ShapefileException $e) {
      $logger->warning($e->getMessage());
      return t('Error importing PLZ');
    }
  }

}
