<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that a taxonomy term has at most one parent.
 *
 * Added to the term's parent base field, so it applies to the term form,
 * imports, and anything else that validates terms.
 *
 * @see mukurtu_taxonomy_entity_base_field_info_alter()
 */
#[Constraint(
  id: 'MukurtuSingleParent',
  label: new TranslatableMarkup('Single parent term', [], ['context' => 'Validation']),
)]
class MukurtuSingleParent extends SymfonyConstraint {

  /**
   * The violation message.
   */
  public string $message = 'A term can have only one parent.';

}
