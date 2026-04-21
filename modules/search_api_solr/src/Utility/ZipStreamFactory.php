<?php

namespace Drupal\search_api_solr\Utility;

use ZipStream\ZipStream;

/**
 * Handles ZipStream v2 vs v3 issues.
 */
class ZipStreamFactory {

  /**
   * Returns a ZipStream instance.
   *
   * @param \ZipStream\Option\Archive|ressource|NUll $archive_options_or_ressource
   *   Archive options.
   *
   * @return \ZipStream\ZipStream
   *   The ZipStream that contains all configuration files.
   */
  public static function createInstance($name, $archive_options_or_ressource = NULL): ZipStream {
    if (class_exists('\ZipStream\Option\Archive')) {
      // Version 2.x.
      return new ZipStream($name, $archive_options_or_ressource);
    }

    // In case of PHP 7.4 the ZipStream 3.x code leads to parse errors.
    // So it has to moved to another file.
    return ZipStream3Factory::createInstance($name, $archive_options_or_ressource);
  }

}
