<?php

namespace Drupal\genpass;

/**
 * A service class to keep a static variable used to interlock validation.
 */
class ValidationInterlockHelper implements ValidationInterlockHelperInterface {

  /**
   * The value of the user.settings:verify_mail if encountered in validation.
   *
   * @var bool|null
   */
  protected $verifyMail = NULL;

  /**
   * {@inheritdoc}
   */
  public function setVerifyMail($value): void {
    $this->verifyMail = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getVerifyMail(): mixed {
    return $this->verifyMail;
  }

}
