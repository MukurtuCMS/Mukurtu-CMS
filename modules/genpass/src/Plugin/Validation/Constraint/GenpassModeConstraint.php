<?php

namespace Drupal\genpass\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks the Genpass mode is compatible with system configuration.
 */
#[Constraint(
  id: 'GenpassMode',
  label: new TranslatableMarkup(
    'Genpass Mode interlock constraint',
    [],
    ['context' => 'Validation']
  ),
  type: 'integer'
)]
class GenpassModeConstraint extends SymfonyConstraint {

  /**
   * Error message when genpass_mode chosen is not compatible with verify_email.
   *
   * @var string
   */
  public $incompatibleSettings = 'User password entry option %chosen is not available when email verification is enabled.';

  /**
   * Variable mode to operate this constraint in.
   *
   * @var string
   */
  public $operationMode = 'genpass_mode';

  /**
   * Constructs a new GenpassModeConstraint object.
   */
  public function __construct(
    array $options,
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    $this->operationMode = $options['operationMode'] ?? $this->operationMode;

    parent::__construct([], $groups, $payload);
  }

}
