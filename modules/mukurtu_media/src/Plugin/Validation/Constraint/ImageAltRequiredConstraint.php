<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Requires alt text when an image is uploaded.
 */
#[Constraint(
  id: 'ImageAltRequired',
  label: new TranslatableMarkup('Image Alt Required', options: ['context' => 'Validation'])
)]
final class ImageAltRequiredConstraint extends SymfonyConstraint {

  /**
   * The error message.
   *
   * @var string
   */
  public string $message = 'Alternative text is required.';

}
