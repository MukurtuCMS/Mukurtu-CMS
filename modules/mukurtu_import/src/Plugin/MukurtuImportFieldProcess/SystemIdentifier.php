<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;

/**
 * Imports a langcode or UUID, leaving a blank cell for the system to assign.
 *
 * A round-tripped export carries the Locale and UUID columns, and on a new
 * row they are left blank. Passed through as '', they fail validation
 * ("Language: the value you selected is not a valid choice") or collide on
 * the UUID's unique constraint. Skipping a blank cell leaves the field
 * unset, so new content gets the bundle's default language and a generated
 * UUID, and existing content keeps its own (see
 * ProtocolAwareEntityContent::restoreBlankedFieldValues()).
 */
#[MukurtuImportFieldProcess(
  id: 'system_identifier',
  label: new TranslatableMarkup('System identifier'),
  description: new TranslatableMarkup('Language code or UUID. A blank cell lets the system assign the value.'),
  field_types: ['language', 'uuid'],
  weight: 0,
)]
class SystemIdentifier extends DefaultProcess {

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    return [
      [
        'plugin' => 'mukurtu_skip_on_blank',
        'source' => $source,
      ],
      [
        'plugin' => 'callback',
        'callable' => 'trim',
      ],
    ];
  }

}
