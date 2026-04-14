<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 */
#[MukurtuImportFieldProcess(
  id: 'cultural_protocol',
  label: new TranslatableMarkup('Cultural Protocols'),
  description: new TranslatableMarkup('Cultural Protocols.'),
  field_types: ['cultural_protocol'],
  weight: 0,
  properties: ['protocols', 'sharing_setting'],
)]
class CulturalProtocols extends MukurtuImportFieldProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;
    $subfield = $context['subfield'] ?? NULL;
    $process = [];

    if ($subfield == 'protocols') {
      $process[] = [
        'plugin' => 'explode',
        'source' => $source,
        'delimiter' => $multivalue_delimiter,
      ];
      // Resolve any UUIDs.
      $process[] = [
        'plugin' => 'uuid_lookup',
        'entity_type' => 'protocol',
      ];
      // Resolve any values passed by protocol name.
      $process[] = [
        'plugin' => 'mukurtu_entity_lookup',
        'value_key' => \Drupal::entityTypeManager()->getDefinition('protocol')->getKey('label'),
        'ignore_case' => TRUE,
        'entity_type' => 'protocol',
      ];
      return $process;
    }

    if ($subfield == 'sharing_setting') {
      $process[] = [
        'plugin' => 'callback',
        'callable' => 'trim',
        'source' => $source,
      ];
      $process[] = [
        'plugin' => 'callback',
        'callable' => 'strtolower',
      ];
      return $process;
    }

    return $source;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    if ($field_property == 'protocols') {
      return t('IDs, UUIDs, or names of the cultural protocols, separated by your selected multi-value delimiter. If using names, they must be exact and match only a single protocol.');
    }
    if ($field_property == 'sharing_setting') {
      return t("Either 'Any' or 'All', case insensitive.");
    }
    return '';
  }

}
