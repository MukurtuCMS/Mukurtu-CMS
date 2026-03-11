<?php

namespace Drupal\mukurtu_taxonomy\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that referenced terms belong to an enabled vocabulary.
 */
#[Constraint(
  id: 'EnabledVocabulary',
  label: new TranslatableMarkup('Enabled Vocabulary', [], ['context' => 'Validation']),
)]
class EnabledVocabularyConstraint extends SymfonyConstraint {

  /**
   * The config key to read enabled vocabularies from.
   *
   * @var string
   */
  public string $configKey;

  /**
   * The violation message.
   *
   * @var string
   */
  public string $message = 'The term "@term" belongs to vocabulary "@vocabulary" which is not allowed for this field.';

}
