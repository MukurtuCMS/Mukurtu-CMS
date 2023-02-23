<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\mukurtu_protocol\CulturalProtocols;
use Exception;

/**
 * Provides a field type of CulturalProtocol.
 *
 * @FieldType(
 *   id = "cultural_protocol",
 *   label = @Translation("Cultural Protocols"),
 *   default_formatter = "cultural_protocol_formatter",
 *   default_widget = "cultural_protocol_widget",
 * )
 */
class CulturalProtocolItem extends FieldItemBase {
  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'protocols' => [
          'description' => 'Comma delimited list of protocol IDs.',
          'type' => 'text',
          'size' => 'normal',
        ],
        'sharing_setting' => [
          'description' => 'The sharing setting.',
          'type' => 'varchar',
          'length' => 512,
        ],
        'protocol_set' => [
          'description' => 'Hash key for the protocol set.',
          'type' => 'text',
          'size' => 'normal',
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['protocols'] = DataDefinition::create('string')
    ->setLabel(t('Protocols'))
    ->setRequired(TRUE);

    $properties['sharing_setting'] = DataDefinition::create('string')
    ->setLabel(t('Sharing Setting'))
    ->setRequired(TRUE);

    $properties['protocol_set_id'] = DataDefinition::create('integer')
    ->setLabel(t('Protocol Set ID'))
    ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    // Build the protocol set ID.
    $protocols = explode(',', $this->protocols);
    $this->protocol_set_id = CulturalProtocols::getProtocolSetId($protocols);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    // Treat the values as property value of the first property, if no array is
    // given.
    $value = $values['value'] ?? NULL;
    if (isset($values) && !is_array($values)) {
      $value = $values;
    }

    if (!(isset($values['protocols']) && isset($values['sharing_setting']))) {
      preg_match('/^(\w+)\s*\((.+)\)\s*/', $value, $matches, PREG_OFFSET_CAPTURE);
      if (!isset($matches[1]) || !isset($matches[2])) {
        return;
       //throw new \InvalidArgumentException();
      }
      $values = [];
      $values['sharing_setting'] = strtolower($matches[1][0]);
      $protocols = explode(',', $matches[2][0]);
      $values['protocols'] = implode(',', array_map(fn($p) => "|$p|", array_map('trim', $protocols)));
    }

    parent::setValue($values, $notify);
  }


  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->protocols) || empty($this->sharing_setting);
  }

}
