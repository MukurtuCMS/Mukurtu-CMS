<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\mukurtu_protocol\Entity\ProtocolControl;
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
        'target_id' => [
          'description' => 'The ID of the Protocol Control Entity.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
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
      ],
      'indexes' => [
        'target_id' => ['target_id'],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $properties['target_id'] = DataDefinition::create('integer')
    ->setLabel(t('Protocol Control ID'))
    ->setSetting('unsigned', TRUE)
    ->setRequired(FALSE);

    $properties['protocols'] = DataDefinition::create('string')
    ->setLabel(t('Protocols'))
    ->setRequired(FALSE);

    $properties['sharing_setting'] = DataDefinition::create('string')
    ->setLabel(t('Sharing Setting'))
    ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    $entity = $this->getEntity();
    $target_id = $this->target_id;
    $protocols = explode(',', $this->protocols);
    $sharing = $this->sharing_setting;
    if (empty($target_id)) {
      $pce = ProtocolControl::create([]);
    } else {
      // @todo Need to figure out all the handling on this for mismatched/missing PCEs.
      /** @var \Drupal\mukurtu_protocol\Entity\ProtocolControl $pce */
      $pce = \Drupal::entityTypeManager()->getStorage('protocol_control')->load($target_id);
      if (empty($pce)) {
        $pce = ProtocolControl::create([]);
      } else {
        $controlledEntity = $pce->getControlledEntity();
        if ($controlledEntity->getEntityTypeId() !== $entity->getEntityTypeId()
        || $controlledEntity->id() !== $entity->id()) {
          throw new Exception("WRONG PCE");
        }
      }
    }
    $pce->setControlledEntity($entity);
    $pce->setProtocols($protocols);
    $pce->setPrivacySetting($sharing);
    $pce->save();

    $this->target_id = $pce->id();
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
      $values['protocols'] = implode(',', array_map('trim', $protocols));
    }

    parent::setValue($values, $notify);
  }


  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return FALSE;
  }

}
