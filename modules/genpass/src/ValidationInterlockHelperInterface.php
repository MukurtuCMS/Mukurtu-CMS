<?php

namespace Drupal\genpass;

/**
 * Interface for the ValidationInterlockHelper.
 */
interface ValidationInterlockHelperInterface {

  /**
   * Set the verifyMail variable.
   *
   * @param mixed $value
   *   The value to set.
   */
  public function setVerifyMail($value): void;

  /**
   * Get the previously set variable.
   *
   * @return mixed
   *   The value set, or default of NULL.
   */
  public function getVerifyMail(): mixed;

}
