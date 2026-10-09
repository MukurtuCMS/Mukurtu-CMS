<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that a value is shaped like a BCP 47 language tag.
 *
 * The check is deliberately permissive: it accepts any ISO 639 code, the
 * qaa-qtz local-use range and private-use tags (x-...), plus script, region
 * and variant subtags. Many Indigenous languages have no ISO 639 code, so a
 * registry lookup would turn them away.
 */
#[Constraint(
  id: 'MukurtuLanguageTag',
  label: new TranslatableMarkup('Language tag', [], ['context' => 'Validation']),
  type: 'string'
)]
class LanguageTagConstraint extends SymfonyConstraint {

  /**
   * The shape of a language tag.
   *
   * A 2-8 letter primary language subtag followed by any number of
   * hyphen-separated subtags, or "x"/"i" (private-use and legacy tags)
   * followed by at least one.
   */
  public const PATTERN = '/^([A-Za-z]{2,8}(-[A-Za-z0-9]{1,8})*|[xXiI](-[A-Za-z0-9]{1,8})+)$/';

  /**
   * The violation message.
   *
   * @var string
   */
  public $message = "@value isn't a valid language code. Use letters, digits, and hyphens, such as <em>haw</em> or <em>x-mylang</em>.";

}
