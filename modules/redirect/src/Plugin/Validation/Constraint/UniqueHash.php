<?php

namespace Drupal\redirect\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a redirect is unique on the site based on its hash.
 *
 * @Constraint(
 *   id = "RedirectUniqueHash",
 *   label = @Translation("Unique redirect", context = "Validation")
 * )
 */
class UniqueHash extends Constraint {

  /**
   * The message displayed in the case that validation fails.
   *
   * @var string
   */
  public $message = 'The source path %source is already being redirected. Do you want to <a href="@edit-page">edit the existing redirect</a>?';

}
