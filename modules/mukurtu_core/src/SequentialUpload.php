<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

use Drupal\Component\Utility\Environment;
use Drupal\Core\StringTranslation\ByteSizeMarkup;

/**
 * Makes a managed_file field upload picked files one at a time.
 *
 * See the mukurtu_core/sequential-upload library.
 */
final class SequentialUpload {

  /**
   * Turns on one-at-a-time uploads for a managed_file element.
   *
   * Also gives the browser the largest file the server takes in one upload,
   * so it can refuse a bigger file before sending it. A request over the
   * limit can be refused before Drupal sees it, and Drupal then can't say
   * which file was too big. The limit goes in drupalSettings because
   * managed_file keeps only the class and ID of its #attributes.
   *
   * @param array $element
   *   The managed_file element.
   *
   * @return array
   *   The element, with the class, library, and limit added.
   */
  public static function apply(array $element): array {
    $max = (int) Environment::getUploadMaxSize();
    $element['#attributes']['class'][] = 'js-mukurtu-sequential-upload';
    $element['#attached']['library'][] = 'mukurtu_core/sequential-upload';
    $element['#attached']['drupalSettings']['mukurtuSequentialUpload'] = [
      'maxFilesize' => $max,
      'maxFilesizeLabel' => (string) ByteSizeMarkup::create($max),
    ];
    return $element;
  }

}
