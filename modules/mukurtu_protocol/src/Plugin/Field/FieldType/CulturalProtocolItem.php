<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\mukurtu_protocol\CulturalProtocols;

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
    ->addConstraint('ValidSharingSettingConstraint')
    ->setRequired(TRUE);

    $properties['protocol_set_id'] = DataDefinition::create('integer')
    ->setLabel(t('Protocol Set ID'))
    ->setRequired(FALSE);

    return $properties;
  }

    /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'protocols';
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

  public static function formatProtocols($value) {
    if (is_array($value)) {
      sort($value, SORT_NUMERIC);
      return implode(',', array_map(fn($p) => "|$p|", array_map('trim', $value)));
    }
    return $value;
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

    if (isset($values['protocols'])) {
      $values['protocols'] = $this->formatProtocols($values['protocols']);
    }

    if (!(isset($values['protocols']) && isset($values['sharing_setting']))) {
      preg_match('/^(\w+)\s*\((.+)\)\s*/', $value, $matches, PREG_OFFSET_CAPTURE);
      if (!isset($matches[1]) || !isset($matches[2])) {
        return;
       //throw new \InvalidArgumentException();
      }
      $values = [];
      $values['sharing_setting'] = strtolower($matches[1][0]);
      $values['protocols'] = explode(',', $matches[2][0]);
      $values['protocols'] = $this->formatProtocols($value['protocols']);
    }

    parent::setValue($values, $notify);
  }

  /**
   * Deserialize the protocols property into an array of protocol IDs.
   *
   * @return array
   */
  public function getProtocolIds() {
    if ($protocolIds = str_replace('|', '', explode(',', $this->protocols))) {
      return array_map(fn ($p) => (int) $p, $protocolIds);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->protocols) || empty($this->sharing_setting);
  }

  /**
   * Get the settable protocol ID options.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *  The account to be used to fetch protocols. Note that unlike
   *  OptionsProviderInterface, providing NULL will use the current user.
   *
   * @return array
   *  An array of protocol IDs.
   */
  public function getSettableProtocolIds(AccountInterface $account = NULL) {
    $protocolIds = CulturalProtocols::getProtocolsByUserPermission(['apply protocol'], $account);
    return $protocolIds;
  }

  /**
   * Return the options array for the sharing settings.
   */
  public function getSettableSharingOptions(AccountInterface $account = NULL) {
    return CulturalProtocols::getItemSharingSettingOptions();
  }

}
