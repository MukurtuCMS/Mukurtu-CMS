<?php

declare(strict_types=1);

namespace Drupal\entity_browser\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\file\Plugin\Validation\Constraint\FileImageDimensionsConstraint;

/**
 * File extension dimensions constraint.
 *
 * @Constraint(
 *   id = "EntityBrowserImageDimensions",
 *   label = @Translation("Entity Browser Image Dimensions", context = "Validation"),
 *   type = "file"
 * )
 */
#[Constraint(
  id: 'EntityBrowserImageDimensions',
  label: new TranslatableMarkup('Entity Browser Image Dimensions', [], ['context' => 'Validation']),
  type: 'file'
)]
class EntityBrowserImageDimensionsConstraint extends FileImageDimensionsConstraint {

}
