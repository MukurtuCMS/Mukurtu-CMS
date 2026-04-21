<?php

namespace Drupal\geolocation_geometry_world_heritage\Plugin\geolocation\GeolocationGeometryData;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geolocation_geometry_data\GeolocationGeometryDataBase;

/**
 * Import Countries of the world.
 *
 * @GeolocationGeometryData(
 *   id = "unesco_world_heritage",
 *   name = @Translation("UNESCO World Heritage"),
 *   description = @Translation("Points of all UNESCO world heritage sites."),
 * )
 */
class UnescoWorldHeritage extends GeolocationGeometryDataBase {

  /**
   * URI to archive.
   *
   * @var string
   */
  public $sourceUri = 'https://whc.unesco.org/en/list/xml/';

  /**
   * Filename of archive.
   *
   * @var string
   */
  public $sourceFilename = 'world_heritage_sites.xml';

  /**
   * Download batch callback.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Batch return.
   */
  public function download(): TranslatableMarkup {
    $destination = \Drupal::service('file_system')->getTempDirectory() . '/' . $this->sourceFilename;
    \Drupal::service('file_system')->delete($destination);

    if (!is_file($destination)) {
      $client = \Drupal::httpClient();
      $client->get($this->sourceUri, [
        'save_to' => $destination,
        'headers' => [
          'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:100.0) Gecko/20100101 Firefox/100.0',
        ],
      ]);
    }

    $fileconent = file_get_contents($destination);

    return t('Successfully downloaded @url', ['@url' => $this->sourceUri]);
  }

  /**
   * {@inheritdoc}
   */
  public function import(&$context): TranslatableMarkup {
    $filename = \Drupal::service('file_system')->getTempDirectory() . '/' . $this->sourceFilename;
    if (!file_exists($filename)) {
      return t('Error importing World heritage sites.');
    }

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    $use_xml_errors = libxml_use_internal_errors();
    libxml_use_internal_errors(TRUE);

    try {
      foreach (simplexml_load_file($filename) as $site) {
        /** @var \Drupal\taxonomy\TermInterface $term */
        $node = $node_storage->create([
          'type' => 'unesco_world_heritage',
          'title' => Html::decodeEntities(strip_tags($site->site)),
          'field_geometry_data_description' => [
            'value' => $site->short_description,
            'format' => filter_default_format(),
          ],
          'field_geometry_data_point' => [
            'geojson' => '{"type": "Point", "coordinates": [' . $site->longitude . ', ' . $site->latitude . ']}',
          ],
        ]);
        $node->save();
      }
    }
    catch (\Exception $e) {
      return t("Error while loading XML: %error", ['%error' => $e->getMessage()]);
    }

    $xml_errors = libxml_get_errors();

    if ($xml_errors) {
      return t("Error while processing XML: %errors Errors", ['%errors' => count($xml_errors)]);
    }

    libxml_clear_errors();
    libxml_use_internal_errors($use_xml_errors);

    return t('Done importing World heritage sites.');
  }

}
