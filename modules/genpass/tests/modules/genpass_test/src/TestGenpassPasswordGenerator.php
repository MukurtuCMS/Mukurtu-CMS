<?php

declare(strict_types=1);

namespace Drupal\genpass_test;

use Drupal\genpass\GenpassPasswordGenerator;

/**
 * Provides testing wrapper of the genpass password generator.
 */
class TestGenpassPasswordGenerator extends GenpassPasswordGenerator {

  /**
   * Allow testing access to the protected initCharacterSets method.
   *
   * @param int $length
   *   The length of the password which will be generated.
   */
  public function initCharacterSets(int $length = -1): void {
    // Default parameter needs to be substituted for config but given this will
    // be tested with config value later, it just needs to be accurate enough
    // to work.
    if ($length == -1) {
      $length = 12;
    }

    parent::initCharacterSets($length);
  }

  /**
   * Clear the internal static cache so module will fall back to backing cache.
   */
  public function clearInternalStatics(): void {
    $this->allowedChars = NULL;
  }

}
