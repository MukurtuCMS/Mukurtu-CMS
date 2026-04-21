<?php

namespace Drupal\geolocation_geometry_data;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileReader;

/**
 * Class Geolocation GeometryData Base.
 *
 * @package Drupal\geolocation_geometry_data
 */
abstract class GeolocationGeometryDataBase {

  /**
   * URI to archive.
   *
   * @var string
   */
  public $sourceUri = '';

  /**
   * Filename of archive.
   *
   * @var string
   */
  public $sourceFilename = '';

  /**
   * Directory extract of archive.
   *
   * @var string
   */
  public $localDirectory = '';

  /**
   * Extracted filename.
   *
   * @var string
   */
  public $shapeFilename = '';

  /**
   * Shape file.
   *
   * @var \Shapefile\ShapefileReader|null
   */
  public $shapeFile;

  /**
   * Return this batch.
   *
   * @return array
   *   Batch return.
   */
  public function getBatch(): array {
    $operations = [
      [[$this, 'download'], []],
      [[$this, 'import'], []],
    ];

    return [
      'title' => t('Import Shapefile'),
      'operations' => $operations,
      'progress_message' => t('Finished step @current / @total.'),
      'init_message' => t('Import is starting.'),
      'error_message' => t('Something went horribly wrong.'),
    ];
  }

  /**
   * Download batch callback.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Batch return.
   */
  public function download(): TranslatableMarkup {
    $destination = \Drupal::service('file_system')->getTempDirectory() . '/' . $this->sourceFilename;

    if (!is_file($destination)) {
      $client = \Drupal::httpClient();
      $client->get($this->sourceUri, ['save_to' => $destination]);
    }

    if (!empty($this->localDirectory) && substr(strtolower($this->sourceFilename), -3) === 'zip') {

      \Drupal::service('file_system')->deleteRecursive(\Drupal::service('file_system')->getTempDirectory() . '/' . $this->localDirectory);

      $zip = new \ZipArchive();
      $res = $zip->open($destination);
      if ($res === TRUE) {
        $zip->extractTo(\Drupal::service('file_system')->getTempDirectory() . '/' . $this->localDirectory);
        $zip->close();

        \Drupal::service('file_system')->delete($destination);
      }
      else {
        return t('ERROR downloading @url', ['@url' => $this->sourceUri]);
      }
    }

    return t('Successfully downloaded @url', ['@url' => $this->sourceUri]);
  }

  /**
   * Import batch callback.
   *
   * @param mixed $context
   *   Batch context.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   Batch return.
   */
  public function import(&$context): ?TranslatableMarkup {
    if (empty($this->shapeFilename)) {
      return t('Shapefilename is empty');
    }

    try {
      $this->shapeFile = new ShapefileReader(\Drupal::service('file_system')->getTempDirectory() . '/' . $this->localDirectory . '/' . $this->shapeFilename, [
        Shapefile::OPTION_DBF_IGNORED_FIELDS => ['BRK_GROUP'],
      ]);
    }
    catch (ShapefileException $e) {
      return t('Failed %message', ['%message' => $e->getMessage()]);
    }

    if (empty($this->shapeFile)) {
      throw new \Exception(t("Shapefile %file is empty.", ['%file' => \Drupal::service('file_system')->getTempDirectory() . '/' . $this->localDirectory . '/' . $this->shapeFilename]));
    }

    return NULL;
  }

}
