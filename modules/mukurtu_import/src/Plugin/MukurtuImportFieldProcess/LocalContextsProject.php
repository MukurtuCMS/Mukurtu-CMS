<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 */
#[MukurtuImportFieldProcess(
  id: 'local_contexts_project',
  label: new TranslatableMarkup('Local Contexts Project'),
  description: new TranslatableMarkup('Local Contexts Project.'),
  field_types: ['local_contexts_project'],
  weight: 0,
)]
class LocalContextsProject extends MukurtuImportFieldProcessPluginBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []): array {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;
    $process = [];
    if ($this->isMultiple($field_config)) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }
    $process[] = [
      'plugin' => 'callback',
      'callable' => 'trim',
    ];
    $process[] = [
      'plugin' => 'local_contexts_project_lookup',
    ];
    $process[0]['source'] = $source;
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL): TranslatableMarkup {
    if ($this->isMultiple($field_config)) {
      return $this->t('Project titles or IDs, separated by your selected multi-value delimiter.');
    }
    return $this->t('The project title or project ID, as it appears in Local Contexts.');
  }

}
