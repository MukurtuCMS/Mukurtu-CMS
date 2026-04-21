<?php

namespace Drupal\search_api_solr\Utility;

use ZipStream\ZipStream;

/**
 * Handles ZipStream v2 vs v3 issues.
 */
class ZipStream3Factory {

  /**
   * Returns a ZipStream instance.
   *
   * @param ressource|NUll $ressource
   *   Archive options.
   *
   * @return \ZipStream\ZipStream
   *   The ZipStream that contains all configuration files.
   */
  public static function createInstance($name, $ressource = NULL): ZipStream {
    if ($ressource) {
      return new ZipStream(outputStream: $ressource, enableZip64: FALSE, defaultEnableZeroHeader: FALSE);
    }

    return new ZipStream(enableZip64: FALSE, defaultEnableZeroHeader: FALSE, outputName: $name);
  }

}
