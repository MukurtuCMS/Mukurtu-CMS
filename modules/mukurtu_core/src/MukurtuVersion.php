<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

use Drupal\Core\Extension\Exception\UnknownExtensionException;

/**
 * Reads the installed Mukurtu version from the profile's VERSION.md.
 */
final class MukurtuVersion {

  /**
   * Gets the Mukurtu version, such as "4.0.10" or "4.x-dev".
   *
   * @return string
   *   The first line of VERSION.md, or an empty string if it can't be read.
   */
  public static function get(): string {
    try {
      $path = \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/VERSION.md';
    }
    catch (UnknownExtensionException) {
      return '';
    }
    // fclose() has to stay inside the guard. Called outside it, a missing or
    // unreadable VERSION.md leaves $file undefined and fclose(NULL) raises a
    // TypeError on PHP 8.
    $version = '';
    if (file_exists($path) && ($file = fopen($path, 'r'))) {
      $version = trim((string) fgets($file));
      fclose($file);
    }
    return $version;
  }

}
