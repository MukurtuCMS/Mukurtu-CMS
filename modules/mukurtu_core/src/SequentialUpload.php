<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

use Drupal\Component\Utility\Environment;
use Drupal\Core\StringTranslation\ByteSizeMarkup;

/**
 * Settings for managed_file fields that upload picked files one at a time.
 *
 * See the mukurtu_core/sequential-upload library, which the form must also
 * attach.
 */
final class SequentialUpload {

  /**
   * Returns the #attributes a managed_file field needs.
   *
   * The size limit lets the browser refuse a file the server can't take
   * before sending it. A request over the limit can be refused before Drupal
   * sees it, and Drupal then can't say which file was too big.
   *
   * @return array
   *   The class that turns on one-at-a-time uploads, and the largest file
   *   the server takes in one upload, in bytes and as text.
   */
  public static function attributes(): array {
    $max = (int) Environment::getUploadMaxSize();
    return [
      'class' => ['js-mukurtu-sequential-upload'],
      'data-mukurtu-max-filesize' => (string) $max,
      'data-mukurtu-max-filesize-label' => (string) ByteSizeMarkup::create($max),
    ];
  }

}
